<?php 

// Conditionally enqueue assets only when the [bluepay_form] shortcode is used
function bluepay_enqueue_assets_conditionally() {
    if (is_singular() && has_shortcode(get_post()->post_content, 'bluepay_form')) {
        // Enqueue CSS
        wp_enqueue_style(
            'bluepay-form-style', 
            plugin_dir_url(__FILE__) . '../assets/css/form.css',
            array(), // No dependencies
            '1.0.0' // Version
        );

        // Enqueue JavaScript for form handling
        wp_enqueue_script(
            'bluepay-form-submit', 
            plugin_dir_url(__FILE__) . '../assets/js/cc-number.js',
            array('jquery'), // Dependencies (requires jQuery)
            '1.0.0', // Version
            true // Load in footer
        );


        // Enqueue JavaScript for dropdowns and date mask
        wp_enqueue_script(
            'bluepay-submit-request-handle', 
            plugin_dir_url(__FILE__) . '../assets/js/date-input-mask.js',
            array(), // No Dependencies
            '1.0.0', // Version
            true // Load in footer
        );

        // Enqueue subscription details script
        wp_enqueue_script(
            'mfx-subscription-details',
            plugins_url('/assets/js/subscription-details.js', dirname(__FILE__)),
            array('jquery'),
            '1.0.2.4',
            true
        );

        // Localize script to pass the AJAX URL to the JavaScript
        wp_localize_script(
            'bluepay-submit-request-handle', 
            'bluepayAjax', 
            array(
                'ajax_url' => admin_url('admin-ajax.php'), // WordPress AJAX endpoint
            )
        );

        // Localize subscription details script
        wp_localize_script('mfx-subscription-details', 'subscriptionDetailsAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('subscription_details_nonce')
        ));
    }
}
add_action('wp_enqueue_scripts', 'bluepay_enqueue_assets_conditionally');
