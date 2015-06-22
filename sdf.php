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
require_once WP_PLUGIN_DIR . '/sdf/SDFStripe.php';
require_once WP_PLUGIN_DIR . '/sdf/SDFSalesforce.php';

class sdf_data {
	private $data;

	public function begin($postdata) {
		$this->data = $postdata;

		$this->required_fields();
		$this->hearabout_category();
		$this->check_email();
		$this->set_full_name();
		$this->set_amount();
		$this->set_recurrence();
		$this->get_ext_amount();

		$this->do_stripe();
		$this->do_init_salesforce();
	}


	// this is an alternative entrypoint to the sdf class.
	public function do_stripe_endpoint($info) {
		sdf_message_handler(MessageTypes::LOG, 'Endpoint request received.');

		$salesforce = new SDFSalesforce();
		$salesforce->update($info);
	}


	private function do_stripe() {
		$stripe = new SDFStripe();
		$stripe->charge($this->get_stripe_details());
	}

	
	private function do_init_salesforce() {
		$salesforce = new SDFSalesforce();
		$salesforce->init($this->get_sf_init_details());
	}


	// ************************************************************************


	private function get_sf_init_details() {
		$info = array();

		$info['first-name'] = $this->data['first-name'];
		$info['last-name']  = $this->data['last-name'];
		$info['email']      = $this->data['email'];
		$info['phone']      = $this->data['tel'];
		$info['address1']   = $this->data['address1'];
		$info['address2']   = $this->data['address2'];
		$info['city']       = $this->data['city'];
		$info['state']      = $this->data['state'];
		$info['zip']        = $this->data['zip'];
		$info['country']    = $this->data['country'];

		return $info;
	}

	private function get_stripe_details() {
		$info = array();

		$info['amount-cents']      = $this->data['amount-cents'];
		$info['amount-string']     = $this->data['amount-string'];
		$info['token']             = $this->data['stripe-token'];
		$info['email']             = $this->data['email'];
		$info['name']              = $this->data['full-name'];
		$info['recurrence-type']   = $this->data['recurrence-type'];
		$info['recurrence-string'] = $this->data['recurrence-string'];

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
			if(!array_key_exists($key, $this->data)
				|| empty($this->data[$key])) {
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

		if(!empty($this->data['hearabout'])) {
			if(!in_array($this->data['hearabout'], $cats)) {
				sdf_message_handler(MessageTypes::LOG,
						'Invalid hearabout category.');

				unset($data['hearabout']);
				unset($data['hearabout-extra']);
			}
		}
	}

	private function check_email() {
		$this->data['email'] = filter_var(
				$this->data['email'], FILTER_SANITIZE_EMAIL);
		if(!filter_var($this->data['email'], FILTER_VALIDATE_EMAIL)) {

			sdf_message_handler(MessageTypes::ERROR,
					'Invalid email address.');
		}
	}

	private function set_full_name() {
		$this->data['full-name'] = 
				$this->data['first-name'] . ' ' . $this->data['last-name'];
	}

	private function set_recurrence() {
		if(array_key_exists('one-time', $this->data)
				&& !empty($this->data['one-time'])) {

			$recurrence = 'Single donation';
			$type = RecurrenceTypes::ONE_TIME;
		} else {
			if(strpos($this->data['donation'], 'annual') !== false) {
				$recurrence = 'Annual';
				$type = RecurrenceTypes::ANNUAL;
			} else {
				$recurrence = 'Monthly';
				$type = RecurrenceTypes::MONTHLY;
			}
		}

		$this->data['recurrence-type'] = $type;
		$this->data['recurrence-string'] = $recurrence;
	}

	private function set_amount() {
		if(array_key_exists('annual-custom', $this->data)) {
			$donated_value = $this->data['annual-custom'];
			unset($this->data['annual-custom']);
		} elseif(array_key_exists('monthly-custom', $this->data)) {
			$donated_value = $this->data['monthly-custom'];
			unset($this->data['monthly-custom']);
		} else {
			$donation = explode('-', $this->data['donation']);
			$donated_value = array_pop($donation);
			$donated_value = (float) $donated_value / 100;
			unset($this->data['donation']);
		}
		
		if(!is_numeric($donated_value)) {
			// replace anything not numeric or a . with nothing
			$donated_value = preg_replace('/([^0-9\\.])/i', '', $donated_value);
		}

		if($donated_value <= 0.50) {
			sdf_message_handler(MessageTypes::ERROR,
					'Invalid request. Donation amount too small.');
		}

		$this->data['amount-cents'] = $donated_value * 100;
		$this->data['amount-string'] = '$' . $donated_value;  
	}

} // end class sdf_data


// Ajax response function
function sdf_parse() {
	if(!isset($_POST['data'])) {
		sdf_message_handler(MessageTypes::LOG,
				__FUNCTION__ . ' No data received');

	} else {
		$sdf = new sdf_data();
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
