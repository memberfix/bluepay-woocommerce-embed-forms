<?php


function generate_bluepay_form_pay_now_link() {
    // Get the global order object (specific to the WebToffee context)
    if (!class_exists('WC_Order')) {
        return __('WooCommerce is not active.', TEXT_DOMAIN);
    }

    // Retrieve the order ID dynamically
    global $wp;
    if (isset($wp->query_vars['order-received'])) {
        $order_id = absint($wp->query_vars['order-received']);
    } else {
        return __('Order ID is missing.', TEXT_DOMAIN);
    }

    // Generate the BluePay link
    if (!empty($order_id)) {
        $link = site_url("/from_bluepay?orderid=" . $order_id);
        return esc_url($link);
    }

    // Return a placeholder or error message if no order ID is available
    return __('Unable to retrieve order ID.', TEXT_DOMAIN);
}

// Register the shortcode to generate the BluePay link
add_shortcode('bluepay_link', 'generate_bluepay_form_pay_now_link');

?>
