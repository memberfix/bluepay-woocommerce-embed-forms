<?php 

require_once plugin_dir_path( __FILE__ ) . 'order-create-checkout.php';
require_once plugin_dir_path( __FILE__ ) . 'bluepay-shareable-link-email.php';


function bluepay_mfx_process_shareble_link () {

    try {
                // Initialize WooCommerce session for new visitors if not active.
                if (WC()->session && !WC()->session->has_session()) {
                    WC()->session->set_customer_session_cookie(true);
                    error_log('[Bluepay MFX] WooCommerce session initialized.');
                }
        
                // Validate email address.
                if (empty($_POST['email']) || !is_email($_POST['email'])) {
                    error_log('[Bluepay MFX] Invalid email address provided: ' . print_r($_POST['email'], true));
                    wp_send_json_error(['message' => __('Invalid email address.', 'woocommerce')]);
                }
        
                $email = sanitize_email($_POST['email']);
        		$billingemail = sanitize_email($_POST['billingemail']);
                error_log('[Bluepay MFX] Sanitized email: ' . $email);
                
                

                $order_id = bluepay_mfx_create_order_checkout($email, $billingemail);
                //$order_id = custom_process_checkout($email, $billingemail);
                bluepay_mfx_send_shareble_link($order_id, $email, $billingemail);
              

        
            } catch (Exception $e) {
                error_log('[Bluepay MFX] Exception occurred: ' . $e->getMessage());
                wp_send_json_error(['message' => __('An unexpected error occurred. Please try again.', 'woocommerce')]);
            }
}