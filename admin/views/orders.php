<?php
/**
 * Orders page view
 *
 * @package AK_Sepidar
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php _e('WooCommerce Order', 'ak-sepidar'); ?></th>
                <th scope="col"><?php _e('Sepidar Order ID', 'ak-sepidar'); ?></th>
                <th scope="col"><?php _e('Customer', 'ak-sepidar'); ?></th>
                <th scope="col"><?php _e('Date', 'ak-sepidar'); ?></th>
                <th scope="col"><?php _e('Total', 'ak-sepidar'); ?></th>
                <th scope="col"><?php _e('Status', 'ak-sepidar'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="6"><?php _e('No orders found.', 'ak-sepidar'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($orders as $item): 
                    $mapping = $item['mapping'];
                    $order_data = $item['order'];
                    $wc_order = wc_get_order($mapping->woocommerce_order_id);
                ?>
                    <tr>
                        <td>
                            <a href="<?php echo admin_url('post.php?post=' . $mapping->woocommerce_order_id . '&action=edit'); ?>">
                                #<?php echo esc_html($mapping->woocommerce_order_id); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html($mapping->sepidar_order_id); ?></td>
                        <td>
                            <?php 
                            if (isset($order_data['customer'])) {
                                echo esc_html($order_data['customer']['firstName'] . ' ' . $order_data['customer']['lastName']);
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><?php echo isset($order_data['date']) ? esc_html($order_data['date']) : '-'; ?></td>
                        <td>
                            <?php 
                            if (isset($order_data['total'])) {
                                echo esc_html(number_format($order_data['total'], 2));
                                if (isset($order_data['currency'])) {
                                    echo ' ' . esc_html($order_data['currency']);
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <span class="status-<?php echo esc_attr($mapping->sync_status); ?>">
                                <?php echo esc_html(ucfirst($mapping->sync_status)); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

