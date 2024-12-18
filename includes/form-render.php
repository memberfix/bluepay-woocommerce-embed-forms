<?php 

function render_bluepay_form($atts) {
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
    $customer_email = $order->get_billing_email();
    $customer_phone = $order->get_billing_phone();
    $billing_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    $billing_address = $order->get_billing_address_1();
    $billing_city = $order->get_billing_city();
    $billing_state = $order->get_billing_state();
    $billing_postcode = $order->get_billing_postcode();
    $billing_country = $order->get_billing_country();

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


    <form action="https://secure.bluepay.com/interfaces/bp10emu" method="POST">


<input type="hidden" name="MERCHANT" value= "<?php echo $merchant_id; ?>">
<input type="hidden" name="TRANSACTION_TYPE" value="SALE">
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
    <input type="text" id="STATE" name="STATE" placeholder="State / County" required>
    <input type="text" id="country" name="country" placeholder="Country">
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
    <input type="text" id="CC_EXPIRES" name="CC_EXPIRES" placeholder="MMYY" required>
    <input type="text" id="CVCCVV2" name="CVCCVV2" placeholder="CVV" required>
</div>

<input type="submit" value="Make Payment">
</form>
    <?php
    return ob_get_clean();
}

add_shortcode('bluepay_form', 'render_bluepay_form');
