<?php

// Add custom tab to WooCommerce My Account menu
add_filter('woocommerce_account_menu_items', 'add_memberships_tab', 40);
function add_memberships_tab($menu_items) {
    $menu_items['mfx-membership'] = 'Memberships';
    return $menu_items;
}

// Register mfx-membership endpoint for the custom tab
add_action('init', 'memberships_add_endpoint');
function memberships_add_endpoint() {
    add_rewrite_endpoint('mfx-membership', EP_PAGES);
}


// Add content to the custom tab
add_action('woocommerce_account_mfx-membership_endpoint', 'memberships_tab_content');
function memberships_tab_content() {
    // Check if we're in the change membership view

        echo do_shortcode('[mfx_subscription_details]');
        ?>
        <div>
            <a href="<?php echo esc_url(get_option('renewal_form_page_url', '/change-my-membership')); ?>" class="elementor-button elementor-button-link elementor-size-sm" style="margin-top: 1rem;">Change Membership</a>
        </div>
        <?php


}


