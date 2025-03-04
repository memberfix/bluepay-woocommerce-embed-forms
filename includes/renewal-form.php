<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Register the shortcode and AJAX actions
add_shortcode('mfx_renewal_form', 'render_renewal_form');
add_action('wp_enqueue_scripts', 'mfx_renewal_form_scripts');
add_action('wp_ajax_get_subscription_filters', 'get_subscription_filters_ajax');
add_action('wp_ajax_get_matching_variations', 'get_matching_variations_ajax');
add_action('wp_ajax_process_selected_variations', 'process_selected_variations_ajax');
add_action('wp_ajax_update_subscription_with_variations', 'update_subscription_with_variations_ajax');


// Filter to add a "Change My Membership" action to default WooCommerce subscription actions
add_filter('wcs_view_subscription_actions', function ($actions, $subscription) {
    $subscription_id = $subscription->get_id(); // Get subscription ID

    $actions['change_membership'] = array(
        'url'  => site_url("/change-my-membership?id={$subscription_id}"), // Dynamic URL
        'name' => 'Change My Membership',
    );

    return $actions;
}, 10, 2);



/**
 * Enqueue necessary scripts for the renewal form
 */
function mfx_renewal_form_scripts() {
    wp_enqueue_script(
        'mfx-renewal-form',
        plugins_url('../assets/js/renewal-form.js', __FILE__),
        array('jquery'),
        '1.0.0',
        true
    );
    
    wp_localize_script('mfx-renewal-form', 'mfx_renewal_form_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mfx_renewal_form_nonce')
    ));
    
    wp_enqueue_style('mfx-renewal-form-style', plugins_url('../assets/css/renewal-form.css', __FILE__));
}

/**
 * Render the renewal form shortcode
 * 
 * @return string The HTML output for the renewal form
 */
function render_renewal_form() {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        return '<p>Please log in to view your subscriptions.</p>';
    }
    
    // Check if WooCommerce Subscriptions is active
    if (!function_exists('wcs_get_users_subscriptions')) {
        return '<p>WooCommerce Subscriptions is required for this feature.</p>';
    }
    
    // Get subscription ID from URL parameter
    $subscription_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    
    // If no subscription ID provided, show error message
    if (!$subscription_id) {
        return '<p>No subscription ID provided. Please use the "Change My Membership" button from your account.</p>';
    }
    
    // Get user's subscriptions
    $user_subscriptions = wcs_get_users_subscriptions(get_current_user_id());
    
    // Check if user has any subscriptions
    if (empty($user_subscriptions)) {
        return '<p>You do not have any active subscriptions.</p>';
    }
    
    // Verify that the subscription belongs to the current user
    $subscription_exists = false;
    foreach ($user_subscriptions as $user_subscription) {
        if ($user_subscription->get_id() == $subscription_id) {
            $subscription_exists = true;
            break;
        }
    }
    
    if (!$subscription_exists) {
        return '<p>You do not have permission to access this subscription.</p>';
    }
    
    // Get the subscription object
    $subscription = wcs_get_subscription($subscription_id);
    if (!$subscription) {
        return '<p>Subscription not found.</p>';
    }
    
    ob_start();
    ?>
    <div class="mfx-renewal-form">
        <div class="back-button-container">
            <a href="<?php echo esc_url(wc_get_endpoint_url('view-subscription', $subscription_id, wc_get_page_permalink('myaccount'))); ?>" class="button back-button">&larr; Back to Subscription</a>
        </div>
        
        <div class="subscription-info">
            <h3>Membership Information</h3>
            <p>Subscription #<?php echo esc_html($subscription_id); ?></p>
            <p>Status: <?php echo esc_html(wcs_get_subscription_status_name($subscription->get_status())); ?></p>
        </div>
        
        <!-- Store subscription ID in hidden div for JavaScript to use -->
        <div id="selected_subscription_id"><?php echo esc_html($subscription_id); ?></div>
        
        <!-- Filters will be loaded here via AJAX -->
        <div id="membership-filters" style="margin-top: 20px;">
            <div class="filter-loading">Loading filters...</div>
            <div class="filter-container"></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Determine which group a subscription belongs to based on its products
 * 
 * @param int $subscription_id The subscription ID to check
 * @return string 'A' for Group A, 'B' for Group B, or empty if not determined
 */
function determine_subscription_group($subscription_id) {
    if (!function_exists('wcs_get_subscription')) {
        return '';
    }
    
    $subscription = wcs_get_subscription($subscription_id);
    if (!$subscription) {
        return '';
    }
    
    // Get settings for product IDs
    $renewal_settings = get_option('mfx_bluepay_renewal_settings', array(
        'membership_product_id' => 12350,
        'premium_service_product_id' => 12390,
        'local_chapter_product_id' => 12428,
        'supplier_product_id' => 12386
    ));
    
    // Group A product IDs
    $group_a_product_ids = array(
        $renewal_settings['membership_product_id'],
        $renewal_settings['premium_service_product_id'],
        $renewal_settings['local_chapter_product_id']
    );
    
    // Group B product ID
    $group_b_product_id = $renewal_settings['supplier_product_id'];
    
    // Check if subscription has any items
    $items = $subscription->get_items();
    if (empty($items)) {
        return '';
    }
    
    // Check each item to determine the group
    foreach ($items as $item) {
        $product_id = $item->get_product_id(); // This gets the parent product ID for variations
        $variation_id = $item->get_variation_id();
        
        // If product is in Group A
        if (in_array($product_id, $group_a_product_ids)) {
            return 'A';
        }
        
        // If product is in Group B
        if ($product_id == $group_b_product_id) {
            return 'B';
        }
    }
    
    return ''; // Group not determined
}

/**
 * Get filter options for a specific attribute from product variations
 * 
 * @param int $parent_product_id The parent product ID
 * @param string $attribute_name The attribute name to filter by
 * @return array Array of attribute values
 */
function get_attribute_filter_options($parent_product_id, $attribute_name) {
    global $wpdb;
    
    // Get attribute values for the specified parent product
    $attribute_values = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT pm.meta_value 
        FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = %s 
        AND pm.meta_value != ''
        AND p.post_parent = %d
        AND EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} pm2
            WHERE pm2.post_id = p.ID
            AND pm2.meta_key = 'attribute_renewal'
            AND pm2.meta_value = 'Yes'
        )
        ORDER BY pm.meta_value ASC",
        'attribute_' . $attribute_name,
        $parent_product_id
    ));
    
    return $attribute_values;
}

/**
 * Get filter options for a group
 * 
 * @param string $group The group (A or B)
 * @return array Array of filter options
 */
function get_group_filter_options($group) {
    // Get settings for product IDs
    $renewal_settings = get_option('mfx_bluepay_renewal_settings', array(
        'membership_product_id' => 12350,
        'premium_service_product_id' => 12390,
        'local_chapter_product_id' => 12428,
        'supplier_product_id' => 12386,
        'revenue_source_product_id' => 12350
    ));
    
    $filters = array();
    
    if ($group == 'A') {
        // Group A has filters for annual-revenue and plan
        $filters['annual-revenue'] = array(
            'label' => 'Annual Revenue',
            'options' => get_attribute_filter_options($renewal_settings['membership_product_id'], 'annual-revenue')
        );
        
        // Get plan values that exist in all three products (similar to product-filter-shortcode.php)
        global $wpdb;
        $plan_values = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT pm1.meta_value
            FROM {$wpdb->postmeta} pm1
            WHERE pm1.meta_key = 'attribute_plan'
            AND pm1.meta_value != ''
            AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm2
                JOIN {$wpdb->posts} p2 ON p2.ID = pm2.post_id
                WHERE pm2.meta_key = 'attribute_plan'
                AND pm2.meta_value = pm1.meta_value
                AND p2.post_parent = %d
                AND EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pmr2
                    WHERE pmr2.post_id = p2.ID
                    AND pmr2.meta_key = 'attribute_renewal'
                    AND pmr2.meta_value = 'Yes'
                )
            )
            AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm3
                JOIN {$wpdb->posts} p3 ON p3.ID = pm3.post_id
                WHERE pm3.meta_key = 'attribute_plan'
                AND pm3.meta_value = pm1.meta_value
                AND p3.post_parent = %d
                AND EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pmr3
                    WHERE pmr3.post_id = p3.ID
                    AND pmr3.meta_key = 'attribute_renewal'
                    AND pmr3.meta_value = 'Yes'
                )
            )
            AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm4
                JOIN {$wpdb->posts} p4 ON p4.ID = pm4.post_id
                WHERE pm4.meta_key = 'attribute_plan'
                AND pm4.meta_value = pm1.meta_value
                AND p4.post_parent = %d
                AND EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pmr4
                    WHERE pmr4.post_id = p4.ID
                    AND pmr4.meta_key = 'attribute_renewal'
                    AND pmr4.meta_value = 'Yes'
                )
            )",
            $renewal_settings['membership_product_id'],
            $renewal_settings['premium_service_product_id'],
            $renewal_settings['local_chapter_product_id']
        ));
        
        $filters['plan'] = array(
            'label' => 'Plan',
            'options' => $plan_values
        );
    } else if ($group == 'B') {
        // Group B only has filter for annual-revenue
        $filters['annual-revenue'] = array(
            'label' => 'Annual Revenue',
            'options' => get_attribute_filter_options($renewal_settings['supplier_product_id'], 'annual-revenue')
        );
    }
    
    return $filters;
}

/**
 * AJAX handler for getting subscription filters
 */
function get_subscription_filters_ajax() {
    // Check nonce for security
    check_ajax_referer('mfx_renewal_form_nonce', 'nonce');
    
    $subscription_id = isset($_POST['subscription_id']) ? intval($_POST['subscription_id']) : 0;
    
    if (!$subscription_id) {
        wp_send_json_error(array('message' => 'Invalid subscription ID'));
        return;
    }
    
    // Determine which group the subscription belongs to
    $group = determine_subscription_group($subscription_id);
    
    if (empty($group)) {
        wp_send_json_error(array('message' => 'Could not determine subscription group'));
        return;
    }
    
    // Get filter options for the group
    $filters = get_group_filter_options($group);
    
    // Return the filters
    wp_send_json_success(array(
        'group' => $group,
        'filters' => $filters
    ));
}

/**
 * Get matching product variations based on selected filters
 * 
 * @param string $group The group (A or B)
 * @param array $filters Selected filter values
 * @return array Array of matching product variations
 */
function get_matching_product_variations($group, $filters) {
    // Get settings for product IDs
    $renewal_settings = get_option('mfx_bluepay_renewal_settings', array(
        'membership_product_id' => 12350,
        'premium_service_product_id' => 12390,
        'local_chapter_product_id' => 12428,
        'supplier_product_id' => 12386
    ));
    
    global $wpdb;
    $matching_variations = array();
    
    // Get filter values
    $annual_revenue = isset($filters['annual-revenue']) ? $filters['annual-revenue'] : '';
    $plan = isset($filters['plan']) ? $filters['plan'] : '';
    
    if ($group == 'A') {
        // Type A: membership_product_id - filter by annual-revenue and plan
        $membership_id = $renewal_settings['membership_product_id'];
        $query = "SELECT p.ID, p.post_title, p.post_parent
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'product_variation'
            AND p.post_status = 'publish'
            AND p.post_parent = %d";
        
        $params = array($membership_id);
        
        // Filter by annual-revenue
        if (!empty($annual_revenue)) {
            $query .= " AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm
                WHERE pm.post_id = p.ID
                AND pm.meta_key = 'attribute_annual-revenue'
                AND pm.meta_value = %s
            )";
            $params[] = $annual_revenue;
        }
        
        // Filter by plan
        if (!empty($plan)) {
            $query .= " AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm
                WHERE pm.post_id = p.ID
                AND pm.meta_key = 'attribute_plan'
                AND pm.meta_value = %s
            )";
            $params[] = $plan;
        }
        
        // Filter by renewal = Yes
        $query .= " AND EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} pm
            WHERE pm.post_id = p.ID
            AND pm.meta_key = 'attribute_renewal'
            AND pm.meta_value = 'Yes'
        )";
        
        // Execute the query
        $results = $wpdb->get_results($wpdb->prepare($query, $params));
        
        if (!empty($results)) {
            foreach ($results as $result) {
                $variation = wc_get_product($result->ID);
                if ($variation) {
                    $parent_product = wc_get_product($result->post_parent);
                    $parent_name = $parent_product ? $parent_product->get_name() : 'Unknown';
                    
                    $attributes = $variation->get_attributes();
                    $attribute_classes = array();
                    
                    // Add class for each attribute
                    foreach ($attributes as $key => $value) {
                        $clean_key = str_replace('pa_', '', $key);
                        $clean_value = sanitize_html_class($value);
                        $attribute_classes[] = "attr-{$clean_key}-{$clean_value}";
                    }
                    
                    // Add class for product type
                    $attribute_classes[] = "attr-type-a";
                    
                    $matching_variations[] = array(
                        'variation_id' => $result->ID,
                        'parent_id' => $result->post_parent,
                        'parent_name' => $parent_name,
                        'name' => $variation->get_name(),
                        'price' => $variation->get_price(),
                        // 'price' => wc_price($variation->get_price()),
                        'price_raw' => $variation->get_price(),
                        'attributes' => $attributes,
                        'attribute_classes' => $attribute_classes,
                        'add_to_cart_url' => $variation->add_to_cart_url(),
                        'type' => 'Type A'
                        //'type' => 'Staffing membership'
                    );
                }
            }
        }
        
        // Type B: premium_service_product_id - filter by annual-revenue and plan
        $premium_service_id = $renewal_settings['premium_service_product_id'];
        $query = "SELECT p.ID, p.post_title, p.post_parent
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'product_variation'
            AND p.post_status = 'publish'
            AND p.post_parent = %d";
        
        $params = array($premium_service_id);
        
        // Filter by annual-revenue
        if (!empty($annual_revenue)) {
            $query .= " AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm
                WHERE pm.post_id = p.ID
                AND pm.meta_key = 'attribute_annual-revenue'
                AND pm.meta_value = %s
            )";
            $params[] = $annual_revenue;
        }
        
        // Filter by plan
        if (!empty($plan)) {
            $query .= " AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm
                WHERE pm.post_id = p.ID
                AND pm.meta_key = 'attribute_plan'
                AND pm.meta_value = %s
            )";
            $params[] = $plan;
        }
        
        // Filter by renewal = Yes
        $query .= " AND EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} pm
            WHERE pm.post_id = p.ID
            AND pm.meta_key = 'attribute_renewal'
            AND pm.meta_value = 'Yes'
        )";
        
        // Execute the query
        $results = $wpdb->get_results($wpdb->prepare($query, $params));
        
        if (!empty($results)) {
            foreach ($results as $result) {
                $variation = wc_get_product($result->ID);
                if ($variation) {
                    $parent_product = wc_get_product($result->post_parent);
                    $parent_name = $parent_product ? $parent_product->get_name() : 'Unknown';
                    
                    $attributes = $variation->get_attributes();
                    $attribute_classes = array();
                    
                    // Add class for each attribute
                    foreach ($attributes as $key => $value) {
                        $clean_key = str_replace('pa_', '', $key);
                        $clean_value = sanitize_html_class($value);
                        $attribute_classes[] = "attr-{$clean_key}-{$clean_value}";
                    }
                    
                    // Add class for product type
                    $attribute_classes[] = "attr-type-b";
                    
                    $matching_variations[] = array(
                        'variation_id' => $result->ID,
                        'parent_id' => $result->post_parent,
                        'parent_name' => $parent_name,
                        'name' => $variation->get_name(),
                        'price' => $variation->get_price(),
                        // 'price' => wc_price($variation->get_price()),
                        'price_raw' => $variation->get_price(),
                        'attributes' => $attributes,
                        'attribute_classes' => $attribute_classes,
                        'add_to_cart_url' => $variation->add_to_cart_url(),
                        'type' => 'Type B'
                        //'type' => 'Premium service'
                    );
                }
            }
        }
        
        // Type C: local_chapter_product_id - ignore annual-revenue, filter by plan only
        $local_chapter_id = $renewal_settings['local_chapter_product_id'];
        $query = "SELECT p.ID, p.post_title, p.post_parent
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'product_variation'
            AND p.post_status = 'publish'
            AND p.post_parent = %d";
        
        $params = array($local_chapter_id);
        
        // Filter by plan only, ignore annual-revenue
        if (!empty($plan)) {
            $query .= " AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm
                WHERE pm.post_id = p.ID
                AND pm.meta_key = 'attribute_plan'
                AND pm.meta_value = %s
            )";
            $params[] = $plan;
        }
        
        // Filter by renewal = Yes
        $query .= " AND EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} pm
            WHERE pm.post_id = p.ID
            AND pm.meta_key = 'attribute_renewal'
            AND pm.meta_value = 'Yes'
        )";
        
        // Execute the query
        $results = $wpdb->get_results($wpdb->prepare($query, $params));
        
        if (!empty($results)) {
            foreach ($results as $result) {
                $variation = wc_get_product($result->ID);
                if ($variation) {
                    $parent_product = wc_get_product($result->post_parent);
                    $parent_name = $parent_product ? $parent_product->get_name() : 'Unknown';
                    
                    $attributes = $variation->get_attributes();
                    $attribute_classes = array();
                    
                    // Add class for each attribute
                    foreach ($attributes as $key => $value) {
                        $clean_key = str_replace('pa_', '', $key);
                        $clean_value = sanitize_html_class($value);
                        $attribute_classes[] = "attr-{$clean_key}-{$clean_value}";
                    }
                    
                    // Add class for product type
                    $attribute_classes[] = "attr-type-c";
                    
                    $matching_variations[] = array(
                        'variation_id' => $result->ID,
                        'parent_id' => $result->post_parent,
                        'parent_name' => $parent_name,
                        'name' => $variation->get_name(),
                        'price' => $variation->get_price(),
                        // 'price' => wc_price($variation->get_price()),
                        'price_raw' => $variation->get_price(),
                        'attributes' => $attributes,
                        'attribute_classes' => $attribute_classes,
                        'add_to_cart_url' => $variation->add_to_cart_url(),
                        'type' => 'Type C'
                        //'type' => 'Local Chapter'
                    );
                }
            }
        }
    } else if ($group == 'B') {
        // Type D: supplier_product_id - filter by annual-revenue, ignore plan
        $supplier_id = $renewal_settings['supplier_product_id'];
        $query = "SELECT p.ID, p.post_title, p.post_parent
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'product_variation'
            AND p.post_status = 'publish'
            AND p.post_parent = %d";
        
        $params = array($supplier_id);
        
        // Filter by annual-revenue
        if (!empty($annual_revenue)) {
            $query .= " AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm
                WHERE pm.post_id = p.ID
                AND pm.meta_key = 'attribute_annual-revenue'
                AND pm.meta_value = %s
            )";
            $params[] = $annual_revenue;
        }
        
        // Filter by renewal = Yes
        $query .= " AND EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} pm
            WHERE pm.post_id = p.ID
            AND pm.meta_key = 'attribute_renewal'
            AND pm.meta_value = 'Yes'
        )";
        
        // Execute the query
        $results = $wpdb->get_results($wpdb->prepare($query, $params));
        
        if (!empty($results)) {
            foreach ($results as $result) {
                $variation = wc_get_product($result->ID);
                if ($variation) {
                    $parent_product = wc_get_product($result->post_parent);
                    $parent_name = $parent_product ? $parent_product->get_name() : 'Unknown';
                    
                    $attributes = $variation->get_attributes();
                    $attribute_classes = array();
                    
                    // Add class for each attribute
                    foreach ($attributes as $key => $value) {
                        $clean_key = str_replace('pa_', '', $key);
                        $clean_value = sanitize_html_class($value);
                        $attribute_classes[] = "attr-{$clean_key}-{$clean_value}";
                    }
                    
                    // Add class for product type
                    $attribute_classes[] = "attr-type-d";
                    
                    $matching_variations[] = array(
                        'variation_id' => $result->ID,
                        'parent_id' => $result->post_parent,
                        'parent_name' => $parent_name,
                        'name' => $variation->get_name(),
                        'price' => $variation->get_price(),
                        // 'price' => wc_price($variation->get_price()),
                        'price_raw' => $variation->get_price(),
                        'attributes' => $attributes,
                        'attribute_classes' => $attribute_classes,
                        'add_to_cart_url' => $variation->add_to_cart_url(),
                        'type' => 'Type D'
                    );
                }
            }
        }
    }
    
    return $matching_variations;
}

/**
 * AJAX handler for getting matching product variations
 */
function get_matching_variations_ajax() {
    // Check nonce for security
    check_ajax_referer('mfx_renewal_form_nonce', 'nonce');
    
    $group = isset($_POST['group']) ? sanitize_text_field($_POST['group']) : '';
    $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
    
    if (empty($group) || empty($filters)) {
        wp_send_json_error(array('message' => 'Invalid parameters'));
        return;
    }
    
    // Sanitize filters
    $sanitized_filters = array();
    foreach ($filters as $key => $value) {
        $sanitized_filters[sanitize_text_field($key)] = sanitize_text_field($value);
    }
    
    // Get matching product variations
    $variations = get_matching_product_variations($group, $sanitized_filters);
    
    // Return the variations
    wp_send_json_success(array(
        'variations' => $variations
    ));
}

/**
 * AJAX handler for processing selected variations
 */
function process_selected_variations_ajax() {
    // Check nonce for security
    check_ajax_referer('mfx_renewal_form_nonce', 'nonce');
    
    // Get selected variations
    $variations = isset($_POST['variations']) ? $_POST['variations'] : array();
    
    if (empty($variations)) {
        wp_send_json_error(array('message' => 'No variations selected.'));
        return;
    }
    
    // Sanitize variation IDs
    $variation_ids = array_map('intval', $variations);
    
    // Add each variation to cart
    $added_to_cart = array();
    $errors = array();
    
    foreach ($variation_ids as $variation_id) {
        $variation = wc_get_product($variation_id);
        
        if (!$variation || !$variation->is_purchasable()) {
            $errors[] = sprintf('Variation #%d is not available for purchase.', $variation_id);
            continue;
        }
        
        // Add to cart
        $added = WC()->cart->add_to_cart($variation->get_parent_id(), 1, $variation_id);
        
        if ($added) {
            $added_to_cart[] = $variation_id;
        } else {
            $errors[] = sprintf('Could not add variation #%d to cart.', $variation_id);
        }
    }
    
    // Send response
    if (!empty($added_to_cart)) {
        wp_send_json_success(array(
            'message' => sprintf('%d product(s) added to cart.', count($added_to_cart)),
            'added_to_cart' => $added_to_cart,
            'errors' => $errors,
            'redirect_url' => wc_get_cart_url()
        ));
    } else {
        wp_send_json_error(array(
            'message' => 'Could not add products to cart.',
            'errors' => $errors
        ));
    }
}

/**
 * AJAX handler for updating a subscription with selected variations
 */
function update_subscription_with_variations_ajax() {
    // Check nonce for security
    check_ajax_referer('mfx_renewal_form_nonce', 'nonce');
    
    // Get selected subscription and variations
    $subscription_id = isset($_POST['subscription_id']) ? intval($_POST['subscription_id']) : 0;
    $variations = isset($_POST['variations']) ? $_POST['variations'] : array();
    
    if (empty($subscription_id) || empty($variations)) {
        wp_send_json_error(array('message' => 'No subscription or variations selected.'));
        return;
    }
    
    // Sanitize variation IDs
    $variation_ids = array_map('intval', $variations);
    
    // Get renewal settings for product IDs
    $renewal_settings = get_option('mfx_bluepay_renewal_settings', array(
        'membership_product_id' => 12350,
        'premium_service_product_id' => 12390,
        'local_chapter_product_id' => 12428,
        'supplier_product_id' => 12386
    ));
    
    // Extract plan from selected variations
    $selected_plan = '';
    $has_type_d_product = false;
    
    foreach ($variation_ids as $variation_id) {
        $variation = wc_get_product($variation_id);
        if (!$variation) {
            continue;
        }
        
        $parent_id = $variation->get_parent_id();
        
        // Check if this is Type A (membership) product
        if ($parent_id == $renewal_settings['membership_product_id']) {
            // Get the plan attribute
            $plan = $variation->get_attribute('plan');
            if (!empty($plan)) {
                error_log("Found plan attribute in Type A variation #{$variation_id}: {$plan}");
                $selected_plan = $plan;
                break; // Use the first plan we find from Type A
            }
        }
        
        // Check if this is Type D (supplier) product
        if ($parent_id == $renewal_settings['supplier_product_id']) {
            $has_type_d_product = true;
            // Type D doesn't have plan attribute, but we'll note that we found one
            error_log("Found Type D product variation #{$variation_id}");
        }
    }
    
    // If no plan was found from Type A but we have Type D products, use 'annual' as default
    if (empty($selected_plan) && $has_type_d_product) {
        $selected_plan = 'annual';
        error_log("Using default 'annual' plan for Type D product");
    }
    
    // Call the subscription update function
    // This will pass the request to the subscription-update.php handler
    $_POST['variations'] = $variation_ids;
    
    // Add the plan if found
    if (!empty($selected_plan)) {
        $_POST['selected_plan'] = $selected_plan;
        error_log("Setting selected_plan to: {$selected_plan}");
    }
    
    // Include the subscription update file if not already included
    if (!function_exists('mfx_process_subscription_update')) {
        require_once plugin_dir_path(__FILE__) . 'subscription-update.php';
    }
    
    // Call the function directly
    mfx_process_subscription_update();
}
