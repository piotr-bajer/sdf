<?php
/*
	Do unconditional update of contact info in salesforce.
*/

namespace SDF;

require_once WP_PLUGIN_DIR . '/sdf/types.php';
require_once WP_PLUGIN_DIR . '/sdf/message.php';

class UCSalesforce extends \SDF\Salesforce {

	private $contact;

	private static $FRIEND_OF_SPARK = '00130000007qhRG';

	public function init(&$info) {
		try {

			parent::api();
			self::$contact = parent::get_contact($info['email']);
			self::merge_contact($info);
			parent::upsert();

		} catch(Exception $e) {
			sdf_message_handler(MessageTypes::LOG,
					__FUNCTION__ . ' : ' . $e->faultstring);
			
			parent::emergency_email($info, $e->faultstring);
		}
	}

	// Take the info array, and this->contact
	// and try to reconcile any differences.
	// I can't figure out how to make this code
	// less of a plate of pasta
	private function merge_contact(&$info) {

		// Set basic member level, update later when we
		// get capture confirmation
		if(!isset(self::$contact->Member_Level__c)) {
			self::$contact->Member_Level__c = 'Donor';
		}

		// This person is obviously active, since they're
		// submitting their information on this form
		self::$contact->Contact_Status__c = 'Active';
		self::$contact->Active_Member__c = true;

		// Setup their company
		if(!isset(self::$contact->AccountId)) {
			if(!isset($info['company'])) {
				self::$contact->AccountId = static::$FRIEND_OF_SPARK;
			} else {
				$id = self::company($info['company']);
				if(is_null($id)) {
					self::$contact->AccountId = static::$FRIEND_OF_SPARK;
				} else {
					self::$contact->AccountId = $id;
				}
			}
		}

		// Name
		// We just blindly take whatever the contact submits.
		// To improve this, we should notify the team when there
		// is an existing value, and the new value is different.
		self::$contact->FirstName = $info['first-name'];
		self::$contact->LastName  = $info['last-name'];

		// Address, taking the same strategy as name.
		if(empty($info['address2'])) {
			self::$contact->MailingStreet = $info['address1'];
		} else {
			self::$contact->MailingStreet = 
					$info['address1']
					. "\n"
					. $info['address2'];
		}

		self::$contact->MailingCity       = $info['city'];
		self::$contact->MailingState      = $info['state'];
		self::$contact->MailingPostalCode = $info['zip'];
		self::$contact->MailingCountry    = $info['country'];

		// Phone number
		self::$contact->Phone = $info['tel'];

		// Email
		// This is safe because the contact's email will
		// either be null or the given address
		self::$contact->Email = $info['email'];

		if(!isset(self::$contact->First_Active_Date__c)) {
			self::$contact->First_Active_Date__c =
					date(parent::$DATE_FORMAT);
		}

		// Same strategy as name.
		if(!empty($info['gender'])) {
			self::$contact->Gender__c = ucfirst($info['gender']);
		}
	
		// Every contact needs an 'Owner'
		if(!isset(self::$contact->Board_Member_Contact_Owner__c)) {
			self::$contact->Board_Member_Contact_Owner__c = 'Amanda Brock';
		}
	
		// Birth month and year
		if(!empty($info['birthday-month']) 
				&& !empty($info['birthday-year'])) {

			$birthday = $info['birthday-year'] . '-' . $info['birthday-month'];
			$birthday = strtotime($birthday);

			// if strtotime bailed out, we will too
			if($birthday != false) {
				self::$contact->Birth_Month_Year__c = date('m/Y', $birthday);
			}
		}


		if(!empty($info['hearabout'])) {
			if(!isset(self::$contact->How_did_you_hear_about_Spark__c)) {
				self::$contact->How_did_you_hear_about_Spark__c =
						ucfirst($info['hearabout']);
			}

			// Set referral if it's potentially a contact.
			if($info['hearabout'] == 'Friend') {
				if(!empty($info['hearabout-extra'])) {

					if(!isset(self::$contact->Referred_By__c)) {
						$id = search_salesforce(SearchBy::NAME,
								$info['hearabout-extra']);

						self::$contact->Referred_By__c = $id;
					}
				}
			}
		}
	}

	// Find the company by name, or create a new company
	private function company($name)f{
		$search = 'FIND {"' . seld::data['company'] 
			. '"} IN NAME FIELDS RETURNING Account(Id)';

		try {
			$records = parent::$connection->search($search);

			if(count($records->searchRecords)) {
				$id = $records->searchRecords[0]->Id;
			} else {
				$company = new stdClass();
				$company->Name = $name;

				$created = static::$connection->create(
						array($company), 'Account');

				$id = $created[0]->id;
			}

		} catch(Exception $e) {
			// We can also keep this error suppressed
			// because knowing the contact's company is not required.
			sdf_message_handler(MessageTypes::LOG,
					__FUNCTION__ . ' : ' . $e->faultstring);
		}

		return $id;
	}
	
} // end class ?>
