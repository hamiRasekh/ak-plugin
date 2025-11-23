<?php
/**
 * Logs page view
 *
 * @package AK_Sepidar
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="ak-sepidar-logs-header">
        <div class="ak-sepidar-stats">
            <span class="stat-item">
                <strong><?php _e('Total Logs:', 'ak-sepidar'); ?></strong> <?php echo number_format($total_logs); ?>
            </span>
            <span class="stat-item">
                <strong><?php _e('Errors:', 'ak-sepidar'); ?></strong> 
                <span style="color: #dc3232;"><?php echo number_format(AK_Sepidar_Database::get_error_logs_count()); ?></span>
            </span>
        </div>

        <div class="ak-sepidar-actions">
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ak-sepidar-logs&ak_sepidar_export_logs=1'), 'ak_sepidar_export_logs'); ?>" class="button">
                <?php _e('Export to CSV', 'ak-sepidar'); ?>
            </a>
        </div>
    </div>

    <form method="get" action="">
        <input type="hidden" name="page" value="ak-sepidar-logs">
        
        <p class="search-box">
            <label class="screen-reader-text" for="order_id"><?php _e('Order ID:', 'ak-sepidar'); ?></label>
            <input type="number" id="order_id" name="order_id" value="<?php echo $order_id ? esc_attr($order_id) : ''; ?>" placeholder="<?php _e('Order ID', 'ak-sepidar'); ?>">
            
            <label class="screen-reader-text" for="log_type"><?php _e('Log Type:', 'ak-sepidar'); ?></label>
            <select id="log_type" name="log_type">
                <option value=""><?php _e('All Types', 'ak-sepidar'); ?></option>
                <option value="info" <?php selected($log_type, 'info'); ?>><?php _e('Info', 'ak-sepidar'); ?></option>
                <option value="success" <?php selected($log_type, 'success'); ?>><?php _e('Success', 'ak-sepidar'); ?></option>
                <option value="error" <?php selected($log_type, 'error'); ?>><?php _e('Error', 'ak-sepidar'); ?></option>
                <option value="warning" <?php selected($log_type, 'warning'); ?>><?php _e('Warning', 'ak-sepidar'); ?></option>
            </select>
            
            <?php submit_button(__('Filter', 'ak-sepidar'), 'secondary', '', false); ?>
            <a href="<?php echo admin_url('admin.php?page=ak-sepidar-logs'); ?>" class="button"><?php _e('Clear', 'ak-sepidar'); ?></a>
        </p>
    </form>

    <form method="post" action="" style="margin: 20px 0;">
        <?php wp_nonce_field('ak_sepidar_delete_logs'); ?>
        <p>
            <label>
                <?php _e('Delete logs older than:', 'ak-sepidar'); ?>
                <select name="delete_days">
                    <option value="0"><?php _e('All logs', 'ak-sepidar'); ?></option>
                    <option value="7">7 <?php _e('days', 'ak-sepidar'); ?></option>
                    <option value="30">30 <?php _e('days', 'ak-sepidar'); ?></option>
                    <option value="60">60 <?php _e('days', 'ak-sepidar'); ?></option>
                    <option value="90">90 <?php _e('days', 'ak-sepidar'); ?></option>
                </select>
            </label>
            <?php submit_button(__('Delete Logs', 'ak-sepidar'), 'delete', 'ak_sepidar_delete_logs', false); ?>
        </p>
    </form>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php _e('ID', 'ak-sepidar'); ?></th>
                <th scope="col"><?php _e('Order ID', 'ak-sepidar'); ?></th>
                <th scope="col"><?php _e('Type', 'ak-sepidar'); ?></th>
                <th scope="col"><?php _e('Message', 'ak-sepidar'); ?></th>
                <th scope="col"><?php _e('Data', 'ak-sepidar'); ?></th>
                <th scope="col"><?php _e('Date', 'ak-sepidar'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="6"><?php _e('No logs found.', 'ak-sepidar'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log->id); ?></td>
                        <td>
                            <?php if ($log->woocommerce_order_id): ?>
                                <a href="<?php echo admin_url('post.php?post=' . $log->woocommerce_order_id . '&action=edit'); ?>">
                                    #<?php echo esc_html($log->woocommerce_order_id); ?>
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="log-type log-type-<?php echo esc_attr($log->log_type); ?>">
                                <?php echo esc_html(ucfirst($log->log_type)); ?>
                            </span>
                        </td>
                        <td>
                            <strong><?php echo esc_html($log->log_message); ?></strong>
                            <?php if ($log->log_type === 'error' && $log->log_data): 
                                $data = json_decode($log->log_data, true);
                                if (is_array($data) && isset($data['backtrace']) && !empty($data['backtrace'])):
                            ?>
                                <br><small style="color: #666;">
                                    <?php 
                                    $first_trace = reset($data['backtrace']);
                                    if (isset($first_trace['file']) && isset($first_trace['line'])) {
                                        echo esc_html($first_trace['file'] . ':' . $first_trace['line']);
                                    }
                                    ?>
                                </small>
                            <?php endif; endif; ?>
                        </td>
                        <td>
                            <?php if ($log->log_data): 
                                $data = json_decode($log->log_data, true);
                                if ($data):
                            ?>
                                <details>
                                    <summary><?php _e('View Data', 'ak-sepidar'); ?></summary>
                                    <pre><?php echo esc_html(print_r($data, true)); ?></pre>
                                </details>
                            <?php 
                                else:
                                    echo esc_html($log->log_data);
                                endif;
                            else: 
                                echo '-';
                            endif; ?>
                        </td>
                        <td><?php echo esc_html($log->created_at); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                $page_links = paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => $total_pages,
                    'current' => $paged,
                ));
                echo $page_links;
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

