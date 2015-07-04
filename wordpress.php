<?php
/**
* Wordpress settings and options page.
*
* TODO: Would also like to include the following settings:
* page picker for the donate form plugin.
* page picker for landing page.
*/

require_once WP_PLUGIN_DIR . '/sdf/message.php';
require_once WP_PLUGIN_DIR . '/sdf/Stripe.php';
require_once WP_PLUGIN_DIR . '/sdf/Salesforce.php';


function sdf_activate() {
	if(wp_next_scheduled('sdf_bimonthly_hook') == false) {
		wp_schedule_event(
			current_time('timestamp'), // timestamp
			'bimonthly', // recurrence
			'sdf_bimonthly_hook' // hook
		);
	}
}


function sdf_register_settings() {
	// Stripe stuff
	register_setting(
		'sdf', // option group name
		'stripe_api_secret_key', // option name
		'sdf_stripe_secret_sanitize' // option sanitization callback
	);
	register_setting(
		'sdf',
		'stripe_api_public_key',
		'sdf_string_setting_sanitize'
	);
	add_settings_section(
		'sdf_stripe_api', // id attribute of tags ??
		'Stripe', // section title
		'sdf_stripe_api_section_print', // callback to print section content
		'spark-form-admin' // page slug to work on
	);

	// Salesforce stuff
	register_setting(
		'sdf',
		'salesforce_username',
		'sdf_string_setting_sanitize'
	);
	register_setting(
		'sdf',
		'salesforce_password',
		'sdf_sf_password_validate'
	);
	register_setting(
		'sdf',
		'salesforce_token',
		'sdf_salesforce_api_check'
	);
	add_settings_section(
		'sdf_salesforce_api',
		'Salesforce',
		'sdf_salesforce_section_print',
		'spark-form-admin'
	);

	// email security
	register_setting(
		'sdf',
		'alert_email_list',
		'sdf_validate_settings_emails'
	);
	register_setting(
		'sdf',
		'sf_email_reply_to',
		'sdf_validate_settings_emails'
	);
	add_settings_section(
		'sdf_alert_email',
		'Alert Emails',
		'sdf_email_section_print',
		'spark-form-admin'
	);

	// Donation page address and contact email
	register_setting(
		'sdf',
		'spark_address',
		'sdf_string_no_sanitize'
	);
	register_setting(
		'sdf',
		'spark_contact_email',
		'sdf_validate_settings_emails'
	);
	add_settings_section(
		'sdf_spark_details',
		'Spark Details',
		'sdf_spark_details_print',
		'spark-form-admin'
	);
}


// unregister the settings when deactivated
function sdf_deactivate() {
	unregister_setting(
		'sdf', // option group
		'stripe_api_secret_key', // option name
		'sdf_stripe_secret_sanitize' // sanitize callback.
	);
	unregister_setting(
		'sdf',
		'stripe_api_public_key',
		'sdf_stripe_secret_sanitize'
	);
	unregister_setting(
		'sdf',
		'salesforce_username',
		'sdf_string_setting_sanitize'
	);
	unregister_setting(
		'sdf',
		'salesforce_password',
		'sdf_string_setting_sanitize'
	);
	unregister_setting(
		'sdf',
		'salesforce_token',
		'sdf_salesforce_api_check'
	);
	unregister_setting(
		'sdf',
		'alert_email_list',
		'sdf_validate_settings_emails'
	);
	unregister_setting(
		'sdf',
		'sf_email_reply_to',
		'sdf_validate_settings_emails'
	);
	unregister_setting(
		'sdf',
		'spark_address',
		'sdf_string_no_sanitize'
	);
	unregister_setting(
		'sdf',
		'spark_contact_email',
		'sdf_validate_settings_emails'
	);

	wp_clear_scheduled_hook(
		'sdf_bimonthly_hook'
	);
}

function sdf_options_page() { ?>
	<div class="wrap">
		<?php screen_icon(); ?>
		<h2>Spark Donation Form</h2>
		<form method="post" action="options.php">
			<?php
				settings_fields('sdf');
				do_settings_sections('spark-form-admin');
				submit_button();
			?>
		</form>
	</div>
<?php }


// ****************************************************************************
// Options page


function sdf_stripe_api_section_print() { ?>
	<p>Enter your API keys.<br>Ensure that your public and private keys match,
	whether in live mode, or test mode.</p>

	<?php sdf_print_stripe_api_settings_form();
}

function sdf_salesforce_section_print() { ?>
	<p>Enter your username, password, and the security token.<br>If you reset
	your password, you will need to <a href='https://na3.salesforce.com/_ui/sy
	stem/security/ResetApiTokenEdit?retURL=%2Fui%2Fsetup%2FSetup%3Fsetupid%3DP
	ersonalInfo&setupid=ResetApiToken'>reset your security token too.</a></p>

	<?php sdf_print_salesforce_settings_form();
}

function sdf_email_section_print() { ?>
	<p>Set the email addresses that receive alert emails, and the reply-to
	address for outgoing mails.</p>

	<?php sdf_print_email_settings_form();
}

function sdf_spark_details_print() { ?>
	<p>Set the Spark SF contact address and contact email.</p>
	
	<?php sdf_print_spark_details_form();
}

function sdf_print_email_settings_form() { ?>
<table class="form-table">
	<tr valign="top">
		<th scope="row">
			Notification addresses:<br>
			<span id="note">(Separate emails with a comma and space)</span>
		</th>
		<td>
			<input class="sdf-wide" 
				type="text" 
				id="alert_email_list"
				name="alert_email_list"
				value="<?php echo esc_attr(get_option('alert_email_list')); ?>" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">Reply-to:</th>
		<td>
			<input class="sdf-wide" 
				type="text"
				id="sf_email_reply_to"
				name="sf_email_reply_to"
				value="<?php echo esc_attr(get_option('sf_email_reply_to')); ?>" />
		</td>
	</tr>
</table>
<?php }

function sdf_print_stripe_api_settings_form() { ?>
<table class="form-table">
	<tr valign="top">
		<th scope="row">Stripe secret key:</th>
		<td>
			<input class="sdf-wide" 
				type="text"
				id="stripe_api_secret_key"
				name="stripe_api_secret_key"
				value="<?php echo esc_attr(get_option('stripe_api_secret_key')); ?>" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">Stripe public key:</th>
		<td>
			<input class="sdf-wide" 
				type="text"
				id="stripe_api_public_key"
				name="stripe_api_public_key"
				value="<?php echo esc_attr(get_option('stripe_api_public_key')); ?>" />
		</td>
	</tr>
</table>
<?php }

function sdf_print_salesforce_settings_form() { ?>
<table class="form-table">
	<tr valign="top">
		<th scope="row">Salesforce username:</th>
		<td>
			<input class="sdf-wide" 
				type="text" 
				id="salesforce_username" 
				name="salesforce_username" 
				value="<?php echo esc_attr(get_option('salesforce_username')); ?>" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">Salesforce password:</th>
		<td>
			<input class="sdf-wide" 
				type="password" 
				id="salesforce_password" 
				name="salesforce_password" 
				value="<?php echo sdf_password_dummy('salesforce_password'); ?>" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">Salesforce token:</th>
		<td>
			<input class="sdf-wide" 
				type="text" 
				id="salesforce_token" 
				name="salesforce_token" 
				value="<?php echo esc_attr(get_option('salesforce_token')); ?>" />
		</td>
	</tr>
</table>
<?php }


function sdf_print_spark_details_form() { ?>
<table class="form-table">
	<tr valign="top">
		<th scope="row">Spark's mailing address</th>
		<td>
			<textarea class="sdf-wide" 
				id="spark_address" 
				name="spark_address" 
				rows="3"><?php echo esc_attr(get_option('spark_address')); ?></textarea>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">Spark's contact email</th>
		<td>
			<input class="sdf-wide" 
				type="text" 
				id="spark_contact_email" 
				name="spark_contact_email" 
				value="<?php echo esc_attr(get_option('spark_contact_email')); ?>" />
		</td>
	</tr>
</table>
<?php }


// Test the given stripe credential
function sdf_stripe_secret_sanitize($input) {
	if(strlen($input)) {
		\SDF\Stripe::api($input);
		try {
			$count = Stripe_Plan::all()->count;
		} catch(Stripe_Error $e) {
			$message = $e->getJsonBody();
			$message = $message['error']['message'];
			add_settings_error(
				'stripe_api_secret_key', // id, or slug, of the pertinent setting
				'stripe_api_secret_key_auth_error', // id or slug of the error itself
				$message,
				'error' // message type, since this function actually handles everything including updates
			);
		}
	}
	return $input;
}


// Similarly, test the salesforce credentials
// Actually, only test the token.
function sdf_salesforce_api_check($input) {
	if(strlen($input) > 0) {
		$input = sdf_string_setting_sanitize($input);
		if(get_option('salesforce_username') 
			&& get_option('salesforce_password')) {

			try {
				\SDF\Salesforce::api($input);
			} catch(Exception $e) {
				$message = '<span id="source">Salesforce error:</span> ' 
					. htmlspecialchars($e->faultstring);

				add_settings_error(
					'salesforce_token',
					'salesforce_token_auth_error',
					$message,
					'error'
				);
			}
		}
	}
	return $input;
}


function sdf_string_setting_sanitize($input) {
	return trim(sanitize_text_field($input));
}


function sdf_string_no_sanitize($input) {
	return $input;
}


// This function prevents overwriting the saved password
// with dummy data                     

// It needs to be specific to salesforce because we need to get
// the specific option 'salesforce_password',
// and there's no way to pass another parameter
function sdf_sf_password_validate($input) {
	if($input == sdf_password_dummy('salesforce_password')) {
		return get_option('salesforce_password');
	} else {
		return trim($input);
	}
}


function sdf_validate_settings_emails($input) {
	// can receive a list or one email.
	$list = explode(", ", $input);
	foreach($list as $email) {
		if(strlen($email)) {
			if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$message = '<span id="source">Check this email address:</span> '
					. htmlspecialchars($email);
				add_settings_error(
					'email_alert',
					'email_value_error',
					$message,
					'error'
				);
			}
		}
	}
	if(count($list) == 1) {
		return $input;
	} else {
		return implode(', ', $list);
	}
}


function sdf_create_menu() {
	$page = add_options_page(
		'Spark Form Settings', // the options page html title tag contents
		'Spark Donation Form', // settings menu link title
		'manage_options', // capability requirement
		'spark-form-admin', // options page slug
		'sdf_options_page' // callback to print markup
	);
	add_action('admin_print_styles-' . $page, 'sdf_enqueue_admin_styles' );
}


function sdf_enqueue_admin_styles() {
	wp_enqueue_style(
		'sdf_admin_css', // style handle
		plugins_url('sdf/css/admin.css') // href
	);
}


// If there is a value in the database, we output a dummy value
// Or if there is no value in the database, we print nothing.
function sdf_password_dummy($setting) {
	$string = get_option($setting);
	for($ii = 0; $ii < strlen($string); $ii++) {
		$string[$ii] = '*';
	}
	return $string;
}


// Add settings link on plugin page
function sdf_settings_link($links) { 
	$settings_link = 
		'<a href="/wp-admin/options-general.php?page=spark-form-admin">Settings</a>';
	array_unshift($links, $settings_link); 
	return $links; 
}
