<?php

/**
Capture the events from Stripe,
so that customers that are charged again will
be updated in Salesforce.
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

// load the stripe api library
require_once "../sdf.php";
$sdf = new sdf_data();
$sdf->stripe_api();
print_r($sdf);


// get and unwrap request
$body = @file_get_contents('php://input');
$event = json_decode($body);

file_put_contents($dir . "/event.txt", $event);

http_response_code(200);
?>

