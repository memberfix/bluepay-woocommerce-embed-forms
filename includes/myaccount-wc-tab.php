<?php

// Add custom tab to WooCommerce My Account menu
add_filter('woocommerce_account_menu_items', 'add_memberships_tab', 40);
function add_memberships_tab($menu_items) {
    $menu_items['memberships'] = 'Memberships';
    return $menu_items;
}

// Register endpoint for the custom tab
add_action('init', 'memberships_add_endpoint');
function memberships_add_endpoint() {
    add_rewrite_endpoint('memberships', EP_PAGES);
    add_rewrite_endpoint('change-membership', EP_PAGES);
}

// Enqueue membership styles
add_action('wp_enqueue_scripts', 'enqueue_membership_styles');
function enqueue_membership_styles() {
    if (is_wc_endpoint_url('memberships')) {
        wp_enqueue_style(
            'membership-tabs',
            plugins_url('/assets/css/membership-tabs.css', dirname(__FILE__)),
            array(),
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/membership-tabs.css')
        );
    }
}

// Add content to the custom tab
add_action('woocommerce_account_memberships_endpoint', 'memberships_tab_content');
function memberships_tab_content() {
    // Check if we're in the change membership view
    if (isset($_GET['view']) && $_GET['view'] === 'change') {
        echo '<div class="woocommerce-memberships-change">';
        echo '<a href="' . wc_get_account_endpoint_url('memberships') . '" class="button">&laquo; Back to Current Membership</a>';
        echo '<h3>Change Membership</h3>';
        echo do_shortcode('[product_filter]');
        echo '</div>';
    } else {
        echo '<div class="woocommerce-memberships-current">';
        echo do_shortcode('[mfx_subscription_details]');
        echo '<p><a href="' . add_query_arg('view', 'change', wc_get_account_endpoint_url('memberships')) . '" class="button">Change Membership</a></p>';
        echo '</div>';
    }
}

