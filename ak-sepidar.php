<?php
/**
 * Plugin Name: AK Sepidar Integration
 * Plugin URI: https://example.com/ak-sepidar
 * Description: اتصال ووکامرس به سیستم سپیدار - همگام‌سازی سفارش‌ها و ایجاد معاملات در سپیدار
 * Version: 1.0.0
 * Author: hami rasekh
 * Author URI: https://example.com
 * Text Domain: ak-sepidar
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AK_SEPIDAR_VERSION', '1.0.0');
define('AK_SEPIDAR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AK_SEPIDAR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AK_SEPIDAR_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
final class AK_Sepidar {

    /**
     * Plugin instance
     *
     * @var AK_Sepidar
     */
    private static $instance = null;

    /**
     * Get plugin instance
     *
     * @return AK_Sepidar
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
        $this->init();
    }

    /**
     * Initialize plugin
     */
    private function init() {
        // Check if WooCommerce is active
        add_action('plugins_loaded', array($this, 'check_woocommerce'));
        
        // Load plugin files
        $this->load_dependencies();
        
        // Initialize plugin
        add_action('init', array($this, 'init_plugin'));
    }

    /**
     * Check if WooCommerce is active
     */
    public function check_woocommerce() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('AK Sepidar Integration requires WooCommerce to be installed and active.', 'ak-sepidar'); ?></p>
        </div>
        <?php
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once AK_SEPIDAR_PLUGIN_DIR . 'includes/class-database.php';
        require_once AK_SEPIDAR_PLUGIN_DIR . 'includes/class-helper.php';
        require_once AK_SEPIDAR_PLUGIN_DIR . 'includes/class-error-logger.php';
        require_once AK_SEPIDAR_PLUGIN_DIR . 'includes/class-sepidar-api.php';
        require_once AK_SEPIDAR_PLUGIN_DIR . 'includes/class-order-sync.php';
        require_once AK_SEPIDAR_PLUGIN_DIR . 'includes/class-ak-sepidar.php';
        
        if (is_admin()) {
            require_once AK_SEPIDAR_PLUGIN_DIR . 'admin/class-admin.php';
        }
    }

    /**
     * Initialize plugin
     */
    public function init_plugin() {
        // Initialize error logger
        if (get_option('ak_sepidar_enable_error_logging', 'yes') === 'yes') {
            AK_Sepidar_Error_Logger::init();
        }
        
        // Load text domain
        load_plugin_textdomain('ak-sepidar', false, dirname(AK_SEPIDAR_PLUGIN_BASENAME) . '/languages');
        
        // Initialize main class
        AK_Sepidar_Main::instance();
        
        // Initialize admin
        if (is_admin()) {
            AK_Sepidar_Admin::instance();
        }
    }
}

/**
 * Activation hook
 */
function ak_sepidar_activate() {
    // Create database tables
    require_once AK_SEPIDAR_PLUGIN_DIR . 'includes/class-database.php';
    AK_Sepidar_Database::create_tables();
    
    // Set default options
    add_option('ak_sepidar_version', AK_SEPIDAR_VERSION);
    add_option('ak_sepidar_api_url', 'http://127.0.0.1:7373/api');
    add_option('ak_sepidar_sync_trigger', 'payment_received');
    add_option('ak_sepidar_enabled', 'yes');
}

/**
 * Deactivation hook
 */
function ak_sepidar_deactivate() {
    // Clean up if needed
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'ak_sepidar_activate');
register_deactivation_hook(__FILE__, 'ak_sepidar_deactivate');

// Initialize plugin
AK_Sepidar::instance();

