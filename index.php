<?php
/**
 * Plugin Name: BluePay Woocommerce Embed Forms
 * Plugin URI: https://memberfix.rocks
 * Description: Embed forms. Changing order status. Renewal form.
 * Version: 1.0.7.4
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
//require_once plugin_dir_path( __FILE__ ) . 'includes/product-filter-shortcode.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/subscription-update.php';
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
    // Create all required pages
    mfx_create_required_pages();
    
    // Force refresh permalink
    flush_rewrite_rules();
}

/**
 * Create all required pages for the plugin functionality
 */
function mfx_create_required_pages() {
    // Pages to create with their details
    $pages = array(
        'change-my-membership' => array(
            'title' => 'Change My Membership',
            'content' => '[product_filter]

<h3>Renew Your Subscription</h3>
[mfx_renewal_form]',
            'option_name' => 'renewal_form_page_url'
        ),
        'payment-form' => array(
            'title' => 'Payment Form',
            'content' => '<h2>Complete Your Payment</h2>
[bluepay_form]',
            'option_name' => 'bluepay_payment_form_url'
        ),
        'form-bluepay' => array(
            'title' => 'BluePay Payment Form',
            'content' => '<h2>Complete Your Payment</h2>
[bluepay_form]',
            'option_name' => 'bluepay_form_page_url'
        ),
        'payment-result' => array(
            'title' => 'Payment Result',
            'content' => '<h2>Payment Result</h2>
[bluepay_response_result]',
            'option_name' => 'bluepay_approved_url' // Also used for declined and error URLs
        ),
        'order-confirmation' => array(
            'title' => 'Order Confirmation',
            'content' => '<h2>Order Confirmation</h2>
[bluepay_gateway_order_confirmation]',
            'option_name' => 'bluepay_confirmed_order_page_url'
        )
    );
    
    // Create each page if it doesn't exist
    foreach ($pages as $slug => $page_data) {
        $existing_page = get_page_by_path($slug);
        
        if (empty($existing_page)) {
            // Create the page
            $new_page_data = array(
                'post_title'    => $page_data['title'],
                'post_name'     => $slug,
                'post_content'  => $page_data['content'],
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'post_author'   => 1
            );
            
            $page_id = wp_insert_post($new_page_data);
            
            if (!is_wp_error($page_id)) {
                // Save the URL in plugin settings
                $page_url = get_permalink($page_id);
                update_option($page_data['option_name'], $page_url);
                
                // For payment result page, set all result URLs to the same page
                if ($slug === 'payment-result') {
                    update_option('bluepay_declined_url', $page_url);
                    update_option('bluepay_error_url', $page_url);
                }
            }
        } else {
            // Update existing page URL in settings
            $page_url = get_permalink($existing_page->ID);
            update_option($page_data['option_name'], $page_url);
            
            // For payment result page, set all result URLs to the same page
            if ($slug === 'payment-result') {
                update_option('bluepay_declined_url', $page_url);
                update_option('bluepay_error_url', $page_url);
            }
            
            // Check if the page content needs to be updated
            if ($slug === 'change-my-membership') {
                mfx_update_change_membership_page();
            }
        }
    }
}

register_activation_hook( __FILE__, 'mfx_bluepay_activate' );

// Plugin deactivation hook
function mfx_bluepay_deactivate() {
    // Perform tasks on deactivation, if any
}

register_deactivation_hook( __FILE__, 'mfx_bluepay_deactivate' );

// Run updates when plugin is loaded (for existing installations)
add_action('init', 'mfx_bluepay_run_updates');

function mfx_bluepay_run_updates() {
    // Get current plugin version
    $current_version = get_option('mfx_bluepay_version', '0');
    
    // If this is a new installation or an update
    if (version_compare($current_version, '1.0.6.8', '<')) {
        // Update the Change Membership page with the renewal form shortcode
        add_action('init', 'mfx_update_change_membership_page', 20);
        
        // Update the stored version number
        update_option('mfx_bluepay_version', '1.0.6.8');
    }
    
    // If this is an update to version 1.0.7.0 or newer
    if (version_compare($current_version, '1.0.7.0', '<')) {
        // Create all required pages for existing installations
        add_action('init', 'mfx_create_required_pages', 20);
        
        // Update the stored version number
        update_option('mfx_bluepay_version', '1.0.7.0');
    }
    
    // If this is an update to version 1.0.7.3 or newer
    if (version_compare($current_version, '1.0.7.3', '<')) {
        // Ensure the form-bluepay page exists
        $form_bluepay_page = get_page_by_path('form-bluepay');
        
        if (empty($form_bluepay_page)) {
            // Create the form-bluepay page
            $page_data = array(
                'post_title'    => 'BluePay Payment Form',
                'post_name'     => 'form-bluepay',
                'post_content'  => '<h2>Complete Your Payment</h2>\n[bluepay_form]',
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'post_author'   => 1
            );
            
            $page_id = wp_insert_post($page_data);
            
            if (!is_wp_error($page_id)) {
                // Save the URL in plugin settings
                update_option('bluepay_form_page_url', get_permalink($page_id));
            }
        } else {
            // Update the URL in settings
            update_option('bluepay_form_page_url', get_permalink($form_bluepay_page->ID));
        }
        
        // Update the stored version number
        update_option('mfx_bluepay_version', '1.0.7.3');
    }
}
