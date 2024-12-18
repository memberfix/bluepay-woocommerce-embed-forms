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


// Register settings
function bluepay_register_settings() {
    register_setting('bluepay_settings_group', 'bluepay_merchant_id');
    register_setting('bluepay_settings_group', 'bluepay_tamper_proof_seal');
    register_setting('bluepay_settings_group', 'bluepay_approved_url');
    register_setting('bluepay_settings_group', 'bluepay_declined_url');
    register_setting('bluepay_settings_group', 'bluepay_error_url');
    register_setting('bluepay_settings_group', 'bluepay_mode_variation');
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
                    <th scope="row"><label for="bluepay_mode_variation">Mode Variation</label></th>
                    <td>
                        <select name="bluepay_mode_variation" id="bluepay_mode_variation">
                            <option value="TEST" <?php selected(get_option('bluepay_mode_variation'), 'TEST'); ?>>Test</option>
                            <option value="LIVE" <?php selected(get_option('bluepay_mode_variation'), 'LIVE'); ?>>Live</option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
