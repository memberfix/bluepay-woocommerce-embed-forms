<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'subscription-update.php';

// Register shortcode and necessary actions
add_shortcode('product_filter', 'render_product_filter');
add_action('wp_enqueue_scripts', 'product_filter_scripts');
add_action('wp_ajax_filter_products', 'handle_product_filter');
add_action('wp_ajax_nopriv_filter_products', 'handle_product_filter');

function product_filter_scripts() {
    wp_enqueue_script('product-filter', plugins_url('../assets/js/product-filter.js', __FILE__), array('jquery'), '1.0', true);
    wp_localize_script('product-filter', 'productFilterAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('product_filter_nonce')
    ));
    
    wp_enqueue_style('product-filter-style', plugins_url('../assets/css/product-filter.css', __FILE__));
}

function render_product_filter() {
    global $wpdb;
    
    // Get unique values for plan attribute
    $plan_values = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT meta_value 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = %s 
        AND meta_value != ''",
        'attribute_plan'
    ));
    
    // Get unique values for annual revenue attribute (only from membership product)
    $revenue_values = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT pm1.meta_value 
        FROM {$wpdb->postmeta} pm1
        JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
        WHERE pm1.meta_key = %s 
        AND pm1.meta_value != ''
        AND pm2.meta_key = 'attribute_renewal'
        AND pm2.meta_value = 'Yes'
        AND pm1.post_id IN (
            SELECT ID 
            FROM {$wpdb->posts} 
            WHERE post_parent = %d
        )",
        'attribute_annual-revenue',
        12350 // Membership product ID
    ));
    
    // Get unique values for available-for-renewal attribute
    $renewal_values = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT meta_value 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = %s 
        AND meta_value != ''",
        'attribute_renewal'
    ));
    
    ob_start();
    ?>
    <div class="product-filter-container">
        <?php if (function_exists('wcs_get_users_subscriptions')): ?>
            <!-- Current Subscription Details -->
            <div class="current-subscription-details">
                <h3>Current Subscription Details</h3>
                <?php
                $current_user_id = get_current_user_id();
                $subscriptions = wcs_get_users_subscriptions($current_user_id);
                if (!empty($subscriptions)): 
                    foreach ($subscriptions as $subscription): ?>
                        <div class="subscription-info">
                            <h5>Subscription #<?php echo esc_html($subscription->get_id()); ?></h4>
                            <?php foreach ($subscription->get_items() as $item): ?>
                                <div class="item-info">
                                    <strong><?php echo esc_html($item->get_name()); ?></strong>
                                    <?php 
                                    foreach ($item->get_formatted_meta_data() as $meta): ?>
                                        <p><<?php echo esc_html($meta->display_key); ?>: 
                                           <?php echo wp_kses_post($meta->display_value); ?></p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach;
                else: ?>
                    <p>No active subscriptions found.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Section 1: Filters -->
        <div class="filter-section">
            <h3>Subscription Update</h3>
            <div class="filter-group">
                <h4>Select Plan</h4>
                <div class="radio-group" id="plan-filters">
                    <?php foreach ($plan_values as $plan) : ?>
                        <label>
                            <input type="radio" name="plan" value="<?php echo esc_attr($plan); ?>">
                            <?php echo esc_html($plan); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="filter-group">
                <h4>Select Annual Revenue</h4>
                <div class="radio-group" id="revenue-filters">
                    <?php foreach ($revenue_values as $revenue) : ?>
                        <label>
                            <input type="radio" name="revenue" value="<?php echo esc_attr($revenue); ?>">
                            <?php echo esc_html($revenue); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Section 2: Membership Products -->
        <div class="products-section" id="membership-products">
            <h3>Membership Options</h3>
            <div class="product-variations"></div>
        </div>

        <!-- Section 3: Premium Service Products -->
        <div class="products-section" id="premium-service-products">
            <h3>Premium Service Options</h3>
            <div class="product-variations"></div>
        </div>

        <!-- Section 4: Local chapter Products -->
        <div class="products-section" id="local-chapter-products">
            <h3>Local Chapter Options</h3>
            <div class="product-variations"></div>
        </div>
    </div>
    <div class="total-container">
        <div class="selected-products-summary">
            <div class="total-section">
                <h4>Total</h4>
                <div class="total-amount">$<span id="total-price">0.00</span></div>
                <?php if (function_exists('wcs_get_users_subscriptions')): 
                    $current_user_id = get_current_user_id();
                    $subscriptions = wcs_get_users_subscriptions($current_user_id);
                    if (!empty($subscriptions)): 
                        foreach ($subscriptions as $subscription): ?>
                            <input type="hidden" id="subscription_id" value="<?php echo esc_attr($subscription->get_id()); ?>">
                            <?php break; // Only get the first subscription
                        endforeach;
                    endif;
                endif; ?>
                <div class="update-subscription-button-container">
                    <button id="update-subscription-btn" class="button alt">Update Subscription</button>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function handle_product_filter() {
    check_ajax_referer('product_filter_nonce', 'nonce');
    
    $plan = isset($_POST['plan']) ? sanitize_text_field($_POST['plan']) : '';
    $revenue = isset($_POST['revenue']) ? sanitize_text_field($_POST['revenue']) : '';
    
    $parent_products = array(
        'membership' => 12350,
        'premium_service' => 12390,
        'local_chapter' => 12428
    );
    
    $response_data = array();
    
    foreach ($parent_products as $type => $parent_id) {
        $args = array(
            'post_type' => 'product_variation',
            'posts_per_page' => -1,
            'post_parent' => $parent_id,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'attribute_renewal',
                    'value' => 'Yes',
                    'compare' => '='
                )
            )
        );
        
        if (!empty($plan)) {
            $args['meta_query'][] = array(
                'key' => 'attribute_plan',
                'value' => $plan,
                'compare' => '='
            );
        }
        
        // Only apply revenue filter for membership and premium service products
        if ($type !== 'local_chapter' && !empty($revenue)) {
            $args['meta_query'][] = array(
                'key' => 'attribute_annual-revenue',
                'value' => $revenue,
                'compare' => '='
            );
        }
        
        $products = new WP_Query($args);
        ob_start();
        
        if ($products->have_posts()) {
            echo '<div class="variations-list">';
            while ($products->have_posts()) {
                $products->the_post();
                $variation = wc_get_product(get_the_ID());
                $is_membership = ($type === 'membership');
                ?>
                <div class="variation-item">
                    <label>
                        <input type="checkbox" 
                               name="variation_<?php echo $type; ?>[]" 
                               value="<?php echo esc_attr(get_the_ID()); ?>"
                               data-price="<?php echo esc_attr($variation->get_price()); ?>"
                               <?php if ($is_membership) echo 'checked onclick="return false;" style="pointer-events: none;"'; ?>>
                        <span class="variation-details">
                            <span class="name"><?php echo esc_html($variation->get_name()); ?></span>
                            <span class="price"><?php echo $variation->get_price_html(); ?></span>
                        </span>
                    </label>
                </div>
                <?php
            }
            echo '</div>';
        } else {
            echo '<p>No products available for your selected criteria.</p>';
        }
        
        wp_reset_postdata();
        $response_data[$type] = ob_get_clean();
    }
    
    // Debugging
    error_log('handle_product_filter: ' . print_r($response_data, true));
    
    wp_send_json_success($response_data);
}
