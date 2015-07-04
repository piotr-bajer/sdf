<?php

/**
* Capture the events from Stripe,
* so that customers that are charged again will
* be updated in Salesforce.
*/

// we need the wordpress options stuff to be loaded
function find_wordpress_base_path() {
    $dir = dirname(__FILE__);
    do {
        if(file_exists($dir . "/wp-config.php")) {
            return $dir;
        }
    } while($dir = realpath("$dir/.."));

    return null;
}

require_once find_wordpress_base_path() . "/wp-load.php";
require_once 'sdf.php';
$sdf = new SDF();

// get and unwrap request
$body = @file_get_contents('php://input');
$event = json_decode($body, true);

if($event['type'] == 'charge.succeeded') {
	$email   = $event['data']['object']['receipt_email'];
	$cents   = $event['data']['object']['amount'];
	$invoice = $event['data']['object']['invoice'];

	// Stripe seems to not handle certain email addresses,
	// so we fall back to the charge description
	if(is_null($email)) {
		$email = $event['data']['object']['description'];
	}
	
	$info = array(
		'email'      => $email,
		'amount'     => $cents,
		'invoice-id' => $invoice
	);

	// do the rest of the processing in the class
	$response_code = $sdf->do_stripe_endpoint($info);

	http_response_code($response_code);
}

// this must be present or else the charge will be held
http_response_code(200); ?>
