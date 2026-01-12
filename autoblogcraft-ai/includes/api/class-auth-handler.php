<?php
/**
 * Auth Handler
 *
 * Handles API key authentication and permission checks for REST API.
 *
 * @package AutoBlogCraft\API
 * @since 2.0.0
 */

namespace AutoBlogCraft\API;

use AutoBlogCraft\Core\Logger;
use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Auth Handler class
 *
 * Manages API authentication and authorization.
 *
 * @since 2.0.0
 */
class Auth_Handler {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * API key header name
     *
     * @var string
     */
    private $header_name = 'X-ABC-API-Key';

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->logger = Logger::instance();
    }

    /**
     * Check API key permission
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error True if authorized, error otherwise.
     */
    public function check_api_key_permission($request) {
        $api_key = $this->get_api_key_from_request($request);

        if (empty($api_key)) {
            return new WP_Error(
                'missing_api_key',
                'API key is required. Provide it in the ' . $this->header_name . ' header.',
                ['status' => 401]
            );
        }

        $validation = $this->validate_api_key($api_key);

        if (is_wp_error($validation)) {
            return $validation;
        }

        // Store validated key in request for later use
        $request->set_param('_abc_api_key_id', $validation['id']);
        
        return true;
    }

    /**
     * Check admin permission
     *
     * Requires API key with admin capabilities.
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error True if authorized, error otherwise.
     */
    public function check_admin_permission($request) {
        $auth_check = $this->check_api_key_permission($request);

        if (is_wp_error($auth_check)) {
            return $auth_check;
        }

        $api_key_id = $request->get_param('_abc_api_key_id');
        $capabilities = $this->get_api_key_capabilities($api_key_id);

        if (!in_array('admin', $capabilities, true)) {
            return new WP_Error(
                'insufficient_permissions',
                'This API key does not have admin permissions.',
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Check read-only permission
     *
     * Requires API key with read capabilities.
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error True if authorized, error otherwise.
     */
    public function check_read_permission($request) {
        return $this->check_api_key_permission($request);
    }

    /**
     * Get API key from request
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return string API key.
     */
    private function get_api_key_from_request($request) {
        // Try header first
        $api_key = $request->get_header($this->header_name);

        if (!empty($api_key)) {
            return $api_key;
        }

        // Try query parameter (less secure, for testing only)
        return $request->get_param('api_key') ?? '';
    }

    /**
     * Validate API key
     *
     * @since 2.0.0
     * @param string $api_key API key.
     * @return array|WP_Error Validation result or error.
     */
    private function validate_api_key($api_key) {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_api_keys';
        
        $key_data = $wpdb->get_row($wpdb->prepare("
            SELECT id, name, capabilities, rate_limit, status
            FROM {$table}
            WHERE api_key = %s
        ", hash('sha256', $api_key)), ARRAY_A);

        if (!$key_data) {
            $this->logger->warning('Invalid API key attempt', [
                'key_prefix' => substr($api_key, 0, 8) . '***',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);

            return new WP_Error(
                'invalid_api_key',
                'Invalid API key.',
                ['status' => 401]
            );
        }

        // Check if key is active
        if ($key_data['status'] !== 'active') {
            return new WP_Error(
                'inactive_api_key',
                'This API key has been disabled.',
                ['status' => 403]
            );
        }

        // Check rate limit
        $rate_limit_check = $this->check_rate_limit($key_data['id'], $key_data['rate_limit']);
        
        if (is_wp_error($rate_limit_check)) {
            return $rate_limit_check;
        }

        // Update last used timestamp
        $wpdb->update(
            $table,
            ['last_used_at' => current_time('mysql')],
            ['id' => $key_data['id']]
        );

        $this->logger->debug('API key validated', [
            'key_id' => $key_data['id'],
            'key_name' => $key_data['name'],
        ]);

        return $key_data;
    }

    /**
     * Check rate limit
     *
     * @since 2.0.0
     * @param int $key_id API key ID.
     * @param int $rate_limit Requests per hour limit.
     * @return bool|WP_Error True if within limit, error otherwise.
     */
    private function check_rate_limit($key_id, $rate_limit) {
        if (empty($rate_limit)) {
            return true; // No limit
        }

        global $wpdb;

        $table = $wpdb->prefix . 'abc_api_requests';
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$table}
            WHERE api_key = %s
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ", $key_id));

        if ($count >= $rate_limit) {
            $this->logger->warning('API rate limit exceeded', [
                'key_id' => $key_id,
                'limit' => $rate_limit,
                'current' => $count,
            ]);

            return new WP_Error(
                'rate_limit_exceeded',
                sprintf('Rate limit exceeded. Maximum %d requests per hour.', $rate_limit),
                [
                    'status' => 429,
                    'limit' => $rate_limit,
                    'current' => $count,
                    'reset_at' => current_time('timestamp') + 3600,
                ]
            );
        }

        return true;
    }

    /**
     * Get API key capabilities
     *
     * @since 2.0.0
     * @param int $key_id API key ID.
     * @return array Capabilities.
     */
    private function get_api_key_capabilities($key_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_api_keys';
        
        $capabilities = $wpdb->get_var($wpdb->prepare("
            SELECT capabilities
            FROM {$table}
            WHERE id = %d
        ", $key_id));

        if (empty($capabilities)) {
            return ['read'];
        }

        return json_decode($capabilities, true) ?: ['read'];
    }

    /**
     * Generate API key
     *
     * @since 2.0.0
     * @param string $name Key name.
     * @param array $capabilities Capabilities.
     * @param int $rate_limit Requests per hour limit.
     * @return array|WP_Error API key data or error.
     */
    public function generate_api_key($name, $capabilities = ['read'], $rate_limit = 1000) {
        global $wpdb;

        // Generate random API key
        $api_key = 'abc_' . bin2hex(random_bytes(32));
        $api_key_hash = hash('sha256', $api_key);

        $table = $wpdb->prefix . 'abc_api_keys';
        
        $inserted = $wpdb->insert($table, [
            'name' => $name,
            'api_key' => $api_key_hash,
            'capabilities' => wp_json_encode($capabilities),
            'rate_limit' => $rate_limit,
            'status' => 'active',
            'created_at' => current_time('mysql'),
        ]);

        if (!$inserted) {
            return new WP_Error('generation_failed', 'Failed to generate API key');
        }

        $key_id = $wpdb->insert_id;

        $this->logger->info('API key generated', [
            'id' => $key_id,
            'name' => $name,
            'capabilities' => $capabilities,
        ]);

        return [
            'id' => $key_id,
            'key' => $api_key, // Only returned once
            'name' => $name,
            'capabilities' => $capabilities,
            'rate_limit' => $rate_limit,
        ];
    }

    /**
     * Revoke API key
     *
     * @since 2.0.0
     * @param int $key_id API key ID.
     * @return bool Success.
     */
    public function revoke_api_key($key_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_api_keys';
        
        $updated = $wpdb->update(
            $table,
            ['status' => 'revoked'],
            ['id' => $key_id]
        );

        if ($updated) {
            $this->logger->info('API key revoked', ['id' => $key_id]);
        }

        return (bool) $updated;
    }

    /**
     * List API keys
     *
     * @since 2.0.0
     * @return array API keys (without actual key values).
     */
    public function list_api_keys() {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_api_keys';
        
        $keys = $wpdb->get_results("
            SELECT 
                id,
                name,
                capabilities,
                rate_limit,
                status,
                created_at,
                last_used_at
            FROM {$table}
            ORDER BY created_at DESC
        ", ARRAY_A);

        foreach ($keys as &$key) {
            $key['capabilities'] = json_decode($key['capabilities'], true);
        }

        return $keys;
    }

    /**
     * Create API keys table
     *
     * @since 2.0.0
     */
    public static function create_api_keys_table() {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_api_keys';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            api_key varchar(255) NOT NULL,
            capabilities text DEFAULT NULL,
            rate_limit int(11) DEFAULT 1000,
            status varchar(20) DEFAULT 'active',
            created_at datetime NOT NULL,
            last_used_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY api_key (api_key),
            KEY status (status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
