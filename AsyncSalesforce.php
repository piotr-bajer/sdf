<?php
/*
	Do asynchronous part of salesforce updates.
*/

namespace SDF;

require_once WP_PLUGIN_DIR . '/sdf/types.php';
require_once WP_PLUGIN_DIR . '/sdf/message.php';

class AsyncSalesforce extends \SDF\Salesforce {

	private $transaction;
	private $donations;
	private $contact;

	private static $DISPLAY_NAME = 'Spark';
	private static $DONOR_SINGLE_TEMPLATE = '00X50000001VaHS';
	private static $DONOR_MONTHLY_TEMPLATE = '00X50000001eVEX';
	private static $DONOR_ANNUAL_TEMPLATE = '00X50000001eVEc';
	private static $RECURRING_TEMPLATE = '00X50000001fRwu';

	public function init(&$info) {
		try {
	
			parent::api();
			self::$contact = parent::get_contact($info['email']);

			// We need to get the invoice details from the charge

			self::get_donations();

		// need to update totals in the contact object
		$this->recalc_sum($info['amount']);

		// add a new line in the description
		$this->description();

		$this->renewal_date();
		$this->cleanup();

		$this->upsert();
		$this->new_donation();
		$this->send_email();

		// $this->get_contact();
		// $this->get_donations();
		// $this->update();
		// $this->upsert();
		// $this->new_donation();
		// $this->send_email();

		/*
		// these ones need to be triggered on the async side
		$this->set_constant();
	
		$this->description();

		$this->type();
		$this->start_date();
		$this->renewal_date();

		$this->first_active();

		$this->unextended();
		$this->cleanup();
		*/
	}



	// Find out how much the amount is for the year if it's monthly 
	// XXX
	private function get_ext_amount() {
		if($this->data['recurrence-type'] == RecurrenceTypes::MONTHLY) {
			$times = 13 - intval(date('n'));
			$this->data['amount-ext'] = $times * $this->data['amount'];
		} else {
			$this->data['amount-ext'] = $this->data['amount'];
		}
	}


	// Find the donations for our contact, so that we can determine their
	// donor level
	private function get_donations() {
		$donations_list = array();

		if(self::$contact->Id !== null) {
			// the first of the year
			$cutoff_date = strtotime(date('Y') . '-01-01');

			$query = 'SELECT 
							(SELECT Amount__c, Donation_Date__c FROM Donations__r)
						FROM
							Contact
						WHERE
							Contact.Id = \'' . self::$contact->Id . '\'';


			try {

				$response = static::$connection->query($query);
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

			} catch(Exception $e) {
				sdf_message_handler(LOG, __FUNCTION__ . ' : ' . $e->faultstring);
			}
		}

		$this->donations = $donations_list;
	}

	// Find out if the donation li's will update the contact's membership level
	// we want the TOTAL amount of donations for this calendar year
	// and we want to know whether that passes the 75 dollar cutoff.
	// then they are a member
	// Also sets contact->paid__c and total__c
	private function recalc_sum($amount = null) {
		$sum = 0;

		foreach($this->donations as $donation) {
			$sum += $donation['amount'];
		}

		if(!empty($amount)) {
			$sum += $amount;
		} else {
			$sum += $this->data['amount-ext']; // XXX no extension, if we're updating everytime the donation goes through
		}

		if($sum >= 75) { // change to intval?
			$this->data['is-member'] = static::$IS_MEMBER;
		} else {
			$this->data['is-member'] = static::$IS_NOT_MEMBER;
		}

		self::$contact->Total_paid_this_year__c = $sum;
		// last amount paid
		self::$contact->Paid__c = $this->data['amount-ext']; // XXX this amount, or what we get from the endpoint
	}

	private function member_level() {
		$amount = self::$contact->Total_paid_this_year__c;
		$level = 'Donor';

		if($amount >= 75 && $amount < 100) {
			$level = 'Friend';
		} else if($amount >= 100 && $amount < 250) {
			$level = 'Member';
		} else if($amount >= 250 && $amount < 500) {
			$level = 'Affiliate';
		} else if($amount >= 500 && $amount < 1000) {
			$level = 'Sponsor';
		} else if($amount >= 1000 && $amount < 2500) {
			$level = 'Investor';
		} else if($amount >= 2500) {
			$level = 'Benefactor';
		}

		self::$contact->Member_Level__c = $level;
	}

	// Set the values that will always be the same
	private function set_constant() {
		self::$contact->Payment_Type__c = 'Credit Card';
	}






	// XXX
	private function description() {
		$this->transaction = $this->data['recurrence-string']
			. ' - ' . $this->data['amount-string']. ' - '
			. date('n/d/y') . ' - Online donation from ' . home_url() . '.';

		if(!empty($this->data['inhonorof'])) {
			$this->transaction .= ' In honor of: ' . $this->data['inhonorof'];
		}

		if(isset(self::$contact->Description)) {
			$desc = self::$contact->Description . "\n" . $this->transaction;
		} else {
			$desc = $this->transaction;
		}

		self::$contact->Description = $desc;
	}


	// SalesForce Type - Donor or Member
	private function type() {
		$type = $this->data['is-member'] == static::$IS_MEMBER ? 'Spark Member' : 'Donor';
		self::$contact->Type__c = $type;
	}

	private function start_date() {
		self::$contact->Membership_Start_Date__c = date(parent::$DATE_FORMAT);
	}

	// Renewal is always updated to be one more year from now
	private function renewal_date() {
		self::$contact->Renewal_Date__c = date(parent::$DATE_FORMAT, strtotime('+1 year'));
	}


	// We need to keep track of the individual donation amount,
	// not extended, not total for this year.
	private function unextended() {
		self::$contact->Donation_Each__c = $this->data['amount'];
	}





	// Uses transaction to hold donation description
	// Create the donation line item child object
	// Called after insert, because we have to have the contact id
	private function new_donation() {
		$donation = new stdClass();
		$donation->Contact__c = self::$contact->Id;
		$donation->Amount__c = $this->data['amount-ext']; // XXX
		$donation->Description__c = strlen($this->transaction) > 255 ? substr($this->transaction, 0, 252) . '...' : $this->transaction;
		$donation->Donation_Date__c = date(parent::$DATE_FORMAT);
		$donation->Type__c = 'Membership';

		try {
			static::$connection->create(array($donation), 'Donation__c');
		} catch(Exception $e) {
			sdf_message_handler(LOG, __FUNCTION__ . ' : ' . $e->faultstring);
		}
	}

	// Send an email to the Spark team
	// Send an email to our lovely donor
	private function send_email() {

		switch($this->data['recurrence-type']) {
			case static::$MONTHLY: $template = static::$DONOR_MONTHLY_TEMPLATE; break;
			case static::$ANNUAL: $template = static::$DONOR_ANNUAL_TEMPLATE; break;
			case static::$ONE_TIME: $template = static::$DONOR_SINGLE_TEMPLATE; break;
		}

		$donor_email = new SingleEmailMessage();
		$donor_email->setTemplateId($template);
		$donor_email->setTargetObjectId(self::$contact->Id);
		$donor_email->setReplyTo(get_option('sf_email_reply_to'));
		$donor_email->setSenderDisplayName(static::$DISPLAY_NAME);

		try {
			static::$connection->sendSingleEmail(array($donor_email));
		} catch(Exception $e) {
			sdf_message_handler(LOG, __FUNCTION__ . ': Donor email failure! ' . $e->faultstring);
		}

		// Alert email
		$hear = $this->hdyh();
		$honor = empty($this->data['inhonorof']) ? null : 'In honor of: ' . $this->data['inhonorof'];

		$body = <<<EOF
A donation has been made!

Name: {self::$contact->FirstName} {self::$contact->LastName}
Amount: {$this->data['amount-string']}
Recurrence: {$this->data['recurrence-string']}
Email: {self::$contact->Email}
Location: {$this->data['city']}, {$this->data['state']}
{$honor}
{$hear}
EOF;

		$spark_email = new SingleEmailMessage();
		$spark_email->setSenderDisplayName('Spark Donations');
		$spark_email->setToAddresses(explode(', ', get_option('alert_email_list')));
		$spark_email->setPlainTextBody($body);
		$spark_email->setSubject('New Donation Alert');

		try {
			static::$connection->sendSingleEmail(array($spark_email));
		} catch(Exception $e) {
			sdf_message_handler(LOG, __FUNCTION__ . ': Alert email failure! ' . $e->faultstring);
		}
	}

	// Get the string describing hearabout and hearabout-extra
	private function hdyh() {
		$begin = "How did they hear about Spark? ";
		if(isset(self::$contact->How_did_you_hear_about_Spark__c)) {
			if(isset($this->data['hearabout-extra']) && !empty($this->data['hearabout-extra'])) {
				$str = self::$contact->How_did_you_hear_about_Spark__c . ": " . $this->data['hearabout-extra'];
				return $begin . $str;
			}
			return $begin . self::$contact->How_did_you_hear_about_Spark__c;
		}
		return null;
	}



} // end class ?>
