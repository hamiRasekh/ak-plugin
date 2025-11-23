<?php
/**
 * Error Logger class for AK Sepidar plugin
 *
 * @package AK_Sepidar
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Sepidar_Error_Logger {

    /**
     * Initialize error logging
     */
    public static function init() {
        // Set error handler
        set_error_handler(array(__CLASS__, 'handle_php_error'));
        
        // Set exception handler
        set_exception_handler(array(__CLASS__, 'handle_exception'));
        
        // Set shutdown handler for fatal errors
        register_shutdown_function(array(__CLASS__, 'handle_shutdown'));
    }

    /**
     * Handle PHP errors
     *
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @return bool
     */
    public static function handle_php_error($errno, $errstr, $errfile = '', $errline = 0) {
        // Only log errors that are enabled
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $error_types = array(
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Notice',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated',
        );

        $error_type = isset($error_types[$errno]) ? $error_types[$errno] : 'Unknown Error';

        // Only log if it's related to our plugin
        if (strpos($errfile, 'ak-sepidar') !== false || strpos($errfile, 'ak_sepidar') !== false) {
            AK_Sepidar_Database::add_log(array(
                'log_type' => 'error',
                'log_message' => sprintf(
                    'PHP %s: %s in %s on line %d',
                    $error_type,
                    $errstr,
                    basename($errfile),
                    $errline
                ),
                'log_data' => array(
                    'error_type' => $error_type,
                    'error_code' => $errno,
                    'error_message' => $errstr,
                    'file' => $errfile,
                    'line' => $errline,
                    'backtrace' => self::get_backtrace(),
                ),
            ));
        }

        // Don't prevent default error handling
        return false;
    }

    /**
     * Handle exceptions
     *
     * @param Exception $exception
     */
    public static function handle_exception($exception) {
        // Only log if it's related to our plugin
        $trace = $exception->getTrace();
        $is_plugin_error = false;

        foreach ($trace as $item) {
            if (isset($item['file']) && (strpos($item['file'], 'ak-sepidar') !== false || strpos($item['file'], 'ak_sepidar') !== false)) {
                $is_plugin_error = true;
                break;
            }
        }

        if ($is_plugin_error || strpos($exception->getFile(), 'ak-sepidar') !== false) {
            AK_Sepidar_Database::add_log(array(
                'log_type' => 'error',
                'log_message' => sprintf(
                    'Uncaught Exception: %s in %s on line %d',
                    $exception->getMessage(),
                    basename($exception->getFile()),
                    $exception->getLine()
                ),
                'log_data' => array(
                    'exception_class' => get_class($exception),
                    'exception_message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                    'backtrace' => self::get_backtrace(),
                ),
            ));
        }
    }

    /**
     * Handle fatal errors on shutdown
     */
    public static function handle_shutdown() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE))) {
            // Only log if it's related to our plugin
            if (strpos($error['file'], 'ak-sepidar') !== false || strpos($error['file'], 'ak_sepidar') !== false) {
                AK_Sepidar_Database::add_log(array(
                    'log_type' => 'error',
                    'log_message' => sprintf(
                        'Fatal Error: %s in %s on line %d',
                        $error['message'],
                        basename($error['file']),
                        $error['line']
                    ),
                    'log_data' => array(
                        'error_type' => 'Fatal Error',
                        'error_message' => $error['message'],
                        'file' => $error['file'],
                        'line' => $error['line'],
                        'backtrace' => self::get_backtrace(),
                    ),
                ));
            }
        }
    }

    /**
     * Get backtrace
     *
     * @return array
     */
    private static function get_backtrace() {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        // Remove first few items (this function and error handler)
        $backtrace = array_slice($backtrace, 3);
        
        // Limit to 10 items
        $backtrace = array_slice($backtrace, 0, 10);
        
        // Simplify backtrace
        $simplified = array();
        foreach ($backtrace as $item) {
            $simplified[] = array(
                'file' => isset($item['file']) ? basename($item['file']) : '',
                'line' => isset($item['line']) ? $item['line'] : '',
                'function' => isset($item['function']) ? $item['function'] : '',
                'class' => isset($item['class']) ? $item['class'] : '',
            );
        }
        
        return $simplified;
    }

    /**
     * Log API error with full details
     *
     * @param string $message
     * @param array $context
     */
    public static function log_api_error($message, $context = array()) {
        AK_Sepidar_Database::add_log(array(
            'log_type' => 'error',
            'log_message' => $message,
            'log_data' => array_merge($context, array(
                'type' => 'api_error',
                'timestamp' => current_time('mysql'),
                'backtrace' => self::get_backtrace(),
            )),
        ));
    }

    /**
     * Log general error
     *
     * @param string $message
     * @param array $context
     */
    public static function log_error($message, $context = array()) {
        AK_Sepidar_Database::add_log(array(
            'log_type' => 'error',
            'log_message' => $message,
            'log_data' => array_merge($context, array(
                'type' => 'general_error',
                'timestamp' => current_time('mysql'),
            )),
        ));
    }

    /**
     * Log warning
     *
     * @param string $message
     * @param array $context
     */
    public static function log_warning($message, $context = array()) {
        AK_Sepidar_Database::add_log(array(
            'log_type' => 'warning',
            'log_message' => $message,
            'log_data' => array_merge($context, array(
                'type' => 'warning',
                'timestamp' => current_time('mysql'),
            )),
        ));
    }

    /**
     * Log info
     *
     * @param string $message
     * @param array $context
     */
    public static function log_info($message, $context = array()) {
        AK_Sepidar_Database::add_log(array(
            'log_type' => 'info',
            'log_message' => $message,
            'log_data' => $context,
        ));
    }
}

