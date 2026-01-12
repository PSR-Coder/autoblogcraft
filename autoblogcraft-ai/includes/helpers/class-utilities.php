<?php
/**
 * Utility Functions
 *
 * General-purpose utility functions for AutoBlogCraft.
 * Provides common operations used throughout the plugin.
 *
 * @package AutoBlogCraft\Helpers
 * @since 2.0.0
 */

namespace AutoBlogCraft\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Utilities class
 *
 * Responsibilities:
 * - Parse time intervals
 * - Format data for display
 * - Handle array operations
 * - Provide date/time helpers
 * - Manage file operations
 *
 * @since 2.0.0
 */
class Utilities {

    /**
     * Parse interval string to seconds
     *
     * @since 2.0.0
     * @param string $interval Interval string (e.g., '5min', '1hr', '2days').
     * @return int Seconds.
     */
    public static function parse_interval($interval) {
        $interval = strtolower(trim($interval));
        
        // Check if already in seconds
        if (is_numeric($interval)) {
            return absint($interval);
        }
        
        // Parse interval
        preg_match('/^(\d+)\s*([a-z]+)$/', $interval, $matches);
        
        if (count($matches) !== 3) {
            return 0;
        }
        
        $value = absint($matches[1]);
        $unit = $matches[2];
        
        $multipliers = [
            's' => 1,
            'sec' => 1,
            'second' => 1,
            'seconds' => 1,
            'm' => 60,
            'min' => 60,
            'minute' => 60,
            'minutes' => 60,
            'h' => 3600,
            'hr' => 3600,
            'hour' => 3600,
            'hours' => 3600,
            'd' => 86400,
            'day' => 86400,
            'days' => 86400,
            'w' => 604800,
            'week' => 604800,
            'weeks' => 604800,
        ];
        
        return $value * ($multipliers[$unit] ?? 0);
    }

    /**
     * Format bytes to human-readable size
     *
     * @since 2.0.0
     * @param int $bytes     Bytes.
     * @param int $precision Decimal precision.
     * @return string Formatted size.
     */
    public static function format_bytes($bytes, $precision = 2) {
        $bytes = max(0, (int) $bytes);
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Truncate text with ellipsis
     *
     * @since 2.0.0
     * @param string $text      Text to truncate.
     * @param int    $length    Maximum length.
     * @param string $ellipsis  Ellipsis string.
     * @param bool   $word_safe Truncate at word boundary.
     * @return string Truncated text.
     */
    public static function truncate_text($text, $length = 100, $ellipsis = '...', $word_safe = true) {
        $text = wp_strip_all_tags($text);
        
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        
        $truncated = mb_substr($text, 0, $length);
        
        if ($word_safe) {
            // Find last space
            $last_space = mb_strrpos($truncated, ' ');
            if ($last_space !== false) {
                $truncated = mb_substr($truncated, 0, $last_space);
            }
        }
        
        return $truncated . $ellipsis;
    }

    /**
     * Get campaign type label
     *
     * @since 2.0.0
     * @param string $type Campaign type.
     * @return string Localized label.
     */
    public static function get_campaign_type_label($type) {
        $labels = [
            'rss' => __('RSS Feed', 'autoblogcraft'),
            'youtube' => __('YouTube', 'autoblogcraft'),
            'amazon' => __('Amazon Products', 'autoblogcraft'),
            'news' => __('News Articles', 'autoblogcraft'),
            'website' => __('Website Scraping', 'autoblogcraft'),
        ];
        
        return $labels[$type] ?? __('Unknown', 'autoblogcraft');
    }

    /**
     * Get status label
     *
     * @since 2.0.0
     * @param string $status Status.
     * @return string Localized label.
     */
    public static function get_status_label($status) {
        $labels = [
            'active' => __('Active', 'autoblogcraft'),
            'paused' => __('Paused', 'autoblogcraft'),
            'draft' => __('Draft', 'autoblogcraft'),
            'pending' => __('Pending', 'autoblogcraft'),
            'processing' => __('Processing', 'autoblogcraft'),
            'completed' => __('Completed', 'autoblogcraft'),
            'failed' => __('Failed', 'autoblogcraft'),
            'skipped' => __('Skipped', 'autoblogcraft'),
        ];
        
        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * Get status badge HTML
     *
     * @since 2.0.0
     * @param string $status Status.
     * @return string HTML badge.
     */
    public static function get_status_badge($status) {
        $colors = [
            'active' => 'green',
            'paused' => 'orange',
            'draft' => 'gray',
            'pending' => 'blue',
            'processing' => 'blue',
            'completed' => 'green',
            'failed' => 'red',
            'skipped' => 'gray',
        ];
        
        $color = $colors[$status] ?? 'gray';
        $label = self::get_status_label($status);
        
        return sprintf(
            '<span class="abc-badge abc-badge-%s">%s</span>',
            esc_attr($color),
            esc_html($label)
        );
    }

    /**
     * Format relative time
     *
     * @since 2.0.0
     * @param int|string $timestamp Timestamp.
     * @return string Relative time.
     */
    public static function format_relative_time($timestamp) {
        if (is_string($timestamp)) {
            $timestamp = strtotime($timestamp);
        }
        
        return human_time_diff($timestamp, current_time('timestamp')) . ' ' . __('ago', 'autoblogcraft');
    }

    /**
     * Format date
     *
     * @since 2.0.0
     * @param int|string $timestamp Timestamp.
     * @param string     $format    Date format.
     * @return string Formatted date.
     */
    public static function format_date($timestamp, $format = null) {
        if (is_string($timestamp)) {
            $timestamp = strtotime($timestamp);
        }
        
        if ($format === null) {
            $format = get_option('date_format') . ' ' . get_option('time_format');
        }
        
        return date_i18n($format, $timestamp);
    }

    /**
     * Check if array is associative
     *
     * @since 2.0.0
     * @param array $array Array to check.
     * @return bool
     */
    public static function is_associative_array($array) {
        if (!is_array($array) || empty($array)) {
            return false;
        }
        
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Array get with default
     *
     * @since 2.0.0
     * @param array  $array   Array.
     * @param string $key     Key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public static function array_get($array, $key, $default = null) {
        if (!is_array($array)) {
            return $default;
        }
        
        // Support dot notation
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            foreach ($keys as $k) {
                if (!isset($array[$k])) {
                    return $default;
                }
                $array = $array[$k];
            }
            return $array;
        }
        
        return $array[$key] ?? $default;
    }

    /**
     * Remove empty values from array
     *
     * @since 2.0.0
     * @param array $array Array to clean.
     * @return array Cleaned array.
     */
    public static function array_remove_empty($array) {
        return array_filter($array, function($value) {
            return !empty($value);
        });
    }

    /**
     * Get file extension
     *
     * @since 2.0.0
     * @param string $filename Filename.
     * @return string Extension.
     */
    public static function get_file_extension($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * Check if file is image
     *
     * @since 2.0.0
     * @param string $filename Filename.
     * @return bool
     */
    public static function is_image_file($filename) {
        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        return in_array(self::get_file_extension($filename), $image_extensions);
    }

    /**
     * Generate random string
     *
     * @since 2.0.0
     * @param int    $length     Length.
     * @param string $characters Allowed characters.
     * @return string Random string.
     */
    public static function random_string($length = 10, $characters = null) {
        if ($characters === null) {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }
        
        $string = '';
        $max = strlen($characters) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[random_int(0, $max)];
        }
        
        return $string;
    }

    /**
     * Convert string to slug
     *
     * @since 2.0.0
     * @param string $string String to convert.
     * @return string Slug.
     */
    public static function str_to_slug($string) {
        return sanitize_title($string);
    }

    /**
     * Extract domain from URL
     *
     * @since 2.0.0
     * @param string $url URL.
     * @return string Domain.
     */
    public static function get_domain($url) {
        $parsed = parse_url($url);
        return $parsed['host'] ?? '';
    }

    /**
     * Check if URL is valid
     *
     * @since 2.0.0
     * @param string $url URL to check.
     * @return bool
     */
    public static function is_valid_url($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Get percentage
     *
     * @since 2.0.0
     * @param int $value Value.
     * @param int $total Total.
     * @param int $precision Decimal precision.
     * @return float Percentage.
     */
    public static function get_percentage($value, $total, $precision = 2) {
        if ($total == 0) {
            return 0;
        }
        
        return round(($value / $total) * 100, $precision);
    }

    /**
     * Pluralize word
     *
     * @since 2.0.0
     * @param int    $count    Count.
     * @param string $singular Singular form.
     * @param string $plural   Plural form (optional).
     * @return string Pluralized word.
     */
    public static function pluralize($count, $singular, $plural = null) {
        if ($plural === null) {
            $plural = $singular . 's';
        }
        
        return $count == 1 ? $singular : $plural;
    }

    /**
     * Convert array to CSV
     *
     * @since 2.0.0
     * @param array $data Data array.
     * @return string CSV string.
     */
    public static function array_to_csv($data) {
        if (empty($data)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }

    /**
     * Check if string starts with
     *
     * @since 2.0.0
     * @param string $string String to check.
     * @param string $prefix Prefix.
     * @return bool
     */
    public static function starts_with($string, $prefix) {
        return substr($string, 0, strlen($prefix)) === $prefix;
    }

    /**
     * Check if string ends with
     *
     * @since 2.0.0
     * @param string $string String to check.
     * @param string $suffix Suffix.
     * @return bool
     */
    public static function ends_with($string, $suffix) {
        return substr($string, -strlen($suffix)) === $suffix;
    }

    /**
     * Clamp value between min and max
     *
     * @since 2.0.0
     * @param int|float $value Value.
     * @param int|float $min   Minimum.
     * @param int|float $max   Maximum.
     * @return int|float Clamped value.
     */
    public static function clamp($value, $min, $max) {
        return max($min, min($max, $value));
    }
}
