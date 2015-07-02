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
		$this->amount            = $data['amount-cents'];
		$this->amount_string     = $data['amount-string'];
		$this->token             = $data['token'];
		$this->email             = $data['email'];
		$this->name              = $data['name'];
		$this->recurrence_type   = $data['recurrence-type'];
		$this->recurrence_string = $data['recurrence-string'];

		self::api();
		self::invoice();
	}


	// This function is public since we use it to test keys input to
	// the options page.
	public static function api($input = null) {
		require_once WP_PLUGIN_DIR . '/sdf/lib/stripe/lib/Stripe.php';

		if(!empty($input)) {
			\Stripe::setApiKey($input);
		} else {
			\Stripe::setApiKey(get_option('stripe_api_secret_key'));
		}
		\Stripe::setApiVersion('2013-08-13');
	}

	private function invoice() {
		if($this->recurrence_type == RecurrenceTypes::ONE_TIME) {
			self::single_charge();
		} else {
			self::recurring_charge();
		}
	}

	private function single_charge() {
		try {
			\Stripe_Charge::create(array(
				'amount' => $this->amount,
				'card' => $this->token,
				'currency' => 'usd',
				'description' => $this->email
			));
		} catch(\Stripe_Error $e) {
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
			$plan = \Stripe_Plan::retrieve($plan_id);
		} catch(\Stripe_Error $e) {
			if($this->recurrence_type == RecurrenceTypes::ANNUAL) {
				$recurrence = 'year';
			} else {
				$recurrence = 'month';
			}

			$new_plan = array(
				'id' => $plan_id,
				'currency' => 'USD',
				'interval' => $recurrence,
				'amount' => $cents,
				'name' => $this->amount_string . ' ' . $recurrence . 'ly gift'
			);

			try {
				$plan = \Stripe_Plan::create($new_plan);
			} catch(\Stripe_Error $e) {
				$body = $e->getJsonBody();
				sdf_message_handler(MessageTypes::LOG,
						__FUNCTION__ . ' : ' . $body['error']['message']);
				sdf_message_handler(MessageTypes::ERROR,
						'Something\'s not right. Please try again.');
			}
		}

		$this->stripe_plan = $plan;
	}

	// Create the basic customer
	private function stripe_customer() {
		$info = array(
			'card' => $this->token,
			'email' => $this->email,
			'description' => $this->name
		);

		try {
			$customer = \Stripe_Customer::create($info);
		} catch(\Stripe_Error $e) {
			$body = $e->getJsonBody();
			sdf_message_handler(MessageTypes::ERROR,
					$body['error']['message']);
		}

		$this->stripe_customer = $customer;
	}

	// sign up for the plan.
	private function subscribe() {
		try {
			$this->stripe_customer->updateSubscription(
					array('plan' => $this->stripe_plan->id));

		} catch(\Stripe_Error $e) {
			$body = $e->getJsonBody();
			sdf_message_handler(MessageTypes::ERROR,
					$body['error']['message']);
		}
	}
} // end class ?>
