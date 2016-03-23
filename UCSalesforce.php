<?php
/*
	Do UnConditional update of contact info in salesforce.
*/

namespace SDF;

require_once WP_PLUGIN_DIR . '/sdf/types.php';
require_once WP_PLUGIN_DIR . '/sdf/message.php';

class UCSalesforce extends Salesforce {

	protected $contact;

	private static $FRIEND_OF_SPARK = '00130000007qhRG';

	public function init(&$info) {
		try {
			sdf_message_handler(\SDF\MessageTypes::DEBUG, 'Entered UCSalesforce class');

			parent::api();
			$this->contact = parent::get_contact($info['email']);
			self::merge_contact($info);
			parent::upsert_contact();

			self::create_pending_li($info);

			sdf_message_handler(MessageTypes::DEBUG,
					'Finished handling Salesforce contact data');

		} catch(\Exception $e) {
			sdf_message_handler(MessageTypes::LOG,
					__FUNCTION__ . ' : ' . print_r($e, true));
			
			parent::emergency_email($info, $e->getMessage());
		}
	}

	// Take the info array, and this->contact
	// and try to reconcile any differences.
	// I can't figure out how to make this code
	// less of a plate of pasta
	private function merge_contact(&$info) {

		sdf_message_handler(MessageTypes::DEBUG,
				'Attempting to reconcile existing Salesforce data (if any) with submitted donation data');

		// Set basic member level, update later when we
		// get capture confirmation
		if(!isset($this->contact->Member_Level__c)) {
			$this->contact->Member_Level__c = 'Donor';
			sdf_message_handler(MessageTypes::DEBUG, 'Setting donor level to "Donor"');
		}

		// This person is obviously active, since they're
		// submitting their information on this form
		$this->contact->Contact_Status__c = 'Active';
		$this->contact->Active_Member__c = true;

		// Setup their company
		if(isset($info['company'])) {
			$id = self::company($info['company']);
			if(is_null($id)) {
				$this->contact->AccountId = static::$FRIEND_OF_SPARK;
				sdf_message_handler(MessageTypes::DEBUG,
						'Setting donor company to FRIEND OF SPARK');
			} else {
				$this->contact->AccountId = $id;
				sdf_message_handler(MessageTypes::DEBUG,
						'Setting donor company to submitted value');
			}
		} else {
			$this->contact->AccountId = static::$FRIEND_OF_SPARK;
			sdf_message_handler(MessageTypes::DEBUG,
					'Setting donor company to FRIEND OF SPARK');
		}


		// Name
		// We just blindly take whatever the contact submits.
		// To improve this, we should notify the team when there
		// is an existing value, and the new value is different.
		$this->contact->FirstName = $info['first-name'];
		$this->contact->LastName  = $info['last-name'];

		sdf_message_handler(MessageTypes::DEBUG, 'Setting name');

		// Address, taking the same strategy as name.
		if(empty($info['address2'])) {
			$this->contact->MailingStreet = $info['address1'];
		} else {
			$this->contact->MailingStreet = 
					$info['address1']
					. "\n"
					. $info['address2'];
		}

		sdf_message_handler(MessageTypes::DEBUG, 'Setting address');

		$this->contact->MailingCity       = $info['city'];
		$this->contact->MailingState      = $info['state'];
		$this->contact->MailingPostalCode = $info['zip'];
		$this->contact->MailingCountry    = $info['country'];

		// Phone number
		$this->contact->Phone = $info['tel'];

		sdf_message_handler(MessageTypes::DEBUG, 'Setting telephone number');

		// Email
		// This is safe because the contact's email will
		// either be null or the given address
		$this->contact->Email = $info['email'];

		sdf_message_handler(MessageTypes::DEBUG,
				sprintf('Setting email: %s', $this->contact->Email));

		if(!isset($this->contact->First_Active_Date__c)) {
			$this->contact->First_Active_Date__c =
					date(parent::$DATE_FORMAT);

			sdf_message_handler(MessageTypes::DEBUG, 'Setting first active date');
		}

		// Same strategy as name.
		if(!empty($info['gender'])) {
			$this->contact->Gender__c = ucfirst($info['gender']);

			sdf_message_handler(MessageTypes::DEBUG, 'Setting gender');
		}
	
		// Every contact needs an 'Owner'
		if(!isset($this->contact->Board_Member_Contact_Owner__c)) {
			$this->contact->Board_Member_Contact_Owner__c = 'Amanda Brock';

			sdf_message_handler(MessageTypes::DEBUG, 'Setting board member contact owner');
		}
	
		// Birth month and year
		if(!empty($info['birthday-month']) 
				&& !empty($info['birthday-year'])) {

			$birthday = $info['birthday-year'] . '-' . $info['birthday-month'];
			$birthday = strtotime($birthday);

			// if strtotime bailed out, we will too
			if($birthday != false) {
				$this->contact->Birth_Month_Year__c = date('m/Y', $birthday);

				sdf_message_handler(MessageTypes::DEBUG, 'Setting birth month year');
			}
		}


		// Referral field
		if(!empty($info['hearabout'])) {
			$info['referral'] = ucfirst($info['hearabout']);

			if(!isset($this->contact->How_did_you_hear_about_Spark__c)) {	
				$this->contact->How_did_you_hear_about_Spark__c = $info['referral'];

				sdf_message_handler(MessageTypes::DEBUG,
						'Setting preliminary referral data');
			}

			// Set referral if it's potentially a contact.
			if($info['hearabout'] == 'Friend') {
				if(!empty($info['hearabout-extra'])) {
					sdf_message_handler(MessageTypes::DEBUG,
							'Attempting to find referring contact');
					
					$id = parent::search_salesforce(SearchBy::NAME,
							$info['hearabout-extra']);

					$this->contact->Referred_By__c = $id; // could be null
				}
			}

			// Get the extra data if it's there
			if(!empty($info['hearabout-extra'])) {
				$info['referral'] .= ': ' . $info['hearabout-extra'];
			}
		}
	}

	// Find the company by name, or create a new company
	private function company($name) {
		sdf_message_handler(MessageTypes::DEBUG,
				sprintf('Attempting to search for company name: %s', $name));

		if(strlen($name) > 0) {
			$search = sprintf('FIND {"%s"} IN NAME FIELDS RETURNING Account(Id)',
					parent::sosl_reserved_chars($name));

			try {
				$records = parent::$connection->search($search);

				sdf_message_handler(MessageTypes::DEBUG,
						sprintf('Searching Salesforce with query: %s', $search));

				if(count($records->searchRecords)) {
					$id = $records->searchRecords[0]->record->Id;

					sdf_message_handler(MessageTypes::DEBUG,
							sprintf('Found company with id: %s', $id));
				} else {
					sdf_message_handler(MessageTypes::DEBUG, 'Creating new company');

					$company = new \stdClass();
					$company->Name = $name;

					$created = static::$connection->create(
							array($company), 'Account');

					$id = $created[0]->id;
				}

			} catch(\Exception $e) {
				// We can also keep this error suppressed
				// because knowing the contact's company is not required.
				sdf_message_handler(MessageTypes::LOG,
						__FUNCTION__ . ' : ' . $e->getMessage());
			}

			return $id;
		}
	}

	// creates a donation line item with in-honor-of information
	private function create_pending_li(&$info) {
		sdf_message_handler(MessageTypes::DEBUG,
				'Attempting to create pending donation item');

		$donation = new \stdClass();
		$donation->Contact__c       = $this->contact->Id;
		$donation->Donation_Date__c = date(parent::$DATE_FORMAT);
		$donation->Type__c          = 'Membership';
		$donation->Stripe_Status__c = 'Pending';
		$donation->Stripe_Id__c     = $info['stripe-id'];
		$donation->Referred_by__c   = parent::string_truncate($info['referral'], 255);

		$donation->In_Honor_Of__c =
				parent::string_truncate($info['inhonorof'], 64);

		parent::create(array($donation), 'Donation__c');
	}

} // end class ?>
