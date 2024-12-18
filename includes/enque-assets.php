<?php 


function bluepay_enqueue_assets() {
    // Enqueue CSS
    wp_enqueue_style(
        'bluepay-form-style', 
        plugin_dir_url(__FILE__) . 'assets/css/form.css',
        array(), // Dependencies
        '1.0.0' // Version
    );

    // Enqueue JS
    wp_enqueue_script(
        'bluepay-form-script', 
        plugin_dir_url(__FILE__) . 'assets/js/cc-number.js',
        array('jquery'), // Dependencies (requires jQuery)
        '1.0.0', // Version
        true // Load script in the footer
    );
}

// Conditionally enqueue assets only when the shortcode is used
function bluepay_enqueue_assets_conditionally($atts) {
    // Only enqueue when the shortcode [bluepay_form] is detected
    if (is_singular() && has_shortcode(get_post()->post_content, 'bluepay_form')) {
        bluepay_enqueue_assets();
    }
}
add_action('wp_enqueue_scripts', 'bluepay_enqueue_assets_conditionally');
