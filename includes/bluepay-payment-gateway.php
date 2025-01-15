<?php

require_once plugin_dir_path( __FILE__ ) . 'bluepay-shareable-link-email.php';

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


// Initialize the gateway
add_action('plugins_loaded', 'bluepay_mfx_payment_gateway_init', 11);

function bluepay_mfx_payment_gateway_init() {
    class WC_Gateway_Bluepay_MFX extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'bluepay_mfx';
            $this->method_title = __('Shareable Payment Link', 'woocommerce');
            $this->method_description = __('A custom payment gateway for Bluepay MFX.', 'woocommerce');
            $this->has_fields = true;

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user settings.
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');

            // Define supported features.
            $this->supports = [
                'products',
                'subscriptions',
                'subscription_suspension',
                'subscription_reactivation',
                'subscription_cancellation',
                'subscription_amount_changes',
                'subscription_date_changes',
                'subscription_payment_method_change_admin',
                'subscription_payment_method_change_customer',
                'multiple_subscriptions',
            ];

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
                    'default' => __('Shareable Payment Link', 'woocommerce'),
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
            echo '<p><input type="email" id="bluepay_mfx_share_to_email" placeholder="Your email address" required></p>';
            echo '<input type="hidden" id="bluepay_mfx_hidden_email" name="bluepay_mfx_hidden_email" value="">';
            echo '<p><button id="bluepay_mfx_share_button" class="elementor-button elementor-button-link elementor-size-sm" style="margin-top: 1rem;">' . __('Share Payment Details', 'woocommerce') . '</button></p>';
            ?>
            <script>
                jQuery(document).ready(function ($) {
                    $('#bluepay_mfx_share_button').on('click', function (e) {
                        e.preventDefault();
                        const email = $('#bluepay_mfx_share_to_email').val();
                        if (email) {
                            $('#bluepay_mfx_hidden_email').val(email);
                        }
                        $(this).prop('disabled', true).text('Processing...');
                        
                        $('#place_order').click(); // Trigger WooCommerce checkout
                    });
                });
            </script>
            <?php
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);

            if (!$order) {
                return ['result' => 'failure', 'message' => __('Invalid order ID.', 'woocommerce')];
            }

            // Retrieve the email from posted data
            $email = isset($_POST['bluepay_mfx_hidden_email']) ? sanitize_email($_POST['bluepay_mfx_hidden_email']) : '';

            if (empty($email)) {
                wc_add_notice(__('Please provide a valid email address.', 'woocommerce'), 'error');
                return ['result' => 'failure'];
            }

            // Update the order status
            $order->update_status('pending', __('Awaiting Bluepay payment.', 'woocommerce'));

            // Generate the payment link
            $payment_link = $this->generate_payment_link($order, $email);

            if (!$payment_link) {
                return ['result' => 'failure', 'message' => __('Failed to generate payment link.', 'woocommerce')];
            }

            // Add the payment link as an order note
            $order->add_order_note(sprintf(__('Payment link generated: %s', 'woocommerce'), $payment_link));

            $billing_email = $order->get_billing_email(); // Get the billing email
            bluepay_mfx_send_shareble_link($order_id, $email, $billing_email);

            // Save the order
            $order->save();

            return [
                'result' => 'success',
                'redirect' => $payment_link,
            ];
        }

        private function generate_payment_link($order, $email) {
            $base_url = get_option('bluepay_confirmed_order_page_url', '');

            if (!$base_url) {
                return false;
            }

            return esc_url_raw(add_query_arg([
                'order_id' => $order->get_id(),
                'key' => $order->get_order_key(),
                'sent_to' => $email,
            ], $base_url));
        }
    }

    // Add the gateway to WooCommerce
    function add_bluepay_mfx_gateway($methods) {
        $methods[] = 'WC_Gateway_Bluepay_MFX';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_bluepay_mfx_gateway');
}
