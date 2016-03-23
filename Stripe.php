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
	private $stripe_id;

	private $amount;
	private $amount_string;
	private $token;
	private $email;
	private $name;
	private $recurrence_type;
	private $recurrence_string;


	// entrypoint
	public function charge(&$data) {
		sdf_message_handler(MessageTypes::DEBUG, 'Entered Stripe class');

		$this->amount            = $data['amount-cents'];
		$this->amount_string     = $data['amount-string'];
		$this->token             = $data['token'];
		$this->email             = $data['email'];
		$this->name              = $data['name'];
		$this->recurrence_type   = $data['recurrence-type'];
		$this->recurrence_string = $data['recurrence-string'];

		$this->stripe_id = self::invoice();
	}


	// This function is public since we use it to test keys input to
	// the options page.
	public static function api($input = null) {
		require_once WP_PLUGIN_DIR
			. '/sdf/vendor/stripe/stripe-php/init.php';

		if(!empty($input)) {
			\Stripe\Stripe::setApiKey($input);
		} else {
			\Stripe\Stripe::setApiKey(get_option('stripe_api_secret_key'));
		}
		\Stripe\Stripe::setApiVersion('2015-10-01');
	}

	public function get_stripe_id() {
		return $this->stripe_id;
	}

	// chunky and slow!
	public function get_subscription_from_charge($charge) {
		try {
			$charge = \Stripe\Charge::retrieve($charge);
			$invoice_id = $charge['invoice'];
			
			$invoice = \Stripe\Invoice::retrieve($invoice_id);
			$invoice_items = $invoice['lines']['data'];

			foreach ($invoice_items as $item) {
				if(strcmp($item['type'], 'subscription') === 0) {
					return $item['id'];
				}
			}
		} catch(\Stripe\Error\InvalidRequest $e) {
			return null;
		}
	}

	private function invoice() {
		if($this->recurrence_type == RecurrenceTypes::ONE_TIME) {
			return self::single_charge();
		} else {
			return self::recurring_charge();
		}
	}

	private function single_charge() {
		sdf_message_handler(MessageTypes::DEBUG, 'Doing a single charge');

		try {
			$result = \Stripe\Charge::create(array(
				'amount' => $this->amount,
				'card' => $this->token,
				'currency' => 'usd',
				'description' => $this->email
			));

			sdf_message_handler(MessageTypes::DEBUG,
					sprintf('Single charge created, amount: %d (cents), id: %s',
						$result->amount, $result->id));

			return $result->id;

		} catch(\Stripe\Error\Base $e) {
			sdf_message_handler(MessageTypes::ERROR, $e);
		}
	}

	private function recurring_charge() {
		sdf_message_handler(MessageTypes::DEBUG, 'Creating a recurring charge');
		self::plan();
		self::stripe_customer();
		return self::subscribe();
	}

	// We assume that the plan has been created, and try to retrieve it
	// and if we fail, then we create the plan
	private function plan() {
		$plan_id = strtolower($this->recurrence_string) . '-' . $this->amount;

		try {
			sdf_message_handler(MessageTypes::DEBUG,
					'Attempting to get plan from Stripe');

			$plan = \Stripe\Plan::retrieve($plan_id);

			sdf_message_handler(MessageTypes::DEBUG, 'Plan found');

		} catch(\Stripe\Error\InvalidRequest $e) {

			sdf_message_handler(MessageTypes::DEBUG, 'Plan not found');

			if($this->recurrence_type == RecurrenceTypes::ANNUAL) {
				$recurrence = 'year';
			} else {
				$recurrence = 'month';
			}

			$new_plan = array(
				'id' => $plan_id,
				'currency' => 'USD',
				'interval' => $recurrence,
				'amount' => $this->amount,
				'name' => $this->amount_string . ' ' . $recurrence . 'ly gift'
			);

			try {
				sdf_message_handler(MessageTypes::DEBUG,
						'Attempting to create new plan');

				$plan = \Stripe\Plan::create($new_plan);
			} catch(\Stripe\Error\Base $e) {
				sdf_message_handler(MessageTypes::LOG,
						__FUNCTION__ . ' : ' . $e);
				sdf_message_handler(MessageTypes::ERROR,
						'Something went wrong! Try again?');
			}
		}

		sdf_message_handler(MessageTypes::DEBUG,
				sprintf('Plan id is: %s', $plan->id));

		$this->stripe_plan = $plan;
	}

	// Create the basic customer
	private function stripe_customer() {
		sdf_message_handler(MessageTypes::DEBUG,
				sprintf('Creating Stripe customer: %s', $this->email));
		$info = array(
			'card' => $this->token,
			'email' => $this->email,
			'description' => $this->name
		);

		try {
			$customer = \Stripe\Customer::create($info);
		} catch(\Stripe\Error\Base $e) {
			sdf_message_handler(MessageTypes::ERROR, $e);
		}

		$this->stripe_customer = $customer;

		sdf_message_handler(MessageTypes::DEBUG,
				sprintf('Stripe customer created: %s', $customer->id));
	}

	// sign up for the plan.
	private function subscribe() {
		sdf_message_handler(MessageTypes::DEBUG,
				'Signing the customer up for the plan');

		try {
			$result = $this->stripe_customer->updateSubscription(
					array('plan' => $this->stripe_plan->id));

			sdf_message_handler(MessageTypes::DEBUG,
					sprintf('Signed up customer %s for plan %s, subscription id: %s',
						$result->customer, $result->plan->id, $result->id));

			return $result->id;

		} catch(\Stripe\Error\Base $e) {
			sdf_message_handler(MessageTypes::ERROR, $e);
		}
	}
} // end class ?>
