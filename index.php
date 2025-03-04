<?php
/**
 * Plugin Name: BluePay Woocommerce Embed Forms
 * Plugin URI: https://memberfix.rocks
 * Description: Embed forms. Changing order status. Renewal form.
 * Version: 1.0.6.9
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
require_once plugin_dir_path( __FILE__ ) . 'includes/billing-company-handler.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/bluepay-form-render.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/bluepay-settings-page.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/bluepay-payment-gateway.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/bluepay-response-result.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/order-confirmation-page.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/bluepay-payment-link-shortcode.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/get_guest_invoice_button.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/product-filter-shortcode.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/myaccount-wc-tab.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/bluepay-order-update.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/renewal-form.php';

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

// Function to update Change My Membership page content
function mfx_update_change_membership_page() {
    $page = get_page_by_path('change-my-membership');
    
    if (!empty($page)) {
        // Check if the page content already contains the renewal form shortcode
        if (strpos($page->post_content, '[mfx_renewal_form]') === false) {
            // Add the renewal form shortcode to the page content
            $updated_content = $page->post_content;
            
            // If the page only has the product filter shortcode, add the renewal form after it
            if (trim($updated_content) === '[product_filter]') {
                $updated_content = "[product_filter]\n\n<h3>Renew Your Subscription</h3>\n[mfx_renewal_form]";
            } else {
                // Otherwise append it to the end
                $updated_content .= "\n\n<h3>Renew Your Subscription</h3>\n[mfx_renewal_form]";
            }
            
            // Update the page
            wp_update_post(array(
                'ID' => $page->ID,
                'post_content' => $updated_content
            ));
        }
    }
}

// Plugin activation hook
function mfx_bluepay_activate() {
    // Create Change Membership page if it doesn't exist
    $page = get_page_by_path('change-my-membership');
    
    if (empty($page)) {
        $page_data = array(
            'post_title'    => 'Change My Membership',
            'post_name'     => 'change-my-membership',
            'post_content'  => '[product_filter]

<h3>Renew Your Subscription</h3>
[mfx_renewal_form]',
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
        
        // Update the page content to include the renewal form shortcode
        mfx_update_change_membership_page();
    }
}

register_activation_hook( __FILE__, 'mfx_bluepay_activate' );

// Plugin deactivation hook
function mfx_bluepay_deactivate() {
    // Perform tasks on deactivation, if any
}

register_deactivation_hook( __FILE__, 'mfx_bluepay_deactivate' );

// Run updates when plugin is loaded (for existing installations)
add_action('plugins_loaded', 'mfx_bluepay_run_updates');

function mfx_bluepay_run_updates() {
    // Get current plugin version
    $current_version = get_option('mfx_bluepay_version', '0');
    
    // If this is a new installation or an update
    if (version_compare($current_version, '1.0.6.8', '<')) {
        // Update the Change Membership page with the renewal form shortcode
        mfx_update_change_membership_page();
        
        // Update the stored version number
        update_option('mfx_bluepay_version', '1.0.6.8');
    }
}
