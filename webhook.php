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

// require_once find_wordpress_base_path() . "/wp-load.php";
require_once '../wordpress/wp-load.php'; // XXX
require_once 'sdf.php';
$sdf = new SDF();

// get and unwrap request
$body = @file_get_contents('php://input');
$event = json_decode($body, true);

if(strpos($event['type'], 'charge.') === 0) { // matches charge.*
	$type    = $event['type'];
	$email   = $event['data']['object']['receipt_email'];
	$cents   = $event['data']['object']['amount'];
	$invoice = $event['data']['object']['invoice'];

	$charge  = $event['data']['object']['id'];

	// Stripe seems to not handle certain email addresses,
	// so we fall back to the charge description
	if(is_null($email)) {
		$email = $event['data']['object']['description'];
	}
	
	$info = array(
		'type'       => $type,
		'email'      => $email,
		'amount'     => $cents,
		'charge-id'  => $charge,
		'invoice-id' => $invoice
	);

	// do the rest of the processing in the class
	$response_code = $sdf->do_stripe_endpoint($info);

	http_response_code($response_code);
} else {
	http_response_code(200);
} ?>
