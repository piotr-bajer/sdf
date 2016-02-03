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

			parent::api();
			$this->contact = parent::get_contact($info['email']);
			self::merge_contact($info);
			parent::upsert_contact();

			self::create_pending_li($info);

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

		// Set basic member level, update later when we
		// get capture confirmation
		if(!isset($this->contact->Member_Level__c)) {
			$this->contact->Member_Level__c = 'Donor';
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
			} else {
				$this->contact->AccountId = $id;
			}
		} else {
			$this->contact->AccountId = static::$FRIEND_OF_SPARK;
		}


		// Name
		// We just blindly take whatever the contact submits.
		// To improve this, we should notify the team when there
		// is an existing value, and the new value is different.
		$this->contact->FirstName = $info['first-name'];
		$this->contact->LastName  = $info['last-name'];

		// Address, taking the same strategy as name.
		if(empty($info['address2'])) {
			$this->contact->MailingStreet = $info['address1'];
		} else {
			$this->contact->MailingStreet = 
					$info['address1']
					. "\n"
					. $info['address2'];
		}

		$this->contact->MailingCity       = $info['city'];
		$this->contact->MailingState      = $info['state'];
		$this->contact->MailingPostalCode = $info['zip'];
		$this->contact->MailingCountry    = $info['country'];

		// Phone number
		$this->contact->Phone = $info['tel'];

		// Email
		// This is safe because the contact's email will
		// either be null or the given address
		$this->contact->Email = $info['email'];

		if(!isset($this->contact->First_Active_Date__c)) {
			$this->contact->First_Active_Date__c =
					date(parent::$DATE_FORMAT);
		}

		// Same strategy as name.
		if(!empty($info['gender'])) {
			$this->contact->Gender__c = ucfirst($info['gender']);
		}
	
		// Every contact needs an 'Owner'
		$this->contact->Board_Member_Contact_Owner__c = 'Amanda Brock';
	
		// Birth month and year
		if(!empty($info['birthday-month']) 
				&& !empty($info['birthday-year'])) {

			$birthday = $info['birthday-year'] . '-' . $info['birthday-month'];
			$birthday = strtotime($birthday);

			// if strtotime bailed out, we will too
			if($birthday != false) {
				$this->contact->Birth_Month_Year__c = date('m/Y', $birthday);
			}
		}


		// Referral field
		if(!empty($info['hearabout'])) {
			$info['referral'] = ucfirst($info['hearabout']);

			if(!isset($this->contact->How_did_you_hear_about_Spark__c)) {	
				$this->contact->How_did_you_hear_about_Spark__c = $info['referral'];
			}

			// Set referral if it's potentially a contact.
			if($info['hearabout'] == 'Friend') {
				if(!empty($info['hearabout-extra'])) {
					
					$id = parent::search_salesforce(SearchBy::NAME,
							$info['hearabout-extra']);

					$this->contact->Referred_By__c = $id;
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
		if(strlen($name) > 0) {
			$search = sprintf('FIND {"%s"} IN NAME FIELDS RETURNING Account(Id)',
					parent::sosl_reserved_chars($name));

			try {
				$records = parent::$connection->search($search);

				if(count($records->searchRecords)) {
					$id = $records->searchRecords[0]->record->Id;
				} else {
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
		$donation = new \stdClass();
		$donation->Contact__c       = $this->contact->Id;
		$donation->Donation_Date__c = date(parent::$DATE_FORMAT);
		$donation->Type__c          = 'Membership';
		$donation->Stripe_Status__c = 'Pending';
		$donation->Stripe_Id__c     = $info['stripe-id'];
		$donation->Referred_by__c   = parent::string_truncate($info['referral']);

		$donation->In_Honor_Of__c =
				parent::string_truncate($info['inhonorof'], 64);

		parent::create(array($donation), 'Donation__c');
	}

} // end class ?>
