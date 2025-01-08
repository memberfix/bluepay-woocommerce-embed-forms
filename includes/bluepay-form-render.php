<?php 

function render_bluepay_form($atts) {

    $default_atts = array(
        'transaction_type' => 'SALE', // Default to SALE
    );

    // Merge user-defined attributes with defaults
    $atts = shortcode_atts($default_atts, $atts, 'bluepay_form');

    // Get the order ID from the URL parameter
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;

    if (!$order_id) {
        return '<form><p>Invalid Order ID.</p></form>';
    }

    // Load the WooCommerce order
    $order = wc_get_order($order_id);

    if (!$order) {
        return '<form><p>Order not found.</p></form>';
    }

    // Check if the order status is pending or on-hold
    $order_status = $order->get_status();
    if (!in_array($order_status, ['pending', 'on-hold'])) {
        return '<p>This order is not available for payment.</p>';
    }

    // Get the order total and format it
    $order_total = number_format((float)$order->get_total(), 2, '.', '');


    $approved_url = esc_url(get_option('bluepay_approved_url', '')) . '?wcorder=' . esc_attr($order_id);
    $declined_url = esc_url(get_option('bluepay_declined_url', '')) . '?wcorder=' . esc_attr($order_id);
    $error_url = esc_url(get_option('bluepay_error_url', '')) . '?wcorder=' . esc_attr($order_id);
    $merchant_id = esc_attr(get_option('bluepay_merchant_id', ''));
    $tamper_proof_seal = esc_attr(get_option('bluepay_tamper_proof_seal', ''));
    $mode_variation = esc_attr(get_option('bluepay_mode_variation', ''));

    $base_url = home_url('/wp-content/plugins/woocommerce/assets/images/icons/credit-cards/');



    // Form HTML
    ob_start();
    ?>
<form style="margin-top: 1rem; margin-bottom: 1rem;">
	<h3>Order # <?php echo esc_html($order_id); ?></h3>
    <h3>Amount $<?php echo esc_html($order_total); ?> </h3>
</form>


    <form  id="bluepay-payment-form" action="https://secure.bluepay.com/interfaces/bp10emu" method="POST">


<input type="hidden" name="MERCHANT" value= "<?php echo $merchant_id; ?>">
<input type="hidden" name="TRANSACTION_TYPE" value="<?php echo esc_attr($atts['transaction_type']); ?>">
<input type="hidden" name="TAMPER_PROOF_SEAL" value= "<?php echo $tamper_proof_seal; ?>">
<input type="hidden" name="APPROVED_URL" value="<?php echo $approved_url; ?>">
<input type="hidden" name="DECLINED_URL" value="<?php echo $declined_url; ?>">
<input type="hidden" name="MISSING_URL" value="<?php echo $error_url; ?>">
<input type="hidden" name="MODE" value= "<?php echo $mode_variation; ?>">
<input type="hidden" name="AUTOCAP" value="0">
<input type="hidden" name="REBILLING" value="">
<input type="hidden" name="REB_CYCLES" value="">
<input type="hidden" name="REB_AMOUNT" value="">
<input type="hidden" name="REB_EXPR" value="">
<input type="hidden" name="REB_FIRST_DATE" value="">
<input type="hidden" name="TPS_HASH_TYPE" value="MD5">
<input type="hidden" name="TPS_DEF" value="MERCHANT TRANSACTION_TYPE MODE TPS_DEF REBILLING REB_CYCLES REB_AMOUNT REB_EXPR REB_FIRST_DATE">
<input type="hidden" name="CUSTOM_ID" value="<?php echo esc_attr($order_id); ?>">
<input type="hidden" name="AMOUNT" value="<?php echo esc_attr($order_total); ?>">


<!-- Customer Information Section -->
<h3>Customer Information</h3>

<label for="EMAIL">Email:</label>
<input type="email" id="EMAIL" name="EMAIL" placeholder="Email" required>


<label for="NAME">Name on Card:</label>
<input type="text" name="NAME" id="NAME" placeholder="Name on Card" required>


<label for="PHONE">Phone:</label>
<input type="tel" id="PHONE" name="PHONE" placeholder="Phone Number" required>

<!-- Billing Address Section -->
<h3>Billing Address</h3>

<label for="ADDR1">Street Address:</label>
<input type="text" id="ADDR1" name="ADDR1" placeholder="Street Address" required>

<div class="flex-container">
    <input type="text" id="CITY" name="CITY" placeholder="Town / City" required>
    <input type="text" id="ZIPCODE" name="ZIPCODE" placeholder="Postcode / ZIP" required>
</div>

<div class="flex-container">
    <select id="STATE" name="STATE" class="select-input state-select-input">
        <option value="AL">AL</option>
        <option value="AK">AK</option>
        <option value="AR">AR</option>
        <option value="AZ">AZ</option>
        <option value="CA">CA</option>
        <option value="CO">CO</option>
        <option value="CT">CT</option>
        <option value="DC">DC</option>
        <option value="DE">DE</option>
        <option value="FL">FL</option>
        <option value="GA">GA</option>
        <option value="HI">HI</option>
        <option value="IA">IA</option>
        <option value="ID">ID</option>
        <option value="IL">IL</option>
        <option value="IN">IN</option>
        <option value="KS">KS</option>
        <option value="KY">KY</option>
        <option value="LA">LA</option>
        <option value="MA">MA</option>
        <option value="MD">MD</option>
        <option value="ME">ME</option>
        <option value="MI">MI</option>
        <option value="MN">MN</option>
        <option value="MO">MO</option>
        <option value="MS">MS</option>
        <option value="MT">MT</option>
        <option value="NC">NC</option>
        <option value="NE">NE</option>
        <option value="NH">NH</option>
        <option value="NJ">NJ</option>
        <option value="NM">NM</option>
        <option value="NV">NV</option>
        <option value="NY">NY</option>
        <option value="ND">ND</option>
        <option value="OH">OH</option>
        <option value="OK">OK</option>
        <option value="OR">OR</option>
        <option value="PA">PA</option>
        <option value="RI">RI</option>
        <option value="SC">SC</option>
        <option value="SD">SD</option>
        <option value="TN">TN</option>
        <option value="TX">TX</option>
        <option value="UT">UT</option>
        <option value="VT">VT</option>
        <option value="VA">VA</option>
        <option value="WA">WA</option>
        <option value="WI">WI</option>
        <option value="WV">WV</option>
        <option value="WY">WY</option>
        <option value="AS">AS</option>
        <option value="GU">GU</option>
        <option value="MP">MP</option>
        <option value="PR">PR</option>
        <option value="UM">UM</option>
        <option value="VI">VI</option>
    </select>
    <input type="text" id="STATE" name="STATE" class="state-text-input" placeholder="County / Region"
        required>
    <select id="COUNTRY" name="COUNTRY" class="select-input" placeholder="Country">
        <option value="United States">United States</option>
        <option value="Afghanistan">Afghanistan</option>
        <option value="Albania">Albania</option>
        <option value="Algeria">Algeria</option>
        <option value="American Samoa">American Samoa</option>
        <option value="Andorra">Andorra</option>
        <option value="Angola">Angola</option>
        <option value="Anguilla">Anguilla</option>
        <option value="Antartica">Antarctica</option>
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
        <option value="Bosnia and Herzegowina">Bosnia and Herzegowina</option>
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
        <option value="Cocos Islands">Cocos (Keeling) Islands</option>
        <option value="Colombia">Colombia</option>
        <option value="Comoros">Comoros</option>
        <option value="Congo">Congo</option>
        <option value="Congo">Congo, the Democratic Republic of the</option>
        <option value="Cook Islands">Cook Islands</option>
        <option value="Costa Rica">Costa Rica</option>
        <option value="Cota D'Ivoire">Cote d'Ivoire</option>
        <option value="Croatia">Croatia (Hrvatska)</option>
        <option value="Cuba">Cuba</option>
        <option value="Cyprus">Cyprus</option>
        <option value="Czech Republic">Czech Republic</option>
        <option value="Denmark">Denmark</option>
        <option value="Djibouti">Djibouti</option>
        <option value="Dominica">Dominica</option>
        <option value="Dominican Republic">Dominican Republic</option>
        <option value="East Timor">East Timor</option>
        <option value="Ecuador">Ecuador</option>
        <option value="Egypt">Egypt</option>
        <option value="El Salvador">El Salvador</option>
        <option value="Equatorial Guinea">Equatorial Guinea</option>
        <option value="Eritrea">Eritrea</option>
        <option value="Estonia">Estonia</option>
        <option value="Ethiopia">Ethiopia</option>
        <option value="Falkland Islands">Falkland Islands (Malvinas)</option>
        <option value="Faroe Islands">Faroe Islands</option>
        <option value="Fiji">Fiji</option>
        <option value="Finland">Finland</option>
        <option value="France">France</option>
        <option value="France Metropolitan">France, Metropolitan</option>
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
        <option value="Guinea-Bissau">Guinea-Bissau</option>
        <option value="Guyana">Guyana</option>
        <option value="Haiti">Haiti</option>
        <option value="Heard and McDonald Islands">Heard and Mc Donald Islands</option>
        <option value="Holy See">Holy See (Vatican City State)</option>
        <option value="Honduras">Honduras</option>
        <option value="Hong Kong">Hong Kong</option>
        <option value="Hungary">Hungary</option>
        <option value="Iceland">Iceland</option>
        <option value="India">India</option>
        <option value="Indonesia">Indonesia</option>
        <option value="Iran">Iran (Islamic Republic of)</option>
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
        <option value="Democratic People's Republic of Korea">Korea, Democratic People's Republic of
        </option>
        <option value="Korea">Korea, Republic of</option>
        <option value="Kuwait">Kuwait</option>
        <option value="Kyrgyzstan">Kyrgyzstan</option>
        <option value="Lao">Lao People's Democratic Republic</option>
        <option value="Latvia">Latvia</option>
        <option value="Lebanon">Lebanon</option>
        <option value="Lesotho">Lesotho</option>
        <option value="Liberia">Liberia</option>
        <option value="Libyan Arab Jamahiriya">Libyan Arab Jamahiriya</option>
        <option value="Liechtenstein">Liechtenstein</option>
        <option value="Lithuania">Lithuania</option>
        <option value="Luxembourg">Luxembourg</option>
        <option value="Macau">Macau</option>
        <option value="Macedonia">Macedonia, The Former Yugoslav Republic of</option>
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
        <option value="Micronesia">Micronesia, Federated States of</option>
        <option value="Moldova">Moldova, Republic of</option>
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
        <option value="Russia">Russian Federation</option>
        <option value="Rwanda">Rwanda</option>
        <option value="Saint Kitts and Nevis">Saint Kitts and Nevis</option>
        <option value="Saint Lucia">Saint LUCIA</option>
        <option value="Saint Vincent">Saint Vincent and the Grenadines</option>
        <option value="Samoa">Samoa</option>
        <option value="San Marino">San Marino</option>
        <option value="Sao Tome and Principe">Sao Tome and Principe</option>
        <option value="Saudi Arabia">Saudi Arabia</option>
        <option value="Senegal">Senegal</option>
        <option value="Seychelles">Seychelles</option>
        <option value="Sierra">Sierra Leone</option>
        <option value="Singapore">Singapore</option>
        <option value="Slovakia">Slovakia (Slovak Republic)</option>
        <option value="Slovenia">Slovenia</option>
        <option value="Solomon Islands">Solomon Islands</option>
        <option value="Somalia">Somalia</option>
        <option value="South Africa">South Africa</option>
        <option value="South Georgia">South Georgia and the South Sandwich Islands</option>
        <option value="Span">Spain</option>
        <option value="Sri Lanka">Sri Lanka</option>
        <option value="St. Helena">St. Helena</option>
        <option value="St. Pierre and Miguelon">St. Pierre and Miquelon</option>
        <option value="Sudan">Sudan</option>
        <option value="Suriname">Suriname</option>
        <option value="Svalbard">Svalbard and Jan Mayen Islands</option>
        <option value="Swaziland">Swaziland</option>
        <option value="Sweden">Sweden</option>
        <option value="Switzerland">Switzerland</option>
        <option value="Syria">Syrian Arab Republic</option>
        <option value="Taiwan">Taiwan, Province of China</option>
        <option value="Tajikistan">Tajikistan</option>
        <option value="Tanzania">Tanzania, United Republic of</option>
        <option value="Thailand">Thailand</option>
        <option value="Togo">Togo</option>
        <option value="Tokelau">Tokelau</option>
        <option value="Tonga">Tonga</option>
        <option value="Trinidad and Tobago">Trinidad and Tobago</option>
        <option value="Tunisia">Tunisia</option>
        <option value="Turkey">Turkey</option>
        <option value="Turkmenistan">Turkmenistan</option>
        <option value="Turks and Caicos">Turks and Caicos Islands</option>
        <option value="Tuvalu">Tuvalu</option>
        <option value="Uganda">Uganda</option>
        <option value="Ukraine">Ukraine</option>
        <option value="United Arab Emirates">United Arab Emirates</option>
        <option value="United Kingdom">United Kingdom</option>
        <option value="United States Minor Outlying Islands">United States Minor Outlying Islands</option>
        <option value="Uruguay">Uruguay</option>
        <option value="Uzbekistan">Uzbekistan</option>
        <option value="Vanuatu">Vanuatu</option>
        <option value="Venezuela">Venezuela</option>
        <option value="Vietnam">Viet Nam</option>
        <option value="Virgin Islands (British)">Virgin Islands (British)</option>
        <option value="Virgin Islands (U.S)">Virgin Islands (U.S.)</option>
        <option value="Wallis and Futana Islands">Wallis and Futuna Islands</option>
        <option value="Western Sahara">Western Sahara</option>
        <option value="Yemen">Yemen</option>
        <option value="Serbia">Serbia</option>
        <option value="Zambia">Zambia</option>
        <option value="Zimbabwe">Zimbabwe</option>
    </select>
</div>

<!-- Payment Information Section -->
<h3>Payment Information</h3>

<div class="credit-card-icons">
  <label for="CC_NUM">Credit Card (BluePay)</label>

<div class="credit-card-icons">
    <label for="CC_NUM">Credit Card (BluePay)</label>
    <img src="<?php echo esc_url($base_url . 'visa.svg'); ?>" alt="Visa">
    <img src="<?php echo esc_url($base_url . 'mastercard.svg'); ?>" alt="MasterCard">
    <img src="<?php echo esc_url($base_url . 'amex.svg'); ?>" alt="American Express">
    <img src="<?php echo esc_url($base_url . 'discover.svg'); ?>" alt="Discover">
</div>
</div>

 <input type="tel" inputmode="numeric" pattern="[0-9\s]{13,19}" autocomplete="cc-number" maxlength="19" placeholder="xxxx xxxx xxxx xxxx"  id="CC_NUM" name="CC_NUM" required>

<div class="flex-container">
    <input type="tel" id="CC_EXPIRES" name="CC_EXPIRES" placeholder="MM/YY" maxlength="5"
        oninput="formatExpiryDate(this)" required>
    <input type="tel" id="CVCCVV2" name="CVCCVV2" placeholder="CVV" maxlength="3" required>
</div>

<input type="submit" value="Make Payment">
</form>
    <?php
    return ob_get_clean();
}

add_shortcode('bluepay_form', 'render_bluepay_form');

function handle_bluepay_ajax_submission() {
    // Get the order ID
    $order_id = intval($_POST['CUSTOM_ID']);
    $order = wc_get_order($order_id);

    if (!$order) {
        wp_send_json_error(['message' => 'Order not found.']);
    }

    // Save billing details to the WooCommerce order
    $order->set_billing_email(sanitize_email($_POST['EMAIL']));
    $order->set_billing_phone(sanitize_text_field($_POST['PHONE']));
    $order->set_billing_address_1(sanitize_text_field($_POST['ADDR1']));
    $order->set_billing_city(sanitize_text_field($_POST['CITY']));
    $order->set_billing_postcode(sanitize_text_field($_POST['ZIPCODE']));
    $order->set_billing_state(sanitize_text_field($_POST['STATE']));
    $order->set_billing_country(sanitize_text_field($_POST['COUNTRY']));

    $full_name = sanitize_text_field($_POST['NAME']);
    $name_parts = explode(' ', $full_name, 2);
    $order->set_billing_first_name($name_parts[0] ?? '');
    $order->set_billing_last_name($name_parts[1] ?? '');

    $order->save();

    wp_send_json_success(['message' => 'Order updated successfully.']);
}
add_action('wp_ajax_handle_bluepay_submission', 'handle_bluepay_ajax_submission');
add_action('wp_ajax_nopriv_handle_bluepay_submission', 'handle_bluepay_ajax_submission');
