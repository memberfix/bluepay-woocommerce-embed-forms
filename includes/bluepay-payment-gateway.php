<?php 

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
			echo '<p><button id="bluepay_mfx_update_order_send_email" class="elementor-button elementor-button-link elementor-size-sm" style="margin-top: 1rem;">' . __('Share Payment Details', 'woocommerce') . '</button></p>';
            ?>
            <script>
                jQuery(document).ready(function($) {
                    $('#bluepay_mfx_update_order_send_email').on('click', function(e) {
                        e.preventDefault();
                        const email = $('#bluepay_mfx_email').val();

                        if (!email || !email.includes('@')) {
                            alert('<?php echo esc_js(__('Please enter a valid email address.', 'woocommerce')); ?>');
                            return;
                        }

                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            method: 'POST',
                            data: {
                                action: 'bluepay_mfx_update_order_send_email',
                                email: email
                            },
                            success: function(response) {
                                if (response.success) {
                                    window.location.href = response.data.redirect_url;
                                } else {
                                    alert(response.data.message || '<?php echo esc_js(__('An error occurred. Please try again.', 'woocommerce')); ?>');
                                }
                            },
                            error: function() {
                                alert('<?php echo esc_js(__('An error occurred. Please try again.', 'woocommerce')); ?>');
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

add_action('wp_ajax_bluepay_mfx_update_order_send_email', 'bluepay_mfx_update_order_send_email');
add_action('wp_ajax_nopriv_bluepay_mfx_update_order_send_email', 'bluepay_mfx_update_order_send_email');

function bluepay_mfx_update_order_send_email() {
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
        error_log('[Bluepay MFX] Sanitized email: ' . $email);

        // Validate cart contents.
        if (!WC()->cart || WC()->cart->is_empty()) {
            error_log('[Bluepay MFX] Cart is empty. Session contents: ' . print_r(WC()->session->get_session_data(), true));
            wp_send_json_error(['message' => __('Your cart is empty. Please add items to proceed.', 'woocommerce')]);
        }

        // Create a new WooCommerce order.
        $order = wc_create_order();
        error_log('[Bluepay MFX] Order created. ID: ' . $order->get_id());

        // Add cart items to the order.
        foreach (WC()->cart->get_cart() as $cart_item) {
            $order->add_product($cart_item['data'], $cart_item['quantity']);
            error_log('[Bluepay MFX] Added product to order: ' . $cart_item['data']->get_name());
        }

        // Calculate order totals.
        $order->calculate_totals();
        error_log('[Bluepay MFX] Order totals calculated.');

        // Generate the payment link.
        $payment_link = site_url("/form-bluepay?order_id={$order->get_id()}");
        error_log('[Bluepay MFX] Payment link generated: ' . $payment_link);

        // Send payment link via email.
        $subject = __('Bluepay Payment Details', 'woocommerce');
        $message = sprintf(
            __('Please complete your payment using the following link: <a href="%s">%s</a>', 'woocommerce'),
            esc_url($payment_link),
            esc_html($payment_link)
        );
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        if (!wp_mail($email, $subject, $message, $headers)) {
            error_log('[Bluepay MFX] Failed to send email to: ' . $email);
            wp_send_json_error(['message' => __('Failed to send email. Please try again.', 'woocommerce')]);
        }

        error_log('[Bluepay MFX] Email sent successfully to: ' . $email);

        // Empty the cart to prevent duplicate orders.
        WC()->cart->empty_cart();
        error_log('[Bluepay MFX] Cart emptied.');

        // Redirect to the thank-you page.
        $thank_you_url = $order->get_checkout_order_received_url();
        wp_send_json_success(['redirect_url' => $thank_you_url]);

    } catch (Exception $e) {
        error_log('[Bluepay MFX] Exception occurred: ' . $e->getMessage());
        wp_send_json_error(['message' => __('An unexpected error occurred. Please try again.', 'woocommerce')]);
    }
}
