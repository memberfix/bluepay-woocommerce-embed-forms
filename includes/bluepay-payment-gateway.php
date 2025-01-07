<?php 

require_once plugin_dir_path( __FILE__ ) . 'bluepay-process-shareble-link.php';

add_filter('woocommerce_payment_gateways', 'add_bluepay_mfx_gateway');

function add_bluepay_mfx_gateway($gateways) {
    $gateways[] = 'WC_Bluepay_MFX_Gateway';
    return $gateways;
}

add_action('plugins_loaded', 'init_bluepay_mfx_gateway');

function init_bluepay_mfx_gateway() {
    class WC_Bluepay_MFX_Gateway extends WC_Payment_Gateway {

        public function __construct() {
            $this->id = 'bluepay_mfx';
            $this->method_title = __('Shareable payment link', 'woocommerce');
            $this->method_description = __('A custom payment gateway for Bluepay MFX.', 'woocommerce');
            $this->has_fields = true;

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user settings.
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');

            // Save settings.
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        }

        public function init_form_fields() {
            $this->form_fields = [
                'enabled' => [
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable Bluepay MFX Payment Gateway', 'woocommerce'),
                    'default' => 'yes',
                ],
                'title' => [
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                    'default' => __('Shareable payment link', 'woocommerce'),
                    'desc_tip' => true,
                ],
                'description' => [
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                    'default' => __('Pay via Bluepay MFX.', 'woocommerce'),
                ],
            ];
        }

        public function payment_fields() {
            echo '<div class="bluepay-mfx-container">';
            echo '<p>' . esc_html__('Enter your email to receive the payment details:', 'woocommerce') . '</p>';
            echo '<p><input type="email" id="bluepay_mfx_email" placeholder="Your email address" required></p>';
            echo '<p><button id="bluepay_mfx_share_payment_details" class="elementor-button elementor-button-link elementor-size-sm" style="margin-top: 1rem;">' . __('Share Payment Details', 'woocommerce') . '</button></p>';
            ?>
			            <script>
                jQuery(document).ready(function($) {
                    $('#bluepay_mfx_share_payment_details').on('click', function(e) {
                        e.preventDefault();
        
                        const email = $('#bluepay_mfx_email').val();
                        const button = $(this);
						const billingemail = $('#billing_email').val();
        
                        if (!email || !email.includes('@')) {
                            alert('<?php echo esc_js(__('Please enter a valid email address.', 'woocommerce')); ?>');
                            return;
                        }
        
                        // Show processing message and disable the button
                        button.text('<?php echo esc_js(__('Processing...', 'woocommerce')); ?>');
                        button.prop('disabled', true);
        
                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            method: 'POST',
                            data: {
                                action: 'bluepay_mfx_process_shareble_link',
                                email: email,
								billingemail: billingemail
                            },
                            success: function(response) {
                                if (response.success) {
                                    // Redirect the user on success
                                    window.location.href = response.data.redirect_url;
                                } else {
                                    alert(response.data.message || '<?php echo esc_js(__('An error occurred. Please try again.', 'woocommerce')); ?>');
                                    // Re-enable the button on error
                                    button.text('<?php echo esc_js(__('Share Payment Details', 'woocommerce')); ?>');
                                    button.prop('disabled', false);
                                }
                            },
                            error: function() {
                                alert('<?php echo esc_js(__('An error occurred. Please try again.', 'woocommerce')); ?>');
                                // Re-enable the button on error
                                button.text('<?php echo esc_js(__('Share Payment Details', 'woocommerce')); ?>');
                                button.prop('disabled', false);
                            }
                        });
                    });
                });
            </script>
            <?php
            echo '</div>';
        }
        
    }
}

add_action('wp_ajax_bluepay_mfx_process_shareble_link', 'bluepay_mfx_process_shareble_link');
add_action('wp_ajax_nopriv_bluepay_mfx_process_shareble_link', 'bluepay_mfx_process_shareble_link');

// function bluepay_mfx_1update_order_send_email() {
//     try {
//         // Initialize WooCommerce session for new visitors if not active.
//         if (WC()->session && !WC()->session->has_session()) {
//             WC()->session->set_customer_session_cookie(true);
//             error_log('[Bluepay MFX] WooCommerce session initialized.');
//         }

//         // Validate email address.
//         if (empty($_POST['email']) || !is_email($_POST['email'])) {
//             error_log('[Bluepay MFX] Invalid email address provided: ' . print_r($_POST['email'], true));
//             wp_send_json_error(['message' => __('Invalid email address.', 'woocommerce')]);
//         }

//         $email = sanitize_email($_POST['email']);
// 		$billingemail = sanitize_email($_POST['billingemail']);
//         error_log('[Bluepay MFX] Sanitized email: ' . $email);

//         // Validate cart contents.
//         if (!WC()->cart || WC()->cart->is_empty()) {
//             error_log('[Bluepay MFX] Cart is empty. Session contents: ' . print_r(WC()->session->get_session_data(), true));
//             wp_send_json_error(['message' => __('Your cart is empty. Please add items to proceed.', 'woocommerce')]);
//         }

//         // Create a new WooCommerce order.
//         $order = wc_create_order();
//         error_log('[Bluepay MFX] Order created. ID: ' . $order->get_id());

//         // Add cart items to the order.
//         foreach (WC()->cart->get_cart() as $cart_item) {
//             $order->add_product($cart_item['data'], $cart_item['quantity']);
//             error_log('[Bluepay MFX] Added product to order: ' . $cart_item['data']->get_name());
//         }

//         // Calculate order totals.
//         $order->calculate_totals();
//         error_log('[Bluepay MFX] Order totals calculated.');

//         // Create or fetch the customer ID using the provided email
//         if ($billingemail) {
//             $user = get_user_by('email', $billingemail);
//             if ($user) {
//                 $customer_id = $user->ID; // Existing user
//             } else {
//                 // Create a new user for the email
//                 $password = wp_generate_password(); // Generate a random password
//                 $customer_id = wp_create_user($billingemail, $password, $billingemail);
//                 if (is_wp_error($customer_id)) {
//                     wp_send_json_error(['message' => __('Unable to create customer account.', 'woocommerce')]);
//                 }
//             }

//             // Assign the customer ID to the order
//             $order->set_customer_id($customer_id);
// 		}
		
//             $order->save();

//         $base_url = get_option('bluepay_confirmed_order_page_url', '');
//         // Generate the payment link.
//         $payment_link = esc_url("{$base_url}?order_id={$order->get_id()}");
//         error_log('[Bluepay MFX] Payment link generated: ' . $payment_link);


//         $sent_to_subject = get_option('bluepay_sent_to_email_subject', '');
//         $sent_to_body = get_option('bluepay_sent_to_email_body', '');

//         // Use provided subject or fallback to a default.
//         $subject = !empty($sent_to_subject) ? $sent_to_subject : __('TechServe Payment Details', 'woocommerce');

//         // Use provided body or fallback to a default with the payment link.
//         $message_body = !empty($sent_to_body) ? $sent_to_body : __('Kindly Complete the Payment.', 'woocommerce');
//         $message = sprintf(
//             '%s<br><br>Payment Link: <a href="%s">%s</a>',
//             $message_body,
//             esc_url($payment_link),
//             esc_html($payment_link)
//         );
//         // Define headers for HTML emails.
//         $headers = ['Content-Type: text/html; charset=UTF-8'];


//         if (!wp_mail($email, $subject, $message, $headers)) {
//             error_log('[Bluepay MFX] Failed to send email to: ' . $email);
//             wp_send_json_error(['message' => __('Failed to send email. Please try again.', 'woocommerce')]);
//         }

//         error_log('[Bluepay MFX] Email sent successfully to: ' . $email);

//         // Empty the cart to prevent duplicate orders.
//         WC()->cart->empty_cart();
//         error_log('[Bluepay MFX] Cart emptied.');

    
//         // Redirect to the thank-you page with order_id and email as URL parameters.
    

//         if (empty($base_url)) {
//             error_log('[Bluepay MFX] Thank you page URL is not set.');
//             wp_send_json_error(['message' => __('Thank you page URL is not configured.', 'woocommerce')]);
//         }

//         // Add query arguments for order ID and email
//         $bluepay_confirmed_order_page_url = esc_url_raw(
//             add_query_arg(
//                 [
//                     'order_id' => $order->get_id(),
//                     'sent_to'  => $email 
//                 ],
//                 $base_url // Ensure the base URL is sanitized
//             )
//         );
        
//         // Send the response with the redirect URL
//         wp_send_json_success(['redirect_url' => $bluepay_confirmed_order_page_url]);
        


//     } catch (Exception $e) {
//         error_log('[Bluepay MFX] Exception occurred: ' . $e->getMessage());
//         wp_send_json_error(['message' => __('An unexpected error occurred. Please try again.', 'woocommerce')]);
//     }
// }
