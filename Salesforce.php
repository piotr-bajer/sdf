<?php
/**
*	Salesforce base class.
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

namespace SDF;

require_once WP_PLUGIN_DIR . '/sdf/types.php';
require_once WP_PLUGIN_DIR . '/sdf/message.php';

class Salesforce {

	protected static $connection;
	protected static $DATE_FORMAT = 'Y-m-d';

	// This function is public to allow verification from settings page
	// just like the Stripe API
	public static function api($input = null) {
		require_once(WP_PLUGIN_DIR
				. '/sdf/vendor/phpforce/soap-client/src/Phpforce/SoapClient/ClientBuilder.php');

		if(is_null($input)) {
			$token = get_option('salesforce_token');
		} else {
			$token = $input;
		}

		if(\LIVEMODE) {
			$wsdl = '/sdf/config/enterprise.wsdl.xml';
		} else {
			$wsdl = '/sdf/config/test.enterprise.wsdl.xml';
		}


		$builder = new \Phpforce\SoapClient\ClientBuilder(
			$wsdl,
			get_option('salesforce_username'),
			get_option('salesforce_password'),
			$token			
		);

		self::$connection = $builder->build();
	}

	// This method queries the data in SalesForce using the provided email
	protected function get_contact($email = null) {

		$contact = new \stdClass();
		$id = null;

		try {
			if(!is_null($email)) {
				$id = self::search_salesforce(SearchBy::EMAIL, $email);
			}

			if(!is_null($id)) {
				$fields = array( // # is index of contact object
					'AccountId', // 3
					'Description', // 25
					'Membership_Start_Date__c', // 44
					'Renewal_Date__c', // 52
					'Member_Level__c', // 53
					'First_Active_Date__c', // 56
					'Board_Member_Contact_Owner__c', // 61
					'How_did_you_hear_about_Spark__c', // 66
					'Total_paid_this_year__c',
					'Referred_By__c',
					'FirstName',
					'LastName',
					'Email',
					'MailingCity',
					'MailingState'
				);

				$fieldlist = implode(', ', $fields);

				$contact = array_pop(self::$connection->retrieve(
						$fieldlist, 'Contact', array($id)));
			}
		} catch(\Exception $e) {
			// We can catch this error here because
			// we'll just use the form values everywhere
			sdf_message_handler(MessageTypes::LOG,
					__FUNCTION__ . ' : ' . $e->faultstring);
		}

		$contact->Id = $id;
		return $contact;
	}

	// Searches SalesForce for a contact object,
	// Returns their ID or null
	protected function search_salesforce($search, $needle) {
		if(strlen($needle) > 0) {
			switch($search) {
				case SearchBy::NAME:
					$key = 'NAME';
					break;
				
				case SearchBy::EMAIL:
					$key = 'EMAIL';
					break;
			}

			$query = sprintf('FIND {"%s"} IN %s FIELDS RETURNING CONTACT(ID)',
					self::sosl_reserved_chars($needle), $key);


			$response = self::$connection->search($query);

			if(count($response)) {
				return array_pop($response->searchRecords)->Id;
			} 
		}

		return null;
	}


	public function sosl_reserved_chars($string) {
		// ? & | ! { } [ ] ( ) ^ ~ * : \ " ' + -

		$targets = array(
			'\\', // has to be the first replacement, or it loops forever
			'?', '&', '|', '!',
			'{', '}', '[', ']',
			'(', ')', '^', '~',
			'*', ':', '"', "'",
			'+', '-'
		);

		$replacements = array(
			'\\\\',
			'\?', '\&', '\|', '\!',
			'\{', '\}', '\[', '\]',
			'\(', '\)', '\^', '\~',
			'\*', '\:', '\"', "\'",
			'\+', '\-'
		);

		return str_replace($targets, $replacements, $string);
	}


	protected function string_truncate(&$string, $length) {

		if(strlen($string) > $length) {
			return substr($string, 0, $length - 3) . '...';
		} else {
			return $string;
		}
	}


	// This function removes empty fields from the contact object
	// must be called from context with contact property
	protected function cleanup() {
		foreach($this->contact as $property => $value) {
			if(is_null($value)) {
				unset($this->contact->$property);
			}
		}
	}


	protected function create($object, $object_name) {
		if(!is_array($object)) {
			$object = array($object);
		}

		$response = self::$connection->create($object, $object_name);

		if(empty($response->success)) {
			throw new \Exception($response->errors[0]->message, 1);
		}
	}

	// Send the data to Salesforce
	protected function upsert_contact() {
		if(isset($this->contact->Id)) {
			// update on id.
			self::$connection->update(array($this->contact), 'Contact');

		} else {

			// create new contact.
			self::create(array($this->contact), 'Contact');

		}
	}

	// This function is called if something goes wrong..
	// so we don't lose the user data.
	protected function emergency_email(&$info, &$error_message) {
		$body = "Something went wrong, and this info was not inserted into Salesforce.\n"
			. "Here is the contact info:\n"
			. print_r($info, true)
			. "\n\nAnd here's the error message:\n"
			. $error_message;

		$spark_email = new \SingleEmailMessage();
		$spark_email->setSenderDisplayName('Spark Donations');
		$spark_email->setToAddresses(explode(', ',
		 		get_option('alert_email_list')));
		$spark_email->setPlainTextBody($body);
		$spark_email->setSubject('Salesforce Capture Alert');

		self::$connection->sendSingleEmail(array($spark_email));
	}

} // end class ?>
