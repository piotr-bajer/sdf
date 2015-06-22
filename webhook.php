<?php

/**
* Capture the events from Stripe,
* so that customers that are charged again will
* be updated in Salesforce.
*/

// steps
// get and unwrap json from stripe
// based on which type of event it is:
// look up the customer in salesforce
// update their info and write back

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

define('BASE_PATH', find_wordpress_base_path());
require_once BASE_PATH . "/wp-load.php";

// load all our goodies
require_once "../sdf.php";
$sdf = new sdf_data();

// get and unwrap request
$body = @file_get_contents('php://input');
$event = json_decode($body, true);

if($event['type'] == 'charge.succeeded') {
	$email = $event['data']['object']['receipt_email'];
	$cents = $event['data']['object']['amount'];

	$info = array(
		'email' => $email,
		'amount' => $cents
	);

	// do the rest of the processing in the class
	$sdf->do_stripe_endpoint($info);
}

// this must be present or else the charge will be held
http_response_code(200); ?>
