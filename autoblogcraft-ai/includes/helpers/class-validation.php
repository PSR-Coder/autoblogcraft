<?php
/**
 * Validation Helpers
 *
 * Input validation functions
 *
 * @package AutoBlogCraft\Helpers
 * @since 2.0.0
 */

namespace AutoBlogCraft\Helpers;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Validation
 *
 * Comprehensive input validation
 */
class Validation {

    /**
     * Validate URL
     *
     * @param string $url URL to validate
     * @return bool
     */
    public static function is_valid_url($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate RSS/Feed URL
     *
     * @param string $url URL to validate
     * @return bool
     */
    public static function is_valid_rss_url($url) {
        if (!self::is_valid_url($url)) {
            return false;
        }

        // Check if URL contains common feed patterns
        return preg_match('/\.xml$|\/feed\/|\/rss\/|\/atom\//i', $url) === 1;
    }

    /**
     * Validate sitemap URL
     *
     * @param string $url URL to validate
     * @return bool
     */
    public static function is_valid_sitemap_url($url) {
        if (!self::is_valid_url($url)) {
            return false;
        }

        return preg_match('/sitemap.*\.xml$/i', $url) === 1;
    }

    /**
     * Validate YouTube channel URL
     *
     * @param string $url URL to validate
     * @return bool
     */
    public static function is_valid_youtube_channel($url) {
        if (!self::is_valid_url($url)) {
            return false;
        }

        return preg_match('/youtube\.com\/(channel\/|c\/|user\/|@)/i', $url) === 1;
    }

    /**
     * Validate YouTube playlist URL
     *
     * @param string $url URL to validate
     * @return bool
     */
    public static function is_valid_youtube_playlist($url) {
        if (!self::is_valid_url($url)) {
            return false;
        }

        return preg_match('/youtube\.com\/.*[?&]list=/i', $url) === 1;
    }

    /**
     * Validate JSON string
     *
     * @param string $json JSON to validate
     * @return bool
     */
    public static function is_valid_json($json) {
        if (!is_string($json) || empty($json)) {
            return false;
        }

        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Validate campaign type
     *
     * @param string $type Campaign type
     * @return bool
     */
    public static function is_valid_campaign_type($type) {
        $valid_types = ['website', 'youtube', 'amazon', 'news'];
        return in_array($type, $valid_types, true);
    }

    /**
     * Validate AI provider
     *
     * @param string $provider Provider name
     * @return bool
     */
    public static function is_valid_ai_provider($provider) {
        $valid_providers = ['openai', 'gemini', 'claude', 'deepseek'];
        return in_array($provider, $valid_providers, true);
    }

    /**
     * Validate log level
     *
     * @param string $level Log level
     * @return bool
     */
    public static function is_valid_log_level($level) {
        $valid_levels = ['debug', 'info', 'success', 'warning', 'error'];
        return in_array($level, $valid_levels, true);
    }

    /**
     * Validate status
     *
     * @param string $status Status value
     * @return bool
     */
    public static function is_valid_status($status) {
        $valid_statuses = ['pending', 'processing', 'completed', 'failed', 'skipped'];
        return in_array($status, $valid_statuses, true);
    }

    /**
     * Validate campaign status
     *
     * @param string $status Campaign status
     * @return bool
     */
    public static function is_valid_campaign_status($status) {
        $valid_statuses = ['active', 'paused', 'archived'];
        return in_array($status, $valid_statuses, true);
    }

    /**
     * Validate email address
     *
     * @param string $email Email to validate
     * @return bool
     */
    public static function is_valid_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate integer range
     *
     * @param mixed $value Value to validate
     * @param int $min Minimum value
     * @param int $max Maximum value
     * @return bool
     */
    public static function is_valid_int_range($value, $min, $max) {
        if (!is_numeric($value)) {
            return false;
        }

        $int_value = (int) $value;
        return $int_value >= $min && $int_value <= $max;
    }

    /**
     * Validate temperature (0.0 - 2.0)
     *
     * @param mixed $value Temperature value
     * @return bool
     */
    public static function is_valid_temperature($value) {
        if (!is_numeric($value)) {
            return false;
        }

        $float_value = (float) $value;
        return $float_value >= 0.0 && $float_value <= 2.0;
    }

    /**
     * Validate array of values
     *
     * @param mixed $value Value to validate
     * @param callable $validator Validation callback
     * @return bool
     */
    public static function is_valid_array($value, callable $validator) {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!$validator($item)) {
                return false;
            }
        }

        return true;
    }
}
