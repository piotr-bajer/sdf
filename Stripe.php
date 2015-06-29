<?php
/**
*Do the stripe parts of the donation.
*/

namespace SDF;

require_once WP_PLUGIN_DIR . '/sdf/message.php';
require_once WP_PLUGIN_DIR . '/sdf/types.php';

class Stripe {
	private $stripe_plan;
	private $stripe_customer;

	private $amount;
	private $amount_string;
	private $token;
	private $email;
	private $name;
	private $recurrence_type;
	private $recurrence_string;


	// entrypoint
	public function charge(&$data) {
		self::$amount            = $data['amount-cents'];
		self::$amount_string     = $data['amount-string'];
		self::$token             = $data['token'];
		self::$email             = $data['email'];
		self::$name              = $data['name'];
		self::$recurrence_type   = $data['recurrence-type'];
		self::$recurrence_string = $data['recurrence-string'];

		self::api();
		self::invoice();
	}


	// This function is public since we use it to test keys input to
	// the options page.
	public static function api($input = null) {
		require_once(WP_PLUGIN_DIR . '/sdf/lib/stripe/lib/Stripe.php');
		if(!empty($input)) {
			Stripe::setApiKey($input);
		} else {
			Stripe::setApiKey(get_option('stripe_api_secret_key'));
		}
		Stripe::setApiVersion('2013-08-13');
	}

	private function invoice() {
		if(self::$recurrence_type == RecurrenceTypes::ONE_TIME) {
			self::single_charge();
		} else {
			self::recurring_charge();
		}
	}

	private function single_charge() {
		try {
			Stripe_Charge::create(array(
				'amount' => $this->amount,
				'card' => $this->token,
				'currency' => 'usd',
				'description' => $this->email
			));
		} catch(Stripe_Error $e) {
			$body = $e->getJsonBody();
			sdf_message_handler(MessageTypes::ERROR,
					$body['error']['message']);
		}
	}

	private function recurring_charge() {
		self::plan();
		self::stripe_customer();
		self::subscribe();
	}

	// We assume that the plan has been created, and try to retrieve it
	// and if we fail, then we create the plan
	private function plan() {
		$plan_id = strtolower($this->recurrence_string) . '-' . $this->amount;

		try {
			$plan = Stripe_Plan::retrieve($plan_id);
		} catch(Stripe_Error $e) {
			if(self::$recurrence_type == RecurrenceTypes::ANNUAL) {
				$recurrence = 'year';
			} else {
				$recurrence = 'month';
			}

			$cents = self::$amount * 100;

			$new_plan = array(
				'id' => $plan_id,
				'currency' => 'USD',
				'interval' => $recurrence,
				'amount' => $cents,
				'name' => self::$amount_string . ' ' . $recurrence . 'ly gift'
			);

			try {
				$plan = Stripe_Plan::create($new_plan);
			} catch(Stripe_Error $e) {
				$body = $e->getJsonBody();
				sdf_message_handler(MessageTypes::LOG, __FUNCTION__ . ' : ' . $body['error']['message']);
				sdf_message_handler(MessageTypes::ERROR, 'Something\'s not right. Please try again.');
			}
		}

		self::$stripe_plan = $plan;
	}

	// Create the basic customer
	private function stripe_customer() {
		$info = array(
			'card' => self::$token,
			'email' => self::$email,
			'description' => self::$name
		);

		try {
			$customer = Stripe_Customer::create($info);
		} catch(Stripe_Error $e) {
			$body = $e->getJsonBody();
			sdf_message_handler(MessageTypes::ERROR,
					$body['error']['message']);
		}

		self::$stripe_customer = $customer;
	}

	// sign up for the plan.
	private function subscribe() {
		try {
			self::$stripe_customer->updateSubscription(
					array('plan' => self::$stripe_plan->id));

		} catch(Stripe_Error $e) {
			$body = $e->getJsonBody();
			sdf_message_handler(MessageTypes::ERROR,
					$body['error']['message']);
		}
	}
} // end class ?>
