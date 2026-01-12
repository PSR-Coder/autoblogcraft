<?php
/**
 * AI Key Manager
 *
 * Manages API keys with secure encryption and database operations.
 * Single Responsibility: Key CRUD operations only.
 *
 * @package AutoBlogCraft\AI
 * @since 2.0.0
 */

namespace AutoBlogCraft\AI;

use AutoBlogCraft\Helpers\Encryption;
use AutoBlogCraft\Core\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Key Manager class
 *
 * Handles API key storage, retrieval, and validation.
 * Uses PBKDF2-based encryption for secure key storage.
 */
class Key_Manager {
    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Database table name
     *
     * @var string
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'abc_api_keys';
        $this->logger = Logger::instance();
    }

    /**
     * Add API key
     *
     * @param array $args {
     *     Key configuration
     *
     *     @type string $provider       Provider name (openai, gemini, claude, deepseek)
     *     @type string $api_key        Plaintext API key
     *     @type string $label          Optional label for identification
     *     @type int    $daily_quota    Daily request quota (0 = unlimited)
     *     @type int    $monthly_quota  Monthly request quota (0 = unlimited)
     * }
     * @return int|WP_Error Key ID or error
     */
    public function add_key($args) {
        // Validate required fields
        if (empty($args['provider']) || empty($args['api_key'])) {
            return new WP_Error(
                'missing_required_fields',
                __('Provider and API key are required', 'autoblogcraft-ai')
            );
        }

        // Validate provider
        $valid_providers = ['openai', 'gemini', 'claude', 'deepseek'];
        if (!in_array($args['provider'], $valid_providers, true)) {
            return new WP_Error(
                'invalid_provider',
                sprintf(
                    __('Invalid provider. Must be one of: %s', 'autoblogcraft-ai'),
                    implode(', ', $valid_providers)
                )
            );
        }

        // Encrypt API key
        $encrypted = Encryption::encrypt($args['api_key']);
        
        if (is_wp_error($encrypted)) {
            $this->logger->error(
                null,
                'ai',
                'Failed to encrypt API key: ' . $encrypted->get_error_message()
            );
            return $encrypted;
        }

        // Prepare data
        global $wpdb;
        $data = [
            'provider' => sanitize_text_field($args['provider']),
            'key_encrypted' => $encrypted,
            'label' => !empty($args['label']) ? sanitize_text_field($args['label']) : '',
            'daily_quota' => isset($args['daily_quota']) ? absint($args['daily_quota']) : 0,
            'monthly_quota' => isset($args['monthly_quota']) ? absint($args['monthly_quota']) : 0,
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        // Insert
        $inserted = $wpdb->insert($this->table_name, $data, [
            '%s', // provider
            '%s', // key_encrypted
            '%s', // label
            '%d', // daily_quota
            '%d', // monthly_quota
            '%s', // status
            '%s', // created_at
            '%s', // updated_at
        ]);

        if ($inserted === false) {
            $this->logger->error(
                null,
                'ai',
                'Database error adding API key: ' . $wpdb->last_error
            );
            return new WP_Error('db_error', __('Failed to save API key', 'autoblogcraft-ai'));
        }

        $key_id = $wpdb->insert_id;

        $this->logger->info(
            null,
            'ai',
            sprintf('Added %s API key (ID: %d)', $args['provider'], $key_id)
        );

        return $key_id;
    }

    /**
     * Get API key by ID
     *
     * @param int $key_id Key ID
     * @param bool $decrypt Whether to decrypt the key
     * @return array|WP_Error Key data or error
     */
    public function get_key($key_id, $decrypt = true) {
        global $wpdb;

        $key_id = absint($key_id);
        if ($key_id === 0) {
            return new WP_Error('invalid_key_id', __('Invalid key ID', 'autoblogcraft-ai'));
        }

        $key = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $key_id),
            ARRAY_A
        );

        if (!$key) {
            return new WP_Error('key_not_found', __('API key not found', 'autoblogcraft-ai'));
        }

        // Decrypt if requested
        if ($decrypt && !empty($key['key_encrypted'])) {
            $decrypted = Encryption::decrypt($key['key_encrypted']);
            
            if (is_wp_error($decrypted)) {
                $this->logger->error(
                    null,
                    'ai',
                    sprintf('Failed to decrypt key %d: %s', $key_id, $decrypted->get_error_message())
                );
                return $decrypted;
            }

            $key['api_key'] = $decrypted;
            unset($key['key_encrypted']); // Remove encrypted version from response
        }

        return $key;
    }

    /**
     * Get all keys for a provider
     *
     * @param string $provider Provider name
     * @param bool $active_only Only return active keys
     * @return array Array of key data (without decrypted keys)
     */
    public function get_keys_by_provider($provider, $active_only = true) {
        global $wpdb;

        $where = $wpdb->prepare('WHERE provider = %s', $provider);
        
        if ($active_only) {
            $where .= " AND status = 'active'";
        }

        $keys = $wpdb->get_results(
            "SELECT id, provider, label, daily_quota, monthly_quota, requests_today, requests_month, last_used, status, created_at 
            FROM {$this->table_name} 
            {$where}
            ORDER BY created_at DESC",
            ARRAY_A
        );

        return $keys ?: [];
    }

    /**
     * Update key metadata (not the key itself)
     *
     * @param int $key_id Key ID
     * @param array $data Data to update (label, quotas, status)
     * @return bool|WP_Error
     */
    public function update_key($key_id, $data) {
        global $wpdb;

        $key_id = absint($key_id);
        
        // Verify key exists
        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$this->table_name} WHERE id = %d", $key_id)
        );

        if (!$exists) {
            return new WP_Error('key_not_found', __('API key not found', 'autoblogcraft-ai'));
        }

        // Only allow updating specific fields
        $allowed_fields = ['label', 'daily_quota', 'monthly_quota', 'status'];
        $update_data = [];
        $format = [];

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                switch ($field) {
                    case 'label':
                        $update_data[$field] = sanitize_text_field($data[$field]);
                        $format[] = '%s';
                        break;
                    case 'daily_quota':
                    case 'monthly_quota':
                        $update_data[$field] = absint($data[$field]);
                        $format[] = '%d';
                        break;
                    case 'status':
                        if (in_array($data[$field], ['active', 'inactive', 'error'], true)) {
                            $update_data[$field] = $data[$field];
                            $format[] = '%s';
                        }
                        break;
                }
            }
        }

        if (empty($update_data)) {
            return new WP_Error('no_valid_fields', __('No valid fields to update', 'autoblogcraft-ai'));
        }

        $update_data['updated_at'] = current_time('mysql');
        $format[] = '%s';

        $updated = $wpdb->update(
            $this->table_name,
            $update_data,
            ['id' => $key_id],
            $format,
            ['%d']
        );

        if ($updated === false) {
            return new WP_Error('db_error', __('Failed to update key', 'autoblogcraft-ai'));
        }

        $this->logger->info(
            null,
            'ai',
            sprintf('Updated API key %d', $key_id)
        );

        return true;
    }

    /**
     * Delete API key
     *
     * @param int $key_id Key ID
     * @return bool|WP_Error
     */
    public function delete_key($key_id) {
        global $wpdb;

        $key_id = absint($key_id);

        // Check if key is in use by any campaign
        $in_use = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}abc_campaign_ai_config WHERE primary_key_id = %d",
                $key_id
            )
        );

        if ($in_use > 0) {
            return new WP_Error(
                'key_in_use',
                __('Cannot delete API key that is in use by campaigns', 'autoblogcraft-ai')
            );
        }

        $deleted = $wpdb->delete(
            $this->table_name,
            ['id' => $key_id],
            ['%d']
        );

        if ($deleted === false) {
            return new WP_Error('db_error', __('Failed to delete key', 'autoblogcraft-ai'));
        }

        $this->logger->info(
            null,
            'ai',
            sprintf('Deleted API key %d', $key_id)
        );

        return true;
    }

    /**
     * Track API key usage
     *
     * @param int $key_id Key ID
     * @param int $tokens Tokens consumed
     * @return bool
     */
    public function track_usage($key_id, $tokens = 0) {
        global $wpdb;

        $key_id = absint($key_id);
        $tokens = absint($tokens);

        // Increment request counters
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table_name} 
                SET requests_today = requests_today + 1,
                    requests_month = requests_month + 1,
                    tokens_used = tokens_used + %d,
                    last_used = %s,
                    updated_at = %s
                WHERE id = %d",
                $tokens,
                current_time('mysql'),
                current_time('mysql'),
                $key_id
            )
        );

        return $updated !== false;
    }

    /**
     * Check if key has quota available
     *
     * @param int $key_id Key ID
     * @return bool|WP_Error True if available, WP_Error if quota exceeded
     */
    public function check_quota($key_id) {
        $key = $this->get_key($key_id, false);

        if (is_wp_error($key)) {
            return $key;
        }

        // Check daily quota
        if ($key['daily_quota'] > 0 && $key['requests_today'] >= $key['daily_quota']) {
            return new WP_Error(
                'daily_quota_exceeded',
                sprintf(
                    __('Daily quota exceeded (%d/%d)', 'autoblogcraft-ai'),
                    $key['requests_today'],
                    $key['daily_quota']
                )
            );
        }

        // Check monthly quota
        if ($key['monthly_quota'] > 0 && $key['requests_month'] >= $key['monthly_quota']) {
            return new WP_Error(
                'monthly_quota_exceeded',
                sprintf(
                    __('Monthly quota exceeded (%d/%d)', 'autoblogcraft-ai'),
                    $key['requests_month'],
                    $key['monthly_quota']
                )
            );
        }

        return true;
    }

    /**
     * Reset daily counters for all keys
     * Called by cron job daily
     *
     * @return int Number of keys reset
     */
    public function reset_daily_counters() {
        global $wpdb;

        $reset = $wpdb->query(
            "UPDATE {$this->table_name} 
            SET requests_today = 0, 
                updated_at = '" . current_time('mysql') . "'"
        );

        $this->logger->info(
            null,
            'ai',
            sprintf('Reset daily counters for %d API keys', $reset)
        );

        return $reset !== false ? $reset : 0;
    }

    /**
     * Reset monthly counters for all keys
     * Called by cron job monthly
     *
     * @return int Number of keys reset
     */
    public function reset_monthly_counters() {
        global $wpdb;

        $reset = $wpdb->query(
            "UPDATE {$this->table_name} 
            SET requests_month = 0, 
                updated_at = '" . current_time('mysql') . "'"
        );

        $this->logger->info(
            null,
            'ai',
            sprintf('Reset monthly counters for %d API keys', $reset)
        );

        return $reset !== false ? $reset : 0;
    }
}
