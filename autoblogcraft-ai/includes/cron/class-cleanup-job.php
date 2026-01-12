<?php
/**
 * Cleanup Job
 *
 * Scheduled job for cleaning up old data and maintaining database health.
 * Runs daily to remove expired cache, old logs, and completed queue items.
 *
 * @package AutoBlogCraft\Cron
 * @since 2.0.0
 */

namespace AutoBlogCraft\Cron;

use AutoBlogCraft\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cleanup Job class
 *
 * Responsibilities:
 * - Clean old logs
 * - Remove expired cache
 * - Delete old queue items
 * - Optimize database tables
 * - Remove orphaned data
 *
 * @since 2.0.0
 */
class Cleanup_Job {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->logger = Logger::instance();
    }

    /**
     * Execute cleanup job
     *
     * @since 2.0.0
     * @return array Execution results.
     */
    public function execute() {
        $this->logger->info("Cleanup job started");

        $start_time = microtime(true);

        $stats = [
            'logs_deleted' => 0,
            'cache_deleted' => 0,
            'queue_deleted' => 0,
            'transients_deleted' => 0,
            'orphaned_deleted' => 0,
        ];

        // Clean old logs
        $stats['logs_deleted'] = $this->clean_logs();

        // Clean translation cache
        $stats['cache_deleted'] = $this->clean_translation_cache();

        // Clean completed queue items
        $stats['queue_deleted'] = $this->clean_queue();

        // Clean transients
        $stats['transients_deleted'] = $this->clean_transients();

        // Clean orphaned data
        $stats['orphaned_deleted'] = $this->clean_orphaned_data();

        // Optimize tables
        $this->optimize_tables();

        $duration = round(microtime(true) - $start_time, 2);

        $this->logger->info("Cleanup job completed", [
            'duration' => $duration,
            'stats' => $stats,
        ]);

        return $stats;
    }

    /**
     * Clean old logs
     *
     * @since 2.0.0
     * @return int Number of logs deleted.
     */
    private function clean_logs() {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_logs';

        // Get retention days from settings (default 30 days)
        $retention_days = apply_filters('abc_log_retention_days', 30);

        // Delete old logs
        $count = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ));

        if ($count > 0) {
            $this->logger->info("Deleted {$count} old log entries");
        }

        return $count;
    }

    /**
     * Clean expired translation cache
     *
     * @since 2.0.0
     * @return int Number of cache entries deleted.
     */
    private function clean_translation_cache() {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_translation_cache';

        // Delete expired cache
        $count = $wpdb->query(
            "DELETE FROM {$table} WHERE expires_at < NOW()"
        );

        if ($count > 0) {
            $this->logger->info("Deleted {$count} expired cache entries");
        }

        // Also clean old cache (older than 90 days regardless of expiry)
        $old_count = $wpdb->query(
            "DELETE FROM {$table} 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );

        if ($old_count > 0) {
            $this->logger->info("Deleted {$old_count} old cache entries");
        }

        return $count + $old_count;
    }

    /**
     * Clean old queue items
     *
     * @since 2.0.0
     * @return int Number of queue items deleted.
     */
    private function clean_queue() {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_discovery_queue';

        // Get retention days from settings (default 30 days for completed)
        $retention_days = apply_filters('abc_queue_retention_days', 30);

        // Delete old completed items
        $completed_count = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} 
            WHERE status = 'completed' 
            AND discovered_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ));

        // Delete old failed items (90 days)
        $failed_count = $wpdb->query(
            "DELETE FROM {$table} 
            WHERE status = 'failed' 
            AND discovered_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );

        // Delete old skipped items (60 days)
        $skipped_count = $wpdb->query(
            "DELETE FROM {$table} 
            WHERE status = 'skipped' 
            AND discovered_at < DATE_SUB(NOW(), INTERVAL 60 DAY)"
        );

        $total = $completed_count + $failed_count + $skipped_count;

        if ($total > 0) {
            $this->logger->info("Deleted {$total} old queue items", [
                'completed' => $completed_count,
                'failed' => $failed_count,
                'skipped' => $skipped_count,
            ]);
        }

        return $total;
    }

    /**
     * Clean plugin transients
     *
     * @since 2.0.0
     * @return int Number of transients deleted.
     */
    private function clean_transients() {
        global $wpdb;

        // Delete expired transients with 'abc_' prefix
        $count = $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_timeout_abc_%' 
            AND option_value < UNIX_TIMESTAMP()"
        );

        // Delete the corresponding transient options
        if ($count > 0) {
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_abc_%' 
                AND option_name NOT LIKE '_transient_timeout_%'"
            );
        }

        if ($count > 0) {
            $this->logger->info("Deleted {$count} expired transients");
        }

        return $count;
    }

    /**
     * Clean orphaned data
     *
     * @since 2.0.0
     * @return int Number of orphaned items deleted.
     */
    private function clean_orphaned_data() {
        global $wpdb;

        $total = 0;

        // Clean orphaned queue items (campaign deleted)
        $queue_table = $wpdb->prefix . 'abc_discovery_queue';
        $queue_count = $wpdb->query(
            "DELETE q FROM {$queue_table} q 
            LEFT JOIN {$wpdb->posts} p ON q.campaign_id = p.ID 
            WHERE p.ID IS NULL"
        );

        $total += $queue_count;

        // Clean orphaned AI configs (campaign deleted)
        $ai_table = $wpdb->prefix . 'abc_campaign_ai_config';
        $ai_count = $wpdb->query(
            "DELETE c FROM {$ai_table} c 
            LEFT JOIN {$wpdb->posts} p ON c.campaign_id = p.ID 
            WHERE p.ID IS NULL"
        );

        $total += $ai_count;

        // Clean orphaned SEO settings (campaign deleted)
        $seo_table = $wpdb->prefix . 'abc_seo_settings';
        $seo_count = $wpdb->query(
            "DELETE s FROM {$seo_table} s 
            LEFT JOIN {$wpdb->posts} p ON s.campaign_id = p.ID 
            WHERE p.ID IS NULL"
        );

        $total += $seo_count;

        if ($total > 0) {
            $this->logger->info("Deleted {$total} orphaned data items", [
                'queue' => $queue_count,
                'ai_configs' => $ai_count,
                'seo_settings' => $seo_count,
            ]);
        }

        return $total;
    }

    /**
     * Optimize database tables
     *
     * @since 2.0.0
     */
    private function optimize_tables() {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'abc_discovery_queue',
            $wpdb->prefix . 'abc_api_keys',
            $wpdb->prefix . 'abc_campaign_ai_config',
            $wpdb->prefix . 'abc_translation_cache',
            $wpdb->prefix . 'abc_logs',
            $wpdb->prefix . 'abc_seo_settings',
        ];

        foreach ($tables as $table) {
            // Check if table exists
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            
            if ($exists) {
                $wpdb->query("OPTIMIZE TABLE {$table}");
            }
        }

        $this->logger->debug("Optimized " . count($tables) . " database tables");
    }

    /**
     * Clean specific campaign data
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return array Cleanup stats.
     */
    public function clean_campaign($campaign_id) {
        global $wpdb;

        $this->logger->info("Cleaning campaign data: ID={$campaign_id}");

        $stats = [
            'queue_deleted' => 0,
            'ai_config_deleted' => 0,
            'seo_deleted' => 0,
            'cache_deleted' => 0,
        ];

        // Clean queue
        $queue_table = $wpdb->prefix . 'abc_discovery_queue';
        $stats['queue_deleted'] = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$queue_table} WHERE campaign_id = %d",
            $campaign_id
        ));

        // Clean AI config
        $ai_table = $wpdb->prefix . 'abc_campaign_ai_config';
        $stats['ai_config_deleted'] = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$ai_table} WHERE campaign_id = %d",
            $campaign_id
        ));

        // Clean SEO settings
        $seo_table = $wpdb->prefix . 'abc_seo_settings';
        $stats['seo_deleted'] = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$seo_table} WHERE campaign_id = %d",
            $campaign_id
        ));

        // Clean translation cache
        $cache_table = $wpdb->prefix . 'abc_translation_cache';
        $stats['cache_deleted'] = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$cache_table} WHERE campaign_id = %d",
            $campaign_id
        ));

        return $stats;
    }

    /**
     * Get cleanup statistics
     *
     * @since 2.0.0
     * @return array Cleanup statistics.
     */
    public function get_stats() {
        global $wpdb;

        $stats = [];

        // Logs
        $logs_table = $wpdb->prefix . 'abc_logs';
        $stats['logs_total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$logs_table}");
        $stats['logs_old'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$logs_table} 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Cache
        $cache_table = $wpdb->prefix . 'abc_translation_cache';
        $stats['cache_total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$cache_table}");
        $stats['cache_expired'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$cache_table} WHERE expires_at < NOW()"
        );

        // Queue
        $queue_table = $wpdb->prefix . 'abc_discovery_queue';
        $stats['queue_total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$queue_table}");
        $stats['queue_old'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$queue_table} 
            WHERE status = 'completed' 
            AND discovered_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Transients
        $stats['transients_total'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_abc_%'"
        );
        $stats['transients_expired'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_timeout_abc_%' 
            AND option_value < UNIX_TIMESTAMP()"
        );

        return $stats;
    }

    /**
     * Force cleanup now
     *
     * @since 2.0.0
     * @return array Cleanup results.
     */
    public function force_cleanup() {
        $this->logger->info("Force cleanup triggered");
        return $this->execute();
    }
}
