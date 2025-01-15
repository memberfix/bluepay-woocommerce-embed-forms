<?php
require_once plugin_dir_path( __FILE__ ) . 'order-status-complete.php';
require_once plugin_dir_path( __FILE__ ) . 'send-mail-on-payment-attempt.php';
require_once plugin_dir_path( __FILE__ ) . 'bluepay-settings-page.php'; // Added to get $base_url variable value

function bluepay_response_result_shortcode() {
    // Parse the URL query parameters
    $status = isset($_GET['Result']) ? sanitize_text_field($_GET['Result']) : 'N/A';
    $wcorder = isset($_GET['wcorder']) ? sanitize_text_field($_GET['wcorder']) : 'N/A';
    $invoice_id = isset($_GET['INVOICE_ID']) ? sanitize_text_field($_GET['INVOICE_ID']) : 'N/A';
    $message = isset($_GET['MESSAGE']) ? sanitize_text_field($_GET['MESSAGE']) : 'N/A';
    $order_id = isset($_GET['ORDER_ID']) ? sanitize_text_field($_GET['ORDER_ID']) : 'N/A'; // Added $order_id variable
    
    $base_url = get_option('bluepay_confirmed_order_page_url', ''); // Added $base_url variable
    
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
        <?php 
        // The heading changes depending on transaction status
        if ($status === 'APPROVED') { 
            echo '<h2><span id="result">SUCCESS</span></h2>'; 
        } else { 
            echo '<h2><span id="result">' . esc_html($status) . '</span></h2>'; 
        }
        ?>
        <h3>Order Number: <span id="wcorder"><?php echo esc_html($wcorder); ?></span></h3>
        <?php if ($status === 'APPROVED') : ?>
            <!-- 'Invoice ID' phrase replaced with a 'Transaction ID' -->
            <h4>Transaction ID: <span id="invoice"><?php echo esc_html($invoice_id); ?></span></h4>
        <?php endif; ?>
        <!-- '$message' variable replaced with a '$status' -->
        <h4 style="color: #F98200 !important;"><span id="message"><?php echo esc_html($status); ?></span></h4>
        <!-- 'Try again' button added in case of transaction is not approved -->
        <?php if ($status !== 'APPROVED') : ?>
            <a href="<?php echo $base_url . "?order_id=" . $order_id; ?>" style="margin-top:2rem;" class="elementor-button elementor-button-link elementor-size-sm">
                Try again
            </a>
        <?php endif; ?>
    </form>
    <?php
    return ob_get_clean();
}

// Register the shortcode
add_shortcode('bluepay_response_result', 'bluepay_response_result_shortcode');

