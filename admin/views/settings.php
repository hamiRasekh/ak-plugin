<?php
/**
 * Settings page view
 *
 * @package AK_Sepidar
 */

if (!defined('ABSPATH')) {
    exit;
}

$api_url = get_option('ak_sepidar_api_url', 'http://127.0.0.1:7373/api');
$username = get_option('ak_sepidar_username', '');
$password = get_option('ak_sepidar_password', '');
$sync_trigger = get_option('ak_sepidar_sync_trigger', 'payment_received');
$enabled = get_option('ak_sepidar_enabled', 'yes');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('ak_sepidar_settings', 'ak_sepidar_settings_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ak_sepidar_enabled"><?php _e('Enable Integration', 'ak-sepidar'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="ak_sepidar_enabled" name="ak_sepidar_enabled" value="yes" <?php checked($enabled, 'yes'); ?>>
                    <p class="description"><?php _e('Enable or disable the Sepidar integration.', 'ak-sepidar'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="ak_sepidar_api_url"><?php _e('API URL', 'ak-sepidar'); ?></label>
                </th>
                <td>
                    <input type="url" id="ak_sepidar_api_url" name="ak_sepidar_api_url" value="<?php echo esc_attr($api_url); ?>" class="regular-text" required>
                    <p class="description"><?php _e('Base URL for Sepidar API (e.g., http://127.0.0.1:7373/api)', 'ak-sepidar'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="ak_sepidar_username"><?php _e('Username', 'ak-sepidar'); ?></label>
                </th>
                <td>
                    <input type="text" id="ak_sepidar_username" name="ak_sepidar_username" value="<?php echo esc_attr($username); ?>" class="regular-text" required>
                    <p class="description"><?php _e('Username for Sepidar API authentication.', 'ak-sepidar'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="ak_sepidar_password"><?php _e('Password', 'ak-sepidar'); ?></label>
                </th>
                <td>
                    <input type="password" id="ak_sepidar_password" name="ak_sepidar_password" value="" class="regular-text" placeholder="<?php _e('Leave blank to keep current password', 'ak-sepidar'); ?>">
                    <p class="description"><?php _e('Password for Sepidar API authentication.', 'ak-sepidar'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="ak_sepidar_sync_trigger"><?php _e('Sync Trigger', 'ak-sepidar'); ?></label>
                </th>
                <td>
                    <select id="ak_sepidar_sync_trigger" name="ak_sepidar_sync_trigger">
                        <option value="order_created" <?php selected($sync_trigger, 'order_created'); ?>><?php _e('When order is created', 'ak-sepidar'); ?></option>
                        <option value="payment_received" <?php selected($sync_trigger, 'payment_received'); ?>><?php _e('When payment is received', 'ak-sepidar'); ?></option>
                        <option value="order_completed" <?php selected($sync_trigger, 'order_completed'); ?>><?php _e('When order is completed', 'ak-sepidar'); ?></option>
                    </select>
                    <p class="description"><?php _e('When should orders be synced to Sepidar?', 'ak-sepidar'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="ak_sepidar_enable_error_logging"><?php _e('Enable Error Logging', 'ak-sepidar'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="ak_sepidar_enable_error_logging" name="ak_sepidar_enable_error_logging" value="yes" <?php checked(get_option('ak_sepidar_enable_error_logging', 'yes'), 'yes'); ?>>
                    <p class="description"><?php _e('Enable automatic logging of PHP errors, exceptions, and fatal errors related to this plugin.', 'ak-sepidar'); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save Settings', 'ak-sepidar'), 'primary', 'ak_sepidar_save_settings'); ?>
    </form>

    <hr>

    <h2><?php _e('Test Connection', 'ak-sepidar'); ?></h2>
    <p><?php _e('Click the button below to test the connection to Sepidar API.', 'ak-sepidar'); ?></p>
    <button type="button" id="ak-sepidar-test-connection" class="button"><?php _e('Test Connection', 'ak-sepidar'); ?></button>
    <div id="ak-sepidar-test-result" style="margin-top: 10px;"></div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#ak-sepidar-test-connection').on('click', function() {
        var button = $(this);
        var result = $('#ak-sepidar-test-result');
        
        button.prop('disabled', true).text('<?php _e('Testing...', 'ak-sepidar'); ?>');
        result.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ak_sepidar_test_connection',
                _ajax_nonce: '<?php echo wp_create_nonce('ak_sepidar_test_connection'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                } else {
                    result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                result.html('<div class="notice notice-error"><p><?php _e('An error occurred while testing the connection.', 'ak-sepidar'); ?></p></div>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Test Connection', 'ak-sepidar'); ?>');
            }
        });
    });
});
</script>

