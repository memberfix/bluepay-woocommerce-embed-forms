<?php
//require_once plugin_dir_path( __FILE__ ) . 'get-single-transaction-details.php';
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
            <h4>Invoice ID: <span id="invoice"><?php echo esc_html($invoice_id); ?></span></h4>
        <?php endif; ?>
        <h4 style="color: #F98200 !important;"><span id="message"><?php echo esc_html($message); ?></span></h4>
    </form>
    <?php
    return ob_get_clean();
}

// Register the shortcode
add_shortcode('bluepay_response_result', 'bluepay_response_result_shortcode');



function mfx_update_order_status_and_txn_id($order_id, $txn_id) {
    // Load the order
    $order = wc_get_order($order_id); // wc_get_order is preferred for getting order objects
    // Check if the order exists
    if ($order instanceof WC_Order) {
        // Update the order status to 'completed'
        $order->update_status('completed'); // Use standard single quotes
        // Set the payment method and title
        $order->set_payment_method('bluepay');
        $order->set_payment_method_title('Credit Card (BluePay)');
        // Set the transaction ID
        $order->set_transaction_id($txn_id);
        // Add an order note with the invoice ID and status
        $order_note = 'Transaction completed via BluePay. Invoice ID: ' . esc_html($txn_id) . '. Status: Completed.';

        $order->add_order_note($order_note);
        // Save the order
        $order->save();
    }
}