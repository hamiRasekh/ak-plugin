<?php
/**
 * Sepidar API class
 *
 * @package AK_Sepidar
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Sepidar_API {

    /**
     * API base URL
     *
     * @var string
     */
    private $api_url;

    /**
     * Username
     *
     * @var string
     */
    private $username;

    /**
     * Password
     *
     * @var string
     */
    private $password;

    /**
     * Auth token
     *
     * @var string
     */
    private $token = null;

    /**
     * Token expiry time
     *
     * @var int
     */
    private $token_expiry = 0;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_url = get_option('ak_sepidar_api_url', 'http://127.0.0.1:7373/api');
        $this->username = get_option('ak_sepidar_username', '');
        $this->password = get_option('ak_sepidar_password', '');
    }

    /**
     * Authenticate and get token
     *
     * @return bool
     */
    public function authenticate() {
        // Check if token is still valid
        if ($this->token && time() < $this->token_expiry) {
            return true;
        }

        $response = $this->request('POST', '/Users/Login', array(
            'username' => $this->username,
            'password' => $this->password,
        ), false);

        if ($response) {
            // Try different possible token field names
            $token = null;
            if (isset($response['token'])) {
                $token = $response['token'];
            } elseif (isset($response['accessToken'])) {
                $token = $response['accessToken'];
            } elseif (isset($response['access_token'])) {
                $token = $response['access_token'];
            } elseif (is_string($response)) {
                $token = $response;
            }

            if ($token) {
                $this->token = $token;
                // Set token expiry to 1 hour from now
                $this->token_expiry = time() + 3600;
                return true;
            }
        }

        AK_Sepidar_Database::add_log(array(
            'log_type' => 'error',
            'log_message' => 'Failed to authenticate with Sepidar API',
            'log_data' => $response,
        ));

        return false;
    }

    /**
     * Make API request
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param bool $require_auth Whether authentication is required
     * @return array|false
     */
    public function request($method, $endpoint, $data = array(), $require_auth = true) {
        if ($require_auth && !$this->authenticate()) {
            return false;
        }

        // Clean and build URL
        $api_url = rtrim(esc_url_raw($this->api_url), '/');
        $endpoint = ltrim($endpoint, '/');
        $url = $api_url . '/' . $endpoint;

        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
        );

        if ($require_auth && $this->token) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->token;
        }

        if (!empty($data) && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            AK_Sepidar_Database::add_log(array(
                'log_type' => 'error',
                'log_message' => 'API request failed: ' . $response->get_error_message(),
                'log_data' => array(
                    'url' => $url,
                    'method' => $method,
                    'data' => $data,
                ),
            ));
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Handle empty body
        if (empty($body)) {
            if ($status_code >= 200 && $status_code < 300) {
                return true; // Success with no body
            }
            
            AK_Sepidar_Database::add_log(array(
                'log_type' => 'error',
                'log_message' => 'API request returned error status with empty body: ' . $status_code,
                'log_data' => array(
                    'url' => $url,
                    'method' => $method,
                    'status_code' => $status_code,
                ),
            ));
            
            return false;
        }
        
        $decoded = json_decode($body, true);
        
        // If JSON decode failed, try to return the raw body
        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($status_code >= 200 && $status_code < 300) {
                return $body; // Return raw body if successful
            }
            
            AK_Sepidar_Error_Logger::log_api_error(
                'API request returned error status and invalid JSON: ' . $status_code,
                array(
                    'url' => $url,
                    'method' => $method,
                    'status_code' => $status_code,
                    'body' => substr($body, 0, 500), // First 500 chars
                    'json_error' => json_last_error_msg(),
                )
            );
            
            return false;
        }

        if ($status_code >= 200 && $status_code < 300) {
            return $decoded;
        }

        AK_Sepidar_Database::add_log(array(
            'log_type' => 'error',
            'log_message' => 'API request returned error status: ' . $status_code,
            'log_data' => array(
                'url' => $url,
                'method' => $method,
                'status_code' => $status_code,
                'response' => $decoded,
            ),
        ));

        return false;
    }

    /**
     * Create customer
     *
     * @param array $customer_data
     * @return array|false
     */
    public function create_customer($customer_data) {
        return $this->request('POST', '/Customers#109', $customer_data);
    }

    /**
     * Get customer by ID
     *
     * @param int $customer_id
     * @return array|false
     */
    public function get_customer($customer_id) {
        return $this->request('GET', '/Customers/' . $customer_id . '#109');
    }

    /**
     * Create order
     *
     * @param array $order_data
     * @return array|false
     */
    public function create_order($order_data) {
        return $this->request('POST', '/Orders#109', $order_data);
    }

    /**
     * Get order by ID
     *
     * @param int $order_id
     * @return array|false
     */
    public function get_order($order_id) {
        return $this->request('GET', '/Orders/' . $order_id . '#109');
    }

    /**
     * Delete order
     *
     * @param int $order_id
     * @return array|false
     */
    public function delete_order($order_id) {
        return $this->request('DELETE', '/Orders/' . $order_id);
    }

    /**
     * Create invoice
     *
     * @param array $invoice_data
     * @return array|false
     */
    public function create_invoice($invoice_data) {
        return $this->request('POST', '/Invoices#109', $invoice_data);
    }

    /**
     * Get invoice by ID
     *
     * @param int $invoice_id
     * @return array|false
     */
    public function get_invoice($invoice_id) {
        return $this->request('GET', '/Invoices/' . $invoice_id . '#109');
    }

    /**
     * Get items
     *
     * @return array|false
     */
    public function get_items() {
        return $this->request('GET', '/Items');
    }

    /**
     * Get currencies
     *
     * @return array|false
     */
    public function get_currencies() {
        return $this->request('GET', '/Currencies');
    }

    /**
     * Get sale types
     *
     * @return array|false
     */
    public function get_sale_types() {
        return $this->request('GET', '/SaleTypes#108');
    }

    /**
     * Get stocks
     *
     * @return array|false
     */
    public function get_stocks() {
        return $this->request('GET', '/Stocks');
    }

    /**
     * Get units
     *
     * @return array|false
     */
    public function get_units() {
        return $this->request('GET', '/Units');
    }
}

