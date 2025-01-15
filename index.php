<?php
/**
 * Plugin Name: BluePay Woocommerce Embed Forms
 * Plugin URI: https://memberfix.rocks
 * Description: Embed forms. Changing order status
 * Version: 1.0.6
 * Requires at least: 6.0
 * Requires PHP: 7.0
 * Author: Denys Melnychuk
 * Author URI: https://memberfix.rocks
 * License: GPL2
 * Text Domain: mfx-bluepay-form
 * Domain Path: 
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Include required files
require_once plugin_dir_path( __FILE__ ) . 'includes/enque-assets.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/bluepay-form-render.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/bluepay-settings-page.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/bluepay-payment-gateway.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/bluepay-response-result.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/order-confirmation-page.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/bluepay-payment-link-shortcode.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/get_guest_invoice_button.php';





// Plugin activation hook
function mfx_bluepay_activate() {
    // Perform tasks on activation, if any
}


register_activation_hook( __FILE__, 'mfx_bluepay_activate' );

// Plugin deactivation hook
function mfx_bluepay_deactivate() {
    // Perform tasks on deactivation, if any
}

register_deactivation_hook( __FILE__, 'mfx_bluepay_deactivate' );

