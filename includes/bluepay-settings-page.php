<?php
// Add an options page for BluePay settings
add_action('admin_menu', 'bluepay_settings_menu');
add_action('admin_init', 'bluepay_register_settings');

function bluepay_settings_menu() {
    add_submenu_page(
        'woocommerce',                  // Parent menu slug (WooCommerce menu)
        'BluePay Settings',             // Page title
        'BluePay Settings',             // Menu title
        'manage_options',               // Capability
        'bluepay-settings',             // Menu slug
        'render_bluepay_settings_page'  // Callback function to render the settings
    );
}


// Get available product attributes
function get_available_product_attributes() {
    global $wpdb;
    $attribute_options = array();
    
    // Get global product attributes
    $attributes = wc_get_attribute_taxonomies();
    foreach ($attributes as $attribute) {
        $attribute_options[$attribute->attribute_name] = $attribute->attribute_label;
    }
    
    // Get variation attributes from postmeta
    $variation_attributes = $wpdb->get_col(
        "SELECT DISTINCT meta_key 
        FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE p.post_type = 'product_variation'
        AND meta_key LIKE 'attribute_%'
        AND meta_key NOT LIKE 'attribute_pa_%'"
    );
    
    foreach ($variation_attributes as $attr) {
        $attr_name = str_replace('attribute_', '', $attr);
        if (!isset($attribute_options[$attr_name])) {
            // Convert attribute name to label (e.g., 'plan' becomes 'Plan')
            $attr_label = ucfirst(str_replace('-', ' ', $attr_name));
            $attribute_options[$attr_name] = $attr_label;
        }
    }
    
    return $attribute_options;
}

// Register settings
function bluepay_register_settings() {
    register_setting('bluepay_settings_group', 'bluepay_merchant_id');
    register_setting('bluepay_settings_group', 'bluepay_tamper_proof_seal');
    register_setting('bluepay_settings_group', 'bluepay_approved_url');
    register_setting('bluepay_settings_group', 'bluepay_declined_url');
    register_setting('bluepay_settings_group', 'bluepay_error_url');
    register_setting('bluepay_settings_group', 'bluepay_mode_variation');
    register_setting('bluepay_settings_group', 'bluepay_confirmed_order_page_url');
    register_setting('bluepay_settings_group', 'bluepay_confirmed_order_page_additional_details');
    register_setting('bluepay_settings_group', 'bluepay_sent_to_email_subject');
    register_setting('bluepay_settings_group', 'bluepay_sent_to_email_body');
}


// Render the settings page
function render_bluepay_settings_page() {
    ?>
    <div class="wrap">
        <h1>BluePay Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('bluepay_settings_group');
            do_settings_sections('bluepay_settings_group');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="bluepay_merchant_id">Merchant ID</label></th>
                    <td><input type="text" name="bluepay_merchant_id" id="bluepay_merchant_id" value="<?php echo esc_attr(get_option('bluepay_merchant_id')); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="bluepay_tamper_proof_seal">Tamper Proof Seal</label></th>
                    <td><input type="text" name="bluepay_tamper_proof_seal" id="bluepay_tamper_proof_seal" value="<?php echo esc_attr(get_option('bluepay_tamper_proof_seal')); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="bluepay_approved_url">Approved URL</label></th>
                    <td><input type="text" name="bluepay_approved_url" id="bluepay_approved_url" value="<?php echo esc_url(get_option('bluepay_approved_url')); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="bluepay_declined_url">Declined URL</label></th>
                    <td><input type="text" name="bluepay_declined_url" id="bluepay_declined_url" value="<?php echo esc_url(get_option('bluepay_declined_url')); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="bluepay_error_url">Error URL</label></th>
                    <td><input type="text" name="bluepay_error_url" id="bluepay_error_url" value="<?php echo esc_url(get_option('bluepay_error_url')); ?>" class="regular-text"></td>
                </tr>

                <tr>
                    <th scope="row"><label for="bluepay_confirmed_order_page_url">Order Confirmation Page URL</label></th>
                    <td><input type="text" name="bluepay_confirmed_order_page_url" id="bluepay_confirmed_order_page_url" value="<?php echo esc_url(get_option('bluepay_confirmed_order_page_url')); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="bluepay_confirmed_order_page_additional_details">Order Confirmation Page Additional details</label></th>
                    <td>
                    <textarea name="bluepay_confirmed_order_page_additional_details" id="bluepay_confirmed_order_page_additional_details" rows="5" class="large-text"><?php echo esc_textarea(get_option('bluepay_confirmed_order_page_additional_details')); ?></textarea>
                    </td>
                </tr>
                <hr>

                <tr>
                    <th scope="row"><label for="bluepay_mode_variation">Mode Variation</label></th>
                    <td>
                        <select name="bluepay_mode_variation" id="bluepay_mode_variation">
                            <option value="TEST" <?php selected(get_option('bluepay_mode_variation'), 'TEST'); ?>>Test</option>
                            <option value="LIVE" <?php selected(get_option('bluepay_mode_variation'), 'LIVE'); ?>>Live</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bluepay_sent_to_email_subject">Sent to email Subject</label></th>
                    <td><input type="text" name="bluepay_sent_to_email_subject" id="bluepay_sent_to_email_subject" value="<?php echo esc_attr(get_option('bluepay_sent_to_email_subject')); ?>" class="regular-text"></td>
                </tr>

                <tr>
                    <th scope="row"><label for="bluepay_sent_to_email_body">Sent to Email Body</label></th>
                    <td>
                        <textarea name="bluepay_sent_to_email_body" id="bluepay_sent_to_email_body" rows="5" class="large-text"><?php echo esc_textarea(get_option('bluepay_sent_to_email_body')); ?></textarea>
                    </td>
                </tr>


            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
