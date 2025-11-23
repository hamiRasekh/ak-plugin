<?php
/**
 * Admin class
 *
 * @package AK_Sepidar
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Sepidar_Admin {

    /**
     * Plugin instance
     *
     * @var AK_Sepidar_Admin
     */
    private static $instance = null;

    /**
     * Get plugin instance
     *
     * @return AK_Sepidar_Admin
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_ak_sepidar_test_connection', array($this, 'ajax_test_connection'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Sepidar Integration', 'ak-sepidar'),
            __('Sepidar', 'ak-sepidar'),
            'manage_options',
            'ak-sepidar',
            array($this, 'render_settings_page'),
            'dashicons-admin-generic',
            30
        );

        add_submenu_page(
            'ak-sepidar',
            __('Settings', 'ak-sepidar'),
            __('Settings', 'ak-sepidar'),
            'manage_options',
            'ak-sepidar',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'ak-sepidar',
            __('Transactions', 'ak-sepidar'),
            __('Transactions', 'ak-sepidar'),
            'manage_options',
            'ak-sepidar-transactions',
            array($this, 'render_transactions_page')
        );

        add_submenu_page(
            'ak-sepidar',
            __('Orders', 'ak-sepidar'),
            __('Orders', 'ak-sepidar'),
            'manage_options',
            'ak-sepidar-orders',
            array($this, 'render_orders_page')
        );

        add_submenu_page(
            'ak-sepidar',
            __('Invoices', 'ak-sepidar'),
            __('Invoices', 'ak-sepidar'),
            'manage_options',
            'ak-sepidar-invoices',
            array($this, 'render_invoices_page')
        );

        add_submenu_page(
            'ak-sepidar',
            __('Logs', 'ak-sepidar'),
            __('Logs', 'ak-sepidar'),
            'manage_options',
            'ak-sepidar-logs',
            array($this, 'render_logs_page')
        );
    }

    /**
     * Handle log actions (delete, export)
     */
    public function handle_log_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Delete logs
        if (isset($_POST['ak_sepidar_delete_logs']) && check_admin_referer('ak_sepidar_delete_logs')) {
            $days = isset($_POST['delete_days']) ? absint($_POST['delete_days']) : 0;
            if ($days > 0) {
                AK_Sepidar_Database::delete_old_logs($days);
                echo '<div class="notice notice-success"><p>' . sprintf(__('Logs older than %d days have been deleted.', 'ak-sepidar'), $days) . '</p></div>';
            } else {
                AK_Sepidar_Database::delete_all_logs();
                echo '<div class="notice notice-success"><p>' . __('All logs have been deleted.', 'ak-sepidar') . '</p></div>';
            }
        }

        // Export logs
        if (isset($_GET['ak_sepidar_export_logs']) && check_admin_referer('ak_sepidar_export_logs')) {
            $this->export_logs();
        }
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('ak_sepidar_settings', 'ak_sepidar_api_url');
        register_setting('ak_sepidar_settings', 'ak_sepidar_username');
        register_setting('ak_sepidar_settings', 'ak_sepidar_password');
        register_setting('ak_sepidar_settings', 'ak_sepidar_sync_trigger');
        register_setting('ak_sepidar_settings', 'ak_sepidar_enabled');
        register_setting('ak_sepidar_settings', 'ak_sepidar_enable_error_logging');
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'ak-sepidar') === false) {
            return;
        }

        wp_enqueue_style('ak-sepidar-admin', AK_SEPIDAR_PLUGIN_URL . 'admin/css/admin.css', array(), AK_SEPIDAR_VERSION);
        wp_enqueue_script('ak-sepidar-admin', AK_SEPIDAR_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), AK_SEPIDAR_VERSION, true);
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (isset($_POST['ak_sepidar_save_settings']) && check_admin_referer('ak_sepidar_settings', 'ak_sepidar_settings_nonce')) {
            // Validate credentials
            $api_url = sanitize_text_field($_POST['ak_sepidar_api_url']);
            $username = sanitize_text_field($_POST['ak_sepidar_username']);
            $password = !empty($_POST['ak_sepidar_password']) ? sanitize_text_field($_POST['ak_sepidar_password']) : get_option('ak_sepidar_password', '');
            
            $validation = AK_Sepidar_Helper::validate_api_credentials($api_url, $username, $password);
            if ($validation !== true) {
                echo '<div class="notice notice-error"><p>' . implode('<br>', $validation) . '</p></div>';
            } else {
                update_option('ak_sepidar_api_url', AK_Sepidar_Helper::sanitize_api_url($api_url));
                update_option('ak_sepidar_username', $username);
                if (!empty($_POST['ak_sepidar_password'])) {
                    update_option('ak_sepidar_password', $password);
                }
                update_option('ak_sepidar_sync_trigger', sanitize_text_field($_POST['ak_sepidar_sync_trigger']));
                update_option('ak_sepidar_enabled', isset($_POST['ak_sepidar_enabled']) ? 'yes' : 'no');
                update_option('ak_sepidar_enable_error_logging', isset($_POST['ak_sepidar_enable_error_logging']) ? 'yes' : 'no');

                echo '<div class="notice notice-success"><p>' . __('Settings saved successfully.', 'ak-sepidar') . '</p></div>';
            }
        }

        include AK_SEPIDAR_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Render transactions page
     */
    public function render_transactions_page() {
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : null;
        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;

        $args = array(
            'limit' => $per_page,
            'offset' => $offset,
            'sync_status' => $status,
        );

        $mappings = AK_Sepidar_Database::get_mappings($args);
        $total = AK_Sepidar_Database::get_mappings_count($status);
        $total_pages = max(1, ceil($total / $per_page));

        include AK_SEPIDAR_PLUGIN_DIR . 'admin/views/transactions.php';
    }

    /**
     * Render orders page
     */
    public function render_orders_page() {
        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;

        $args = array(
            'limit' => $per_page,
            'offset' => $offset,
        );

        $mappings = AK_Sepidar_Database::get_mappings($args);
        $orders = array();
        $api = new AK_Sepidar_API();

        foreach ($mappings as $mapping) {
            if ($mapping->sepidar_order_id) {
                $order_data = $api->get_order($mapping->sepidar_order_id);
                if ($order_data) {
                    $orders[] = array(
                        'mapping' => $mapping,
                        'order' => $order_data,
                    );
                }
            }
        }

        include AK_SEPIDAR_PLUGIN_DIR . 'admin/views/orders.php';
    }

    /**
     * Render invoices page
     */
    public function render_invoices_page() {
        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;

        $args = array(
            'limit' => $per_page,
            'offset' => $offset,
        );

        $mappings = AK_Sepidar_Database::get_mappings($args);
        $invoices = array();
        $api = new AK_Sepidar_API();

        foreach ($mappings as $mapping) {
            if ($mapping->sepidar_invoice_id) {
                $invoice_data = $api->get_invoice($mapping->sepidar_invoice_id);
                if ($invoice_data) {
                    $invoices[] = array(
                        'mapping' => $mapping,
                        'invoice' => $invoice_data,
                    );
                }
            }
        }

        include AK_SEPIDAR_PLUGIN_DIR . 'admin/views/invoices.php';
    }

    /**
     * Render logs page
     */
    public function render_logs_page() {
        // Handle actions
        $this->handle_log_actions();

        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : null;
        $log_type = isset($_GET['log_type']) ? sanitize_text_field($_GET['log_type']) : null;
        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $per_page = 50;
        $offset = ($paged - 1) * $per_page;

        $args = array(
            'order_id' => $order_id,
            'log_type' => $log_type,
            'limit' => $per_page,
            'offset' => $offset,
        );

        $logs = AK_Sepidar_Database::get_logs($args);
        $total_logs = AK_Sepidar_Database::get_logs_count($args);
        $total_pages = max(1, ceil($total_logs / $per_page));

        include AK_SEPIDAR_PLUGIN_DIR . 'admin/views/logs.php';
    }

    /**
     * Export logs to CSV
     */
    private function export_logs() {
        $args = array(
            'limit' => 10000, // Export up to 10000 logs
            'offset' => 0,
        );

        $logs = AK_Sepidar_Database::get_logs($args);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=ak-sepidar-logs-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Headers
        fputcsv($output, array('ID', 'Order ID', 'Type', 'Message', 'Data', 'Date'));

        // Data
        foreach ($logs as $log) {
            $data = $log->log_data ? json_decode($log->log_data, true) : '';
            $data_str = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
            
            fputcsv($output, array(
                $log->id,
                $log->woocommerce_order_id ?: '',
                $log->log_type,
                $log->log_message,
                $data_str,
                $log->created_at,
            ));
        }

        fclose($output);
        exit;
    }

    /**
     * AJAX handler for test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('ak_sepidar_test_connection', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'ak-sepidar')));
        }

        $api = new AK_Sepidar_API();
        
        if ($api->authenticate()) {
            // Try to get a simple endpoint to verify connection
            $currencies = $api->get_currencies();
            if ($currencies !== false) {
                wp_send_json_success(array('message' => __('Connection successful! API is working correctly.', 'ak-sepidar')));
            } else {
                wp_send_json_error(array('message' => __('Authentication successful but API request failed. Please check your API URL.', 'ak-sepidar')));
            }
        } else {
            wp_send_json_error(array('message' => __('Failed to authenticate. Please check your username and password.', 'ak-sepidar')));
        }
    }
}

