<?php
/*
	Do asynchronous part of salesforce updates.
*/

namespace SDF;

require_once WP_PLUGIN_DIR . '/sdf/types.php';
require_once WP_PLUGIN_DIR . '/sdf/message.php';

class AsyncSalesforce extends Salesforce {

	private $donations;
	protected $contact;

	private static $DISPLAY_NAME = 'Spark';
	private static $DONOR_SINGLE_TEMPLATE = '00X50000001VaHS';
	private static $DONOR_MONTHLY_TEMPLATE = '00X50000001eVEX';
	private static $DONOR_ANNUAL_TEMPLATE = '00X50000001eVEc';
	// private static $RECURRING_TEMPLATE = '00X50000001fRwu';

	public function init(&$info) {
		try {

			// We need this in a few places
			$info['dollar-amount'] = $info['amount'] / 100;
	
			parent::api();
			$this->contact = parent::get_contact($info['email']);

			// Get the other donations we need to know about
			self::get_donations();

			// add a new line in the description
			// do this before calculating sum, so that
			// we know the recurrence type
			self::description($info);

			// Calculate the totals, so we know what level
			// the donor is
			self::recalc_sum($info);



			// Directly update some fields
			$this->contact->Payment_Type__c = 'Credit Card';
			$this->contact->Paid__c = $info['dollar-amount'];
			$this->contact->Donation_Each__c = $this->data['amount'];
			$this->contact->Renewal_Date__c = 
					date(parent::$DATE_FORMAT, strtotime('+1 year'));

			$this->contact->Membership_Start_Date__c =
					date(parent::$DATE_FORMAT);

			parent::cleanup();
			parent::upsert();

			self::new_donation($info);
			self::send_email($info);
		} catch(\Exception $e) {
			sdf_message_handler(MessageTypes::LOG,
					__FUNCTION__ . ' : General failure in AsyncSalesforce. ' 
					. $e->faultstring);
		}
	}



	// Find out how much the amount is for the year if it's monthly 
	private function get_ext_amount($recurrence, $dollar_amount) {
		if($recurrence == RecurrenceTypes::MONTHLY) {
			$times = 13 - intval(date('n'));
		} else {
			$times = 1;
		}

		return $times * $dollar_amount;
	}


	// Find the donations for our contact, so that we can determine their
	// donor level
	private function get_donations() {
		$donations_list = array();

		if($this->contact->Id !== null) {
			// the first of the year
			$cutoff_date = strtotime(date('Y') . '-01-01');

			$query = 'SELECT 
							(SELECT Amount__c, Donation_Date__c FROM Donations__r)
						FROM
							Contact
						WHERE
							Contact.Id = \'' . $this->contact->Id . '\'';


			try {

				$response = parent::$connection->query($query);
				$records = $response->records[0]->Donations__r->records;

				foreach($records as $donation) {
					$li = array();
					$date = strtotime($donation->Donation_Date__c);

					if($date >= $cutoff_date) {
						// donations from this calendar year
						$li['date'] = $donation->Donation_Date__c;
						$li['amount'] = $donation->Amount__c;
						$donations_list[] = $li;
					}
				}

			} catch(\Exception $e) {
				// It's okay if there's no donations returned here,
				// though our calculation will be wrong
				sdf_message_handler(MessageTypes::LOG,
						__FUNCTION__ . ' : ' . $e->faultstring);
			}
		}

		$this->donations = $donations_list;
	}


	private function description(&$info) {
		// Get the recurrence string from the invoice data
		$invoice = json_decode($info['invoice'], true);

		// This means the user is signed up for recurring donations
		if($invoice['lines']['data'][0]['type'] == 'subscription') {
			if($invoice['lines']['data'][0]['plan']['interval'] == 'year') {
				$info['recurrence-string'] = 'Annual';
				$info['recurrence-type'] = RecurrenceTypes::ANNUAL;
			} else {
				$info['recurrence-string'] = 'Monthly';
				$info['recurrence-type'] = RecurrenceTypes::MONTHLY;
			}
		} else {
			$info['recurrence-string'] = 'One time';
			$info['recurrence-type'] = RecurrenceTypes::ONE_TIME;
		}

		$fmt = $info['recurrence-string'] . ' - %.2n - ' 
				. date('n/d/y') . ' - Online donation from '
				. home_url() . '.';

		$desc = money_format($fmt, $info['dollar-amount']);
		$info['desc'] = $desc;

		// if(!empty($this->data['inhonorof'])) {
		// 	$this->transaction .= ' In honor of: ' 
		// 			. $this->data['inhonorof'];
		// }

		if(isset($this->contact->Description)) {
			$this->contact->Description .= "\n" . $desc;
		} else {
			$this->contact->Description = $desc;
		}
	}


	// Find out if the donation li's will update the 
	// contact's membership level
	// we want the TOTAL amount of donations for this calendar year
	// and we want to know whether that passes the 75 dollar cutoff.
	private function recalc_sum(&$info) {
		$sum = 0;

		foreach($this->donations as $donation) {
			$sum += $donation['amount'];
		}

		$sum += $info['dollar-amount'];

		$this->contact->Total_paid_this_year__c = $sum;

		// now we'll take into account the donations we expect
		// for the rest of the year from this donor.
		$sum += self::get_ext_amount($info['recurrence-type'],
				$info['dollar_amount']);


		if($sum >= 75) { 
			$this->contact->Type__c = 'Spark Member';
		} else {
			$this->contact->Type__c = 'Donor';
		}
		

		// Get text label for membership level
		$level = 'Donor';

		if($sum >= 75 && $sum < 100) {
			$level = 'Friend';
		} else if($sum >= 100 && $sum < 250) {
			$level = 'Member';
		} else if($sum >= 250 && $sum < 500) {
			$level = 'Affiliate';
		} else if($sum >= 500 && $sum < 1000) {
			$level = 'Sponsor';
		} else if($sum >= 1000 && $sum < 2500) {
			$level = 'Investor';
		} else if($sum >= 2500) {
			$level = 'Benefactor';
		}

		$this->contact->Member_Level__c = $level;
	}


	// Create the donation line item child object
	private function new_donation(&$info) {
		$donation = new \stdClass();
		$donation->Contact__c = $this->contact->Id;
		$donation->Amount__c = $info['amount'] / 100;
		$donation->Donation_Date__c = date(parent::$DATE_FORMAT);
		$donation->Type__c = 'Membership';

		if(strlen($info['desc']) > 255) {
			$donation->Description__c =
					substr($info['desc'], 0, 252) . '...';
		} else {
			$donation->Description__c = $info['desc'];
		}

		try {
			parent::$connection->create(array($donation), 'Donation__c');
		} catch(\Exception $e) {
			sdf_message_handler(MessageTypes::LOG,
					__FUNCTION__ . ' : ' . $e->faultstring);
		}
	}


	// Send an email to the Spark team
	// Send an email to our lovely donor
	private function send_email(&$info) {

		switch($info['recurrence-type']) {
			case RecurrenceTypes::MONTHLY: 
					$template = self::$DONOR_MONTHLY_TEMPLATE; break;
			case RecurrenceTypes::ANNUAL: 
					$template = self::$DONOR_ANNUAL_TEMPLATE; break;
			case RecurrenceTypes::ONE_TIME: 
					$template = self::$DONOR_SINGLE_TEMPLATE; break;
		}

		$donor_email = new \SingleEmailMessage();
		$donor_email->setTemplateId($template);
		$donor_email->setTargetObjectId($this->contact->Id);
		$donor_email->setReplyTo(get_option('sf_email_reply_to'));
		$donor_email->setSenderDisplayName(self::$DISPLAY_NAME);

		try {
			parent::$connection->sendSingleEmail(array($donor_email));
		} catch(\Exception $e) {
			sdf_message_handler(MessageTypes::LOG,
					__FUNCTION__ . ' : Donor email failure! ' 
					. $e->faultstring);
		}

		// Alert email
		$body = <<<EOF
A donation has been made!

Name: {$this->contact->FirstName} {$this->contact->LastName}
Amount: ${$info['dollar-amount']}
Recurrence: {$info['recurrence-string']}
Email: {$this->contact->Email}
Location: {$this->contact->MailingCity}, {$this->contact->MailingState}
EOF;

		$spark_email = new \SingleEmailMessage();
		$spark_email->setSenderDisplayName('Spark Donations');
		$spark_email->setPlainTextBody($body);
		$spark_email->setSubject('New Donation Alert');
		$spark_email->setToAddresses(explode(', ', 
				get_option('alert_email_list')));

		try {
			parent::$connection->sendSingleEmail(array($spark_email));
		} catch(\Exception $e) {
			sdf_message_handler(MessageTypes::LOG,
					__FUNCTION__ . ' : Alert email failure! ' 
					. $e->faultstring);
		}
	}
} // end class ?>
