<?php
/**
 * Helper functions for AK Sepidar plugin
 *
 * @package AK_Sepidar
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Sepidar_Helper {

    /**
     * Sanitize API URL
     *
     * @param string $url
     * @return string
     */
    public static function sanitize_api_url($url) {
        $url = esc_url_raw(trim($url));
        // Remove trailing slash
        $url = rtrim($url, '/');
        return $url;
    }

    /**
     * Validate API credentials
     *
     * @param string $api_url
     * @param string $username
     * @param string $password
     * @return array|true Returns true if valid, array with error message if invalid
     */
    public static function validate_api_credentials($api_url, $username, $password) {
        $errors = array();

        if (empty($api_url)) {
            $errors[] = __('API URL is required', 'ak-sepidar');
        } elseif (!filter_var($api_url, FILTER_VALIDATE_URL)) {
            $errors[] = __('API URL is not valid', 'ak-sepidar');
        }

        if (empty($username)) {
            $errors[] = __('Username is required', 'ak-sepidar');
        }

        if (empty($password)) {
            $errors[] = __('Password is required', 'ak-sepidar');
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Format error message from API response
     *
     * @param mixed $response
     * @param string $default_message
     * @return string
     */
    public static function format_api_error($response, $default_message = '') {
        if (is_array($response)) {
            if (isset($response['message'])) {
                return $response['message'];
            } elseif (isset($response['error'])) {
                return is_string($response['error']) ? $response['error'] : json_encode($response['error'], JSON_UNESCAPED_UNICODE);
            } elseif (isset($response['errors'])) {
                if (is_array($response['errors'])) {
                    return implode(', ', $response['errors']);
                }
                return (string) $response['errors'];
            }
        } elseif (is_string($response)) {
            return $response;
        }

        return $default_message ?: __('Unknown error occurred', 'ak-sepidar');
    }

    /**
     * Check if order should be synced
     *
     * @param WC_Order $order
     * @return bool
     */
    public static function should_sync_order($order) {
        // Check if plugin is enabled
        if (get_option('ak_sepidar_enabled', 'yes') !== 'yes') {
            return false;
        }

        // Check if order is valid
        if (!$order || !$order->get_id()) {
            return false;
        }

        // Skip if order is a draft or auto-draft
        $status = $order->get_status();
        if (in_array($status, array('draft', 'auto-draft', 'trash'))) {
            return false;
        }

        return true;
    }
}

