<?php
/*
	Plugin Name: Spark Donation Form
	Plugin URI:
	Description: Create and integrate a form with payment processing and CRM
	Author: Steve Avery
	Version: 2.0
	Author URI: https://stevenavery.com/
*/

error_reporting(E_ERROR | E_WARNING | E_PARSE); // XXX remove for debugging
defined('ABSPATH') or die("Unauthorized.");

require_once WP_PLUGIN_DIR . '/sdf/types.php';
require_once WP_PLUGIN_DIR . '/sdf/message.php';
require_once WP_PLUGIN_DIR . '/sdf/wordpress.php';
require_once WP_PLUGIN_DIR . '/sdf/SDFStripe.php';
require_once WP_PLUGIN_DIR . '/sdf/SDFSalesforce.php';

class sdf_data {

	private $data;

	// private static $ONE_TIME = 0;
	// private static $ANNUAL = 1;
	// private static $MONTHLY = 2;


	// XXX remove
	// private static $IS_NOT_CUSTOM = 0;
	// private static $IS_CUSTOM = 1;


	// private static $IS_NOT_MEMBER = 0;
	// private static $IS_MEMBER = 1;

	// ************************************************************************
	// Main

	// Start processing, validation, execution....
	public function begin($postdata) {
		$this->data = $postdata;

		$this->validate();
		$this->setup_additional_info();

		$this->do_stripe();
		$this->do_salesforce();
	}

	private function validate() {
		$this->required_fields();
		$this->donation_category();
		$this->hearabout_category();
		$this->check_email();
	}

	private function setup_additional_info() {
		$this->set_amount();
		$this->set_recurrence();
		$this->get_ext_amount();
	}

	private function do_stripe() {
		$stripe = new SDFCharge();
		$stripe->charge($this->get_stripe_details());
	}

	

	private function do_salesforce() {
		$this->salesforce_api();
		$this->get_contact();
		$this->get_donations();
		$this->update();
		$this->upsert();
		$this->new_donation();
		$this->send_email();
	}

	// this is an alternative entrypoint to the sdf class.
	public function do_stripe_endpoint($info) {
		sdf_message_handler(LOG, 'Endpoint request received.');
		static::$IS_ENDPOINT = true;

		$this->salesforce_api();

		$this->get_contact($info['email']);

		// gets existing donations
		$this->get_donations();

		// need to update totals in the contact object
		$this->recalc_sum($info['amount']);

		// add a new line in the description
		$this->description();

		$this->renewal_date();
		$this->cleanup();

		$this->upsert();
		$this->new_donation();
		$this->send_email();

	}

	private function get_stripe_details() {
		$info = array();

		$info['amount'] = $this->data['amount']; // in cents
		$info['amount-string'] = $this->data['amount-string'];
		$info['token'] = $this->data['stripe-token'];
		$info['email'] = $this->data['email'];
		$info['name'] = $this->data['first-name'] . ' ' . $this->data['last-name'];
		$info['recurrence-type'] = $this->data['recurrence-type'];
		$info['recurrence-string'] = $this->data['recurrence-string'];

		return $info;
	}

	// ************************************************************************
	// Validation functions

	private function required_fields() {
		$fields = array(
			'donation',
			'first-name',
			'last-name',
			'email',
			'tel',
			'address1',
			'city',
			'state',
			'zip',
			'stripe-token'
		);

		foreach($fields as $key) {
			if(!array_key_exists($key, $this->data)
				|| empty($this->data[$key])) {
				sdf_message_handler(ERROR, 'Error: Missing required fields.');
			}
		}
	}

	private function donation_category() {
		$cats = array(
			'annual-75',
			'annual-100',
			'annual-250',
			'annual-500',
			'annual-1000',
			'annual-2500',
			'monthly-5',
			'monthly-10',
			'monthly-20',
			'monthly-50',
			'monthly-100',
			'monthly-200',
			'annual-custom',
			'monthly-custom'
		);


		if(!in_array($this->data['donation'], $cats)) {
			sdf_message_handler(ERROR, 'Invalid donation amount.');
		}
	}

	private function hearabout_category() {
		$cats = array(
			'Renewing Membership',
			'Friend',
			'Website',
			'Search',
			'Event'
		);

		if(!empty($this->data['hearabout'])) {
			if(!in_array($this->data['hearabout'], $cats)) {
				sdf_message_handler(LOG, 'Invalid hearabout category.');
				unset($data['hearabout']);
				unset($data['hearabout-extra']);
			}
		}
	}

	private function check_email() {
		$this->data['email'] = filter_var($this->data['email'], FILTER_SANITIZE_EMAIL);
		if(!filter_var($this->data['email'], FILTER_VALIDATE_EMAIL)) {
			sdf_message_handler(MessageTypes::ERROR, 'Invalid email address.');
		}
	}

	// ************************************************************************
	// Setup functions

	private function set_recurrence() {
		if(array_key_exists('one-time', $this->data)
			&& !empty($this->data['one-time'])) {
				$recurrence = 'Single donation';
				$type = static::$ONE_TIME;
		} else {
			if(strpos($this->data['donation'], 'annual') !== false) {
				$recurrence = 'Annual';
				$type = static::$ANNUAL;
			} else {
				$recurrence = 'Monthly';
				$type = static::$MONTHLY;
			}
		}

		$this->data['recurrence-type'] = $type;
		$this->data['recurrence-string'] = $recurrence;
	}

	// set up data['amount'] and ['amount-string']
	// is also testing for sensibility, can stop execution
	// side effect: sets data['custom']
	private function set_amount() {
		if(strpos($this->data['donation'], 'custom') !== false) {
			$this->data['custom'] = static::$IS_CUSTOM; // XXX

			if(array_key_exists('annual-custom', $this->data)) {
				$donated_value = $this->data['annual-custom'];
			} else if(array_key_exists('monthly-custom', $this->data)) {
				$donated_value = $this->data['monthly-custom'];
			}

			unset($this->data['monthly-custom']);
			unset($this->data['annual-custom']);

			// XXX move to separate function
			if(!is_numeric($donated_value)) {
				// replace anything not numeric or a . with nothing
				$donated_value = preg_replace('/([^0-9\\.])/i', '', $donated_value);
			}

			if($donated_value <= 0.50) {
				sdf_message_handler(ERROR, 'Invalid request. Donation amount too small.');
			}
			// end
		} else {
			$this->data['custom'] = static::$IS_NOT_CUSTOM;
			$donated_value = $this->get_std_amount();
		}

		$this->data['amount'] = $donated_value * 100;
		$this->data['amount-string'] = '$' . $donated_value;  
	}

	// returns the amount in cents of standard donations
	// XXX regex instead?
	private function get_std_amount() {
		$plan_amounts = array(
			'annual-75' => 75,
			'annual-100' => 100,
			'annual-250' => 250,
			'annual-500' => 500,
			'annual-1000' => 1000,
			'annual-2500' => 2500,
			'monthly-5' => 5,
			'monthly-10' => 10,
			'monthly-20' => 20,
			'monthly-50' => 50,
			'monthly-100' => 100,
			'monthly-200' => 200
		);

		// potentially erroneous return of null
		return $plan_amounts[$this->data['donation']];
	}

	// Find out how much the amount is for the year if it's monthly
	private function get_ext_amount() {
		if($this->data['recurrence-type'] == static::$MONTHLY) {
			$times = 13 - intval(date('n'));
			$this->data['amount-ext'] = $times * $this->data['amount'];
		} else {
			$this->data['amount-ext'] = $this->data['amount'];
		}
	}


} // end sdf_data



// ****************************************************************************
// Ajax response function


function sdf_parse() {
	if(!isset($_POST['data'])) {
		sdf_message_handler(MessageTypes::LOG, __FUNCTION__ . ' No data received');
		die();
	} else {
		$sdf = new sdf_data();
		$sdf->begin($_POST['data']);
		unset($_POST['data']);
	}

	sdf_message_handler(MessageTypes::SUCCESS, 'Thank you for your donation!');
	die(); // prevent trailing 0 from admin-ajax.php
}


// ****************************************************************************
// HTML and redirect functions


function sdf_template() {
	global $wp;
	if(array_key_exists('pagename', $wp->query_vars)) {
		if($wp->query_vars['pagename'] == 'donate') { // XXX needs to be changed if the page
			$return_template = 'templates/page_donation.php';  // goes live on a new slug
			sdf_theme_redirect($return_template);
		}
	}
}


function sdf_theme_redirect($url) {
	global $post, $wp_query;
	if(have_posts()) {
		include($url);
		die();
	} else {
		$wp_query->is_404 = true;
	}
}


function sdf_ajaxurl() { ?>
	<script type="text/javascript">
		var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
	</script>
<?php }


function sdf_check_ssl() {
	if(!(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')) {
		header('HTTP/1.1 301 Moved Permanently');
        header('Location: https://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);
        exit();
	}
}


function sdf_noindex() {
	echo '<META NAME="ROBOTS" CONTENT="NOINDEX, NOFOLLOW">';
}

// ****************************************************************************
// Set up hooks

if(is_admin()) {
	add_action('admin_init', 'sdf_register_settings');
	add_action('admin_menu', 'sdf_create_menu');
	// http://codex.wordpress.org/AJAX_in_Plugins#Ajax_on_the_Viewer-Facing_Side
	add_action('wp_ajax_sdf_parse', 'sdf_parse');
	add_action('wp_ajax_nopriv_sdf_parse', 'sdf_parse');
}
add_action('template_redirect', 'sdf_template');
add_action('wp_head', 'sdf_ajaxurl');

register_activation_hook(__FILE__, 'sdf_activate');
register_deactivation_hook(__FILE__, 'sdf_deactivate');
