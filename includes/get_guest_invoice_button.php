<?php

add_action('woocommerce_admin_order_data_after_order_details', 'add_guest_invoice_page_link_to_order_status', 10, 1);

function add_guest_invoice_page_link_to_order_status($order) {
    // Check if the order status is 'pending' or 'on-hold'
    if (in_array($order->get_status(), ['pending', 'on-hold'])) {
        // Get the base URL from settings
        $base_url = get_option('bluepay_confirmed_order_page_url', '');

        if (!$base_url) {
            return; // Return if the base URL is not set
        }

        // Generate the guest invoice page URL with the order ID as a parameter
        $invoice_url = esc_url_raw(add_query_arg([
            'order_id' => $order->get_id(),
        ], $base_url));

        // Add the hyperlink next to the status
        echo '<a href="' . $invoice_url . '" class="button" target="_blank">Guest Invoice Page</a>';
    }
}