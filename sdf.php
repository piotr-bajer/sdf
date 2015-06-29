<?php
/*
	Plugin Name: Spark Donation Form
	Plugin URI:
	Description: Create and integrate a form with payment processing and CRM
	Author: Steve Avery
	Version: 2.0
	Author URI: https://stevenavery.com/
*/

define('LIVEMODE', 0);

if(LIVEMODE) {
	error_reporting(0);
} else {
	error_reporting(E_ERROR | E_WARNING | E_PARSE);
}

defined('ABSPATH') or die("Unauthorized.");

require_once WP_PLUGIN_DIR . '/sdf/types.php';
require_once WP_PLUGIN_DIR . '/sdf/message.php';
require_once WP_PLUGIN_DIR . '/sdf/wordpress.php';
require_once WP_PLUGIN_DIR . '/sdf/Stripe.php';
require_once WP_PLUGIN_DIR . '/sdf/Salesforce.php';

class SDF {

	private $data;

	public function begin($postdata) {
		self::$data = $postdata;

		self::required_fields();
		self::hearabout_category();
		self::check_email();
		self::set_full_name();
		self::set_amount();
		self::set_recurrence();
		self::get_ext_amount();

		self::do_stripe();
		self::do_init_salesforce();
	}


	// this is an alternative entrypoint to the sdf class.
	public function do_stripe_endpoint($info) {
		sdf_message_handler(MessageTypes::LOG, 'Endpoint request received.');

		$salesforce = new \SDF\AsyncSalesforce();
		$salesforce->update($info);
	}


	private function do_stripe() {
		$stripe = new \SDF\Stripe();
		$stripe->charge(self::get_stripe_details());
	}

	
	private function do_init_salesforce() {
		$salesforce = new \SDF\UCSalesforce();
		$salesforce->init(self::get_sf_init_details());
	}


	// ************************************************************************


	private function get_sf_init_details() {
		$info = array();

		$info['first-name']      = self::$data['first-name'];
		$info['last-name']       = self::$data['last-name'];
		$info['email']           = self::$data['email'];
		$info['phone']           = self::$data['tel'];
		$info['address1']        = self::$data['address1'];
		$info['address2']        = self::$data['address2'];
		$info['city']            = self::$data['city'];
		$info['state']           = self::$data['state'];
		$info['zip']             = self::$data['zip'];
		$info['country']         = self::$data['country'];
		$info['company']         = self::$data['company'];
		$info['birthday-month']  = self::$data['birthday-month'];
		$info['birthday-year']   = self::$data['birthday-year'];
		$info['gender']          = self::$data['gender'];
		$info['hearabout']       = self::$data['hearabout'];
		$info['hearabout-extra'] = self::$data['hearabout-extra'];

		return $info;
	}

	private function get_stripe_details() {
		$info = array();

		$info['amount-cents']      = self::$data['amount-cents'];
		$info['amount-string']     = self::$data['amount-string'];
		$info['token']             = self::$data['stripe-token'];
		$info['email']             = self::$data['email'];
		$info['name']              = self::$data['full-name'];
		$info['recurrence-type']   = self::$data['recurrence-type'];
		$info['recurrence-string'] = self::$data['recurrence-string'];

		return $info;
	}

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
			if(!array_key_exists($key, self::$data)
				|| empty(self::$data[$key])) {
				sdf_message_handler(MessageTypes::ERROR,
						'Error: Missing required fields.');
			}
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

		if(!empty(self::$data['hearabout'])) {
			if(!in_array(self::$data['hearabout'], $cats)) {
				sdf_message_handler(MessageTypes::LOG,
						'Invalid hearabout category.');

				unset($data['hearabout']);
				unset($data['hearabout-extra']);
			}
		}
	}

	private function check_email() {
		self::$data['email'] = filter_var(
				self::$data['email'], FILTER_SANITIZE_EMAIL);
		if(!filter_var(self::$data['email'], FILTER_VALIDATE_EMAIL)) {

			sdf_message_handler(MessageTypes::ERROR,
					'Invalid email address.');
		}
	}

	private function set_full_name() {
		self::$data['full-name'] = 
				self::$data['first-name'] . ' ' . self::$data['last-name'];
	}

	private function set_recurrence() {
		if(array_key_exists('one-time', self::$data)
				&& !empty(self::$data['one-time'])) {

			$recurrence = 'Single donation';
			$type = RecurrenceTypes::ONE_TIME;
		} else {
			if(strpos(self::$data['donation'], 'annual') !== false) {
				$recurrence = 'Annual';
				$type = RecurrenceTypes::ANNUAL;
			} else {
				$recurrence = 'Monthly';
				$type = RecurrenceTypes::MONTHLY;
			}
		}

		self::$data['recurrence-type'] = $type;
		self::$data['recurrence-string'] = $recurrence;
	}

	private function set_amount() {
		if(array_key_exists('annual-custom', self::$data)) {
			$donated_value = self::$data['annual-custom'];
			unset(self::$data['annual-custom']);
		} elseif(array_key_exists('monthly-custom', self::$data)) {
			$donated_value = self::$data['monthly-custom'];
			unset(self::$data['monthly-custom']);
		} else {
			$donation = explode('-', self::$data['donation']);
			$donated_value = array_pop($donation);
			$donated_value = (float) $donated_value / 100;
			unset(self::$data['donation']);
		}
		
		if(!is_numeric($donated_value)) {
			// replace anything not numeric or a . with nothing
			$donated_value = preg_replace('/([^0-9\\.])/i', '', $donated_value);
		}

		if($donated_value <= 0.50) {
			sdf_message_handler(MessageTypes::ERROR,
					'Invalid request. Donation amount too small.');
		}

		self::$data['amount-cents'] = $donated_value * 100;
		self::$data['amount-string'] = '$' . $donated_value;  
	}

} // end class sdf_data


// Ajax response function
function sdf_parse() {
	if(!isset($_POST['data'])) {
		sdf_message_handler(MessageTypes::LOG,
				__FUNCTION__ . ' No data received');

	} else {
		$sdf = new SDF();
		$sdf->begin($_POST['data']);
		unset($_POST['data']);
		sdf_message_handler(MessageTypes::SUCCESS,
				'Thank you for your donation!');
	}
	
	die(); // prevent trailing 0 from admin-ajax.php
}


// HTML and redirect functions
function sdf_template() {
	global $wp;
	if(array_key_exists('pagename', $wp->query_vars)) {
		if($wp->query_vars['pagename'] == 'donate') { // TODO: make setting
			$return_template = 'templates/page_donation.php';
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
	if(LIVEMODE) {
		if(!(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')) {
			header('HTTP/1.1 301 Moved Permanently');
			header('Location: https://' . $_SERVER['SERVER_NAME']
					. $_SERVER['REQUEST_URI']);
			die();
		}
	}
}

// unused
function sdf_noindex() {
	echo '<META NAME="ROBOTS" CONTENT="NOINDEX, NOFOLLOW">';
}

// ****************************************************************************
// Setup hooks

if(is_admin()) {
	add_action('admin_init', 'sdf_register_settings');
	add_action('admin_menu', 'sdf_create_menu');
	add_action('wp_ajax_sdf_parse', 'sdf_parse');
	add_action('wp_ajax_nopriv_sdf_parse', 'sdf_parse');
}

add_action('template_redirect', 'sdf_template');
add_action('wp_head', 'sdf_ajaxurl');

// XXX not working
add_filter('plugin_action_links_' . $plugin, 'sdf_settings_link' );

register_activation_hook(__FILE__, 'sdf_activate');
register_deactivation_hook(__FILE__, 'sdf_deactivate');
