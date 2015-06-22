<?php
/**
*	Salesforce update class.
*	// Donation flow:
*	// When the donation is made, we send the request to stripe,
*	// and then send the basic data to salesforce.
*	// basic data means there is no donation included.
*	// then after we get the endpoint hit, then we go back 
*	// to salesforce and add the donation data in.
*
*	// This way, we won't double dip when we get the endpoint request.
*	// It does create more requests from the server,
*	// but it saves from having to be stateful in between requests.
*/

require_once WP_PLUGIN_DIR . '/sdf/types.php';
require_once WP_PLUGIN_DIR . '/sdf/message.php';

class SDFSalesforce {

	private static $connection;
	private $contact;
	private $transaction;
	private $donations;

	private static $FRIEND_OF_SPARK = '00130000007qhRG';
	private static $DONOR_SINGLE_TEMPLATE = '00X50000001VaHS';
	private static $DONOR_MONTHLY_TEMPLATE = '00X50000001eVEX';
	private static $DONOR_ANNUAL_TEMPLATE = '00X50000001eVEc';
	private static $RECURRING_TEMPLATE = '00X50000001fRwu';

	private static $DISPLAY_NAME = 'Spark';
	private static $SF_DATE_FORMAT = 'Y-m-d';


	// This function is public to allow verification from settings page
	// just like the Stripe API
	public static function api($input = null) {
		require_once(
			WP_PLUGIN_DIR . '/sdf/salesforce/soapclient/SforceEnterpriseClient.php');

		$connection = new SforceEnterpriseClient();
		$connection->createConnection(
			WP_PLUGIN_DIR . '/sdf/salesforce/soapclient/sdf.wsdl.jsp.xml');

		if(!empty($input)) {
			// we expect the input to be a new token.
			$connection->login(
				get_option('salesforce_username'),
				get_option('salesforce_password') . $input);
		} else {
			$connection->login(
				get_option('salesforce_username'),
				get_option('salesforce_password') . get_option('salesforce_token'));
		}

		static::$connection = clone $connection;
	}

	// This method queries the data in SalesForce using the provided email
	public function get_contact($email = null) {
		if(!empty($email)) {
			$id = $this->search_salesforce(SearchBy::EMAIL, $email);
		} else {
			$id = $this->search_salesforce(SearchBy::EMAIL, $this->data['email']);
		}

		$contact = new stdClass();

		if($id !== null) {
			$fields = array( // # is index of contact object
				'AccountId', // 3
				'Description', // 25
				'Membership_Start_Date__c', // 44
				'Renewal_Date__c', // 52
				'Member_Level__c', // 53
				'First_Active_Date__c', // 56
				'Board_Member_Contact_Owner__c', // 61
				'How_did_you_hear_about_Spark__c', // 66
				'Total_paid_this_year__c'
			);

			$fieldlist = implode(', ', $fields);

			try {
				$contact = array_pop(static::$connection->retrieve(
					$fieldlist,
					'Contact',
					array($id)));

			} catch(Exception $e) {
				sdf_message_handler(
					MessageTypes::LOG, __FUNCTION__ . ' : ' . $e->faultstring);
			}
		}

		$this->contact = $contact;
		$this->contact->Id = $id;
	}

	// Searches SalesForce for the user, and returns their ID, or null
	private function search_salesforce($search, $needle) {
		if($search == SearchBy::EMAIL) {
			$query = 
				'FIND {"' . $needle . '"} IN EMAIL FIELDS RETURNING CONTACT(ID)';
		} elseif($search == SearchBy::NAME) {
			$query = 
				'FIND {"' . $needle . '"} IN NAME FIELDS RETURNING CONTACT(ID)';
				// XXX might not work.
		}
		

		try {
			$response = static::$connection->search($query);
		} catch(Exception $e) {
			sdf_message_handler(
				MessageTypes::LOG, __FUNCTION__ . ' : ' . $e->faultstring);
		}

		if(count($response)) {
			return array_pop($response->searchRecords)->Id;
		} else {
			return null;
		}
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



	// This function has a TON of helper functions that help it build
	// our contact data up.
	// Take what we can from the old contact, and stage the new data for insertion.
	// The helper functions are called in the order of the contact object fields
	private function update() {
		$this->recalc_sum();
		$this->member_level();
		$this->set_constant();
		$this->company();
		$this->name();
		$this->address();
		$this->phone();
		$this->description();
		$this->email();
		$this->type();
		$this->start_date();
		$this->renewal_date();
		$this->first_active();
		$this->gender();
		$this->board_member();
		$this->birthday();
		$this->hdyh_type();
		$this->unextended();
		$this->cleanup();
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

		$this->contact->Total_paid_this_year__c = $sum;
		// last amount paid
		$this->contact->Paid__c = $this->data['amount-ext']; // XXX this amount, or what we get from the endpoint
	}

	private function member_level() {
		$amount = $this->contact->Total_paid_this_year__c;
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

		$this->contact->Member_Level__c = $level;
	}

	// Set the values that will always be the same
	private function set_constant() {
		$this->contact->Payment_Type__c = 'Credit Card';
		$this->contact->Contact_Status__c = 'Active';
		$this->contact->Active_Member__c = true;
	}

	private function company() {
		if(!isset($this->contact->AccountId)) {
			if(!empty($this->data['company'])) {
				$search = 'FIND {"' . $this->data['company'] 
					. '"} IN NAME FIELDS RETURNING Account(Id)';

				try {
					$records = static::$connection->search($search);

					if(count($records->searchRecords)) {
						$id = $records->searchRecords[0]->Id;
					} else {
						$company = new stdClass();
						$company->Name = $this->data['company'];

						try {
							$created = static::$connection->create(array($company), 'Account');
							$id = $created[0]->id;
						} catch(Exception $e) {
							sdf_message_handler(LOG, __FUNCTION__ . ' : ' . $e->faultstring);
							$id = static::$FRIEND_OF_SPARK;
						}
					}

					$this->contact->AccountId = $id;
				} catch(Exception $e) {
					sdf_message_handler(LOG, __FUNCTION__ . ' : ' . $e->faultstring);
					$this->contact->AccountId = static::$FRIEND_OF_SPARK;
				}

			} else {
				$this->contact->AccountId = static::$FRIEND_OF_SPARK;
			}
		}
	}

	private function name() {
		$this->contact->FirstName = $this->data['first-name'];
		$this->contact->LastName = $this->data['last-name'];
	}

	private function address() {
		$this->contact->MailingStreet = empty($this->data['address2']) ? $this->data['address1']
			: $this->data['address1'] . "\n" . $this->data['address2'];

		$this->contact->MailingCity = $this->data['city'];
		$this->contact->MailingState = $this->data['state'];
		$this->contact->MailingPostalCode = $this->data['zip'];
		$this->contact->MailingCountry = $this->data['country'];
	}

	private function phone() {
		$this->contact->Phone = $this->data['tel'];
	}

	private function description() {
		$this->transaction = $this->data['recurrence-string']
			. ' - ' . $this->data['amount-string']. ' - '
			. date('n/d/y') . ' - Online donation from ' . home_url() . '.';

		if(!empty($this->data['inhonorof'])) {
			$this->transaction .= ' In honor of: ' . $this->data['inhonorof'];
		}

		if(isset($this->contact->Description)) {
			$desc = $this->contact->Description . "\n" . $this->transaction;
		} else {
			$desc = $this->transaction;
		}

		$this->contact->Description = $desc;
	}

	private function email() {
		$this->contact->Email = $this->data['email'];
	}

	// SalesForce Type - Donor or Member
	private function type() {
		$type = $this->data['is-member'] == static::$IS_MEMBER ? 'Spark Member' : 'Donor';
		$this->contact->Type__c = $type;
	}

	private function start_date() {
		$this->contact->Membership_Start_Date__c = date(static::$SF_DATE_FORMAT);
	}

	// Renewal is always updated to be one more year from now
	private function renewal_date() {
		$this->contact->Renewal_Date__c = date(static::$SF_DATE_FORMAT, strtotime('+1 year'));
	}

	private function first_active() {
		if(!isset($this->contact->First_Active_Date__c)) {
			$this->contact->First_Active_Date__c = date(static::$SF_DATE_FORMAT);
		}
	}

	private function gender() {
		if(!empty($this->data['gender'])) {
			$this->contact->Gender__c = ucfirst($this->data['gender']);
		}
	}

	private function board_member() {
		if(!isset($this->contact->Board_Member_Contact_Owner__c)) {
			$this->contact->Board_Member_Contact_Owner__c = 'Amanda Brock';
		}
	}

	private function birthday() {
		// Birth_Month_Year__c
		if(!empty($this->data['birthday-month']) && !empty($this->data['birthday-year'])) {
			$this->contact->Birth_Month_Year__c = date('m/Y',
				strtotime($this->data['birthday-year'] . '-' . $this->data['birthday-month']));
		}
	}

	// Form field hearabout-extra is not being sent to SalesForce
	// but we do notify Spark Team about it.

	// XXX we want the hearabout-extra field to go into the contact lookup
	// Referred_By__c
	private function hdyh_type() {
		if(!empty($this->data['hearabout'])) {
			if(!isset($this->contact->How_did_you_hear_about_Spark__c)) {
				$this->contact->How_did_you_hear_about_Spark__c = ucfirst($this->data['hearabout']);
			}
		}
	}

	// We need to keep track of the individual donation amount,
	// not extended, not total for this year.
	private function unextended() {
		$this->contact->Donation_Each__c = $this->data['amount'];
	}

	// This function removes empty fields from the contact object
	private function cleanup() {
		foreach($this->contact as $property => $value) {
			if(is_null($value)) {
				unset($this->contact->$property);
			}
		}
	}

	private function upsert() {
		if(isset($this->contact->Id)) {
			// update on id.
			try {
				static::$connection->update(array($this->contact), 'Contact');
			} catch(Exception $e) {
				sdf_message_handler(LOG, __FUNCTION__ . ' : ' . $e->faultstring);
				$this->emergency_email($e->faultstring);
			}
		} else {
			// create new contact.
			try {
				$response = array_pop(static::$connection->create(array($this->contact), 'Contact'));

				if(empty($response->success)) {
					$error = $response->errors[0]->message;
					$this->emergency_email($error);
				} else {
					$this->contact->Id = $response->id;
				}

			} catch(Exception $e) {
				sdf_message_handler(LOG, __FUNCTION__ . ' : ' . $e->faultstring);
				$this->emergency_email($e->faultstring);
			}
		}
	}

	// Uses transaction to hold donation description
	// Create the donation line item child object
	// Called after insert, because we have to have the contact id
	private function new_donation() {
		$donation = new stdClass();
		$donation->Contact__c = $this->contact->Id;
		$donation->Amount__c = $this->data['amount-ext']; // XXX
		$donation->Description__c = strlen($this->transaction) > 255 ? substr($this->transaction, 0, 252) . '...' : $this->transaction;
		$donation->Donation_Date__c = date(static::$SF_DATE_FORMAT);
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
		$donor_email->setTargetObjectId($this->contact->Id);
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

Name: {$this->contact->FirstName} {$this->contact->LastName}
Amount: {$this->data['amount-string']}
Recurrence: {$this->data['recurrence-string']}
Email: {$this->contact->Email}
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
		if(isset($this->contact->How_did_you_hear_about_Spark__c)) {
			if(isset($this->data['hearabout-extra']) && !empty($this->data['hearabout-extra'])) {
				$str = $this->contact->How_did_you_hear_about_Spark__c . ": " . $this->data['hearabout-extra'];
				return $begin . $str;
			}
			return $begin . $this->contact->How_did_you_hear_about_Spark__c;
		}
		return null;
	}

	// This function is called if something goes wrong in the upsert
	// or create functions.. that way we don't lose the user data.
	// Param err: pass in the fault data
	private function emergency_email($err) {
		$body = "Something went wrong, and this contact was not inserted into Salesforce.\n"
			. "Here is the contact info:\n"
			. strval($this->contact)
			. "\nAnd here's the error message:\n"
			. $err;

		$spark_email = new SingleEmailMessage();
		$spark_email->setSenderDisplayName('Spark Donations');
		$spark_email->setToAddresses(explode(', ', get_option('alert_email_list')));
		$spark_email->setPlainTextBody($body);
		$spark_email->setSubject('Salesforce Capture Alert');

		static::$connection->sendSingleEmail(array($spark_email));
	}
} // end class ?>
