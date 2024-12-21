<?php
/**
 * Plugin Name: BluePay Woocommerce Embed Forms
 * Plugin URI: https://memberfix.rocks
 * Description: Embed forms. Changing order status
 * Version: 1.1.0
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
require_once plugin_dir_path( __FILE__ ) . 'includes/form-render.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/bluepay-settings-page.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/bluepay-payment-gateway.php';

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

// Enqueue CSS and JS
// add_action( 'wp_enqueue_scripts', 'mfx_enqueue_bluepay_assets' );

// Register shortcode for BluePay Form
// function mfx_register_bluepay_form_shortcode() {
//     add_shortcode( 'bluepay_form', 'mfx_render_bluepay_form' );
// }

// add_action( 'init', 'mfx_register_bluepay_form_shortcode' );

// Add admin menu for plugin settings
//add_action( 'admin_menu', 'mfx_add_bluepay_settings_page' );

