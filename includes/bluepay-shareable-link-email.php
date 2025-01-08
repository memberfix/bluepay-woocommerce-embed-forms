<?php

function bluepay_mfx_send_shareble_link ($order_id, $email, $billingemail) {

    $base_url = get_option('bluepay_confirmed_order_page_url', '');
    // Generate the payment link.
    $payment_link = esc_url("{$base_url}?order_id={$order_id}");


    $sent_to_subject = get_option('bluepay_sent_to_email_subject', '');
    $sent_to_body = get_option('bluepay_sent_to_email_body', '');

    // Use provided subject or fallback to a default.
    $subject = !empty($sent_to_subject) ? $sent_to_subject : __('TechServe Payment Details', 'woocommerce');

    // Use provided body or fallback to a default with the payment link.
    $message_body = !empty($sent_to_body) ? $sent_to_body : __('Kindly Complete the Payment.', 'woocommerce');
    $message = sprintf(
        '%s<br><br>Payment Link: <a href="%s">%s</a>',
        $message_body,
        esc_url($payment_link),
        esc_html($payment_link)
    );
    // Define headers for HTML emails.
    $headers = ['Content-Type: text/html; charset=UTF-8'];


    if (!wp_mail($email, $subject, $message, $headers)) {
        wp_send_json_error(['message' => __('Failed to send email. Please try again.', 'woocommerce')]);
    }

}