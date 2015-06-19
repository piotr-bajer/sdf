<?php
/*
	Plugin Name: Spark Donation Form
	Plugin URI:
	Description: Create and integrate a form with payment processing and CRM
	Author: Steve Avery
	Version: 2.0
	Author URI: https://stevenavery.com/
*/

error_reporting(0); // XXX remove for debugging
defined('ABSPATH') or die("Unauthorized.");

define('ERROR', 0);
define('SUCCESS', 1);
define('LOG', 2);

class sdf_data {

	private $data;

	private $strp_plan;
	private $strp_customer;

	private static $sf_cnxn;
	private $sf_contact;
	private $sf_txn;
	private $sf_donations;

	private static $FRIEND_OF_SPARK = '00130000007qhRG';
	private static $DONOR_SINGLE_TEMPLATE = '00X50000001VaHS';
	private static $DONOR_MONTHLY_TEMPLATE = '00X50000001eVEX';
	private static $DONOR_ANNUAL_TEMPLATE = '00X50000001eVEc';
	private static $RECURRING_TEMPLATE = '00X50000001fRwu';

	private static $DISPLAY_NAME = 'Spark';
	private static $SF_DATE_FORMAT = 'Y-m-d';

	private static $ONE_TIME = 0;
	private static $ANNUAL = 1;
	private static $MONTHLY = 2;

	// XXX remove
	private static $IS_NOT_CUSTOM = 0;
	private static $IS_CUSTOM = 1;

	private static $IS_NOT_MEMBER = 0;
	private static $IS_MEMBER = 1;

	// ************************************************************************
	// Main

	// Start processing, validation, execution....
	public function begin($postdata) {
		$this->data = $postdata;

		$this->validate();
		$this->setup_additional_info();

		$this->do_stripe();
		$this->do_salesforce();
	}

	private function validate() {
		$this->required_fields();
		$this->donation_category();
		$this->hearabout_category();
		$this->check_email();
	}

	private function setup_additional_info() {
		$this->set_amount();
		$this->set_recurrence();
		$this->get_ext_amount();
	}

	private function do_stripe() {
		$this->stripe_api();
		$this->charge();
	}

	private function do_salesforce() {
		$this->salesforce_api();
		$this->get_contact();
		$this->get_donations();
		$this->update();
		$this->upsert();
		$this->new_donation();
		$this->send_email();
	}

	// this is an alternative entrypoint to the sdf class.
	public function do_stripe_endpoint($info) {
		sdf_message_handler(LOG, 'Endpoint request received.');

		$this->salesforce_api();

		$this->get_contact($info['email']);

		// gets existing donations
		$this->get_donations();

		// need to update totals in the contact object
		$this->recalc_sum();

		// add a new line in the description
		$this->description();

		$this->renewal_date();
		$this->cleanup();

		$this->upsert();
		$this->new_donation();
		$this->send_email();

	}

	// ************************************************************************
	// Validation functions

	private function required_fields() {
		$fields = array(
			'donation',
			'first-name',
			'last-name',
			'email',
			'tel',
			'address1',
			'city',
			'state',
			'zip',
			'stripe-token'
		);

		foreach($fields as $key) {
			if(!array_key_exists($key, $this->data)
				|| empty($this->data[$key])) {
				sdf_message_handler(ERROR, 'Error: Missing required fields.');
			}
		}
	}

	// XXX change to regex
	private function donation_category() {
		$cats = array(
			'annual-75',
			'annual-100',
			'annual-250',
			'annual-500',
			'annual-1000',
			'annual-2500',
			'monthly-5',
			'monthly-10',
			'monthly-20',
			'monthly-50',
			'monthly-100',
			'monthly-200',
			'annual-custom',
			'monthly-custom'
		);


		if(!in_array($this->data['donation'], $cats)) {
			sdf_message_handler(ERROR, 'Invalid donation amount.');
		}
	}

	private function hearabout_category() {
		$cats = array(
			'Renewing Membership',
			'Friend',
			'Website',
			'Search',
			'Event'
		);

		if(!empty($this->data['hearabout'])) {
			if(!in_array($this->data['hearabout'], $cats)) {
				sdf_message_handler(LOG, 'Invalid hearabout category.');
				unset($data['hearabout']);
				unset($data['hearabout-extra']);
			}
		}
	}

	private function check_email() {
		$this->data['email'] = filter_var($this->data['email'], FILTER_SANITIZE_EMAIL);
		if(!filter_var($this->data['email'], FILTER_VALIDATE_EMAIL)) {
			sdf_message_handler(ERROR, 'Invalid email address.');
		}
	}

	// ************************************************************************
	// Setup functions

	private function set_recurrence() {
		if(array_key_exists('one-time', $this->data) 
			&& !empty($this->data['one-time'])) {
				$recurrence = 'Single donation';
				$type = static::$ONE_TIME;
		} else {
			if(strpos($this->data['donation'], 'annual') !== false) {
				$recurrence = 'Annual';
				$type = static::$ANNUAL;
			} else {
				$recurrence = 'Monthly';
				$type = static::$MONTHLY;
			}
		}

		$this->data['recurrence-type'] = $type;
		$this->data['recurrence-string'] = $recurrence;
	}

	// set up data['amount'] and ['amount-string']
	// is also testing for sensibility, can stop execution
	// side effect: sets data['custom']
	private function set_amount() {
		if(strpos($this->data['donation'], 'custom') !== false) {
			$this->data['custom'] = static::$IS_CUSTOM; // XXX

			if(array_key_exists('annual-custom', $this->data)) {
				$donated_value = $this->data['annual-custom'];
			} else if(array_key_exists('monthly-custom', $this->data)) {
				$donated_value = $this->data['monthly-custom'];
			}

			unset($this->data['monthly-custom']);
			unset($this->data['annual-custom']);

			// XXX move to separate function
			if(!is_numeric($donated_value)) {
				// replace anything not numeric or a . with nothing
				$donated_value = preg_replace('/([^0-9\\.])/i', '', $donated_value);
			}

			if($donated_value <= 0.50) {
				sdf_message_handler(ERROR, 'Invalid request. Donation amount too small.');
			}
			// end
		} else {
			$this->data['custom'] = static::$IS_NOT_CUSTOM;
			$donated_value = $this->get_std_amount();
		}

		$this->data['amount'] = $donated_value;
		$this->data['amount-string'] = '$' . $donated_value;
	}

	// returns the amount in cents of standard donations
	// XXX regex instead?
	private function get_std_amount() {
		$plan_amounts = array(
			'annual-75' => 75,
			'annual-100' => 100,
			'annual-250' => 250,
			'annual-500' => 500,
			'annual-1000' => 1000,
			'annual-2500' => 2500,
			'monthly-5' => 5,
			'monthly-10' => 10,
			'monthly-20' => 20,
			'monthly-50' => 50,
			'monthly-100' => 100,
			'monthly-200' => 200
		);

		// potentially erroneous return of null
		return $plan_amounts[$this->data['donation']];
	}

	// Find out how much the amount is for the year if it's monthly 
	// We won't need to do this if endpoint works.
	// we would still use it for the membership calculation.
	private function get_ext_amount() {
		if($this->data['recurrence-type'] == static::$MONTHLY) {
			$times = 13 - intval(date('n'));
			$this->data['amount-ext'] = $times * $this->data['amount'];
		} else {
			$this->data['amount-ext'] = $this->data['amount'];
		}
	}

	// ************************************************************************
	// Stripe functions

	// This function is public since we use it to test keys input to
	// the options page.
	public static function stripe_api($input = null) {
		require_once(WP_PLUGIN_DIR . '/sdf/stripe/lib/Stripe.php');
		if(!empty($input)) {
			Stripe::setApiKey($input);
		} else {
			Stripe::setApiKey(get_option('stripe_api_secret_key'));
		}
		Stripe::setApiVersion('2013-08-13');
	}

	// This function is public to perform initial setup of plans.
	// Execute when the api keys are updated

	// XXX should be removed
	public static function stripe_default_plans() {
		$plans = array(
			array(
				'id' => 'annual-75',
				'amount' => 7500,
				'currency' => 'USD',
				'interval' => 'year',
				'name' => '$75 Annual Gift',
			),
			array(
				'id' => 'annual-100',
				'amount' => 10000,
				'currency' => 'USD',
				'interval' => 'year',
				'name' => '$100 Annual Gift',
			),
			array(
				'id' => 'annual-250',
				'amount' => 25000,
				'currency' => 'USD',
				'interval' => 'year',
				'name' => '$250 Annual Gift',
			),
			array(
				'id' => 'annual-500',
				'amount' => 50000,
				'currency' => 'USD',
				'interval' => 'year',
				'name' => '$500 Annual Gift',
			),
			array(
				'id' => 'annual-1000',
				'amount' => 100000,
				'currency' => 'USD',
				'interval' => 'year',
				'name' => '$1000 Annual Gift',
			),
			array(
				'id' => 'annual-2500',
				'amount' => 250000,
				'currency' => 'USD',
				'interval' => 'year',
				'name' => '$2500 Annual Gift',
			),
			array(
				'id' => 'monthly-5',
				'amount' => 500,
				'currency' => 'USD',
				'interval' => 'month',
				'name' => '$5 Monthly Gift',
			),
			array(
				'id' => 'monthly-10',
				'amount' => 1000,
				'currency' => 'USD',
				'interval' => 'month',
				'name' => '$10 Monthly Gift',
			),
			array(
				'id' => 'monthly-20',
				'amount' => 2000,
				'currency' => 'USD',
				'interval' => 'month',
				'name' => '$20 Monthly Gift',
			),
			array(
				'id' => 'monthly-50',
				'amount' => 5000,
				'currency' => 'USD',
				'interval' => 'month',
				'name' => '$50 Monthly Gift',
			),
			array(
				'id' => 'monthly-100',
				'amount' => 10000,
				'currency' => 'USD',
				'interval' => 'month',
				'name' => '$100 Monthly Gift',
			),
			array(
				'id' => 'monthly-200',
				'amount' => 20000,
				'currency' => 'USD',
				'interval' => 'month',
				'name' => '$200 Monthly Gift',
			),
		);

		foreach($plans as $plan) {
			try {
				Stripe_Plan::create($plan);
			} catch(Stripe_Error $e) {
				$body = $e->getJsonBody();
				sdf_message_handler(LOG, __FUNCTION__ . ' : ' . $body['error']['message']);

				$message = '<span id="source">Salesforce error:</span> ' . $body['error']['message'];
				add_settings_error(
					'stripe_plans',
					'stripe_plans_error',
					$message,
					'error'
				);
			}
		}
	}

	private function charge() {
		if($this->data['recurrence-type'] == static::$ONE_TIME) {
			$this->single_charge();
		} else {
			$this->recurring_charge();
		}
	}

	private function single_charge() {
		try {
			$cents = $this->data['amount'] * 100;
			Stripe_Charge::create(array(
				'amount' => $cents,
				'card' => $this->data['stripe-token'],
				'currency' => 'usd',
				'description' => $this->data['email']
			));
		} catch(Stripe_Error $e) {
			$body = $e->getJsonBody();
			sdf_message_handler(ERROR, $body['error']['message']);
		}
	}

	private function recurring_charge() {
		if($this->data['custom'] == static::$IS_CUSTOM) {
			$this->custom_plan(); // XXX conglomerate the two.
		} else {
			$this->std_plan();
		}

		$this->stripe_customer();
		$this->subscribe();
	}

	// We assume that the custom plan has been created, and try to retrieve it
	// and if we fail, then we create the plan

	// XXX should consolidate plan logic
	private function custom_plan() {
		$plan_id = strtolower($this->data['recurrence-string']) . '-' . $this->data['amount'];

		try {
			$plan = Stripe_Plan::retrieve($plan_id);
		} catch(Stripe_Error $e) {
			$recurrence = ($this->data['recurrence-type'] == static::$ANNUAL ? 'year' : 'month');

			$cents = $this->data['amount'] * 100;

			$new_plan = array(
				'id' => $plan_id,
				'currency' => 'USD',
				'interval' => $recurrence,
				'amount' => $cents,
				'name' => $this->data['amount-string'] . ' ' . $recurrence . 'ly custom gift'
			);

			try {
				$plan = Stripe_Plan::create($new_plan);
			} catch(Stripe_Error $e) {
				$body = $e->getJsonBody();
				sdf_message_handler(LOG, __FUNCTION__ . ' : ' . $body['error']['message']);
				sdf_message_handler(ERROR, 'Something\'s not right. Please try again.');
			}
		}

		$this->strp_plan = $plan;
	}

	// XXX should be moved into one function with custom_plan
	private function std_plan() {
		try {
			$plan = Stripe_Plan::retrieve($this->data['donation']);
		} catch(Stripe_Error $e) {
			$body = $e->getJsonBody();
			sdf_message_handler(ERROR, $body['error']['message']);
		}

		$this->strp_plan = $plan;
	}

	private function stripe_customer() {
		$info = array(
			'card' => $this->data['stripe-token'],
			'email' => $this->data['email'],
			'description' => $this->data['first-name'] . ' ' . $this->data['last-name']
		);

		try {
			$customer = Stripe_Customer::create($info);
		} catch(Stripe_Error $e) {
			$body = $e->getJsonBody();
			sdf_message_handler(ERROR, $body['error']['message']);
		}

		$this->strp_customer = $customer;
	}

	private function subscribe() {
		try {
			$this->strp_customer->updateSubscription(array('plan' => $this->strp_plan->id));	
		} catch(Stripe_Error $e) {
			$body = $e->getJsonBody();
			sdf_message_handler(ERROR, $body['error']['message']);
		}
	}

	// ************************************************************************
	// SalesForce Functions

	// This function is public to allow verification from settings page
	// just like the Stripe API
	public static function salesforce_api($input = null) {
		require_once(WP_PLUGIN_DIR . '/sdf/salesforce/soapclient/SforceEnterpriseClient.php');
		
		$sf_cnxn = new SforceEnterpriseClient();
		$sf_cnxn->createConnection(WP_PLUGIN_DIR . '/sdf/salesforce/soapclient/sdf.wsdl.jsp.xml');
		
		if(!empty($input)) {
			// we expect the input to be a new token.
			$sf_cnxn->login(get_option('salesforce_username'), get_option('salesforce_password') . $input);
		} else {
			$sf_cnxn->login(get_option('salesforce_username'), get_option('salesforce_password') . get_option('salesforce_token'));	
		}

		static::$sf_cnxn = clone $sf_cnxn;
	}

	// This method queries the data in SalesForce using the provided email, 
	// and fills out this->sf_contact
	private function get_contact($email = null) {
		if(!empty($email)) {
			$id = $this->search_salesforce($email);
		} else {
			$id = $this->search_salesforce($this->data['email']);	
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
				$contact = array_pop(static::$sf_cnxn->retrieve($fieldlist, 'Contact', array($id)));
			} catch(Exception $e) {
				sdf_message_handler(LOG, __FUNCTION__ . ' : ' . $e->faultstring);
			}
		}
		
		$this->sf_contact = $contact;
		$this->sf_contact->Id = $id;
	}

	// Searches SalesForce for the user, and returns their ID, or null
	private function search_salesforce($email) {
		$query = 'FIND {"' . $email . '"} IN EMAIL FIELDS RETURNING CONTACT(ID)';

		try {
			$response = static::$sf_cnxn->search($query);
		} catch(Exception $e) {
			sdf_message_handler(LOG, __FUNCTION__ . ' : ' . $e->faultstring);
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
		
		if($this->sf_contact->Id !== null) {
			// the first of the year
			$cutoff_date = strtotime(date('Y') . '-01-01');

			$query = 'SELECT 
							(SELECT Amount__c, Donation_Date__c FROM Donations__r)
						FROM
							Contact
						WHERE
							Contact.Id = \'' . $this->sf_contact->Id . '\'';

			try {
				$response = static::$sf_cnxn->query($query);
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

		$this->sf_donations = $donations_list;
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
	private function recalc_sum() {
		$sum = 0;

		foreach($this->sf_donations as $donation) {
			$sum += $donation['amount'];
		}

		$sum += $this->data['amount-ext']; // XXX no extension...

		if($sum >= 75) { // change to intval?
			$this->data['is-member'] = static::$IS_MEMBER;
		} else {
			$this->data['is-member'] = static::$IS_NOT_MEMBER;
		}

		$this->sf_contact->Total_paid_this_year__c = $sum;
		// last amount paid
		$this->sf_contact->Paid__c = $this->data['amount-ext']; // XXX this amount, or what we get from the endpoint
	}

	private function member_level() {
		$amount = $this->sf_contact->Total_paid_this_year__c;
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

		$this->sf_contact->Member_Level__c = $level;
	}

	// Set the values that will always be the same
	private function set_constant() {
		$this->sf_contact->Payment_Type__c = 'Credit Card';
		$this->sf_contact->Contact_Status__c = 'Active';
		$this->sf_contact->Active_Member__c = true;
	}

	private function company() {
		if(!isset($this->sf_contact->AccountId)) {
			if(!empty($this->data['company'])) {
				$search = 'FIND {"' . $this->data['company'] 
					. '"} IN NAME FIELDS RETURNING Account(Id)';

				try {
					$records = static::$sf_cnxn->search($search);

					if(count($records->searchRecords)) {
						$id = $records->searchRecords[0]->Id;
					} else {
						$company = new stdClass();
						$company->Name = $this->data['company'];

						try {
							$created = static::$sf_cnxn->create(array($company), 'Account');
							$id = $created[0]->id;
						} catch(Exception $e) {
							sdf_message_handler(LOG, __FUNCTION__ . ' : ' . $e->faultstring);
							$id = static::$FRIEND_OF_SPARK;
						}
					}

					$this->sf_contact->AccountId = $id;
				} catch(Exception $e) {
					sdf_message_handler(LOG, __FUNCTION__ . ' : ' . $e->faultstring);
					$this->sf_contact->AccountId = static::$FRIEND_OF_SPARK;
				}

			} else {
				$this->sf_contact->AccountId = static::$FRIEND_OF_SPARK;
			} 
		}
	}

	private function name() {
		$this->sf_contact->FirstName = $this->data['first-name'];
		$this->sf_contact->LastName = $this->data['last-name'];
	}

	private function address() {
		$this->sf_contact->MailingStreet = empty($this->data['address2']) ? $this->data['address1'] 
			: $this->data['address1'] . "\n" . $this->data['address2'];

		$this->sf_contact->MailingCity = $this->data['city'];
		$this->sf_contact->MailingState = $this->data['state'];
		$this->sf_contact->MailingPostalCode = $this->data['zip'];
		$this->sf_contact->MailingCountry = $this->data['country'];
	}

	private function phone() {
		$this->sf_contact->Phone = $this->data['tel'];
	}

	private function description() {
		$this->sf_txn = $this->data['recurrence-string']
			. ' - ' . $this->data['amount-string']. ' - ' 
			. date('n/d/y') . ' - Online donation from ' . home_url() . '.';

		if(!empty($this->data['inhonorof'])) {
			$this->sf_txn .= ' In honor of: ' . $this->data['inhonorof'];
		}

		if(isset($this->sf_contact->Description)) {
			$desc = $this->sf_contact->Description . "\n" . $this->sf_txn;
		} else {
			$desc = $this->sf_txn;
		}

		$this->sf_contact->Description = $desc;
	}

	private function email() {
		$this->sf_contact->Email = $this->data['email'];
	}

	// SalesForce Type - Donor or Member
	private function type() {
		$type = $this->data['is-member'] == static::$IS_MEMBER ? 'Spark Member' : 'Donor';
		$this->sf_contact->Type__c = $type;
	}

	private function start_date() {
		$this->sf_contact->Membership_Start_Date__c = date(static::$SF_DATE_FORMAT);
	}

	// Renewal is always updated to be one more year from now
	private function renewal_date() {
		$this->sf_contact->Renewal_Date__c = date(static::$SF_DATE_FORMAT, strtotime('+1 year'));
	}

	private function first_active() {
		if(!isset($this->sf_contact->First_Active_Date__c)) {
			$this->sf_contact->First_Active_Date__c = date(static::$SF_DATE_FORMAT);
		}
	}

	private function gender() {
		if(!empty($this->data['gender'])) {
			$this->sf_contact->Gender__c = ucfirst($this->data['gender']);
		}
	}

	private function board_member() {
		if(!isset($this->sf_contact->Board_Member_Contact_Owner__c)) {
			$this->sf_contact->Board_Member_Contact_Owner__c = 'Amanda Brock';
		}
	}

	private function birthday() {
		// Birth_Month_Year__c
		if(!empty($this->data['birthday-month']) && !empty($this->data['birthday-year'])) {
			$this->sf_contact->Birth_Month_Year__c = date('m/Y', 
				strtotime($this->data['birthday-year'] . '-' . $this->data['birthday-month']));
		}
	}

	// Form field hearabout-extra is not being sent to SalesForce
	// but we do notify Spark Team about it.

	// XXX we want the hearabout-extra field to go into the contact lookup
	// Referred_By__c
	private function hdyh_type() {
		if(!empty($this->data['hearabout'])) {
			if(!isset($this->sf_contact->How_did_you_hear_about_Spark__c)) {
				$this->sf_contact->How_did_you_hear_about_Spark__c = ucfirst($this->data['hearabout']);
			}
		}
	}

	// We need to keep track of the individual donation amount,
	// not extended, not total for this year.
	private function unextended() {
		$this->sf_contact->Donation_Each__c = $this->data['amount'];
	}

	// This function removes empty fields from the contact object
	private function cleanup() {
		foreach($this->sf_contact as $property => $value) {
			if(is_null($value)) {
				unset($this->sf_contact->$property);
			}
		}
	}

	private function upsert() {
		if(isset($this->sf_contact->Id)) {
			// update on id.
			try {
				static::$sf_cnxn->update(array($this->sf_contact), 'Contact');
			} catch(Exception $e) {
				sdf_message_handler(LOG, __FUNCTION__ . ' : ' . $e->faultstring);
				$this->emergency_email($e->faultstring);
			}
		} else {
			// create new contact.
			try {
				$response = array_pop(static::$sf_cnxn->create(array($this->sf_contact), 'Contact'));

				if(empty($response->success)) {
					$error = $response->errors[0]->message;
					$this->emergency_email($error);
				} else {
					$this->sf_contact->Id = $response->id;	
				}

			} catch(Exception $e) {
				sdf_message_handler(LOG, __FUNCTION__ . ' : ' . $e->faultstring);
				$this->emergency_email($e->faultstring);
			}
		}
	}

	// Uses sf_txn to hold donation description
	// Create the donation line item child object
	// Called after insert, because we have to have the contact id
	private function new_donation() {
		$donation = new stdClass();
		$donation->Contact__c = $this->sf_contact->Id;
		$donation->Amount__c = $this->data['amount-ext']; // XXX
		$donation->Description__c = strlen($this->sf_txn) > 255 ? substr($this->sf_txn, 0, 252) . '...' : $this->sf_txn;
		$donation->Donation_Date__c = date(static::$SF_DATE_FORMAT);
		$donation->Type__c = 'Membership';

		try {
			static::$sf_cnxn->create(array($donation), 'Donation__c');
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
		$donor_email->setTargetObjectId($this->sf_contact->Id);
		$donor_email->setReplyTo(get_option('sf_email_reply_to'));
		$donor_email->setSenderDisplayName(static::$DISPLAY_NAME);

		try {
			static::$sf_cnxn->sendSingleEmail(array($donor_email));
		} catch(Exception $e) {
			sdf_message_handler(LOG, __FUNCTION__ . ': Donor email failure! ' . $e->faultstring);
		}

		// Alert email
		$hear = $this->hdyh();
		$honor = empty($this->data['inhonorof']) ? null : 'In honor of: ' . $this->data['inhonorof'];

		$body = <<<EOF
A donation has been made!

Name: {$this->sf_contact->FirstName} {$this->sf_contact->LastName}
Amount: {$this->data['amount-string']}
Recurrence: {$this->data['recurrence-string']}
Email: {$this->sf_contact->Email}
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
			static::$sf_cnxn->sendSingleEmail(array($spark_email));
		} catch(Exception $e) {
			sdf_message_handler(LOG, __FUNCTION__ . ': Alert email failure! ' . $e->faultstring);
		}
	}

	// Get the string describing hearabout and hearabout-extra
	private function hdyh() {
		$begin = "How did they hear about Spark? ";
		if(isset($this->sf_contact->How_did_you_hear_about_Spark__c)) {
			if(isset($this->data['hearabout-extra']) && !empty($this->data['hearabout-extra'])) {
				$str = $this->sf_contact->How_did_you_hear_about_Spark__c . ": " . $this->data['hearabout-extra'];
				return $begin . $str;
			}
			return $begin . $this->sf_contact->How_did_you_hear_about_Spark__c;
		}
		return null;
	}

	// This function is called if something goes wrong in the upsert
	// or create functions.. that way we don't lose the user data.
	// Param err: pass in the fault data
	private function emergency_email($err) {
		$body = "Something went wrong, and this contact was not inserted into Salesforce.\n"
			. "Here is the contact info:\n"
			. strval($this->sf_contact)
			. "\nAnd here's the error message:\n"
			. $err;

		$spark_email = new SingleEmailMessage();
		$spark_email->setSenderDisplayName('Spark Donations');
		$spark_email->setToAddresses(explode(', ', get_option('alert_email_list')));
		$spark_email->setPlainTextBody($body);
		$spark_email->setSubject('Salesforce Capture Alert');

		static::$sf_cnxn->sendSingleEmail(array($spark_email));
	}

	// ************************************************************************

} // end sdf_data

function sdf_message_handler($type, $message) {
	// type = (ERROR | SUCCESS | LOG)
	// all messages are written to sdf.log
	// rotated every six months
	// success and error messages are passed as an object to the waiting js
	// the data structure is json, and should be very simple.
	// data.type = error | success
	// data.message = message

	switch($type) {
		case ERROR: $type = 'error'; break;
		case SUCCESS: $type = 'success'; break;
		case LOG: $type = 'log'; break;
	}

	$logmessage = time() . ' - ' . $type . ' - ' . $message . "\n";
	file_put_contents(WP_PLUGIN_DIR . '/sdf/sdf.log', $logmessage, FILE_APPEND);

	if($type != 'log') {
		ob_clean();
		$data = array(
			'type' => $type,
			'message' => $message
		);
		echo json_encode($data);
		ob_flush();
		die();
	}
}

function sdf_clean_log() {
	$file = WP_PLUGIN_DIR . '/sdf/sdf.log';
	$handle = fopen($file, 'r+');
	$linecount = 0;
	while(!feof($handle)) {
		$line = fgets($handle);
		$linecount++;
	}
	ftruncate($handle, 0);
	rewind($handle);
	fwrite($handle, time() . ' - Cron run. ' . $linecount . ' lines cleared.' . "\n");
	fclose($handle);
}

// XXX the duration of the interval does not match the function name
function sdf_add_bimonthly($schedules) {
	$schedules['bimonthly'] = array(
		'interval' => 10000000,
		'display' => __('Every Six Months')
	);
	return $schedules;
}

// ****************************************************************************
// Set up hooks

if(is_admin()) {
	add_action('admin_init', 'sdf_register_settings');
	add_action('admin_menu', 'sdf_create_menu');
	// http://codex.wordpress.org/AJAX_in_Plugins#Ajax_on_the_Viewer-Facing_Side
	add_action('wp_ajax_sdf_parse', 'sdf_parse');
	add_action('wp_ajax_nopriv_sdf_parse', 'sdf_parse');
}
add_action('template_redirect', 'sdf_template');
add_action('wp_head', 'sdf_ajaxurl');
add_filter('cron_schedules', 'sdf_add_bimonthly');
add_action('sdf_bimonthly_hook', 'sdf_clean_log');
register_activation_hook(__FILE__, 'sdf_activate');
register_deactivation_hook(__FILE__, 'sdf_deactivate');

// ****************************************************************************
// Ajax response function

function sdf_parse() {
	if(!isset($_POST['data'])) {
		sdf_message_handler('log', __FUNCTION__ . ' No data received');
		die();
	} else {
		$sdf = new sdf_data();
		$sdf->begin($_POST['data']);
		unset($_POST['data']);
	}
	
	sdf_message_handler(SUCCESS, 'Thank you for your donation!');
	die(); // prevent trailing 0 from admin-ajax.php
}

// ****************************************************************************
// Activation and Deactivation functions

function sdf_activate() {
	if(wp_next_scheduled('sdf_bimonthly_hook') == false) {
		wp_schedule_event(
			current_time('timestamp'), // timestamp
			'bimonthly', // recurrence
			'sdf_bimonthly_hook' // hook
		);
	}

}

function sdf_deactivate() {
	unregister_setting(
		'sdf', // option group
		'stripe_api_secret_key', // option name
		'sdf_stripe_secret_sanitize' // sanitize callback.
	);
	unregister_setting(
		'sdf',
		'stripe_api_public_key',
		'sdf_stripe_secret_sanitize'
	);
	unregister_setting(
		'sdf',
		'salesforce_username',
		'sdf_string_setting_sanitize'
	);
	unregister_setting(
		'sdf',
		'salesforce_password',
		'sdf_string_setting_sanitize'
	);
	unregister_setting(
		'sdf',
		'salesforce_token',
		'sdf_salesforce_api_check'
	);
	unregister_setting(
		'sdf',
		'alert_email_list',
		'sdf_validate_settings_emails'
	);
	unregister_setting(
		'sdf',
		'sf_email_reply_to',
		'sdf_validate_settings_emails'
	);
	unregister_setting(
		'sdf',
		'spark_address',
		'sdf_string_setting_sanitize'
	);
	unregister_setting(
		'sdf',
		'spark_contact_email',
		'sdf_validate_settings_emails'
	);

	wp_clear_scheduled_hook(
		'sdf_bimonthly_hook'
	);
}
// ****************************************************************************
// Options page

/*
TODO: Would also like to include the following settings:
page picker for the donate form plugin.
page picker for landing page.
*/
function sdf_options_page() { ?>
	<div class="wrap">
		<?php screen_icon(); ?>
		<h2>Spark Donation Form</h2>
		<form method="post" action="options.php">
			<?php 
				settings_fields('sdf');
				do_settings_sections('spark-form-admin');
				submit_button();
			?>
		</form>
	</div>
<?php }

function sdf_register_settings() {
	// Stripe stuff
	register_setting(
		'sdf', // option group name
		'stripe_api_secret_key', // option name
		'sdf_stripe_secret_sanitize' // option sanitization callback
	);
	register_setting(
		'sdf',
		'stripe_api_public_key',
		'sdf_string_setting_sanitize'
	);
	add_settings_section(
		'sdf_stripe_api', // id attribute of tags ??
		'Stripe', // section title
		'sdf_stripe_api_section_print', // callback to print section content
		'spark-form-admin' // page slug to work on
	);

	// Salesforce stuff
	register_setting(
		'sdf',
		'salesforce_username',
		'sdf_string_setting_sanitize'
	);
	register_setting(
		'sdf',
		'salesforce_password',
		'sdf_sf_password_validate'
	);
	register_setting(
		'sdf',
		'salesforce_token',
		'sdf_salesforce_api_check'
	);
	add_settings_section(
		'sdf_salesforce_api',
		'Salesforce',
		'sdf_salesforce_section_print',
		'spark-form-admin'
	);

	// email security
	register_setting(
		'sdf',
		'alert_email_list',
		'sdf_validate_settings_emails'
	);
	register_setting(
		'sdf',
		'sf_email_reply_to',
		'sdf_validate_settings_emails'
	);
	add_settings_section(
		'sdf_alert_email',
		'Alert Emails',
		'sdf_email_section_print',
		'spark-form-admin'
	);

	// Donation page address and contact email
	register_setting(
		'sdf',
		'spark_address',
		'sdf_string_no_sanitize'
	);
	register_setting(
		'sdf',
		'spark_contact_email',
		'sdf_validate_settings_emails'
	);
	add_settings_section(
		'sdf_spark_details',
		'Spark Details',
		'sdf_spark_details_print',
		'spark-form-admin'
	);
}

function sdf_stripe_api_section_print() {
	echo "<p>Enter your API keys.<br>Ensure that your public and private keys match, whether in live mode, or test mode.</p>";
	sdf_print_stripe_api_settings_form();
}

function sdf_salesforce_section_print() {
	echo "<p>Enter your username, password, and the security token.<br>If you reset your password, you will need to <a href='https://na3.salesforce.com/_ui/system/security/ResetApiTokenEdit?retURL=%2Fui%2Fsetup%2FSetup%3Fsetupid%3DPersonalInfo&setupid=ResetApiToken'>reset your security token too.</a></p>";
	sdf_print_salesforce_settings_form();
}

function sdf_email_section_print() {
	echo "<p>Set the email addresses that receive alert emails, and the reply-to address for outgoing mails.</p>";
	sdf_print_email_settings_form();
}

function sdf_spark_details_print() {
	echo "<p>Set the Spark SF contact address and contact email.</p>";
	sdf_print_spark_details_form();
}

function sdf_print_email_settings_form() { ?>
<table class="form-table">
	<tr valign="top">
		<th scope="row">Notification addresses:<br><span id="note">(Separate emails with a comma and space)</span></th>
		<td>
			<input class="sdf-wide" type="text" id="alert_email_list" name="alert_email_list" value="<?php echo esc_attr(get_option('alert_email_list')); ?>" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">Reply-to:</th>
		<td>
			<input class="sdf-wide" type="text" id="sf_email_reply_to" name="sf_email_reply_to" value="<?php echo esc_attr(get_option('sf_email_reply_to')); ?>" />
		</td>
	</tr>
</table>
<?php }

function sdf_print_stripe_api_settings_form() { ?>
<table class="form-table">
	<tr valign="top">
		<th scope="row">Stripe secret key:</th>
		<td>
			<input class="sdf-wide" type="text" id="stripe_api_secret_key" name="stripe_api_secret_key" value="<?php echo esc_attr(get_option('stripe_api_secret_key')); ?>" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">Stripe public key:</th>
		<td>
			<input class="sdf-wide" type="text" id="stripe_api_public_key" name="stripe_api_public_key" value="<?php echo esc_attr(get_option('stripe_api_public_key')); ?>" />
		</td>
	</tr>
</table>
<?php }

function sdf_print_salesforce_settings_form() { ?>
<table class="form-table">
	<tr valign="top">
		<th scope="row">Salesforce username:</th>
		<td>
			<input class="sdf-wide" type="text" id="salesforce_username" name="salesforce_username" value="<?php echo esc_attr(get_option('salesforce_username')); ?>" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">Salesforce password:</th>
		<td>
			<input class="sdf-wide" type="password" id="salesforce_password" name="salesforce_password" value="<?php echo sdf_password_dummy('salesforce_password'); ?>" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">Salesforce token:</th>
		<td>
			<input class="sdf-wide" type="text" id="salesforce_token" name="salesforce_token" value="<?php echo esc_attr(get_option('salesforce_token')); ?>" />
		</td>
	</tr>
</table>
<?php }

function sdf_print_spark_details_form() { ?>
<table class="form-table">
	<tr valign="top">
		<th scope="row">Spark's mailing address</th>
		<td>
			<textarea class="sdf-wide" id="spark_address" name="spark_address" rows="3"><?php echo esc_attr(get_option('spark_address')); ?></textarea>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">Spark's contact email</th>
		<td>
			<input class="sdf-wide" type="text" id="spark_contact_email" name="spark_contact_email" value="<?php echo esc_attr(get_option('spark_contact_email')); ?>" />
		</td>
	</tr>
</table>
<?php }

function sdf_stripe_secret_sanitize($input) {
	if(strlen($input)) {
		sdf_data::stripe_api($input);
		try {
			$count = Stripe_Plan::all()->count;
		} catch(Stripe_Error $e) {
			$message = $e->getJsonBody();
			$message = $message['error']['message'];
			add_settings_error(
				'stripe_api_secret_key', // id, or slug, of the pertinent setting
				'stripe_api_secret_key_auth_error', // id or slug of the error itself
				$message,
				'error' // message type, since this function actually handles everything including updates
			);
		}
		if($count == 0) {
			// XXX don't do this at all
			sdf_data::create_std_plans(); 
		}
	}
	return $input;
}

function sdf_salesforce_api_check($input) {
	if(strlen($input)) {
		$input = sdf_string_setting_sanitize($input);
		if(get_option('salesforce_username') && get_option('salesforce_password')) {
			try {
				sdf_data::salesforce_api($input);
			} catch(Exception $e) {
				$message = '<span id="source">Salesforce error:</span> ' . $e->faultstring;
				add_settings_error(
					'salesforce_token',
					'salesforce_token_auth_error',
					$message,
					'error'
				);
			}
		}
	}
	return $input;
}

function sdf_string_setting_sanitize($input) {
	return trim(sanitize_text_field($input));
}

function sdf_string_no_sanitize($input) {
	return $input;
}

// This function prevents overwriting the saved password
// with dummy data

// It needs to be specific to salesforce because we need to get
// the specific option 'salesforce_password',
// and there's no way to pass another parameter
function sdf_sf_password_validate($input) {
	if($input == sdf_password_dummy('salesforce_password')) {
		return get_option('salesforce_password');
	} else {
		return trim($input);
	}
}

function sdf_validate_settings_emails($input) {
	// can receive a list or one email.
	$list = explode(", ", $input);
	foreach($list as $email) {
		if(strlen($email)) {
			if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$message = '<span id="source">Email error:</span> Email address failed validation.';
				add_settings_error(
					'email_alert',
					'email_value_error',
					$message,
					'error'
				);
			}
		}
	}
	if(count($list) == 1) {
		return $input;
	} else {
		return implode(', ', $list);
	}
}

function sdf_create_menu() {
	$page = add_options_page(
		'Spark Form Settings', // the options page html title tag contents
		'Spark Donation Form', // settings menu link title
		'manage_options', // capability requirement
		'spark-form-admin', // options page slug
		'sdf_options_page' // callback to print markup
	);
	add_action('admin_print_styles-' . $page, 'sdf_enqueue_admin_styles' );
}

function sdf_enqueue_admin_styles() {
	wp_enqueue_style(
		'sdf_admin_css', // style handle
		plugins_url('sdf/css/admin.css') // href
	);
}

// If there is a value in the database, we output a dummy value
// Or if there is no value in the database, we print nothing.
function sdf_password_dummy($setting) {
	$string = get_option($setting);
	for($ii = 0; $ii < strlen($string); $ii++) {
		$string[$ii] = '*';
	}
	return $string;
}


// ****************************************************************************
// HTML and redirect functions

function sdf_template() {
	global $wp;
	if(array_key_exists('pagename', $wp->query_vars)) {
		if($wp->query_vars['pagename'] == 'donate') { // XXX needs to be changed if the page
			$return_template = 'templates/page_donation.php';  // goes live on a new slug
			sdf_theme_redirect($return_template);
		}
	}	
}

function sdf_theme_redirect($url) {
	global $post, $wp_query;
	if(have_posts()) {
		include($url);
		die();
	} else {
		$wp_query->is_404 = true;
	}
}

function sdf_ajaxurl() { ?>
	<script type="text/javascript">
		var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
	</script>
<?php }

function sdf_check_ssl() {
	if(!(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')) {
		header('HTTP/1.1 301 Moved Permanently');
        header('Location: https://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);
        exit();
	}
}

function sdf_webshim() { ?>
	<script type="text/javascript">
		webshim.setOptions('extendNative', true);
		webshim.polyfill('forms');
	</script>
<?php }

function sdf_noindex() {
	echo "<META NAME=\"ROBOTS\" CONTENT=\"NOINDEX, NOFOLLOW\">";
}

function sdf_get_form() { ?>
	<div id="sdf_form">
		<form method="post">
			<h1>Make a Donation</h1>
			<fieldset>
				<legend>Make an annual gift:</legend>
				<input class="amount" type="radio" name="donation" id="annual-75" value="annual-75" required><label class="button-look" onclick for="annual-75">$75</label>
				<input class="amount" type="radio" name="donation" id="annual-100" value="annual-100" required><label onclick class="button-look" for="annual-100">$100</label>
				<input class="amount" type="radio" name="donation" id="annual-250" value="annual-250" required><label class="button-look" onclick for="annual-250">$250</label>
				<input class="amount" type="radio" name="donation" id="annual-500" value="annual-500" required><label class="button-look" onclick for="annual-500">$500</label>
				<input class="amount" type="radio" name="donation" id="annual-1000" value="annual-1000" required><label class="button-look" onclick for="annual-1000">$1000</label>
				<input class="amount" type="radio" name="donation" id="annual-2500" value="annual-2500" required><label class="button-look" onclick for="annual-2500">$2500</label>
				<input class="amount" type="radio" name="donation" id="annual-custom" value="annual-custom" required>
				<label class="button-look custom-label" onclick for="annual-custom">Custom amount</label><span id="invalid-annual-custom" class="h5-error-msg" style="display:none;">This field is required. Please enter a valid value.</span>
			</fieldset>
			<fieldset>
				<legend>Or, make a monthly gift:</legend>
				<input class="amount" type="radio" name="donation" id="monthly-5" value="monthly-5" required><label class="button-look" onclick for="monthly-5">$5</label>
				<input class="amount" type="radio" name="donation" id="monthly-10" value="monthly-10" required><label class="button-look" onclick for="monthly-10">$10</label>
				<input class="amount" type="radio" name="donation" id="monthly-20" value="monthly-20" checked required><label class="selected button-look" onclick for="monthly-20">$20</label>
				<input class="amount" type="radio" name="donation" id="monthly-50" value="monthly-50" required><label class="button-look" onclick for="monthly-50">$50</label>
				<input class="amount" type="radio" name="donation" id="monthly-100" value="monthly-100" required><label class="button-look" onclick for="monthly-100">$100</label>
				<input class="amount" type="radio" name="donation" id="monthly-200" value="monthly-200" required><label class="button-look" onclick for="monthly-200">$200</label>
				<input class="amount" type="radio" name="donation" id="monthly-custom" value="monthly-custom" required>
				<label class="button-look custom-label" onclick for="monthly-custom">Custom amount</label><span id="invalid-monthly-custom" class="h5-error-msg" style="display:none;">This field is required. Please enter a valid value.</span>
			</fieldset>
			<label id="one-time-label" for="one-time">No thanks, I only want to make a one-time gift of the amount above.</label>
			<input type="checkbox" name="one-time" id="one-time">
			<br>
			<hr class="dashed-line">
			<div class="wider">
				<label for="hearabout">How did you hear about Spark?</label>
				<select name="hearabout" id="hearabout">
					<option value="">--</option>
					<option value="Renewing Membership">Renewing Membership</option>
					<option value="Friend" class="js-select-extra">Friend</option>
					<option value="Website">Website</option>
					<option value="Search">Search</option>
					<option value="Event" class="js-select-extra">Event</option>
				</select>
				<br>
				<div id="js-select-extra-input" class="hideme">
					<label for="hearabout-extra">Name of <span id="js-select-extra-name"></span></label>
					<input type="text" name="hearabout-extra" id="hearabout-extra">
				</div>
				<label for="inhonorof">Please make this donation in honor of:</label>
				<input type="text" id="inhonorof" name="inhonorof">
			</div>
			<hr class="dashed-line">
			<h3>A little about you:</h3>
			<label for"first-name">Name: <span class="label-required">*</span></label>
			<input name="first-name" id="first-name" type="text" placeholder="First" data-h5-errorid="invalid-fname" required>
			<span id="invalid-fname" class="h5-error-msg" style="display:none;">This field is required.</span>
			<input name="last-name" id="last-name" type="text" placeholder="Last" data-h5-errorid="invalid-lname" required>
			<span id="invalid-lname" class="h5-error-msg" style="display:none;">This field is required.</span>
			<br>
			<label for="company">Company:</label>
			<input class="wider" type="text" id="company" name="company">
			<br>
			<label for="birthday-month">Birthday:</label>
			<input maxlength="2" id="birthday-month" class="date-input" name="birthday-month" pattern="^(0?[1-9]|1[012])" placeholder="Month" data-h5-errorid="invalid-bday-month">
			<span id="bday-separator">/</span>
			<input maxlength="4" id="birthday-year" class="date-input" name="birthday-year" pattern="^(19|20)\d{2}$" placeholder="Year" data-h5-errorid="invalid-bday-year">
			<span id="invalid-bday-month" class="h5-error-msg" style="display:none;">Please enter a valid month. Format: MM</span>
			<span id="invalid-bday-year" class="h5-error-msg" style="display:none;">Please enter a valid year. Format: YYYY</span>
			<br>
			<label for="gender">Gender:</label>
			<select name="gender" id="gender">
				<option value="">--</option>
				<option value="Female">Female</option>
				<option vale="Male">Male</option>
				<option value="other">Other</option>
			</select>
			<br>
			<label for"email">E-mail: <span class="label-required">*</span></label>
			<input class="wider h5-email" name="email" id="email" type="email" data-h5-errorid="invalid-email" required>
			<span id="invalid-email" class="h5-error-msg" style="display:none;">Please enter a valid email.</span>
			<br>
			<label for"tel">Phone: <span class="label-required">*</span></label>
			<input maxlength="15" name="tel" id="tel" type="text" data-h5-errorid="invalid-phone" pattern="^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$" required>
			<span id="invalid-phone" class="h5-error-msg" style="display:none;">Please enter a valid telephone number with area code.</span>
			<br>
			<label for"address1">Street Address: <span class="label-required">*</span></label>
			<input class="wider" name="address1" id="address1" type="text" data-h5-errorid="invalid-addr1" required>
			<span id="invalid-addr1" class="h5-error-msg" style="display:none;">This field is required.</span>
			<br>
			<label for"address2">Address 2:</label>
			<input class="wider" name="address2" id="address2" type="text">
			<br>
			<div class="address-padding cf">
				<div>
					<label for"city">City: <span class="label-required">*</span></label>
					<input name="city" id="city" type="text" data-h5-errorid="invalid-city" required>
					<span id="invalid-city" class="h5-error-msg" style="display:none;">This field is required.</span>
				</div>
				<div>
					<label for"state">State/Province: <span class="label-required">*</span></label>
					<input class="state-width" name="state" id="state" type="text" maxlength="2" pattern="[a-zA-Z]{2}" data-h5-errorid="invalid-state" required>
					<span id="invalid-state" class="h5-error-msg" style="display:none;">This field is required. Use the two letter code.</span>
				</div>
				<div class="last">
					<label for"zip">ZIP/Postal Code: <span class="label-required">*</span></label>
					<input maxlength="10" name="zip" id="zip" type="text" pattern="^\d{5}(-\d{4})?$" data-h5-errorid="invalid-zip" required>
					<span id="invalid-zip" class="h5-error-msg" style="display:none;">Please enter a valid ZIP/postal code.</span>
				</div>
			</div>
			<label for="country">Country:</label>
			<?php sdf_get_country_select('country'); ?>
			<hr class="dashed-line">
			<h3>Billing Information:</h3>
			<label for="cc-number">Credit Card Number: <span class="label-required">*</span></label>
			<input maxlength="16" type="text" id="cc-number" name="cc-number" pattern="\d{14,16}" data-h5-errorid="invalid-cc-num" required>
			<span id="invalid-cc-num" class="h5-error-msg" style="display:none;">Please enter a valid credit card number.</span>
			<br>
			<label for="cc-cvc">Security Code: <span class="label-required">*</span></label>
			<input maxlength="4" type="text" id="cc-cvc" name="cc-cvc" pattern="[\d]{3,4}" data-h5-errorid="invalid-cvc" required>
			<span id="invalid-cvc" class="h5-error-msg" style="display:none;">This field is required.</span>
			<br>
			<label for="cc-exp-mo">Expiration Date: <span class="label-required">*</span></label>
			<input maxlength="2" id="cc-exp-mo" class="date-input" name="cc-exp-mo" placeholder="Month" pattern="^(0?[1-9]|1[012])$" data-h5-errorid="invalid-cc-mo" required>
			<span id="invalid-cc-mo" class="h5-error-msg" style="display:none;">This field is required. Format: MM</span>
			<span id="cc-exp-separator">/</span>
			<input maxlength="4" id="cc-exp-year" class="date-input" name="cc-exp-year" placeholder="Year" pattern="^(1[0-9])|20[\d]{2}" data-h5-errorid="invalid-cc-year" required>
			<span id="invalid-cc-year" class="h5-error-msg" style="display:none;">This field is required. Format: YYYY</span>
			<hr class="dashed-line">
			<label id="copy-personal-info-label" for="copy-personal-info">Copy billing information from above?</label>
			<input type="checkbox" id="copy-personal-info" class="js-copy-personal-info">
			<div id="js-cc-fields">
				<label for="cc-name">Name on Card: <span class="label-required">*</span></label>
				<input class="wider" type="text" id="cc-name" name="cc-name" data-h5-errorid="invalid-cc-name" required>
				<span id="invalid-cc-name" class="h5-error-msg" style="display:none;">This field is required.</span>
				<br>
				<label for="cc-zip">ZIP / Postal Code: <span class="label-required">*</span></label>
				<input maxlength="10" type="text" id="cc-zip" name="cc-zip" pattern="^\d{5}(-\d{4})?$" data-h5-errorid="invalid-cc-zip" required>
				<span id="invalid-cc-zip" class="h5-error-msg" style="display:none;">Please enter a valid ZIP/postal code.</span>
			</div>
			<input type="hidden" name="stripe-token" id="stripe-token">
			<div class="button-dark">
				<a href="javascript:void(0);"id="js-form-submit">Donate Now</a>
				<span>
					<img src="/img/button-dark-tip.png">
				</span>
			</div>
			<hr class="dashed-line">
			<div id="checks">
				<span>Send checks to:</span><br>
				<?php echo get_option('spark_address'); ?>
			</div>
			<div id="contact">
				<span>Questions? <a target="_blank" href="mailto:<?php echo get_option('spark_contact_email', 'programs@sparksf.org'); ?>">Contact us.</a></span>
			</div>
		</form>
	</div>
<?php } // end function sdf_get_form


// TODO: should we move this to its own file?
// XXX use http://baymard.com/labs/country-selector instead
function sdf_get_country_select($name_attr) { ?>
	<select name="<?php echo $name_attr; ?>" id="<?php echo $name_attr; ?>">
		<option value="">--</option>
		<option value="United States">United States</option>
		<option value="Afghanistan">Afghanistan</option>
		<option value="Albania">Albania</option>
		<option value="Algeria">Algeria</option>
		<option value="American Samoa">American Samoa</option>
		<option value="Andorra">Andorra</option>
		<option value="Angola">Angola</option>
		<option value="Anguilla">Anguilla</option>
		<option value="Antarctica">Antarctica</option>
		<option value="Antigua and Barbuda">Antigua and Barbuda</option>
		<option value="Argentina">Argentina</option>
		<option value="Armenia">Armenia</option>
		<option value="Aruba">Aruba</option>
		<option value="Australia">Australia</option>
		<option value="Austria">Austria</option>
		<option value="Azerbaijan">Azerbaijan</option>
		<option value="Bahamas">Bahamas</option>
		<option value="Bahrain">Bahrain</option>
		<option value="Bangladesh">Bangladesh</option>
		<option value="Barbados">Barbados</option>
		<option value="Belarus">Belarus</option>
		<option value="Belgium">Belgium</option>
		<option value="Belize">Belize</option>
		<option value="Benin">Benin</option>
		<option value="Bermuda">Bermuda</option>
		<option value="Bhutan">Bhutan</option>
		<option value="Bolivia">Bolivia</option>
		<option value="Bosnia and Herzegovina">Bosnia and Herzegovina</option>
		<option value="Botswana">Botswana</option>
		<option value="Bouvet Island">Bouvet Island</option>
		<option value="Brazil">Brazil</option>
		<option value="British Indian Ocean Territory">British Indian Ocean Territory</option>
		<option value="Brunei Darussalam">Brunei Darussalam</option>
		<option value="Bulgaria">Bulgaria</option>
		<option value="Burkina Faso">Burkina Faso</option>
		<option value="Burundi">Burundi</option>
		<option value="Cambodia">Cambodia</option>
		<option value="Cameroon">Cameroon</option>
		<option value="Canada">Canada</option>
		<option value="Cape Verde">Cape Verde</option>
		<option value="Cayman Islands">Cayman Islands</option>
		<option value="Central African Republic">Central African Republic</option>
		<option value="Chad">Chad</option>
		<option value="Chile">Chile</option>
		<option value="China">China</option>
		<option value="Christmas Island">Christmas Island</option>
		<option value="Cocos (Keeling) Islands">Cocos (Keeling) Islands</option>
		<option value="Colombia">Colombia</option>
		<option value="Comoros">Comoros</option>
		<option value="Congo">Congo</option>
		<option value="Congo, The Democratic Republic of The">Congo, The Democratic Republic of The</option>
		<option value="Cook Islands">Cook Islands</option>
		<option value="Costa Rica">Costa Rica</option>
		<option value="Cote D'ivoire">Cote D'ivoire</option>
		<option value="Croatia">Croatia</option>
		<option value="Cuba">Cuba</option>
		<option value="Cyprus">Cyprus</option>
		<option value="Czech Republic">Czech Republic</option>
		<option value="Denmark">Denmark</option>
		<option value="Djibouti">Djibouti</option>
		<option value="Dominica">Dominica</option>
		<option value="Dominican Republic">Dominican Republic</option>
		<option value="Ecuador">Ecuador</option>
		<option value="Egypt">Egypt</option>
		<option value="El Salvador">El Salvador</option>
		<option value="Equatorial Guinea">Equatorial Guinea</option>
		<option value="Eritrea">Eritrea</option>
		<option value="Estonia">Estonia</option>
		<option value="Ethiopia">Ethiopia</option>
		<option value="Falkland Islands (Malvinas)">Falkland Islands (Malvinas)</option>
		<option value="Faroe Islands">Faroe Islands</option>
		<option value="Fiji">Fiji</option>
		<option value="Finland">Finland</option>
		<option value="France">France</option>
		<option value="French Guiana">French Guiana</option>
		<option value="French Polynesia">French Polynesia</option>
		<option value="French Southern Territories">French Southern Territories</option>
		<option value="Gabon">Gabon</option>
		<option value="Gambia">Gambia</option>
		<option value="Georgia">Georgia</option>
		<option value="Germany">Germany</option>
		<option value="Ghana">Ghana</option>
		<option value="Gibraltar">Gibraltar</option>
		<option value="Greece">Greece</option>
		<option value="Greenland">Greenland</option>
		<option value="Grenada">Grenada</option>
		<option value="Guadeloupe">Guadeloupe</option>
		<option value="Guam">Guam</option>
		<option value="Guatemala">Guatemala</option>
		<option value="Guinea">Guinea</option>
		<option value="Guinea-bissau">Guinea-bissau</option>
		<option value="Guyana">Guyana</option>
		<option value="Haiti">Haiti</option>
		<option value="Heard Island and Mcdonald Islands">Heard Island and Mcdonald Islands</option>
		<option value="Holy See (Vatican City State)">Holy See (Vatican City State)</option>
		<option value="Honduras">Honduras</option>
		<option value="Hong Kong">Hong Kong</option>
		<option value="Hungary">Hungary</option>
		<option value="Iceland">Iceland</option>
		<option value="India">India</option>
		<option value="Indonesia">Indonesia</option>
		<option value="Iran, Islamic Republic of">Iran, Islamic Republic of</option>
		<option value="Iraq">Iraq</option>
		<option value="Ireland">Ireland</option>
		<option value="Israel">Israel</option>
		<option value="Italy">Italy</option>
		<option value="Jamaica">Jamaica</option>
		<option value="Japan">Japan</option>
		<option value="Jordan">Jordan</option>
		<option value="Kazakhstan">Kazakhstan</option>
		<option value="Kenya">Kenya</option>
		<option value="Kiribati">Kiribati</option>
		<option value="Korea, Democratic People's Republic of">Korea, Democratic People's Republic of</option>
		<option value="Korea, Republic of">Korea, Republic of</option>
		<option value="Kuwait">Kuwait</option>
		<option value="Kyrgyzstan">Kyrgyzstan</option>
		<option value="Lao People's Democratic Republic">Lao People's Democratic Republic</option>
		<option value="Latvia">Latvia</option>
		<option value="Lebanon">Lebanon</option>
		<option value="Lesotho">Lesotho</option>
		<option value="Liberia">Liberia</option>
		<option value="Libyan Arab Jamahiriya">Libyan Arab Jamahiriya</option>
		<option value="Liechtenstein">Liechtenstein</option>
		<option value="Lithuania">Lithuania</option>
		<option value="Luxembourg">Luxembourg</option>
		<option value="Macao">Macao</option>
		<option value="Macedonia, The Former Yugoslav Republic of">Macedonia, The Former Yugoslav Republic of</option>
		<option value="Madagascar">Madagascar</option>
		<option value="Malawi">Malawi</option>
		<option value="Malaysia">Malaysia</option>
		<option value="Maldives">Maldives</option>
		<option value="Mali">Mali</option>
		<option value="Malta">Malta</option>
		<option value="Marshall Islands">Marshall Islands</option>
		<option value="Martinique">Martinique</option>
		<option value="Mauritania">Mauritania</option>
		<option value="Mauritius">Mauritius</option>
		<option value="Mayotte">Mayotte</option>
		<option value="Mexico">Mexico</option>
		<option value="Micronesia, Federated States of">Micronesia, Federated States of</option>
		<option value="Moldova, Republic of">Moldova, Republic of</option>
		<option value="Monaco">Monaco</option>
		<option value="Mongolia">Mongolia</option>
		<option value="Montserrat">Montserrat</option>
		<option value="Morocco">Morocco</option>
		<option value="Mozambique">Mozambique</option>
		<option value="Myanmar">Myanmar</option>
		<option value="Namibia">Namibia</option>
		<option value="Nauru">Nauru</option>
		<option value="Nepal">Nepal</option>
		<option value="Netherlands">Netherlands</option>
		<option value="Netherlands Antilles">Netherlands Antilles</option>
		<option value="New Caledonia">New Caledonia</option>
		<option value="New Zealand">New Zealand</option>
		<option value="Nicaragua">Nicaragua</option>
		<option value="Niger">Niger</option>
		<option value="Nigeria">Nigeria</option>
		<option value="Niue">Niue</option>
		<option value="Norfolk Island">Norfolk Island</option>
		<option value="Northern Mariana Islands">Northern Mariana Islands</option>
		<option value="Norway">Norway</option>
		<option value="Oman">Oman</option>
		<option value="Pakistan">Pakistan</option>
		<option value="Palau">Palau</option>
		<option value="Palestinian Territory, Occupied">Palestinian Territory, Occupied</option>
		<option value="Panama">Panama</option>
		<option value="Papua New Guinea">Papua New Guinea</option>
		<option value="Paraguay">Paraguay</option>
		<option value="Peru">Peru</option>
		<option value="Philippines">Philippines</option>
		<option value="Pitcairn">Pitcairn</option>
		<option value="Poland">Poland</option>
		<option value="Portugal">Portugal</option>
		<option value="Puerto Rico">Puerto Rico</option>
		<option value="Qatar">Qatar</option>
		<option value="Reunion">Reunion</option>
		<option value="Romania">Romania</option>
		<option value="Russian Federation">Russian Federation</option>
		<option value="Rwanda">Rwanda</option>
		<option value="Saint Helena">Saint Helena</option>
		<option value="Saint Kitts and Nevis">Saint Kitts and Nevis</option>
		<option value="Saint Lucia">Saint Lucia</option>
		<option value="Saint Pierre and Miquelon">Saint Pierre and Miquelon</option>
		<option value="Saint Vincent and The Grenadines">Saint Vincent and The Grenadines</option>
		<option value="Samoa">Samoa</option>
		<option value="San Marino">San Marino</option>
		<option value="Sao Tome and Principe">Sao Tome and Principe</option>
		<option value="Saudi Arabia">Saudi Arabia</option>
		<option value="Senegal">Senegal</option>
		<option value="Serbia and Montenegro">Serbia and Montenegro</option>
		<option value="Seychelles">Seychelles</option>
		<option value="Sierra Leone">Sierra Leone</option>
		<option value="Singapore">Singapore</option>
		<option value="Slovakia">Slovakia</option>
		<option value="Slovenia">Slovenia</option>
		<option value="Solomon Islands">Solomon Islands</option>
		<option value="Somalia">Somalia</option>
		<option value="South Africa">South Africa</option>
		<option value="South Georgia and The South Sandwich Islands">South Georgia and The South Sandwich Islands</option>
		<option value="Spain">Spain</option>
		<option value="Sri Lanka">Sri Lanka</option>
		<option value="Sudan">Sudan</option>
		<option value="Suriname">Suriname</option>
		<option value="Svalbard and Jan Mayen">Svalbard and Jan Mayen</option>
		<option value="Swaziland">Swaziland</option>
		<option value="Sweden">Sweden</option>
		<option value="Switzerland">Switzerland</option>
		<option value="Syrian Arab Republic">Syrian Arab Republic</option>
		<option value="Taiwan, Province of China">Taiwan, Province of China</option>
		<option value="Tajikistan">Tajikistan</option>
		<option value="Tanzania, United Republic of">Tanzania, United Republic of</option>
		<option value="Thailand">Thailand</option>
		<option value="Timor-leste">Timor-leste</option>
		<option value="Togo">Togo</option>
		<option value="Tokelau">Tokelau</option>
		<option value="Tonga">Tonga</option>
		<option value="Trinidad and Tobago">Trinidad and Tobago</option>
		<option value="Tunisia">Tunisia</option>
		<option value="Turkey">Turkey</option>
		<option value="Turkmenistan">Turkmenistan</option>
		<option value="Turks and Caicos Islands">Turks and Caicos Islands</option>
		<option value="Tuvalu">Tuvalu</option>
		<option value="Uganda">Uganda</option>
		<option value="Ukraine">Ukraine</option>
		<option value="United Arab Emirates">United Arab Emirates</option>
		<option value="United Kingdom">United Kingdom</option>
		<option value="United States">United States</option>
		<option value="United States Minor Outlying Islands">United States Minor Outlying Islands</option>
		<option value="Uruguay">Uruguay</option>
		<option value="Uzbekistan">Uzbekistan</option>
		<option value="Vanuatu">Vanuatu</option>
		<option value="Venezuela">Venezuela</option>
		<option value="Viet Nam">Viet Nam</option>
		<option value="Virgin Islands, British">Virgin Islands, British</option>
		<option value="Virgin Islands, U.S.">Virgin Islands, U.S.</option>
		<option value="Wallis and Futuna">Wallis and Futuna</option>
		<option value="Western Sahara">Western Sahara</option>
		<option value="Yemen">Yemen</option>
		<option value="Zambia">Zambia</option>
		<option value="Zimbabwe">Zimbabwe</option>
	</select>
<?php }
