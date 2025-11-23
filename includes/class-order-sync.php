<?php
/**
 * Order sync class
 *
 * @package AK_Sepidar
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Sepidar_Order_Sync {

    /**
     * API instance
     *
     * @var AK_Sepidar_API
     */
    private static $api = null;

    /**
     * Get API instance
     *
     * @return AK_Sepidar_API
     */
    private static function get_api() {
        if (is_null(self::$api)) {
            self::$api = new AK_Sepidar_API();
        }
        return self::$api;
    }

    /**
     * Sync order to Sepidar
     *
     * @param int $order_id
     * @return bool
     */
    public static function sync_order($order_id) {
        // Validate order ID
        $order_id = absint($order_id);
        if (!$order_id) {
            return false;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            AK_Sepidar_Database::add_log(array(
                'woocommerce_order_id' => $order_id,
                'log_type' => 'error',
                'log_message' => 'Order not found',
            ));
            return false;
        }

        // Check if order should be synced
        if (!AK_Sepidar_Helper::should_sync_order($order)) {
            AK_Sepidar_Database::add_log(array(
                'woocommerce_order_id' => $order_id,
                'log_type' => 'info',
                'log_message' => 'Order should not be synced (plugin disabled or invalid order)',
            ));
            return false;
        }

        // Check if already synced
        $mapping = AK_Sepidar_Database::get_mapping($order_id);
        if ($mapping && $mapping->sync_status === 'success' && $mapping->sepidar_order_id) {
            AK_Sepidar_Database::add_log(array(
                'woocommerce_order_id' => $order_id,
                'log_type' => 'info',
                'log_message' => 'Order already synced to Sepidar',
            ));
            return true;
        }

        // Prevent duplicate sync attempts
        if ($mapping && $mapping->sync_status === 'processing') {
            AK_Sepidar_Database::add_log(array(
                'woocommerce_order_id' => $order_id,
                'log_type' => 'info',
                'log_message' => 'Order sync already in progress, skipping',
            ));
            return false;
        }

        try {
            // Create or update mapping
            $sync_trigger = current_action();
            if (!$sync_trigger) {
                $sync_trigger = 'manual';
            }

            if (!$mapping) {
                AK_Sepidar_Database::save_mapping(array(
                    'woocommerce_order_id' => $order_id,
                    'sync_status' => 'processing',
                    'sync_trigger' => $sync_trigger,
                ));
            } else {
                AK_Sepidar_Database::save_mapping(array(
                    'woocommerce_order_id' => $order_id,
                    'sync_status' => 'processing',
                    'sync_trigger' => $sync_trigger,
                ));
            }

            // Create customer in Sepidar
            $customer_id = self::sync_customer($order);
            if (!$customer_id) {
                throw new Exception('Failed to create customer in Sepidar');
            }

            // Create order in Sepidar
            $sepidar_order = self::create_sepidar_order($order, $customer_id);
            if (!$sepidar_order || !isset($sepidar_order['id'])) {
                $error_msg = 'Failed to create order in Sepidar';
                if (is_array($sepidar_order) && isset($sepidar_order['message'])) {
                    $error_msg .= ': ' . $sepidar_order['message'];
                } elseif (is_string($sepidar_order)) {
                    $error_msg .= ': ' . $sepidar_order;
                }
                throw new Exception($error_msg);
            }

            // Update mapping
            AK_Sepidar_Database::save_mapping(array(
                'woocommerce_order_id' => $order_id,
                'sepidar_customer_id' => $customer_id,
                'sepidar_order_id' => $sepidar_order['id'],
                'sync_status' => 'success',
                'error_message' => null,
            ));

            AK_Sepidar_Database::add_log(array(
                'woocommerce_order_id' => $order_id,
                'log_type' => 'success',
                'log_message' => 'Order synced successfully to Sepidar',
                'log_data' => array(
                    'sepidar_order_id' => $sepidar_order['id'],
                    'sepidar_customer_id' => $customer_id,
                ),
            ));

            // Send email notification if enabled
            $admin_email = get_option('admin_email');
            if ($admin_email) {
                wp_mail(
                    $admin_email,
                    sprintf(__('Order #%d synced to Sepidar', 'ak-sepidar'), $order_id),
                    sprintf(__('Order #%d has been successfully synced to Sepidar. Order ID: %d', 'ak-sepidar'), $order_id, $sepidar_order['id'])
                );
            }

            return true;

        } catch (Exception $e) {
            AK_Sepidar_Database::save_mapping(array(
                'woocommerce_order_id' => $order_id,
                'sync_status' => 'failed',
                'error_message' => $e->getMessage(),
            ));

            AK_Sepidar_Database::add_log(array(
                'woocommerce_order_id' => $order_id,
                'log_type' => 'error',
                'log_message' => 'Failed to sync order: ' . $e->getMessage(),
            ));

            // Send error email
            $admin_email = get_option('admin_email');
            if ($admin_email) {
                wp_mail(
                    $admin_email,
                    sprintf(__('Error syncing order #%d to Sepidar', 'ak-sepidar'), $order_id),
                    sprintf(__('Error: %s', 'ak-sepidar'), $e->getMessage())
                );
            }

            return false;
        }
    }

    /**
     * Sync customer to Sepidar
     *
     * @param WC_Order $order
     * @return int|false Customer ID
     */
    private static function sync_customer($order) {
        $api = self::get_api();

        // Prepare customer data with validation
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();
        
        // If names are empty, use a default value
        if (empty($first_name) && empty($last_name)) {
            $first_name = __('Customer', 'ak-sepidar');
            $last_name = '#' . $order->get_id();
        } elseif (empty($first_name)) {
            $first_name = $last_name;
        } elseif (empty($last_name)) {
            $last_name = $first_name;
        }

        $customer_data = array(
            'firstName' => sanitize_text_field($first_name),
            'lastName' => sanitize_text_field($last_name),
            'mobile' => sanitize_text_field($order->get_billing_phone() ?: ''),
            'email' => sanitize_email($order->get_billing_email() ?: ''),
            'address' => sanitize_text_field($order->get_billing_address_1() ?: ''),
            'city' => sanitize_text_field($order->get_billing_city() ?: ''),
            'postalCode' => sanitize_text_field($order->get_billing_postcode() ?: ''),
            'country' => sanitize_text_field($order->get_billing_country() ?: ''),
        );

        // Add company if exists
        if ($order->get_billing_company()) {
            $customer_data['companyName'] = sanitize_text_field($order->get_billing_company());
        }

        $response = $api->create_customer($customer_data);

        if ($response && isset($response['id'])) {
            return (int) $response['id'];
        }

        // Log the error with more details
        $error_message = 'Failed to create customer in Sepidar';
        if (is_array($response)) {
            if (isset($response['message'])) {
                $error_message .= ': ' . $response['message'];
            } elseif (isset($response['error'])) {
                $error_message .= ': ' . (is_string($response['error']) ? $response['error'] : json_encode($response['error']));
            }
        } elseif (is_string($response)) {
            $error_message .= ': ' . $response;
        }

        AK_Sepidar_Database::add_log(array(
            'woocommerce_order_id' => $order->get_id(),
            'log_type' => 'error',
            'log_message' => $error_message,
            'log_data' => array(
                'customer_data' => $customer_data,
                'response' => $response,
            ),
        ));

        return false;
    }

    /**
     * Create order in Sepidar
     *
     * @param WC_Order $order
     * @param int $customer_id
     * @return array|false
     */
    private static function create_sepidar_order($order, $customer_id) {
        $api = self::get_api();

        // Get sale types
        $sale_types = $api->get_sale_types();
        $sale_type_id = null;
        if ($sale_types && is_array($sale_types) && !empty($sale_types)) {
            $sale_type_id = $sale_types[0]['id'] ?? null;
        }

        // Get currency
        $currency = $order->get_currency();
        $currencies = $api->get_currencies();
        $currency_id = null;
        if ($currencies && is_array($currencies)) {
            foreach ($currencies as $curr) {
                if (isset($curr['code']) && $curr['code'] === $currency) {
                    $currency_id = $curr['id'];
                    break;
                }
            }
        }

        // Prepare order items
        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            $quantity = (float) $item->get_quantity();
            if ($quantity <= 0) {
                continue;
            }

            $subtotal = (float) $item->get_subtotal();
            $total = (float) $item->get_total();
            $unit_price = $quantity > 0 ? $subtotal / $quantity : 0;
            $discount = $subtotal - $total;

            $items[] = array(
                'itemID' => self::get_sepidar_item_id($product->get_sku() ?: $product->get_id()),
                'quantity' => $quantity,
                'unitPrice' => max(0, $unit_price),
                'discount' => max(0, $discount),
            );
        }

        // Check if we have items
        if (empty($items)) {
            throw new Exception('Order has no valid items to sync');
        }

        // Get order date
        $order_date = $order->get_date_created();
        if (!$order_date) {
            $order_date = new WC_DateTime();
        }

        // Prepare order data
        $order_data = array(
            'customerID' => (int) $customer_id,
            'saleTypeID' => $sale_type_id ? (int) $sale_type_id : null,
            'currencyID' => $currency_id ? (int) $currency_id : null,
            'date' => $order_date->date('Y-m-d'),
            'items' => $items,
            'description' => sprintf(__('WooCommerce Order #%d', 'ak-sepidar'), $order->get_id()),
        );

        // Add shipping if exists
        if ($order->get_shipping_total() > 0) {
            $order_data['shippingCost'] = (float) $order->get_shipping_total();
        }

        // Add tax if exists
        if ($order->get_total_tax() > 0) {
            $order_data['tax'] = (float) $order->get_total_tax();
        }

        return $api->create_order($order_data);
    }

    /**
     * Get Sepidar item ID by SKU or product ID
     *
     * @param string|int $identifier
     * @return int
     */
    private static function get_sepidar_item_id($identifier) {
        // This is a placeholder - you may need to map WooCommerce products to Sepidar items
        // For now, we'll try to use the identifier directly
        // You might want to create a mapping table or use SKU matching
        
        $api = self::get_api();
        $items = $api->get_items();
        
        if ($items && is_array($items)) {
            foreach ($items as $item) {
                if (isset($item['code']) && $item['code'] == $identifier) {
                    return $item['id'];
                }
                if (isset($item['id']) && $item['id'] == $identifier) {
                    return $item['id'];
                }
            }
        }

        // Fallback: return identifier as integer
        return (int) $identifier;
    }

    /**
     * Create invoice in Sepidar
     *
     * @param int $order_id
     * @return bool
     */
    public static function create_invoice($order_id) {
        // Validate order ID
        $order_id = absint($order_id);
        if (!$order_id) {
            return false;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            AK_Sepidar_Database::add_log(array(
                'woocommerce_order_id' => $order_id,
                'log_type' => 'error',
                'log_message' => 'Order not found for invoice creation',
            ));
            return false;
        }

        // Check if plugin is enabled
        if (get_option('ak_sepidar_enabled', 'yes') !== 'yes') {
            return false;
        }

        // Check if order is paid
        if (!$order->is_paid()) {
            AK_Sepidar_Database::add_log(array(
                'woocommerce_order_id' => $order_id,
                'log_type' => 'info',
                'log_message' => 'Order is not paid yet, skipping invoice creation',
            ));
            return false;
        }

        // Check if already has invoice
        $mapping = AK_Sepidar_Database::get_mapping($order_id);
        if (!$mapping || !$mapping->sepidar_order_id) {
            // Order not synced yet, sync it first
            self::sync_order($order_id);
            $mapping = AK_Sepidar_Database::get_mapping($order_id);
        }

        if (!$mapping || !$mapping->sepidar_order_id) {
            AK_Sepidar_Database::add_log(array(
                'woocommerce_order_id' => $order_id,
                'log_type' => 'error',
                'log_message' => 'Cannot create invoice: Order not synced to Sepidar',
            ));
            return false;
        }

        if ($mapping->sepidar_invoice_id) {
            AK_Sepidar_Database::add_log(array(
                'woocommerce_order_id' => $order_id,
                'log_type' => 'info',
                'log_message' => 'Invoice already created in Sepidar',
            ));
            return true;
        }

        try {
            $api = self::get_api();

            // Get sale types
            $sale_types = $api->get_sale_types();
            $sale_type_id = null;
            if ($sale_types && is_array($sale_types) && !empty($sale_types)) {
                $sale_type_id = $sale_types[0]['id'] ?? null;
            }

            // Get currency
            $currency = $order->get_currency();
            $currencies = $api->get_currencies();
            $currency_id = null;
            if ($currencies && is_array($currencies)) {
                foreach ($currencies as $curr) {
                    if (isset($curr['code']) && $curr['code'] === $currency) {
                        $currency_id = $curr['id'];
                        break;
                    }
                }
            }

            // Get payment date
            $payment_date = $order->get_date_paid();
            if (!$payment_date) {
                $payment_date = new WC_DateTime();
            }

            // Prepare invoice data based on order
            $invoice_data = array(
                'orderID' => (int) $mapping->sepidar_order_id,
                'customerID' => (int) $mapping->sepidar_customer_id,
                'saleTypeID' => $sale_type_id ? (int) $sale_type_id : null,
                'currencyID' => $currency_id ? (int) $currency_id : null,
                'date' => $payment_date->date('Y-m-d'),
            );

            $response = $api->create_invoice($invoice_data);

            if ($response && isset($response['id'])) {
                AK_Sepidar_Database::save_mapping(array(
                    'woocommerce_order_id' => $order_id,
                    'sepidar_invoice_id' => $response['id'],
                ));

                AK_Sepidar_Database::add_log(array(
                    'woocommerce_order_id' => $order_id,
                    'log_type' => 'success',
                    'log_message' => 'Invoice created successfully in Sepidar',
                    'log_data' => array(
                        'sepidar_invoice_id' => $response['id'],
                    ),
                ));

                return true;
            }

            $error_msg = 'Failed to create invoice in Sepidar';
            if (is_array($response) && isset($response['message'])) {
                $error_msg .= ': ' . $response['message'];
            } elseif (is_string($response)) {
                $error_msg .= ': ' . $response;
            }
            throw new Exception($error_msg);

        } catch (Exception $e) {
            AK_Sepidar_Database::add_log(array(
                'woocommerce_order_id' => $order_id,
                'log_type' => 'error',
                'log_message' => 'Failed to create invoice: ' . $e->getMessage(),
            ));

            // Send error email
            $admin_email = get_option('admin_email');
            if ($admin_email) {
                wp_mail(
                    $admin_email,
                    sprintf(__('Error creating invoice for order #%d in Sepidar', 'ak-sepidar'), $order_id),
                    sprintf(__('Error: %s', 'ak-sepidar'), $e->getMessage())
                );
            }

            return false;
        }
    }
}

