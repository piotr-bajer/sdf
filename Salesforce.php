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
	private $emergency_email_sent = false;

	// This function is public to allow verification from settings page
	// just like the Stripe API
	public static function api($input = null) {
		sdf_message_handler(MessageTypes::DEBUG, 'Loading up Salesforce library');

		require_once(WP_PLUGIN_DIR . '/sdf/vendor/autoload.php');

		if(is_null($input)) {
			$token = get_option('salesforce_token');
		} else {
			$token = $input;
		}

		sdf_message_handler(MessageTypes::DEBUG, 'Building Salesforce connection');

		$builder = new \Phpforce\SoapClient\ClientBuilder(
			WP_PLUGIN_DIR . '/sdf/config/enterprise.wsdl.xml',
			get_option('salesforce_username'),
			get_option('salesforce_password'),
			$token			
		);

		self::$connection = $builder->build();
	}

	public function has_emergency_email_been_sent() {
		return $emergency_email_sent;
	}

	// This method queries the data in SalesForce using the provided email
	protected function get_contact($email = null) {
		sdf_message_handler(MessageTypes::DEBUG,
				'Attempting to retrieve Salesforce contact data');

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
					'MailingState',
					'Phone'
				);


				$response = self::$connection->retrieve(
						$fields, array($id), 'Contact');

				$contact = array_pop($response);

				sdf_message_handler(MessageTypes::DEBUG,
						sprintf('Got Salesforce contact %s', $contact->Id));
			} else {
				sdf_message_handler(MessageTypes::DEBUG, 'No Salesforce contact available');
			}
		} catch(\Exception $e) {

			// We can catch this error here because
			// we'll just use the form values everywhere
			sdf_message_handler(MessageTypes::LOG,
					__FUNCTION__ . ' : ' . $e->getMessage());
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

			$query = sprintf('FIND {%s} IN %s FIELDS RETURNING CONTACT(ID)',
					self::sosl_reserved_chars($needle), $key);

			sdf_message_handler(MessageTypes::DEBUG,
					sprintf('Searching Salesforce with SOQL query: %s', $query));

			$response = self::$connection->search($query);

			if(count($response)) {
				sdf_message_handler(MessageTypes::DEBUG,
						sprintf('%d result(s) found', count($response->searchRecords)));

				return array_pop($response->searchRecords)->record->Id;
			}

			sdf_message_handler(MessageTypes::DEBUG, 'No results found');
		} else {
			sdf_message_handler(MessageTypes::DEBUG, 'Needle was empty');
		}

		return null;
	}


	protected function sosl_reserved_chars($string) {
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
		sdf_message_handler(MessageTypes::DEBUG, 'Attempting to clean extra fields from contact');

		$null_count = 0;
		foreach($this->contact as $property => $value) {
			if(is_null($value)) {
				unset($this->contact->$property);
				$null_count++;
			}
		}

		if($null_count > 0) {
			sdf_message_handler(MessageTypes::DEBUG,
					sprintf('%d null fields removed', $null_count));
		}
	}


	protected function create($object, $object_name) {
		$response = null;

		try {
			if(!is_array($object)) {
				$object = array($object);
			}

			$response = self::$connection->create($object, $object_name);

		} catch(\Exception $e) {
			sdf_message_handler(MessageTypes::DEBUG,
					sprintf('Caught exception creating %s, raising', $object_name));
			throw $e;
		}

		return array_pop($response); 
	}

	// Send the data to Salesforce
	protected function upsert_contact() {
		if(isset($this->contact->Id)) {
			sdf_message_handler(MessageTypes::DEBUG,
					sprintf('Attempting to update contact %s', $this->contact->Id));

			try {
				// update on id.
				$response = self::$connection->update(array($this->contact), 'Contact');
			} catch(\Exception $e) {
				sdf_message_handler(MessageTypes::DEBUG,
						'Caught exception updating contact, raising');
				throw $e;
			}

			$response = array_pop($response);
			
		} else {
			sdf_message_handler(MessageTypes::DEBUG, 'Attempting to create new contact');

			// create new contact.
			unset($this->contact->Id); // Id field cannot be present
			$response = self::create(array($this->contact), 'Contact');
		}


		if(is_null($response)) {
			throw new \Exception(sprintf(
					'Contact %s not updated or created. Connection response is null', $this->contact->Email));
		} else if(!$response->isSuccess()) {
			throw new \Exception(sprintf('Contact %s not updated or created. %s', 
					$this->contact->Email, $response->getErrors()));
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

		$spark_email = new \Phpforce\SoapClient\Request\SingleEmailMessage();
		$spark_email->subject = 'Salesforce Capture Alert';
		$spark_email->senderDisplayName = 'Spark Donations';
		$spark_email->toAddresses = explode(', ',
		 		get_option('alert_email_list'));
		$spark_email->plainTextBody = $body;

		self::$connection->sendEmail(array($spark_email));
		$emergency_email_sent = true;
	}

} // end class ?>
