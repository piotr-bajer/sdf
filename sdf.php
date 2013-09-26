<?php
/*
	Plugin Name: Spark Donation Form
	Plugin URI:
	Description: Create and integrate a form with payment processing and CRM
	Author: Steve Avery
	Version: 0.1
	Author URI: mailto:schavery@gmail.com
*/
error_reporting(E_ALL); // XXX

function sdf_get_form() { ?>
	<div id="sdf_form">
		<form method="post">
			<h3>Make A Donation:</h3>
			<h4>Amount:</h4>
			<fieldset>
				<legend>Make an annual gift:</legend>
				<input class="amount" type="radio" name="donation" id="annual-75" value="annual-75" required><label for="annual-75">$75</label>
				<input class="amount" type="radio" name="donation" id="annual-100" value="annual-100" checked required><label for="annual-100"><span class="membership-level">$100</span></label>
				<input class="amount" type="radio" name="donation" id="annual-250" value="annual-250" required><label for="annual-250">$250</label>
				<input class="amount" type="radio" name="donation" id="annual-500" value="annual-500" required><label for="annual-500">$500</label>
				<input class="amount" type="radio" name="donation" id="annual-1000" value="annual-1000" required><label for="annual-1000">$1000</label>
				<input class="amount" type="radio" name="donation" id="annual-2500" value="annual-2500" required><label for="annual-2500">$2500</label>
				<input class="amount js-custom-amount-click" type="radio" name="donation" id="annual-custom" value="annual-custom-amount" required><label for="annual-custom-amount">Or, a custom amount:</label><input class="amount money-amount js-custom-amount" type="number" id="annual-custom-amount" name="annual-custom-amount" >
			</fieldset>
			<span id="or">Or</span>
			<fieldset>
				<legend>Make an monthly gift:</legend>
				<input class="amount" type="radio" name="donation" id="monthly-5" value="monthly-5" required><label for="monthly-5">$5</label>
				<input class="amount" type="radio" name="donation" id="monthly-10" value="monthly-10" required><label for="monthly-10"><span class="membership-level">$10</span></label>
				<input class="amount" type="radio" name="donation" id="monthly-20" value="monthly-20" required><label for="monthly-20">$20</label>
				<input class="amount" type="radio" name="donation" id="monthly-50" value="monthly-50" required><label for="monthly-50">$50</label>
				<input class="amount" type="radio" name="donation" id="monthly-100" value="monthly-100" required><label for="monthly-100">$100</label>
				<input class="amount" type="radio" name="donation" id="monthly-200" value="monthly-200" required><label for="monthly-200">$200</label>
				<input class="amount js-custom-amount-click" type="radio" name="donation" id="monthly-custom" value="monthly-custom-amount" required><label for="monthly-custom-amount">Or, a custom amount:</label><input class="amount money-amount js-custom-amount" type="number" id="monthly-custom-amount" name="monthly-custom-amount" >
			</fieldset>
			<label for="one-time">No thanks, I only want to make a one-time gift of the amount above.</label>
			<input type="checkbox" name="one-time" id="one-time">
			<br>
			<label for="hearabout">How did you hear about Spark?</label>
			<select name="hearabout" id="hearabout">
				<option value="">--</option>
				<option value="Renewing Membership">Renewing Membership</option>
				<option value="friend" class="js-select-extra">Friend</option>
				<option value="Website">Website</option>
				<option value="Search">Search</option>
				<option value="event" class="js-select-extra">Event</option>
			</select>
			<br>
			<div id="js-select-extra-input" class="hidden">
				<label for="hearabout-extra">Name of <span id="js-select-extra-name"></span></label>
				<input type="text" name="hearabout-extra" id="hearabout-extra">
			</div>
			<label for="inhonorof">Please make this donation in honor of:</label>
			<input type="text" id="inhonorof" name="inhonorof">
			<hr>
			<h3>A little about you:</h3>
			<label for"first-name">Name:</label>
			<input name="first-name" id="first-name" type="text" placeholder="First" required>
			<input name="last-name" id="last-name" type="text" placeholder="Last" required>
			<br>
			<label for="company">Company:</label>
			<input type="text" id="company" name="company">
			<br>
			<label for="birthday-month">Birthday:</label>
			<input type="number" id="birthday-month" name="birthday-month" placeholder="Month">
			<span id="bday-separator">/</span>
			<input type="number" id="birthday-day" name="birthday-day" placeholder="Day">
			<br>
			<label for="gender">Gender:</label>
			<select name="gender" id="gender">
				<option value="">--</option>
				<option value="female">Female</option>
				<option vale="male">Male</option>
				<option value="other">Other</option>
			</select>
			<br>
			<label for"email">E-mail:</label>
			<input name="email" id="email" type="email" required>
			<br>
			<label for"tel">Phone:</label>
			<input name="tel" id="tel" type="text" required>
			<br>
			<label for"address1">Street Address:</label>
			<input name="address1" id="address1" type="text" required>
			<br>
			<label for"address2">Address 2:</label>
			<input name="address2" id="address2" type="text">
			<br>
			<label for"city">City:</label>
			<label for"state">State/Province:</label>
			<label for"zip">ZIP/Postal Code:</label>
			<br>
			<input name="city" id="city" type="text" required>
			<input name="state" id="state" type="text" required>
			<input name="zip" id="zip" type="text" required>
			<br>
			<label for="country">Country:</label>
			<?php sdf_get_country_select('country'); ?>
			<hr>
			<h3>Billing Information:</h3>
			<label for="cc-number">Credit Card Number:</label>
			<input type="text" id="cc-number" name="cc-number" required>
			<br>
			<label for="cc-cvc">Security Code:</label>
			<input type="text" id="cc-cvc" name="cc-cvc" required>
			<br>
			<label for="cc-exp-mo">Expiration Date:</label>
			<input type="number" id="cc-exp-mo" name="cc-exp-mo" placeholder="Month" required>
			<span id="cc-exp-separator">/</span>
			<input type="number" id="cc-exp-year" name="cc-exp-year" placeholder="Year" required>
			<hr>
			<label for="copy-personal-info">Copy billing information from above?</label>
			<input type="checkbox" id="copy-personal-info" class="js-copy-personal-info">
			<div id="js-cc-fields">
				<label for="cc-name">Name on Card:</label>
				<input type="text" id="cc-name" name="cc-name" required>
				<br>
				<label for="cc-address1">Billing Address:</label>
				<input type="text" id="cc-address1" name="cc-address1" required>
				<br>
				<label for="cc-address2">Address 2:</label>
				<input type="text" id="cc-address2" name="cc-address2">
				<br>
				<label for="cc-city">City:</label>
				<label for="cc-state">State / Province</label>
				<label for="cc-zip">ZIP / Postal Code:</label>
				<br>
				<input type="text" id="cc-city" name="cc-city" required>
				<input type="text" id="cc-state" name="cc-state" required>
				<input type="text" id="cc-zip" name="cc-zip" required>
				<br>
				<label for="cc-country">Country:</label>
				<?php sdf_get_country_select('cc-country'); ?>
			</div>
			<input type="hidden" name="stripe-token" id="stripe-token">
			<input type="button" id="js-form-submit" value="Donate Now">
		</form>
	</div>
<?php } // end function sdf_get_form

function sdf_get_country_select($name_attr) { ?>
	<select name="<?php echo $name_attr; ?>" id="<?php echo $name_attr; ?>" required>
		<option value="">--</option>
		<option value="United States">United States</option>
		<option value="Afghanistan">Afghanistan</option>
		<option value="Albania">Albania</option>
		<option value="Algeria">Algeria</option>
		<option value="American Samoa">American Samoa</option>
		<option value="Andorra">Andorra</option>
		<option value="Angola">Angola</option>
		<option value="Anguilla">Anguilla</option>
		<option value="Antarctica">Antarctica</option>
		<option value="Antigua and Barbuda">Antigua and Barbuda</option>
		<option value="Argentina">Argentina</option>
		<option value="Armenia">Armenia</option>
		<option value="Aruba">Aruba</option>
		<option value="Australia">Australia</option>
		<option value="Austria">Austria</option>
		<option value="Azerbaijan">Azerbaijan</option>
		<option value="Bahamas">Bahamas</option>
		<option value="Bahrain">Bahrain</option>
		<option value="Bangladesh">Bangladesh</option>
		<option value="Barbados">Barbados</option>
		<option value="Belarus">Belarus</option>
		<option value="Belgium">Belgium</option>
		<option value="Belize">Belize</option>
		<option value="Benin">Benin</option>
		<option value="Bermuda">Bermuda</option>
		<option value="Bhutan">Bhutan</option>
		<option value="Bolivia">Bolivia</option>
		<option value="Bosnia and Herzegovina">Bosnia and Herzegovina</option>
		<option value="Botswana">Botswana</option>
		<option value="Bouvet Island">Bouvet Island</option>
		<option value="Brazil">Brazil</option>
		<option value="British Indian Ocean Territory">British Indian Ocean Territory</option>
		<option value="Brunei Darussalam">Brunei Darussalam</option>
		<option value="Bulgaria">Bulgaria</option>
		<option value="Burkina Faso">Burkina Faso</option>
		<option value="Burundi">Burundi</option>
		<option value="Cambodia">Cambodia</option>
		<option value="Cameroon">Cameroon</option>
		<option value="Canada">Canada</option>
		<option value="Cape Verde">Cape Verde</option>
		<option value="Cayman Islands">Cayman Islands</option>
		<option value="Central African Republic">Central African Republic</option>
		<option value="Chad">Chad</option>
		<option value="Chile">Chile</option>
		<option value="China">China</option>
		<option value="Christmas Island">Christmas Island</option>
		<option value="Cocos (Keeling) Islands">Cocos (Keeling) Islands</option>
		<option value="Colombia">Colombia</option>
		<option value="Comoros">Comoros</option>
		<option value="Congo">Congo</option>
		<option value="Congo, The Democratic Republic of The">Congo, The Democratic Republic of The</option>
		<option value="Cook Islands">Cook Islands</option>
		<option value="Costa Rica">Costa Rica</option>
		<option value="Cote D'ivoire">Cote D'ivoire</option>
		<option value="Croatia">Croatia</option>
		<option value="Cuba">Cuba</option>
		<option value="Cyprus">Cyprus</option>
		<option value="Czech Republic">Czech Republic</option>
		<option value="Denmark">Denmark</option>
		<option value="Djibouti">Djibouti</option>
		<option value="Dominica">Dominica</option>
		<option value="Dominican Republic">Dominican Republic</option>
		<option value="Ecuador">Ecuador</option>
		<option value="Egypt">Egypt</option>
		<option value="El Salvador">El Salvador</option>
		<option value="Equatorial Guinea">Equatorial Guinea</option>
		<option value="Eritrea">Eritrea</option>
		<option value="Estonia">Estonia</option>
		<option value="Ethiopia">Ethiopia</option>
		<option value="Falkland Islands (Malvinas)">Falkland Islands (Malvinas)</option>
		<option value="Faroe Islands">Faroe Islands</option>
		<option value="Fiji">Fiji</option>
		<option value="Finland">Finland</option>
		<option value="France">France</option>
		<option value="French Guiana">French Guiana</option>
		<option value="French Polynesia">French Polynesia</option>
		<option value="French Southern Territories">French Southern Territories</option>
		<option value="Gabon">Gabon</option>
		<option value="Gambia">Gambia</option>
		<option value="Georgia">Georgia</option>
		<option value="Germany">Germany</option>
		<option value="Ghana">Ghana</option>
		<option value="Gibraltar">Gibraltar</option>
		<option value="Greece">Greece</option>
		<option value="Greenland">Greenland</option>
		<option value="Grenada">Grenada</option>
		<option value="Guadeloupe">Guadeloupe</option>
		<option value="Guam">Guam</option>
		<option value="Guatemala">Guatemala</option>
		<option value="Guinea">Guinea</option>
		<option value="Guinea-bissau">Guinea-bissau</option>
		<option value="Guyana">Guyana</option>
		<option value="Haiti">Haiti</option>
		<option value="Heard Island and Mcdonald Islands">Heard Island and Mcdonald Islands</option>
		<option value="Holy See (Vatican City State)">Holy See (Vatican City State)</option>
		<option value="Honduras">Honduras</option>
		<option value="Hong Kong">Hong Kong</option>
		<option value="Hungary">Hungary</option>
		<option value="Iceland">Iceland</option>
		<option value="India">India</option>
		<option value="Indonesia">Indonesia</option>
		<option value="Iran, Islamic Republic of">Iran, Islamic Republic of</option>
		<option value="Iraq">Iraq</option>
		<option value="Ireland">Ireland</option>
		<option value="Israel">Israel</option>
		<option value="Italy">Italy</option>
		<option value="Jamaica">Jamaica</option>
		<option value="Japan">Japan</option>
		<option value="Jordan">Jordan</option>
		<option value="Kazakhstan">Kazakhstan</option>
		<option value="Kenya">Kenya</option>
		<option value="Kiribati">Kiribati</option>
		<option value="Korea, Democratic People's Republic of">Korea, Democratic People's Republic of</option>
		<option value="Korea, Republic of">Korea, Republic of</option>
		<option value="Kuwait">Kuwait</option>
		<option value="Kyrgyzstan">Kyrgyzstan</option>
		<option value="Lao People's Democratic Republic">Lao People's Democratic Republic</option>
		<option value="Latvia">Latvia</option>
		<option value="Lebanon">Lebanon</option>
		<option value="Lesotho">Lesotho</option>
		<option value="Liberia">Liberia</option>
		<option value="Libyan Arab Jamahiriya">Libyan Arab Jamahiriya</option>
		<option value="Liechtenstein">Liechtenstein</option>
		<option value="Lithuania">Lithuania</option>
		<option value="Luxembourg">Luxembourg</option>
		<option value="Macao">Macao</option>
		<option value="Macedonia, The Former Yugoslav Republic of">Macedonia, The Former Yugoslav Republic of</option>
		<option value="Madagascar">Madagascar</option>
		<option value="Malawi">Malawi</option>
		<option value="Malaysia">Malaysia</option>
		<option value="Maldives">Maldives</option>
		<option value="Mali">Mali</option>
		<option value="Malta">Malta</option>
		<option value="Marshall Islands">Marshall Islands</option>
		<option value="Martinique">Martinique</option>
		<option value="Mauritania">Mauritania</option>
		<option value="Mauritius">Mauritius</option>
		<option value="Mayotte">Mayotte</option>
		<option value="Mexico">Mexico</option>
		<option value="Micronesia, Federated States of">Micronesia, Federated States of</option>
		<option value="Moldova, Republic of">Moldova, Republic of</option>
		<option value="Monaco">Monaco</option>
		<option value="Mongolia">Mongolia</option>
		<option value="Montserrat">Montserrat</option>
		<option value="Morocco">Morocco</option>
		<option value="Mozambique">Mozambique</option>
		<option value="Myanmar">Myanmar</option>
		<option value="Namibia">Namibia</option>
		<option value="Nauru">Nauru</option>
		<option value="Nepal">Nepal</option>
		<option value="Netherlands">Netherlands</option>
		<option value="Netherlands Antilles">Netherlands Antilles</option>
		<option value="New Caledonia">New Caledonia</option>
		<option value="New Zealand">New Zealand</option>
		<option value="Nicaragua">Nicaragua</option>
		<option value="Niger">Niger</option>
		<option value="Nigeria">Nigeria</option>
		<option value="Niue">Niue</option>
		<option value="Norfolk Island">Norfolk Island</option>
		<option value="Northern Mariana Islands">Northern Mariana Islands</option>
		<option value="Norway">Norway</option>
		<option value="Oman">Oman</option>
		<option value="Pakistan">Pakistan</option>
		<option value="Palau">Palau</option>
		<option value="Palestinian Territory, Occupied">Palestinian Territory, Occupied</option>
		<option value="Panama">Panama</option>
		<option value="Papua New Guinea">Papua New Guinea</option>
		<option value="Paraguay">Paraguay</option>
		<option value="Peru">Peru</option>
		<option value="Philippines">Philippines</option>
		<option value="Pitcairn">Pitcairn</option>
		<option value="Poland">Poland</option>
		<option value="Portugal">Portugal</option>
		<option value="Puerto Rico">Puerto Rico</option>
		<option value="Qatar">Qatar</option>
		<option value="Reunion">Reunion</option>
		<option value="Romania">Romania</option>
		<option value="Russian Federation">Russian Federation</option>
		<option value="Rwanda">Rwanda</option>
		<option value="Saint Helena">Saint Helena</option>
		<option value="Saint Kitts and Nevis">Saint Kitts and Nevis</option>
		<option value="Saint Lucia">Saint Lucia</option>
		<option value="Saint Pierre and Miquelon">Saint Pierre and Miquelon</option>
		<option value="Saint Vincent and The Grenadines">Saint Vincent and The Grenadines</option>
		<option value="Samoa">Samoa</option>
		<option value="San Marino">San Marino</option>
		<option value="Sao Tome and Principe">Sao Tome and Principe</option>
		<option value="Saudi Arabia">Saudi Arabia</option>
		<option value="Senegal">Senegal</option>
		<option value="Serbia and Montenegro">Serbia and Montenegro</option>
		<option value="Seychelles">Seychelles</option>
		<option value="Sierra Leone">Sierra Leone</option>
		<option value="Singapore">Singapore</option>
		<option value="Slovakia">Slovakia</option>
		<option value="Slovenia">Slovenia</option>
		<option value="Solomon Islands">Solomon Islands</option>
		<option value="Somalia">Somalia</option>
		<option value="South Africa">South Africa</option>
		<option value="South Georgia and The South Sandwich Islands">South Georgia and The South Sandwich Islands</option>
		<option value="Spain">Spain</option>
		<option value="Sri Lanka">Sri Lanka</option>
		<option value="Sudan">Sudan</option>
		<option value="Suriname">Suriname</option>
		<option value="Svalbard and Jan Mayen">Svalbard and Jan Mayen</option>
		<option value="Swaziland">Swaziland</option>
		<option value="Sweden">Sweden</option>
		<option value="Switzerland">Switzerland</option>
		<option value="Syrian Arab Republic">Syrian Arab Republic</option>
		<option value="Taiwan, Province of China">Taiwan, Province of China</option>
		<option value="Tajikistan">Tajikistan</option>
		<option value="Tanzania, United Republic of">Tanzania, United Republic of</option>
		<option value="Thailand">Thailand</option>
		<option value="Timor-leste">Timor-leste</option>
		<option value="Togo">Togo</option>
		<option value="Tokelau">Tokelau</option>
		<option value="Tonga">Tonga</option>
		<option value="Trinidad and Tobago">Trinidad and Tobago</option>
		<option value="Tunisia">Tunisia</option>
		<option value="Turkey">Turkey</option>
		<option value="Turkmenistan">Turkmenistan</option>
		<option value="Turks and Caicos Islands">Turks and Caicos Islands</option>
		<option value="Tuvalu">Tuvalu</option>
		<option value="Uganda">Uganda</option>
		<option value="Ukraine">Ukraine</option>
		<option value="United Arab Emirates">United Arab Emirates</option>
		<option value="United Kingdom">United Kingdom</option>
		<option value="United States">United States</option>
		<option value="United States Minor Outlying Islands">United States Minor Outlying Islands</option>
		<option value="Uruguay">Uruguay</option>
		<option value="Uzbekistan">Uzbekistan</option>
		<option value="Vanuatu">Vanuatu</option>
		<option value="Venezuela">Venezuela</option>
		<option value="Viet Nam">Viet Nam</option>
		<option value="Virgin Islands, British">Virgin Islands, British</option>
		<option value="Virgin Islands, U.S.">Virgin Islands, U.S.</option>
		<option value="Wallis and Futuna">Wallis and Futuna</option>
		<option value="Western Sahara">Western Sahara</option>
		<option value="Yemen">Yemen</option>
		<option value="Zambia">Zambia</option>
		<option value="Zimbabwe">Zimbabwe</option>
	</select>
<?php }

function sdf_template() {
	global $wp;
	if(array_key_exists('pagename', $wp->query_vars)) {
		if($wp->query_vars['pagename'] == 'new-donate-page') {
			$return_template = 'templates/page_donation.php';
			do_theme_redirect($return_template);
		}
	}	
}

function do_theme_redirect($url) {
	global $post, $wp_query;
	if (have_posts()) {
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
		'sdf_string_setting_sanitize'
	);
	register_setting(
		'sdf',
		'salesforce_token',
		'sdf_string_setting_sanitize'
	);
	add_settings_section(
		'sdf_salesforce_api',
		'Salesforce',
		'sdf_salesforce_section_print',
		'spark-form-admin'
	);
}

function sdf_stripe_api_section_print() {
	echo "<p>Enter your API keys.<br>Ensure that your public and private keys match, whether in live mode, or test mode.</p>";
	sdf_print_stripe_api_settings_form();
}

function sdf_salesforce_section_print() {
	echo "<p>Enter your username, password, and the security token.<br>If you reset your password, you will need to <a href='https://na3.salesforce.com/_ui/system/security/ResetApiTokenEdit?retURL=%2Fui%2Fsetup%2FSetup%3Fsetupid%3DPersonalInfo&setupid=ResetApiToken'>reset your security token too.</a></p>";
	sdf_print_salesforce_settings_form();
}

function sdf_print_stripe_api_settings_form() { ?>
<table class="form-table">
	<tr valign="top">
		<th scope="row">Stripe secret key:</th>
		<td>
			<input class="sdf-wide" type="text" id="stripe_api_secret_key" name="stripe_api_secret_key" value="<?php echo esc_attr(get_option('stripe_api_secret_key')); ?>" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">Stripe public key:</th>
		<td>
			<input class="sdf-wide" type="text" id="stripe_api_public_key" name="stripe_api_public_key" value="<?php echo esc_attr(get_option('stripe_api_public_key')); ?>" />
		</td>
	</tr>
</table>
<?php }

function sdf_print_salesforce_settings_form() { ?>
<table class="form-table">
	<tr valign="top">
		<th scope="row">Salesforce username:</th>
		<td>
			<input type="text" id="salesforce_username" name="salesforce_username" value="<?php echo esc_attr(get_option('salesforce_username')); ?>" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">Salesforce password:</th>
		<td>
			<input type="password" id="salesforce_password" name="salesforce_password" value="<?php echo esc_attr(get_option('salesforce_password')); ?>" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">Salesforce token:</th>
		<td>
			<input type="text" id="salesforce_token" name="salesforce_token" value="<?php echo esc_attr(get_option('salesforce_token')); ?>" />
		</td>
	</tr>
</table>
<?php }

function sdf_stripe_secret_sanitize($input) {
	// XXX don't do anything if it's empty
	sdf_include_stripe_api($input);
	try {
		$test_customer = Stripe_Customer::create(array('description' => 'test customer'));
	} catch(Stripe_Error $e) {
		$message = $e->getJsonBody();
		$message = $message['error']['message'];
		add_settings_error(
			'stripe_api_secret_key', // id, or slug, depending on how you see things, of the pertinent setting
			'stripe_api_secret_key_auth_error', // id or slug of the error itself
			$message,
			'error' // message type, since this function actually handles everything including updates
		);
	}
	if(isset($test_customer) && method_exists($test_customer, 'delete')) {
		$test_customer->delete();
		add_action('admin_enqueue_scripts', 'sdf_enqueue_admin_scripts'); // XXX
	}
	return $input;
}

function sdf_string_setting_sanitize($input) {
	return trim(sanitize_text_field($input));
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

function sdf_parse() {
	// stupid wordpress you should be more like your friend drupal.
	$data = $_POST['data']/* or die()*/;
	$data['amount'] = sdf_get_amount(&$data);
	$data['membership'] = sdf_get_membership(&$data);
	sdf_do_salesforce(&$data);
	//sdf_do_stripe(&$data);
	die(); // prevent trailing 0 from admin-ajax.php
}

function sdf_get_membership($data) {
	$levels = array(
		'annual' => array(
			7500 => 'Friend',
			10000 => 'Member',
			25000 => 'Affiliate',
			50000 => 'Sponsor',
			100000 => 'Investor',
			250000 => 'Benefactor',
		),
		'monthly' => array(
			500 => 'Friend',
			1000 => 'Member',
			2000 => 'Affiliate',
			5000 => 'Sponsor',
			10000 => 'Investor',
			20000 => 'Benefactor',
		),
	);
	$amount = $data['amount'];
	foreach($levels as $recurrence => $level) {
		if(strpos($data['donation'], $recurrence) !== false) {
			foreach($level as $plan_amount => $name) {
				if($amount > $plan_amount) {
					// don't underflow
					if(prev($level) !== false) { // indicates we aren't at the first element
						return current($level); // because we rewound in the if statement
					} else {
						return reset($level);
					}
				}
			}
		}
	}
}

function sdf_get_amount($data) {
	$plan_amounts = array(
		'annual-75' => 7500,
		'annual-100' => 10000,
		'annual-250' => 25000,
		'annual-500' => 50000,
		'annual-1000' => 100000,
		'annual-2500' => 250000,
		'monthly-5' => 500,
		'monthly-10' => 1000,
		'monthly-20' => 2000,
		'monthly-50' => 5000,
		'monthly-100' => 10000,
		'monthly-200' => 20000
	);
	if(in_array($data['donation'], array_keys($plan_amounts))) {
		$amount = $plan_amounts[$data['donation']];
	} else {
		// custom amount
		$amount = $data[$data['donation']] * 100;
	}
	return $amount;
}

function sdf_do_salesforce($data) {
	$sforce = sdf_include_salesforce_api();
	define('FRIEND_OF_SPARK', '00130000007qhRG');
	define('SF_DATE_FORMAT', 'Y-m-d');

	if(!empty($data['company'])) {
		$sfcompany = new stdClass();
		$sfcompany->Name = $data['company'];
		$return = $sforce->upsert(
			'Name', // key for matching
			array($sfcompany), // data objects
			'Company' // object type
		);
		$company_id = $return[0]->id;
	} else {
		$company_id = FRIEND_OF_SPARK;
	}
	// if(strpos($data['donation'], 'custom') {
	// 	$donor_type = 'Donor';
	// } else {
	// 	$donor_type = 'Friend';
	// }
	/*
	if strpos monthly donation
		if amount is greater than 1000
			$member = 1;
		else
			$member = 0;
	else 
		if amount is greater than 10000
			$member = 1;
		else
			$member = 0
	endif
	*/

	$address = (empty($data['address2'])) ? $data['address1'] : $data['address1'] . "\n" . $data['address2'];
	$birthday = date(SF_DATE_FORMAT, strtotime(date('Y') . '-' . $data['birthday-month'] . '-' . $data['birthday-day']));
	$amount = sprintf('%01.2f', $data['amount'] / 100);
	$hear = $data['hearabout'] . (!empty($data['hearabout-extra']) ? ': ' . $data['hearabout-extra'] : '');
	$renewal = $member ? date(SF_DATE_FORMAT, strtotime('+1 year')) : '';

	$sfcontact = new stdClass();
	$sfcontact->FirstName = $data['first-name'];
	$sfcontact->LastName = $data['last-name'];
	$sfcontact->Phone = $data['tel'];
	$sfcontact->Email = $data['email'];
	$sfcontact->AccountId = $company_id;
	$sfcontact->MailingStreet = $address;
	$sfcontact->MailingCity = $data['city'];
	$sfcontact->MailingState = $data['state'];
	$sfcontact->MailingPostalCode = $data['zip'];
	$sfcontact->MailingCountry = $data['country'];
	$sfcontact->Birthdate = $birthday;
	// $sfcontact->Description; // possible overwrite?
	// $sfcontact->Type__c = $donor_type;
	$sfcontact->Paid__c = $amount;
	$sfcontact->How_did_you_hear__c = $hear;
	// $sfcontact->Active_Member__c = $member;
	// $sfcontact->Membership_Start_Date__c = date(SF_DATE_FORMAT); // only if they are a member
	// $sfcontact->Renewal_Date__c = $renewal;
	$sfcontact->Member_Level__c = $data['membership'];
	$sfcontact->Payment_Type__c = 'Credit Card';
	// $sfcontact->First_Active_Date__c = // i don't want to overwrite this.
	$sfcontact->Gender__c = ($data['gender'] == 'other') ? '' : $data['gender'];

	ob_clean();
	// print_r($sfcontact);
	try {
		$response = $sforce->upsert(
			'Email', // field id for key to upsert on
			array($sfcontact), // objects to send
			'Contact' // object type
		);
	} catch(Exception $e) {
		print_r($e);
	}
	print_r($response);
}

function sdf_do_stripe($data) {
	if(array_key_exists('one-time', $data) && !empty($data['one-time'])) {
		echo sdf_single_charge($data['amount'], $data['stripe-token'], $data['email']);
	} else {
		// recurring donations. Get the plan.
		if(strpos($data['donation'], 'custom') !== false) { // it's a custom plan, potentially new.
			if(strpos($data['donation'], 'annual') !== false) {
				$recurrence = 'year';
			} elseif(strpos($data['donation'], 'monthly') !== false) {
				$recurrence = 'month';
			}
			$plan = sdf_create_custom_stripe_plan($recurrence, $data['amount']);
		} else { // default plans.
			$plan = sdf_get_stripe_default_plan($data['donation']);
		}
		echo sdf_create_subscription($plan, sdf_create_stripe_customer($data));
	}
}

function sdf_create_stripe_customer($data) {
	sdf_include_stripe_api();
	$new_customer = array(
		'card' => $data['stripe-token'],
		'email' => $data['email'],
		'description' => $data['name'],
	);
	return Stripe_Customer::create($new_customer);
}

function sdf_get_stripe_default_plan($id) {
	sdf_include_stripe_api();
	try {
		$plan = Stripe_Plan::retrieve($id);
	} catch(Stripe_Error $e) {
		$body = $e->getJsonBody();
		echo $body['error']['message'];
		die();
	}
	return $plan;
}

function sdf_create_custom_stripe_plan($recurrence, $amount) {
	// if($amount > 50) {
		sdf_include_stripe_api();
		$plan_id_slug = array(
			'year' => 'annual-',
			'month' => 'monthly-'
		);
		$plan_id = $plan_id_slug[$recurrence] . ($amount / 100);
		try {
			$plan = Stripe_Plan::retrieve($plan_id);
		} catch(Stripe_InvalidRequestError $e) {
			$errmsg = 'No such plan: ' . $plan_id;
			$body = $e->getJsonBody();
			if($body['error']['message'] == $errmsg) {
				$new_plan = array(
					'id' => $plan_id,
					'currency' => 'USD',
					'interval' => $recurrence,
					'amount' => $amount,
					'name' => '$' . ($amount / 100) . ' ' . $recurrence . 'ly custom gift'
				);
				$plan = Stripe_Plan::create($new_plan);
			} else {
				ob_clean();
				echo $body['error']['message'];
				die();
			}
		}
		return $plan;
	// } else {
	// 	ob_clean();
	// 	echo 'invalid_amount';
	// 	die();
	// // }
}

function sdf_single_charge($amount, $token, $email) {
	sdf_include_stripe_api();
	try {
		Stripe_Charge::create(array(
			'amount' => $amount, // in pennies
			'card' => $token,
			'currency' => 'usd',
			'description' => $email
		));
	} catch(Stripe_Error $e) {
		$body = $e->getJsonBody();
		return $body['error']['message'];
	}
	return 'single_success';
}

function sdf_enqueue_admin_scripts() {
	wp_enqueue_script(
		'sdf_admin_js', // handle
		plugins_url('sdf/js/admin.js'), // src
		array('jquery') // dependencies
	);
	// exit();
	// wp_enqueue_script( 'script-name', get_template_directory_uri() . '/js/example.js', array(), '1.0.0', true );
}

// function sdf_stripe_default_plans_callback($old) {
// 	wp_register_script(
// 		'sdf_admin_js', // handle
// 		plugins_url('sdf/js/admin.js'), // src
// 		array('jquery') // dependencies
// 	);
// 	wp_enqueue_script('sdf_admin_js');
// 	//wp_register_script( 'script-name', get_template_directory_uri() . '/js/example.js', array(), '1.0.0', true );
// 	//add_action('admin_enqueue_scripts', 'sdf_enqueue_admin_scripts');
// 	//add_action('admin_print_scripts_' . 'spark-form-admin', 'sdf_enqueue_admin_scripts');
// }

function sdf_stripe_default_plans_create() {
	sdf_include_stripe_api();
	if(!Stripe_Plan::all()->count) { // XXX catch errors here
		$plans = array(
			array(
				'id' => 'annual-75',
				'amount' => 7500,
				'currency' => 'USD',
				'interval' => 'year',
				'name' => '$75 Annual Gift',
			),
			array(
				'id' => 'annual-100',
				'amount' => 10000,
				'currency' => 'USD',
				'interval' => 'year',
				'name' => '$100 Annual Gift',
			),
			array(
				'id' => 'annual-250',
				'amount' => 25000,
				'currency' => 'USD',
				'interval' => 'year',
				'name' => '$250 Annual Gift',
			),
			array(
				'id' => 'annual-500',
				'amount' => 50000,
				'currency' => 'USD',
				'interval' => 'year',
				'name' => '$500 Annual Gift',
			),
			array(
				'id' => 'annual-1000',
				'amount' => 100000,
				'currency' => 'USD',
				'interval' => 'year',
				'name' => '$1000 Annual Gift',
			),
			array(
				'id' => 'annual-2500',
				'amount' => 250000,
				'currency' => 'USD',
				'interval' => 'year',
				'name' => '$2500 Annual Gift',
			),
			array(
				'id' => 'monthly-5',
				'amount' => 500,
				'currency' => 'USD',
				'interval' => 'month',
				'name' => '$5 Monthly Gift',
			),
			array(
				'id' => 'monthly-10',
				'amount' => 1000,
				'currency' => 'USD',
				'interval' => 'month',
				'name' => '$10 Monthly Gift',
			),
			array(
				'id' => 'monthly-20',
				'amount' => 2000,
				'currency' => 'USD',
				'interval' => 'month',
				'name' => '$20 Monthly Gift',
			),
			array(
				'id' => 'monthly-50',
				'amount' => 5000,
				'currency' => 'USD',
				'interval' => 'month',
				'name' => '$50 Monthly Gift',
			),
			array(
				'id' => 'monthly-100',
				'amount' => 10000,
				'currency' => 'USD',
				'interval' => 'month',
				'name' => '$100 Monthly Gift',
			),
			array(
				'id' => 'monthly-200',
				'amount' => 20000,
				'currency' => 'USD',
				'interval' => 'month',
				'name' => '$200 Monthly Gift',
			),
		);
		foreach($plans as $plan) {
			try {
				Stripe_Plan::create($plan);
			} catch(Stripe_Error $e) { // XXX
				$body = $e->getJsonBody();
				print_r($body);
				exit();
			}
		}
	}
}

function sdf_create_subscription($plan, $customer) {
	try {
		$customer->updateSubscription(array('plan' => $plan->id));	
	} catch(Stripe_Error $e) {
		ob_clean();
		$body = $e->getJsonBody();
		print_r($body);
		exit(); // XXX
	}
	return 'subscribe_success';
}

function sdf_include_stripe_api($input) {
	require_once(WP_PLUGIN_DIR . '/sdf/stripe/lib/Stripe.php');
	if(!empty($input)) {
		Stripe::setApiKey($input);
	} else {
		Stripe::setApiKey(get_option('stripe_api_secret_key'));
	}
	Stripe::setApiVersion('2013-08-13');
}

function sdf_include_salesforce_api() {
	require_once(WP_PLUGIN_DIR . '/sdf/salesforce/soapclient/SforceEnterpriseClient.php');
	$sf_object = new SforceEnterpriseClient();
	$sf_client = $sf_object->createConnection(WP_PLUGIN_DIR . '/sdf/salesforce/soapclient/enterprise.wsdl.xml');
	$sf_object->login(get_option('salesforce_username'), get_option('salesforce_password') . get_option('salesforce_token'));
	return $sf_object;
}

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
		'sdf_string_setting_sanitize'
	);
}

if(is_admin()) {
	add_action('admin_init', 'sdf_register_settings');
	add_action('admin_menu', 'sdf_create_menu');
	// http://codex.wordpress.org/AJAX_in_Plugins#Ajax_on_the_Viewer-Facing_Side
	add_action('wp_ajax_sdf_parse', 'sdf_parse');
	add_action('wp_ajax_nopriv_sdf_parse', 'sdf_parse');
	add_action('wp_ajax_sdf_stripe_default_plans_create', 'sdf_stripe_default_plans_create');
}
add_action('template_redirect', 'sdf_template');
add_action('wp_head', 'sdf_ajaxurl');
register_deactivation_hook(__FILE__, 'sdf_deactivate');
//add_action('update_option_stripe_api_secret_key', 'sdf_stripe_default_plans_callback'); // XXX
