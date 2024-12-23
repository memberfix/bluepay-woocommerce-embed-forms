<?php


function bluepay_gateway_order_confirmation_shortcode() {
    // Get parameters from the URL
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;
    $sent_to = isset($_GET['sent_to']) ? sanitize_email($_GET['sent_to']) : null;
    $additional_details = get_option('bluepay_thank_you_page_additional_details', '');

    // Validate URL parameters
    if (!$order_id || !$sent_to) {
        return '<p>' . esc_html__('Invalid or missing order details.', 'woocommerce') . '</p>';
    }

    // Display the form
    ob_start();
    ?>
    <div class="bluepay-thank-you-form">
    <form method="" action="" style="margin: 2rem;">
        <h2>
            <?php echo esc_html__('Thank you!', 'woocommerce'); ?></h2>
        <h2>
            <?php echo sprintf(
                esc_html__('Payment details for order #%d were successfully sent to %s.', 'woocommerce'),
                esc_html($order_id),
                esc_html($sent_to)
            ); ?>
        </h2>

       
            <label for="additional_description">
            <?php echo esc_html__($additional_details, 'woocommerce'); ?>
            </label><br>
            <br>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('bluepay_gateway_order_confirmation', 'bluepay_gateway_order_confirmation_shortcode');
