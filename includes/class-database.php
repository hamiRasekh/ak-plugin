<?php
/**
 * Database class for AK Sepidar plugin
 *
 * @package AK_Sepidar
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Sepidar_Database {

    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Mappings table
        $table_mappings = $wpdb->prefix . 'ak_sepidar_mappings';
        $sql_mappings = "CREATE TABLE IF NOT EXISTS $table_mappings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            woocommerce_order_id bigint(20) NOT NULL,
            sepidar_customer_id bigint(20) DEFAULT NULL,
            sepidar_order_id bigint(20) DEFAULT NULL,
            sepidar_invoice_id bigint(20) DEFAULT NULL,
            sync_status varchar(50) DEFAULT 'pending',
            sync_trigger varchar(50) DEFAULT NULL,
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY woocommerce_order_id (woocommerce_order_id),
            KEY sepidar_order_id (sepidar_order_id),
            KEY sepidar_invoice_id (sepidar_invoice_id),
            KEY sync_status (sync_status)
        ) $charset_collate;";

        // Logs table
        $table_logs = $wpdb->prefix . 'ak_sepidar_logs';
        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            woocommerce_order_id bigint(20) DEFAULT NULL,
            log_type varchar(50) NOT NULL,
            log_message text NOT NULL,
            log_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY woocommerce_order_id (woocommerce_order_id),
            KEY log_type (log_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_mappings);
        dbDelta($sql_logs);
    }

    /**
     * Get mapping by WooCommerce order ID
     *
     * @param int $order_id
     * @return object|null
     */
    public static function get_mapping($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_sepidar_mappings';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE woocommerce_order_id = %d",
            $order_id
        ));
    }

    /**
     * Create or update mapping
     *
     * @param array $data
     * @return int|false
     */
    public static function save_mapping($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_sepidar_mappings';

        $defaults = array(
            'woocommerce_order_id' => 0,
            'sepidar_customer_id' => null,
            'sepidar_order_id' => null,
            'sepidar_invoice_id' => null,
            'sync_status' => 'pending',
            'sync_trigger' => null,
            'error_message' => null,
        );

        $data = wp_parse_args($data, $defaults);

        $existing = self::get_mapping($data['woocommerce_order_id']);

        if ($existing) {
            return $wpdb->update(
                $table,
                array(
                    'sepidar_customer_id' => $data['sepidar_customer_id'],
                    'sepidar_order_id' => $data['sepidar_order_id'],
                    'sepidar_invoice_id' => $data['sepidar_invoice_id'],
                    'sync_status' => $data['sync_status'],
                    'sync_trigger' => $data['sync_trigger'],
                    'error_message' => $data['error_message'],
                ),
                array('woocommerce_order_id' => $data['woocommerce_order_id']),
                array('%d', '%d', '%d', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            return $wpdb->insert(
                $table,
                $data,
                array('%d', '%d', '%d', '%d', '%s', '%s', '%s')
            );
        }
    }

    /**
     * Add log entry
     *
     * @param array $data
     * @return int|false
     */
    public static function add_log($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_sepidar_logs';

        $defaults = array(
            'woocommerce_order_id' => null,
            'log_type' => 'info',
            'log_message' => '',
            'log_data' => null,
        );

        $data = wp_parse_args($data, $defaults);

        if ($data['log_data'] && is_array($data['log_data'])) {
            $data['log_data'] = json_encode($data['log_data'], JSON_UNESCAPED_UNICODE);
        }

        return $wpdb->insert(
            $table,
            $data,
            array('%d', '%s', '%s', '%s')
        );
    }

    /**
     * Get logs
     *
     * @param array $args
     * @return array
     */
    public static function get_logs($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_sepidar_logs';

        $defaults = array(
            'order_id' => null,
            'log_type' => null,
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');

        if ($args['order_id']) {
            $where[] = $wpdb->prepare('woocommerce_order_id = %d', $args['order_id']);
        }

        if ($args['log_type']) {
            $where[] = $wpdb->prepare('log_type = %s', $args['log_type']);
        }

        $where_clause = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        // Fallback if sanitize_sql_orderby returns false
        if (!$orderby) {
            $orderby = 'created_at DESC';
        }

        $query = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby LIMIT %d OFFSET %d";
        return $wpdb->get_results($wpdb->prepare($query, $args['limit'], $args['offset']));
    }

    /**
     * Get mappings
     *
     * @param array $args
     * @return array
     */
    public static function get_mappings($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_sepidar_mappings';

        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'sync_status' => null,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');

        if ($args['sync_status']) {
            $where[] = $wpdb->prepare('sync_status = %s', $args['sync_status']);
        }

        $where_clause = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        // Fallback if sanitize_sql_orderby returns false
        if (!$orderby) {
            $orderby = 'created_at DESC';
        }

        $query = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby LIMIT %d OFFSET %d";
        return $wpdb->get_results($wpdb->prepare($query, $args['limit'], $args['offset']));
    }

    /**
     * Get mappings count
     *
     * @param string $status
     * @return int
     */
    public static function get_mappings_count($status = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_sepidar_mappings';

        if ($status) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE sync_status = %s",
                $status
            ));
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    /**
     * Get logs count
     *
     * @param array $args
     * @return int
     */
    public static function get_logs_count($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_sepidar_logs';

        $where = array('1=1');

        if (isset($args['order_id']) && $args['order_id']) {
            $where[] = $wpdb->prepare('woocommerce_order_id = %d', $args['order_id']);
        }

        if (isset($args['log_type']) && $args['log_type']) {
            $where[] = $wpdb->prepare('log_type = %s', $args['log_type']);
        }

        $where_clause = implode(' AND ', $where);

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where_clause");
    }

    /**
     * Delete old logs
     *
     * @param int $days Number of days to keep
     * @return int|false Number of rows deleted
     */
    public static function delete_old_logs($days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_sepidar_logs';

        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < %s",
            $date
        ));
    }

    /**
     * Delete all logs
     *
     * @return int|false Number of rows deleted
     */
    public static function delete_all_logs() {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_sepidar_logs';

        return $wpdb->query("DELETE FROM $table");
    }

    /**
     * Get error logs count
     *
     * @return int
     */
    public static function get_error_logs_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_sepidar_logs';

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE log_type = 'error'"
        );
    }
}

