<?php
/**
 * Transactions page view
 *
 * @package AK_Sepidar
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <ul class="subsubsub">
        <li><a href="<?php echo admin_url('admin.php?page=ak-sepidar-transactions'); ?>" <?php echo $status === null ? 'class="current"' : ''; ?>><?php _e('All', 'ak-sepidar'); ?> <span class="count">(<?php echo AK_Sepidar_Database::get_mappings_count(); ?>)</span></a> |</li>
        <li><a href="<?php echo admin_url('admin.php?page=ak-sepidar-transactions&status=success'); ?>" <?php echo $status === 'success' ? 'class="current"' : ''; ?>><?php _e('Success', 'ak-sepidar'); ?> <span class="count">(<?php echo AK_Sepidar_Database::get_mappings_count('success'); ?>)</span></a> |</li>
        <li><a href="<?php echo admin_url('admin.php?page=ak-sepidar-transactions&status=failed'); ?>" <?php echo $status === 'failed' ? 'class="current"' : ''; ?>><?php _e('Failed', 'ak-sepidar'); ?> <span class="count">(<?php echo AK_Sepidar_Database::get_mappings_count('failed'); ?>)</span></a> |</li>
        <li><a href="<?php echo admin_url('admin.php?page=ak-sepidar-transactions&status=pending'); ?>" <?php echo $status === 'pending' ? 'class="current"' : ''; ?>><?php _e('Pending', 'ak-sepidar'); ?> <span class="count">(<?php echo AK_Sepidar_Database::get_mappings_count('pending'); ?>)</span></a> |</li>
        <li><a href="<?php echo admin_url('admin.php?page=ak-sepidar-transactions&status=processing'); ?>" <?php echo $status === 'processing' ? 'class="current"' : ''; ?>><?php _e('Processing', 'ak-sepidar'); ?> <span class="count">(<?php echo AK_Sepidar_Database::get_mappings_count('processing'); ?>)</span></a></li>
    </ul>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php _e('WooCommerce Order', 'ak-sepidar'); ?></th>
                <th scope="col"><?php _e('Sepidar Customer ID', 'ak-sepidar'); ?></th>
                <th scope="col"><?php _e('Sepidar Order ID', 'ak-sepidar'); ?></th>
                <th scope="col"><?php _e('Sepidar Invoice ID', 'ak-sepidar'); ?></th>
                <th scope="col"><?php _e('Status', 'ak-sepidar'); ?></th>
                <th scope="col"><?php _e('Error', 'ak-sepidar'); ?></th>
                <th scope="col"><?php _e('Created', 'ak-sepidar'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($mappings)): ?>
                <tr>
                    <td colspan="7"><?php _e('No transactions found.', 'ak-sepidar'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($mappings as $mapping): ?>
                    <tr>
                        <td>
                            <a href="<?php echo admin_url('post.php?post=' . $mapping->woocommerce_order_id . '&action=edit'); ?>">
                                #<?php echo esc_html($mapping->woocommerce_order_id); ?>
                            </a>
                        </td>
                        <td><?php echo $mapping->sepidar_customer_id ? esc_html($mapping->sepidar_customer_id) : '-'; ?></td>
                        <td><?php echo $mapping->sepidar_order_id ? esc_html($mapping->sepidar_order_id) : '-'; ?></td>
                        <td><?php echo $mapping->sepidar_invoice_id ? esc_html($mapping->sepidar_invoice_id) : '-'; ?></td>
                        <td>
                            <span class="status-<?php echo esc_attr($mapping->sync_status); ?>">
                                <?php echo esc_html(ucfirst($mapping->sync_status)); ?>
                            </span>
                        </td>
                        <td><?php echo $mapping->error_message ? esc_html($mapping->error_message) : '-'; ?></td>
                        <td><?php echo esc_html($mapping->created_at); ?></td>
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

