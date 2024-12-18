<?php 



function mfx_mail_team_member_on_bluepay_payment_attempt($order_id, $status) {
    // Get the order object
    $order = wc_get_order($order_id);

    if (!$order) {
        return; // Exit if the order is invalid
    }

    // Fetch order details
    $billing_company = $order->get_billing_company() ?: 'N/A'; // Get billing company or default to 'N/A'
    $current_status = $order->get_status(); // Get current WooCommerce order status
    $current_status_formatted = ucfirst(str_replace('-', ' ', $current_status)); // Format status (e.g., 'pending-payment' -> 'Pending payment')

    // Recipient email
    $to = 'web@techservalliance.org';

    // Subject
    $subject = 'Order Payment Update: Order #' . $order_id . ' (' . ucfirst($status) . ')';

    // Email Body (HTML formatted)
    $body = "
        <html>
        <body>
            <h2>Order Payment Notification</h2>
            <p>The payment for order <strong>#$order_id</strong> associated with billing company <strong>{$billing_company}</strong> was attempted with the following status:</p>
            <p><strong>Payment Status:</strong> " . ucfirst($status) . "</p>
            <p>Currently, the order status in WooCommerce is: <strong>{$current_status_formatted}</strong>.</p>
            <p>Please check the order details in the WooCommerce dashboard for further information.</p>
        </body>
        </html>
    ";

    // Headers
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: TechServ Alliance <no-reply@techservalliance.org>',
    );

    // Send the email
    wp_mail($to, $subject, $body, $headers);
}
