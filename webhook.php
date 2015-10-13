<?php

/**
* Capture the events from Stripe,
* so that customers that are charged again will
* be updated in Salesforce.
*/

// we need the wordpress options stuff to be loaded
// this function assumes we are in some subdirectory of wordpress base
// like wp-content/plugins/sdf/ ...
function find_wordpress_base_path() {
	$count = 0;
	$dir = dirname(__FILE__);
	
	do {
		// local development
		if(file_exists('../wordpress/wp-load.php')) {
			return realpath('../wordpress/');
		}

		if(file_exists($dir . "/wp-config.php")) {
			return $dir;
		}

		if($count < 255) {
			$count++;
		} else {
			throw new Exception("Wordpress base path not found");
		}

	} while($dir = realpath("$dir/.."));

	return null;
}

require_once find_wordpress_base_path() . "/wp-load.php"; // XXX outputs 'a'?
require_once 'sdf.php';
$sdf = new SDF();

// get and unwrap request
$body = @file_get_contents('php://input');
$event = json_decode($body, true);
$response_code = 200;

if(strpos($event['type'], 'charge.') === 0) { // matches charge.*
	if(strpos($event['type'], 'charge.succeeded') === 0) {
		$type     = $event['type'];
		$email    = $event['data']['object']['receipt_email'];
		$customer = $event['data']['object']['customer'];
		$cents    = $event['data']['object']['amount'];
		$invoice  = $event['data']['object']['invoice'];

		$charge   = $event['data']['object']['id'];

		// Stripe seems to not handle certain email addresses,
		// so we fall back to the charge description
		if(is_null($email)) {
			$email = $event['data']['object']['description'];
		}
		// even still, we may have to look up the customer by their stripe id
		
		$info = array(
			'type'       => $type,
			'email'      => $email,
			'amount'     => $cents,
			'customer'   => $customer,
			'charge-id'  => $charge,
			'invoice-id' => $invoice,
		);

		// do the rest of the processing in the class
		$response_code = $sdf->do_stripe_endpoint($info);
	} else {
		// this was some other kind of charge.
		// Perhaps an email would be useful?
		sdf_message_handler(\SDF\MessageTypes::LOG,
				sprintf('Endpoint: Charge type: %s', $event['type']));
	}
}

http_response_code($response_code); ?>
