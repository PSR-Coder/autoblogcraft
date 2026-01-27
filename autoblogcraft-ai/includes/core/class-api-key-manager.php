<?php
/**
 * API Key Manager
 *
 * Handles retrieval, validation, and usage tracking of API keys.
 *
 * @package AutoBlogCraft\Core
 * @since 2.1.0
 */

namespace AutoBlogCraft\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Key Manager Class
 */
class API_Key_Manager

    /**
     * Encrypt a value using AES-256-CBC and WP salts
     */
    private function encrypt($value) {
        $method = 'AES-256-CBC';
        $iv = substr(md5(LOGGED_IN_SALT), 0, 16);
        return openssl_encrypt($value, $method, AUTH_KEY, 0, $iv);
    }

    /**
     * Decrypt a value using AES-256-CBC and WP salts
     */
    private function decrypt($value) {
        $method = 'AES-256-CBC';
        $iv = substr(md5(LOGGED_IN_SALT), 0, 16);
        return openssl_decrypt($value, $method, AUTH_KEY, 0, $iv);
    }
{

    /**
     * Get API Key by ID
     *
     * @param int $key_id API Key ID.
     * @return object|null Key object or null if not found/inactive.
     */
    public function get_key($key_id)
    {
        global $wpdb;

        if (empty($key_id)) {
            return null;
        }

        $table_name = $wpdb->prefix . 'abc_api_keys';
        $key = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND status = 'active'",
            $key_id
        ));

        if ($key && isset($key->api_key)) {
            $key->api_key = $this->decrypt($key->api_key);
        }
        return $key;
    }

    /**
     * Get Provider Key
     * 
     * Tries to get a specific key, falling back to a global default for the provider if needed.
     * 
     * @param int $key_id Specific Key ID from campaign config.
     * @param string $provider Provider slug (openai, anthropic, etc).
     * @return string|null Decrypted API key string or null.
     */
    public function get_provider_key($key_id, $provider = '')
    {
        $key_obj = $this->get_key($key_id);

        if ($key_obj) {
            return $key_obj->api_key;
        }
        return null;
    }

    /**
     * Log Key Usage
     * 
     * Increments usage count and updates last used timestamp.
     * 
     * @param int $key_id Key ID.
     * @param int $tokens_used Number of tokens used (optional).
     */
    public function log_usage($key_id, $tokens_used = 0)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'abc_api_keys';

        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name 
            SET usage_count = usage_count + 1, 
                total_tokens_used = total_tokens_used + %d, 
                last_used_at = %s 
            WHERE id = %d",
            $tokens_used,
            current_time('mysql'),
            $key_id
        ));
    }
}
