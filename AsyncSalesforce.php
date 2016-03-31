<?php
/*
	Do asynchronous part of salesforce updates.
*/

namespace SDF;

require_once WP_PLUGIN_DIR . '/sdf/types.php';
require_once WP_PLUGIN_DIR . '/sdf/message.php';

class AsyncSalesforce extends Salesforce {

	private $valid_donations = array();
	private $all_donations = array();
	private $subscription;

	protected $contact;

	private static $DISPLAY_NAME = 'Spark';
	private static $DONOR_SINGLE_TEMPLATE = '00X50000001VaHS';
	private static $DONOR_MONTHLY_TEMPLATE = '00X50000001eVEX';
	private static $DONOR_ANNUAL_TEMPLATE = '00X50000001eVEc';

	public function init(&$info) {
		sdf_message_handler(MessageTypes::DEBUG, 'Entered AsyncSalesforce class');

		try {
			// We need these in a few places
			$info['dollar-amount'] = (float) $info['amount'] / 100;
			$info['amount-string'] = money_format('%.2n', $info['dollar-amount']);

			sdf_message_handler(MessageTypes::DEBUG,
					sprintf('Stripe webhook request has charge amount of %d', $info['amount']));

			parent::api();
			$this->contact = parent::get_contact($info['email']);

			// XXX has contact been called a bunch of times?
			if(is_null($this->contact->Id)) {
				sdf_message_handler(MessageTypes::LOG,
						sprintf('Contact %s not found.', $info['email']));

				// http status code 424 failed dependency
				return 424;
			}

			// Get the other donations we need to know about
			self::get_donations();

			sdf_message_handler(MessageTypes::DEBUG, 'Finished getting donation items');

			// add a new line in the description
			// do this before calculating sum, so that
			// we know the recurrence type
			self::description($info);

			// Calculate the totals, so we know what level
			// the donor is
			self::recalc_sum($info);

			sdf_message_handler(MessageTypes::DEBUG, 'Setting some other donor data up');
			// Directly update some fields
			$this->contact->Paid__c = $info['dollar-amount'];
			$this->contact->Paid_0__c = true;
			$this->contact->Payment_Type__c = 'Credit Card';
			$this->contact->Donation_Each__c = $this->data['amount'];

			$this->contact->Renewal_Date__c = 
					date(parent::$DATE_FORMAT, strtotime('+1 year'));

			$this->contact->Membership_Start_Date__c =
					date(parent::$DATE_FORMAT);

			parent::cleanup();
			parent::upsert_contact();

			sdf_message_handler(MessageTypes::DEBUG, 'Finished updating donor contact');

			self::update_or_create_donation($info);
			self::send_email($info);

		} catch(\Exception $e) {
			sdf_message_handler(MessageTypes::LOG, $e->getMessage());
			parent::emergency_email($info, $e);
		}

		// status code ok
		return 200;
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
		sdf_message_handler(MessageTypes::DEBUG, 'Attempting to get donation items for this donor');

		$valid_donations_list = array();

		if($this->contact->Id !== null) {

			$cutoff_date = new \DateTime();
			$cutoff_date->setTime(0, 0, 0);

			if(time() <= strtotime(date('Y') . '-01-04')) {
				// in this case, we need to also check for donations 
				// that occurred on the last day of the year, because
				// we might have gotten the webhook call this late.
				$cutoff_date->setDate(intval(date('Y')) - 1, 12, 31);
			} else {	
				// the first of the year
				$cutoff_date->setDate(intval(date('Y')), 1, 1);
			}

			sdf_message_handler(MessageTypes::DEBUG,
					sprintf('Cutoff date for the donation items is %s', $cutoff_date->format('Y-m-d H:i:s')));

			// legible query
			$query = 'SELECT 
							(SELECT
								Id, Amount__c, Donation_Date__c,
								 Stripe_Status__c, Stripe_Id__c,
								 In_Honor_Of__c, Referred_by__c
							FROM Donations__r)
						FROM
							Contact
						WHERE
							Contact.Id = \'%s\'';

			// shorten it up a bit
			$query = preg_replace('/\s+/', ' ',
					sprintf($query, $this->contact->Id));

			try {

				$response = parent::$connection->query($query);
				$donations = $response->getQueryResult()->getRecord(0)->Donations__r;

				if(is_null($donations)) {
					sdf_message_handler(MessageTypes::DEBUG,
							sprintf('Got no results from donations query: %s', $query));
					return;
				}

				sdf_message_handler(MessageTypes::DEBUG,
						sprintf('Got %d donations from query', $donations->getSize()));
	
				// we want the successes because that's how we count up
				// the total donated this year.
				// we want pending because that might be the target line item.
				$status_list = array('Pending', 'Success', null);
				$too_old_count = 0;
				$incorrect_status_count = 0;

				for($ii = 0; $ii < $donations->getSize(); $ii++) {

					$donation = $donations->getRecord($ii);

					if(in_array($donation->Stripe_Status__c, $status_list, true)) {

						if($donation->Donation_Date__c >= $cutoff_date) {
							// donations from this calendar year

							$valid_donations_list[] = get_object_vars($donation);
						} else {
							$too_old_count++;
						}
					} else {
						$incorrect_status_count++;
					}

					$this->all_donations[] = get_object_vars($donation);
				}

				sdf_message_handler(MessageTypes::DEBUG,
						sprintf('Finished processing donation items, %d valid donations found', count($valid_donations_list)));

				if($too_old_count > 0) {
					sdf_message_handler(MessageTypes::DEBUG,
							sprintf('%d donations were too old', $too_old_count));
				}

				if($incorrect_status_count > 0) {
					sdf_message_handler(MessageTypes::DEBUG,
							sprintf('%d donations had a status that was unexpected', $incorrect_status_count));
				}

			} catch(\Exception $e) {
				// It's okay if there's no donations returned here,
				// though our calculation will be wrong
				sdf_message_handler(MessageTypes::LOG,
						__FUNCTION__ . ' : ' . $e->getMessage());
			}
		}

		$this->valid_donations = $valid_donations_list;
	}


	// Set $info['desc']
	// Set $this->subscription
	private function description(&$info) {
		sdf_message_handler(MessageTypes::DEBUG, 'Attempting to determine recurrence type of this donation');

		if(is_null($info['invoice'])) {
			sdf_message_handler(MessageTypes::DEBUG, 'No invoice id was present with webhook request, indicating a one time charge');

			$info['recurrence-string'] = 'One time';
			$info['recurrence-type'] = RecurrenceTypes::ONE_TIME;
		} else {
			sdf_message_handler(MessageTypes::DEBUG,
					sprintf('Searching %d line item(s) in the Stripe invoice', count($info['invoice']['lines']['data'])));

			// Get the recurrence string from the invoice data
			// This means the user is signed up for recurring donations
			$ili_count = 0;
			foreach($info['invoice']['lines']['data'] as $ili) {
				$ili_count++;

				if(strcmp($ili['type'],	'subscription') === 0) {

					sdf_message_handler(\SDF\MessageTypes::DEBUG,
							sprintf('Found a subscription line item, id: %s', $ili['id']));

					// We can get the subscription ID from this line item
					$this->subscription = $ili['id'];

					$interval = $ili['plan']['interval'];

					if(strcmp($interval, 'year') === 0) {
						$info['recurrence-string'] = 'Annual';
						$info['recurrence-type'] = RecurrenceTypes::ANNUAL;
					} elseif(strcmp($interval, 'month') === 0) {
						$info['recurrence-string'] = 'Monthly';
						$info['recurrence-type'] = RecurrenceTypes::MONTHLY;
					}

					sdf_message_handler(\SDF\MessageTypes::DEBUG,
							sprintf('Recurrence type for this subscription is: %s', $info['recurrence-string']));

					// bail after first success.
					break;
				}
			}

			sdf_message_handler(\SDF\MessageTypes::DEBUG,
					sprintf('Processed %d invoice line items', $ili_count));
		}

		$fmt = sprintf('%s - %s - %s - Online donation from %s.',
				$info['recurrence-string'], '%.2n', date('n/d/y'), home_url());

		$desc = money_format($fmt, $info['dollar-amount']);
		$info['desc'] = $desc;
	}


	// Find out if the donation li's will update the 
	// contact's membership level
	// we want the TOTAL amount of donations for this calendar year
	// and we want to know whether that passes the 75 dollar cutoff.
	private function recalc_sum(&$info) {
		sdf_message_handler(MessageTypes::DEBUG, 'Attempting to calculate sum of donations');
		$sum = 0;

		foreach($this->valid_donations as $donation) {
			// don't want to add empty amounts,
			// who knows what PHP would do
			if(is_numeric($donation['Amount__c'])) {
				$sum += $donation['Amount__c'];
			}
		}

		$sum += $info['dollar-amount'];

		$this->contact->Total_paid_this_year__c = $sum;

		sdf_message_handler(MessageTypes::DEBUG,
				sprintf('Contact donated $%01.2f this year', $sum));

		// now we'll take into account the donations we expect
		// for the rest of the year from this donor.
		$sum += self::get_ext_amount($info['recurrence-type'],
				$info['dollar_amount']);

		sdf_message_handler(MessageTypes::DEBUG, 'Calculating donor level based on expected donations for this year');


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

		sdf_message_handler(MessageTypes::DEBUG,
				sprintf('Setting donor level to %s', $level));

		$this->contact->Member_Level__c = $level;
	}


	// Create the donation line item child object
	private function update_or_create_donation(&$info) {
		sdf_message_handler(MessageTypes::DEBUG, 'Attempting to update or create a donation line item for this payment');

		$dli_match = false;
		$non_pending_count = 0;
		$invoice_line_items_check_count = 0;

		foreach($this->valid_donations as $dli) {

			if(strcmp($dli['Stripe_Status__c'], 'Pending') === 0) {

				// charge id, which would indicate a one-time donation
				if(strcmp($dli['Stripe_Id__c'], $info['charge-id']) === 0) {
					sdf_message_handler(MessageTypes::DEBUG, 'Found matching Stripe charge id');

					$dli_match = true;

					// update donation with amount and success.
					$this->update_donation($info, $dli);

					sdf_message_handler(MessageTypes::DEBUG, 'Getting referrer and in-honor-of data stored in donation line item');

					// we need these for the email:
					if(strlen($dli['In_Honor_Of__c']) > 0) {
						$info['honor'] = sprintf("In Honor of: %s\n",
								$dli['In_Honor_Of__c']);
					}

					if(strlen($dli['Referred_by__c']) > 0) {
						$info['referral'] = sprintf("Referred by: %s\n",
								$dli['Referred_by__c']);
					}

					// I think we can stop checking donations if we find one
					break;
		
				} else { // if its not a charge id, it could be a subscription id

					if($info['invoice']) {

						$invoice_lis = $info['invoice']['lines']['data'];

						// there could be more than one subscription on an invoice?
						$invoice_line_items_check_count++;
						foreach($invoice_lis as $ili) {
							
							if(strcmp($dli['Stripe_Id__c'], $ili['id']) === 0) {

								sdf_message_handler(MessageTypes::DEBUG, 'Found a matching subscription id');
								
								$dli_match = true;


								$this->update_donation($info, $dli);

								sdf_message_handler(MessageTypes::DEBUG, 'Getting referrer and in-honor-of data stored in donation line item');

								// we need this for the email:
								if(strlen($dli['In_Honor_Of__c']) > 0) {
									$info['honor'] = sprintf("In Honor of: %s\n",
											$dli['In_Honor_Of__c']);
								}

								if(strlen($dli['Referred_by__c']) > 0) {
									$info['referral'] = sprintf("Referred by: %s\n",
											$dli['Referred_by__c']);
								}

								// we don't need to keep looking at dlis, we've found and updated one
								break 2;
							}
						}
					}
				}
			} // end if pending
			else {
				$non_pending_count++;
			}
		} // end for each

		if($non_pending_count > 0) {
			sdf_message_handler(MessageTypes::DEBUG,
					sprintf('Saw %d non-pending donation line items', $non_pending_count));
		}

		if($invoice_line_items_check_count > 0) {
			$count = count($info['invoice']['lines']['data']);

			sdf_message_handler(MessageTypes::DEBUG,
					sprintf('Compared %d potential pending donations with %d Stripe invoice line items (%d total comparisons)', $invoice_line_items_check_count, $count, $invoice_line_items_check_count * $count));
		}



		if(!$dli_match) {
			sdf_message_handler(MessageTypes::DEBUG, 'No previous donation line item to update');
			// this donation is part of a subscription,
			// and is not the first donation. Completely async!
			$this->find_previous_donation_extras($info);
			$this->create_standard_donation($info);
		}

		sdf_message_handler(MessageTypes::DEBUG, 'Finished updating or creating donation item');
	}


	private function find_previous_donation_extras(&$info) {
		sdf_message_handler(MessageTypes::DEBUG, 'Searching previous donations to find extra data for alert email');

		$no_extra_data_count = 0;
		$no_stripe_id_count = 0;
		$subscription_id_match_fail_count = 0;
		$comparison_count = 0;

		if(isset($this->subscription)) {
			$stripe = \SDF::make_stripe();
			
			// It could be any donation
			foreach($this->all_donations as $dli) {
				$comparison_count++;
				// We only care if it has in honor of data
				if(strlen($dli['In_Honor_Of__c']) > 0) {
					// This shouldn't be null if in honor of is present
					if(strlen($dli['Stripe_Id__c']) > 0) {

						// now we can try to fetch the 
						// subscription id from that charge
						$old_subscription =	$stripe->get_subscription_from_charge(
								$dli['Stripe_Id__c']);

						if(strcmp($old_subscription, $this->subscription) === 0) {

							sdf_message_handler(MessageTypes::DEBUG, 'Found donation with matching subscription id and extra data');

							$info['honor'] = sprintf("In Honor of: %s\n",
									$dli['In_Honor_Of__c']);

							if(strlen($dli['Referred_by__c']) > 0) {
								$info['referral'] = sprintf("Referred by: %s\n",
										$dli['Referred_by__c']);
							}

							return;

						} else {
							$subscription_id_match_fail_count++;
						}
					} else {
						$no_stripe_id_count++;
					}
				} else {
					$no_extra_data_count++;
				}
			} // end for each
		} else {
			sdf_message_handler(MessageTypes::DEBUG, 'No subscription id to search on');
			return;
		}

		sdf_message_handler(MessageTypes::DEBUG,
				sprintf('Out of %d potential donations, %d were examined', count($this->all_donations), $comparison_count));

		if($no_extra_data_count > 0) {
			sdf_message_handler(MessageTypes::DEBUG,
					sprintf('%d donations were rejected because they had no extra data', $no_extra_data_count));
		}

		if($no_stripe_id_count > 0) {
			sdf_message_handler(MessageTypes::DEBUG,
					sprintf('%d donations were rejected because they had no stripe id', $no_stripe_id_count));
		}

		if($subscription_id_match_fail_count > 0) {
			sdf_message_handler(MessageTypes::DEBUG,
					sprintf('%d donations were rejected because they were not a part of this subscription', $subscription_id_match_fail_count));
		}
	}


	private function update_donation(&$info, &$donation_li) {
		sdf_message_handler(MessageTypes::DEBUG, 'Attempting to update a pending donation in Salesforce');

		$donation = (object) $donation_li;

		$donation->Amount__c = $info['dollar-amount'];
		$donation->Stripe_Id__c = $info['charge-id'];
		$donation->Description__c = parent::string_truncate($info['desc'], 255);
		$donation->Stripe_Status__c = 'Success';

		$response = parent::$connection->update(array($donation), 'Donation__c');

		if(!array_pop($response)->isSuccess()) {
			throw new Exception('Could not update existing donation', 1);
		}
		sdf_message_handler(MessageTypes::DEBUG, 'Donation updated!');
	}


	private function create_standard_donation(&$info) {
		sdf_message_handler(MessageTypes::DEBUG, 'Attempting to create new donation line item');
		$donation = new \stdClass();

		$donation->Type__c = 'Membership';
		$donation->Amount__c = $info['dollar-amount'];
		$donation->Contact__c = $this->contact->Id;
		$donation->Stripe_Id__c = $info['charge-id'];
		$donation->Description__c = parent::string_truncate($info['desc'], 255);
		$donation->Donation_Date__c = date(parent::$DATE_FORMAT);
		// We might have the in honor of, but we dont really care to save it
		// if this is not the first donation in a series.
		// We should prefer to get the first donation in the series instead.

		$donation->Stripe_Status__c = 'Success';

		$r = parent::create(array($donation), 'Donation__c');

		if(is_null($r)) {
			sdf_message_handler(MessageTypes::DEBUG, 'Creating donation item failed, response was null');
		} else if(!$r->isSuccess()) {
			sdf_message_handler(MessageTypes::DEBUG,
					sprintf('Creating donation item failed, %s',$r->getErrors()));
		}
	}


	// Send an email to the Spark team
	// Send an email to our lovely donor
	private function send_email(&$info) {
		sdf_message_handler(MessageTypes::DEBUG, 'Attempting to send emails');

		switch($info['recurrence-type']) {
			case RecurrenceTypes::MONTHLY: 
					$template = self::$DONOR_MONTHLY_TEMPLATE; break;
			case RecurrenceTypes::ANNUAL: 
					$template = self::$DONOR_ANNUAL_TEMPLATE; break;
			case RecurrenceTypes::ONE_TIME: 
					$template = self::$DONOR_SINGLE_TEMPLATE; break;
		}

		$donor_email = new \Phpforce\SoapClient\Request\SingleEmailMessage();
		$donor_email->targetObjectId = $this->contact->Id;
		$donor_email->replyTo = get_option('sf_email_reply_to');
		$donor_email->senderDisplayName = self::$DISPLAY_NAME;
		$donor_email->templateId = $template;
		

		if(SDFLIVEMODE) {
			$result = parent::$connection->sendEmail(array($donor_email));

			$errors = array_pop($result)->getErrors();
			if(count($errors) > 0) {
				sdf_message_handler(MessageTypes::LOG,
						__FUNCTION__ . ' : Donor email failure! ' 
						. $errors[0]->message);
			}
			sdf_message_handler(MessageTypes::DEBUG, 'Donor email sent');

		} else {
			sdf_message_handler(MessageTypes::DEBUG, 'Not sending email, not in live mode');
		}

		// Alert email //////////////////////////////////////////////
		$body = <<<EOF
A donation has been made!

Name: {$this->contact->FirstName} {$this->contact->LastName}
Amount: {$info['amount-string']}
Recurrence: {$info['recurrence-string']}
Email: {$this->contact->Email}
Phone: {$this->contact->Phone}
Location: {$this->contact->MailingCity}, {$this->contact->MailingState}
{$info['referral']}{$info['honor']}
Salesforce Link: https://na32.salesforce.com/{$this->contact->Id}
EOF;

		$spark_email = new \Phpforce\SoapClient\Request\SingleEmailMessage();
		$spark_email->toAddresses = explode(', ', get_option('alert_email_list'));
		$spark_email->senderDisplayName = 'Spark Donations';
		$spark_email->subject = 'New Donation Alert';
		$spark_email->plainTextBody = $body;

		$result = parent::$connection->sendEmail(array($spark_email));

		$errors = array_pop($result)->getErrors();
		if(count($errors) > 0) {
			sdf_message_handler(MessageTypes::LOG,
					__FUNCTION__ . ' : Alert email failure! ' 
					. $e->getMessage());
		}

		sdf_message_handler(MessageTypes::DEBUG,
				sprintf('Alert email sent to %d recipients', count($spark_email->toAddresses)));
	}
} // end class ?>
