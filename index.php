<?php
/**
 * Plugin Name: BluePay Woocommerce Embed Forms
 * Plugin URI: https://memberfix.rocks
 * Description: Embed forms. Changing order status. Renewal form.
 * Version: 1.0.4.7
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
require_once plugin_dir_path( __FILE__ ) . 'includes/product-filter-shortcode.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/myaccount-wc-tab.php';

// Enqueue subscription update script
function mfx_enqueue_subscription_update_scripts() {
    wp_enqueue_script(
        'mfx-subscription-update',
        plugins_url('assets/js/subscription-update.js', __FILE__),
        array('jquery'),
        '1.0.0',
        true
    );
    
    wp_localize_script('mfx-subscription-update', 'ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('subscription_update_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'mfx_enqueue_subscription_update_scripts');

// Plugin activation hook
function mfx_bluepay_activate() {
    // Create Change Membership page if it doesn't exist
    $page = get_page_by_path('change-my-membership');
    
    if (empty($page)) {
        $page_data = array(
            'post_title'    => 'Change My Membership',
            'post_name'     => 'change-my-membership',
            'post_content'  => '[product_filter]',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_author'   => 1
        );
        
        $page_id = wp_insert_post($page_data);
        
        if (!is_wp_error($page_id)) {
            // Force refresh permalink
            $page_url = get_permalink($page_id);
            flush_rewrite_rules();
            
            // Save the URL in plugin settings
            update_option('renewal_form_page_url', $page_url);
        }
    } else {
        // Update existing page URL in settings
        update_option('renewal_form_page_url', get_permalink($page->ID));
    }
}

register_activation_hook( __FILE__, 'mfx_bluepay_activate' );

// Plugin deactivation hook
function mfx_bluepay_deactivate() {
    // Perform tasks on deactivation, if any
}

register_deactivation_hook( __FILE__, 'mfx_bluepay_deactivate' );
