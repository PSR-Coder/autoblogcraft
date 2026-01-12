<?php
/**
 * Sanitization Helper
 *
 * Provides comprehensive input sanitization and validation for AutoBlogCraft.
 * Ensures data integrity and security throughout the plugin.
 *
 * @package AutoBlogCraft\Helpers
 * @since 2.0.0
 */

namespace AutoBlogCraft\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sanitization class
 *
 * Responsibilities:
 * - Sanitize campaign metadata
 * - Validate and clean URLs
 * - Sanitize JSON data
 * - Clean HTML content
 * - Validate API responses
 *
 * @since 2.0.0
 */
class Sanitization {

    /**
     * Sanitize campaign meta data
     *
     * @since 2.0.0
     * @param array $meta Raw meta data.
     * @return array Sanitized meta data.
     */
    public static function sanitize_campaign_meta($meta) {
        $sanitized = [];

        // Campaign type
        if (isset($meta['campaign_type'])) {
            $sanitized['campaign_type'] = self::sanitize_campaign_type($meta['campaign_type']);
        }

        // Status
        if (isset($meta['campaign_status'])) {
            $sanitized['campaign_status'] = self::sanitize_status($meta['campaign_status']);
        }

        // Schedule interval
        if (isset($meta['schedule_interval'])) {
            $sanitized['schedule_interval'] = self::sanitize_interval($meta['schedule_interval']);
        }

        // Posts per day
        if (isset($meta['posts_per_day'])) {
            $sanitized['posts_per_day'] = absint($meta['posts_per_day']);
        }

        // Target languages
        if (isset($meta['target_languages'])) {
            $sanitized['target_languages'] = self::sanitize_language_array($meta['target_languages']);
        }

        // Source URLs
        if (isset($meta['source_urls'])) {
            $sanitized['source_urls'] = self::sanitize_url_array($meta['source_urls']);
        }

        // Keywords
        if (isset($meta['keywords'])) {
            $sanitized['keywords'] = self::sanitize_keywords($meta['keywords']);
        }

        // Author ID
        if (isset($meta['author_id'])) {
            $sanitized['author_id'] = absint($meta['author_id']);
        }

        // Categories
        if (isset($meta['categories'])) {
            $sanitized['categories'] = array_map('absint', (array) $meta['categories']);
        }

        // Tags
        if (isset($meta['tags'])) {
            $sanitized['tags'] = array_map('sanitize_text_field', (array) $meta['tags']);
        }

        // Enable SEO
        if (isset($meta['enable_seo'])) {
            $sanitized['enable_seo'] = (bool) $meta['enable_seo'];
        }

        // Enable humanizer
        if (isset($meta['enable_humanizer'])) {
            $sanitized['enable_humanizer'] = (bool) $meta['enable_humanizer'];
        }

        // Custom fields
        if (isset($meta['custom_fields'])) {
            $sanitized['custom_fields'] = self::sanitize_custom_fields($meta['custom_fields']);
        }

        return $sanitized;
    }

    /**
     * Sanitize campaign type
     *
     * @since 2.0.0
     * @param string $type Campaign type.
     * @return string
     */
    public static function sanitize_campaign_type($type) {
        $valid_types = ['rss', 'youtube', 'amazon', 'news', 'website'];
        return in_array($type, $valid_types) ? $type : 'rss';
    }

    /**
     * Sanitize campaign status
     *
     * @since 2.0.0
     * @param string $status Campaign status.
     * @return string
     */
    public static function sanitize_status($status) {
        $valid_statuses = ['active', 'paused', 'draft'];
        return in_array($status, $valid_statuses) ? $status : 'draft';
    }

    /**
     * Sanitize schedule interval
     *
     * @since 2.0.0
     * @param string $interval Schedule interval.
     * @return string
     */
    public static function sanitize_interval($interval) {
        $valid_intervals = ['hourly', 'twicedaily', 'daily', 'weekly'];
        return in_array($interval, $valid_intervals) ? $interval : 'daily';
    }

    /**
     * Sanitize array of URLs
     *
     * @since 2.0.0
     * @param array|string $urls URLs to sanitize.
     * @return array
     */
    public static function sanitize_url_array($urls) {
        if (is_string($urls)) {
            $urls = explode("\n", $urls);
        }

        if (!is_array($urls)) {
            return [];
        }

        $sanitized = [];
        foreach ($urls as $url) {
            $url = trim($url);
            $sanitized_url = esc_url_raw($url);
            
            if (!empty($sanitized_url) && filter_var($sanitized_url, FILTER_VALIDATE_URL)) {
                $sanitized[] = $sanitized_url;
            }
        }

        return array_unique($sanitized);
    }

    /**
     * Sanitize language array
     *
     * @since 2.0.0
     * @param array|string $languages Language codes.
     * @return array
     */
    public static function sanitize_language_array($languages) {
        if (is_string($languages)) {
            $languages = explode(',', $languages);
        }

        if (!is_array($languages)) {
            return [];
        }

        $sanitized = [];
        foreach ($languages as $lang) {
            $lang = strtolower(trim($lang));
            // Validate language code format (2 or 5 characters: en, en-US)
            if (preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $lang)) {
                $sanitized[] = $lang;
            }
        }

        return array_unique($sanitized);
    }

    /**
     * Sanitize keywords
     *
     * @since 2.0.0
     * @param array|string $keywords Keywords to sanitize.
     * @return array
     */
    public static function sanitize_keywords($keywords) {
        if (is_string($keywords)) {
            $keywords = explode(',', $keywords);
        }

        if (!is_array($keywords)) {
            return [];
        }

        $sanitized = [];
        foreach ($keywords as $keyword) {
            $keyword = sanitize_text_field(trim($keyword));
            if (!empty($keyword)) {
                $sanitized[] = $keyword;
            }
        }

        return array_unique($sanitized);
    }

    /**
     * Sanitize custom fields
     *
     * @since 2.0.0
     * @param array $fields Custom fields.
     * @return array
     */
    public static function sanitize_custom_fields($fields) {
        if (!is_array($fields)) {
            return [];
        }

        $sanitized = [];
        foreach ($fields as $key => $value) {
            $key = sanitize_key($key);
            
            if (is_array($value)) {
                $sanitized[$key] = self::sanitize_custom_fields($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize JSON data
     *
     * @since 2.0.0
     * @param string|array $json JSON string or array.
     * @return array|false
     */
    public static function sanitize_json($json) {
        if (is_array($json)) {
            return $json;
        }

        if (!is_string($json)) {
            return false;
        }

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        return $decoded;
    }

    /**
     * Sanitize HTML content
     *
     * @since 2.0.0
     * @param string $content HTML content.
     * @param bool   $strip   Strip all HTML tags.
     * @return string
     */
    public static function sanitize_html($content, $strip = false) {
        if ($strip) {
            return wp_strip_all_tags($content);
        }

        // Allow specific HTML tags
        $allowed_tags = [
            'a' => ['href' => true, 'title' => true, 'rel' => true, 'target' => true],
            'p' => [],
            'br' => [],
            'strong' => [],
            'b' => [],
            'em' => [],
            'i' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'h1' => [],
            'h2' => [],
            'h3' => [],
            'h4' => [],
            'h5' => [],
            'h6' => [],
            'blockquote' => [],
            'code' => [],
            'pre' => [],
        ];

        return wp_kses($content, $allowed_tags);
    }

    /**
     * Sanitize API response
     *
     * @since 2.0.0
     * @param mixed $response API response.
     * @return array
     */
    public static function sanitize_api_response($response) {
        if (!is_array($response)) {
            return [];
        }

        $sanitized = [];

        // Common API response fields
        if (isset($response['title'])) {
            $sanitized['title'] = sanitize_text_field($response['title']);
        }

        if (isset($response['content'])) {
            $sanitized['content'] = self::sanitize_html($response['content']);
        }

        if (isset($response['excerpt'])) {
            $sanitized['excerpt'] = sanitize_textarea_field($response['excerpt']);
        }

        if (isset($response['url'])) {
            $sanitized['url'] = esc_url_raw($response['url']);
        }

        if (isset($response['author'])) {
            $sanitized['author'] = sanitize_text_field($response['author']);
        }

        if (isset($response['date'])) {
            $sanitized['date'] = sanitize_text_field($response['date']);
        }

        if (isset($response['tags']) && is_array($response['tags'])) {
            $sanitized['tags'] = array_map('sanitize_text_field', $response['tags']);
        }

        if (isset($response['categories']) && is_array($response['categories'])) {
            $sanitized['categories'] = array_map('sanitize_text_field', $response['categories']);
        }

        return $sanitized;
    }

    /**
     * Validate email address
     *
     * @since 2.0.0
     * @param string $email Email address.
     * @return string|false
     */
    public static function sanitize_email($email) {
        $email = sanitize_email($email);
        return is_email($email) ? $email : false;
    }

    /**
     * Sanitize phone number
     *
     * @since 2.0.0
     * @param string $phone Phone number.
     * @return string
     */
    public static function sanitize_phone($phone) {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        return sanitize_text_field($phone);
    }

    /**
     * Sanitize hex color
     *
     * @since 2.0.0
     * @param string $color Hex color code.
     * @return string
     */
    public static function sanitize_hex_color($color) {
        // Remove # if present
        $color = ltrim($color, '#');

        // Validate hex color
        if (preg_match('/^[A-Fa-f0-9]{6}$/', $color)) {
            return '#' . $color;
        }

        // Return default if invalid
        return '#000000';
    }

    /**
     * Sanitize file path
     *
     * @since 2.0.0
     * @param string $path File path.
     * @return string
     */
    public static function sanitize_file_path($path) {
        // Remove null bytes
        $path = str_replace(chr(0), '', $path);
        
        // Remove multiple slashes
        $path = preg_replace('#/{2,}#', '/', $path);
        
        // Remove ../ to prevent directory traversal
        $path = str_replace('../', '', $path);
        
        return sanitize_text_field($path);
    }

    /**
     * Sanitize database table name
     *
     * @since 2.0.0
     * @param string $table_name Table name.
     * @return string
     */
    public static function sanitize_table_name($table_name) {
        global $wpdb;
        
        // Only allow alphanumeric and underscore
        $table_name = preg_replace('/[^a-zA-Z0-9_]/', '', $table_name);
        
        // Add prefix if not present
        if (strpos($table_name, $wpdb->prefix) !== 0) {
            $table_name = $wpdb->prefix . $table_name;
        }
        
        return $table_name;
    }
}
