<?php
/**
 * Main plugin class
 *
 * @package AK_Sepidar
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Sepidar_Main {

    /**
     * Plugin instance
     *
     * @var AK_Sepidar_Main
     */
    private static $instance = null;

    /**
     * Get plugin instance
     *
     * @return AK_Sepidar_Main
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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Check if plugin is enabled
        if (get_option('ak_sepidar_enabled', 'yes') !== 'yes') {
            return;
        }

        // Initialize order sync
        add_action('woocommerce_init', array($this, 'init_order_sync'));
    }

    /**
     * Initialize order sync
     */
    public function init_order_sync() {
        $sync_trigger = get_option('ak_sepidar_sync_trigger', 'payment_received');

        switch ($sync_trigger) {
            case 'order_created':
                add_action('woocommerce_new_order', array('AK_Sepidar_Order_Sync', 'sync_order'), 10, 1);
                break;

            case 'payment_received':
                add_action('woocommerce_payment_complete', array('AK_Sepidar_Order_Sync', 'sync_order'), 10, 1);
                add_action('woocommerce_order_status_processing', array('AK_Sepidar_Order_Sync', 'sync_order'), 10, 1);
                break;

            case 'order_completed':
                add_action('woocommerce_order_status_completed', array('AK_Sepidar_Order_Sync', 'sync_order'), 10, 1);
                break;
        }

        // Create invoice when order is paid
        add_action('woocommerce_payment_complete', array('AK_Sepidar_Order_Sync', 'create_invoice'), 20, 1);
        add_action('woocommerce_order_status_processing', array('AK_Sepidar_Order_Sync', 'create_invoice'), 20, 1);
    }
}

