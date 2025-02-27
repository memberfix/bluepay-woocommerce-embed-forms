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
    // BluePay Embed Form settings
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
    register_setting('bluepay_settings_group', 'renewal_form_page_url');

    // Renewal/Upgrade Form settings
    register_setting('bluepay_renewal_settings_group', 'mfx_bluepay_renewal_settings', array(
        'type' => 'array',
        'sanitize_callback' => 'bluepay_sanitize_renewal_settings'
    ));

    // Team Name settings
    register_setting('mfx_bluepay_team_name_settings', 'mfx_bluepay_team_name_settings', array(
        'type' => 'array',
        'sanitize_callback' => 'bluepay_sanitize_team_name_settings',
        'default' => array('enable_team_name_billing' => 1)
    ));
}

// Sanitize renewal settings
function bluepay_sanitize_renewal_settings($input) {
    $sanitized = array();
    
    // Sanitize product IDs
    $sanitized['membership_product_id'] = absint($input['membership_product_id']);
    $sanitized['premium_service_product_id'] = absint($input['premium_service_product_id']);
    $sanitized['local_chapter_product_id'] = absint($input['local_chapter_product_id']);
    $sanitized['revenue_source_product_id'] = absint($input['revenue_source_product_id']);
    
    // Sanitize description
    $sanitized['form_description'] = wp_kses_post($input['form_description']);
    
    return $sanitized;
}


// Render the settings page
function bluepay_sanitize_team_name_settings($input) {
    $sanitized = array();
    $sanitized['enable_team_name_billing'] = isset($input['enable_team_name_billing']) ? 1 : 0;
    return $sanitized;
}

function render_bluepay_settings_page() {
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'embed_form';
    $renewal_settings = get_option('mfx_bluepay_renewal_settings', array(
        'membership_product_id' => 12350,
        'premium_service_product_id' => 12390,
        'local_chapter_product_id' => 12428,
        'revenue_source_product_id' => 12350,
        'form_description' => ''
    ));
    $team_name_settings = get_option('mfx_bluepay_team_name_settings', array(
        'enable_team_name_billing' => 1 // Enabled by default
    ));
    ?>
    <div class="wrap">
        <h1>BluePay Settings</h1>
        
        <h2 class="nav-tab-wrapper">
            <a href="?page=bluepay-settings&tab=embed_form" class="nav-tab <?php echo $active_tab == 'embed_form' ? 'nav-tab-active' : ''; ?>">BluePay Embed Form</a>
            <a href="?page=bluepay-settings&tab=renewal_form" class="nav-tab <?php echo $active_tab == 'renewal_form' ? 'nav-tab-active' : ''; ?>">Renewal/Upgrade Form</a>
            <a href="?page=bluepay-settings&tab=team_name" class="nav-tab <?php echo $active_tab == 'team_name' ? 'nav-tab-active' : ''; ?>">Team Name</a>
        </h2>

        <form method="post" action="options.php">
        <?php if ($active_tab == 'embed_form'): ?>
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
                    <th scope="row"><label for="renewal_form_page_url">Change Membership Page URL</label></th>
                    <td>
                        <input type="text" name="renewal_form_page_url" id="renewal_form_page_url" value="<?php echo esc_url(get_option('renewal_form_page_url', '/change-my-membership')); ?>" class="regular-text">
                        <p class="description">The URL of the page where members can change their membership. Default: /change-my-membership</p>
                    </td>
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
        <?php endif; ?>

        <?php if ($active_tab == 'renewal_form'): ?>
            <?php
            settings_fields('bluepay_renewal_settings_group');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row" colspan="2">
                        <h3>Parent Product Configuration</h3>
                        <p class="description">Configure the parent products for different membership types. These products should be variable products in WooCommerce.</p>
                    </th>
                </tr>
                <tr>
                    <th scope="row"><label for="membership_product_id">Membership Product ID</label></th>
                    <td>
                        <input type="number" name="mfx_bluepay_renewal_settings[membership_product_id]" id="membership_product_id" value="<?php echo esc_attr($renewal_settings['membership_product_id']); ?>" class="regular-text">
                        <p class="description">The ID of the main membership product that contains membership variations.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="premium_service_product_id">Premium Service Product ID</label></th>
                    <td>
                        <input type="number" name="mfx_bluepay_renewal_settings[premium_service_product_id]" id="premium_service_product_id" value="<?php echo esc_attr($renewal_settings['premium_service_product_id']); ?>" class="regular-text">
                        <p class="description">The ID of the premium service product that contains service variations.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="local_chapter_product_id">Local Chapter Product ID</label></th>
                    <td>
                        <input type="number" name="mfx_bluepay_renewal_settings[local_chapter_product_id]" id="local_chapter_product_id" value="<?php echo esc_attr($renewal_settings['local_chapter_product_id']); ?>" class="regular-text">
                        <p class="description">The ID of the local chapter product that contains chapter variations.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="revenue_source_product_id">Revenue Filter Product ID</label></th>
                    <td>
                        <input type="number" name="mfx_bluepay_renewal_settings[revenue_source_product_id]" id="revenue_source_product_id" value="<?php echo esc_attr($renewal_settings['revenue_source_product_id']); ?>" class="regular-text">
                        <p class="description">The product ID used to fetch available revenue values for filtering. This is typically the same as the Membership Product ID.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="form_description">Form Description</label></th>
                    <td>
                        <?php
                        wp_editor(
                            $renewal_settings['form_description'],
                            'form_description',
                            array(
                                'textarea_name' => 'mfx_bluepay_renewal_settings[form_description]',
                                'textarea_rows' => 10,
                                'media_buttons' => false,
                                'teeny' => true,
                            )
                        );
                        ?>
                        <p class="description">Description of how the renewal/upgrade form works. This will be shown to administrators only.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        <?php endif; ?>

        <?php if ($active_tab == 'team_name'): ?>
            <?php settings_fields('mfx_bluepay_team_name_settings'); ?>
            <div class="team-name-settings-info">
                <h3>Team Name Billing Company Settings</h3>
                <p class="description" style="margin-bottom: 20px;">
                    This feature automatically sets the billing company field based on the user's team name. Here's how it works:
                </p>
                <ul style="list-style-type: disc; margin-left: 20px; margin-bottom: 20px;">
                    <li>When enabled, the system will check if an order's billing company field is empty</li>
                    <li>If empty, it will automatically set the billing company to the user's team name</li>
                    <li>This applies to both orders and their associated subscriptions</li>
                    <li>If a billing company is already set (manually entered during checkout), it will be preserved</li>
                    <li>This ensures team names are consistently used across orders and subscriptions when no other billing company is specified</li>
                </ul>
            </div>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="enable_team_name_billing">Enable Team Name as Billing Company</label></th>
                    <td>
                        <input type="checkbox" name="mfx_bluepay_team_name_settings[enable_team_name_billing]" id="enable_team_name_billing" value="1" <?php checked(1, $team_name_settings['enable_team_name_billing']); ?>>
                        <p class="description">When enabled, empty billing company fields will be automatically filled with the user's team name.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        <?php endif; ?>
        </form>
    </div>
    <?php
}
