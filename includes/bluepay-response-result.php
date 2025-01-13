<?php
require_once plugin_dir_path( __FILE__ ) . 'order-status-complete.php';
require_once plugin_dir_path( __FILE__ ) . 'send-mail-on-payment-attempt.php';


function bluepay_response_result_shortcode() {
    // Parse the URL query parameters
    $status = isset($_GET['Result']) ? sanitize_text_field($_GET['Result']) : 'N/A';
    $wcorder = isset($_GET['wcorder']) ? sanitize_text_field($_GET['wcorder']) : 'N/A';
    $invoice_id = isset($_GET['INVOICE_ID']) ? sanitize_text_field($_GET['INVOICE_ID']) : 'N/A';
    $message = isset($_GET['MESSAGE']) ? sanitize_text_field($_GET['MESSAGE']) : 'N/A';
    // Check if the status is APPROVED
    if ($status !== 'APPROVED') {
        $invoice_id = ''; // Hide the Invoice ID if the status is not APPROVED
    }
    if ($status === 'APPROVED') {
        mfx_update_order_status_and_txn_id($wcorder, $invoice_id);
    }
    mfx_mail_team_member_on_bluepay_payment_attempt ($wcorder, $status);
    
    // Render the form
    ob_start();
    ?>
    <form>
        <h2><span id="result"><?php echo esc_html($status); ?></span></h2>
        <h3>Order Number: <span id="wcorder"><?php echo esc_html($wcorder); ?></span></h3>
        <?php if ($status === 'APPROVED') : ?>
            <h4>Transaction ID: <span id="invoice"><?php echo esc_html($invoice_id); ?></span></h4>
        <?php endif; ?>
        <h4 style="color: #F98200 !important;"><span id="message"><?php echo esc_html($message); ?></span></h4>
    </form>
    <?php
    return ob_get_clean();
}

// Register the shortcode
add_shortcode('bluepay_response_result', 'bluepay_response_result_shortcode');

