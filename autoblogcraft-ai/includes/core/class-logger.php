<?php
/**
 * Logger Class
 *
 * @package AutoBlogCraft\Core
 * @since 2.0.0
 */

namespace AutoBlogCraft\Core;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Logger
 *
 * Centralized logging system - Singleton pattern
 */
class Logger
{

    /**
     * Singleton instance
     *
     * @var Logger
     */
    protected static $instance = null;

    /**
     * Log levels
     */
    const DEBUG = 'debug';
    const INFO = 'info';
    const SUCCESS = 'success';
    const WARNING = 'warning';
    const ERROR = 'error';

    /**
     * Log level priorities (higher = more important)
     */
    private static $level_priority = [
        'debug' => 1,
        'info' => 2,
        'success' => 3,
        'warning' => 4,
        'error' => 5,
    ];

    /**
     * Get singleton instance
     *
     * @return Logger
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        // Private constructor
    }

    /**
     * Initialize logger
     */
    public function init()
    {
        // Future: Hook into admin notices for critical errors
    }

    /**
     * Log a message
     *
     * @param int|null $campaign_id Campaign ID (null for system logs)
     * @param string $level Log level (debug|info|success|warning|error)
     * @param string $category Category (discovery|processing|ai|cron|system)
     * @param string $message Log message
     * @param array $context Additional context data
     * @param int|null $queue_item_id Queue item ID
     * @param int|null $post_id Post ID
     * @return int|false Log ID or false on failure
     */
    public function log($campaign_id, $level, $category, $message, $context = [], $queue_item_id = null, $post_id = null)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'abc_logs';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            // Table doesn't exist yet, skip logging during installation
            return false;
        }

        // Check if this log level should be recorded based on settings
        if (!$this->should_log($level)) {
            return false;
        }

        // Prepare data
        $data = [
            'campaign_id' => $campaign_id,
            'level' => sanitize_text_field($level),
            'category' => sanitize_text_field($category),
            'message' => sanitize_text_field($message),
            'context' => !empty($context) ? wp_json_encode($context) : null,
            'queue_item_id' => $queue_item_id,
            'post_id' => $post_id,
            'created_at' => current_time('mysql'),
        ];

        // Add stack trace for errors
        if ($level === self::ERROR) {
            $data['stack_trace'] = wp_debug_backtrace_summary();
        }

        // Insert log entry
        $result = $wpdb->insert($table_name, $data);

        if ($result === false) {
            // Fallback to error_log if database insert fails
            error_log(sprintf(
                '[AutoBlogCraft] [%s] [%s] %s',
                strtoupper($level),
                $category,
                $message
            ));
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Check if a log level should be recorded
     *
     * @param string $level Log level to check
     * @return bool True if should log, false otherwise
     */
    private function should_log($level) {
        // Get configured minimum log level (default: info)
        $min_level = get_option('abc_log_level', 'info');

        // Get priorities
        $level_priority = self::$level_priority[$level] ?? 1;
        $min_priority = self::$level_priority[$min_level] ?? 2;

        // Log if current level priority is >= minimum priority
        // Exception: Always allow DEBUG in WP_DEBUG mode
        if ($level === self::DEBUG && (defined('WP_DEBUG') && WP_DEBUG || defined('ABC_DEBUG'))) {
            return true;
        }

        return $level_priority >= $min_priority;
    }

    /**
     * Shorthand methods for different log levels
     */

    public function debug($campaign_id = 0, $category = 'general', $message = '', $context = [])
    {
        // Allow calling with just message as first param
        if (is_string($campaign_id) && empty($message)) {
            $message = $campaign_id;
            $campaign_id = 0;
            $category = 'general';
        }
        return $this->log($campaign_id, self::DEBUG, $category, $message, $context);
    }

    public function info($campaign_id = 0, $category = 'general', $message = '', $context = [])
    {
        // Allow calling with just message as first param
        if (is_string($campaign_id) && empty($message)) {
            $message = $campaign_id;
            $campaign_id = 0;
            $category = 'general';
        }
        return $this->log($campaign_id, self::INFO, $category, $message, $context);
    }

    public function success($campaign_id = 0, $category = 'general', $message = '', $context = [])
    {
        // Allow calling with just message as first param
        if (is_string($campaign_id) && empty($message)) {
            $message = $campaign_id;
            $campaign_id = 0;
            $category = 'general';
        }
        return $this->log($campaign_id, self::SUCCESS, $category, $message, $context);
    }

    public function warning($campaign_id = 0, $category = 'general', $message = '', $context = [])
    {
        // Allow calling with just message as first param
        if (is_string($campaign_id) && empty($message)) {
            $message = $campaign_id;
            $campaign_id = 0;
            $category = 'general';
        }
        return $this->log($campaign_id, self::WARNING, $category, $message, $context);
    }

    public function error($campaign_id = 0, $category = 'general', $message = '', $context = [])
    {
        // Allow calling with just message as first param
        if (is_string($campaign_id) && empty($message)) {
            $message = $campaign_id;
            $campaign_id = 0;
            $category = 'general';
        }
        return $this->log($campaign_id, self::ERROR, $category, $message, $context);
    }

    /**
     * Get logs for a campaign
     *
     * @param int $campaign_id Campaign ID
     * @param int $limit Number of logs to retrieve
     * @param string|null $level Filter by log level
     * @return array
     */
    public function get_logs($campaign_id, $limit = 100, $level = null)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'abc_logs';

        $where = $wpdb->prepare('WHERE campaign_id = %d', $campaign_id);

        if ($level) {
            $where .= $wpdb->prepare(' AND level = %s', $level);
        }

        $query = "SELECT * FROM {$table_name} {$where} ORDER BY created_at DESC LIMIT %d";

        return $wpdb->get_results($wpdb->prepare($query, $limit));
    }

    /**
     * Delete old logs (cleanup)
     *
     * @param int $days Keep logs from last X days (0 = delete all logs)
     * @return int Number of deleted logs
     */
    public function cleanup_old_logs($days = 30)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'abc_logs';

        // Delete all logs if days is 0
        if ($days === 0) {
            // Get count before deleting
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            
            if ($count > 0) {
                // Use DELETE instead of TRUNCATE to return affected rows
                $wpdb->query("DELETE FROM {$table_name}");
            }
            
            return (int) $count;
        }

        // Delete logs older than specified days
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        return $result ?: 0;
    }
}
