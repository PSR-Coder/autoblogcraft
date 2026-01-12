<?php
/**
 * Cleanup Manager
 *
 * Orchestrates cleanup operations for queue, cache, logs, and other data.
 * Prevents database bloat and maintains optimal performance.
 *
 * @package AutoBlogCraft\Core
 * @since 2.0.0
 */

namespace AutoBlogCraft\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cleanup Manager class
 *
 * Responsibilities:
 * - Clean old queue items
 * - Remove expired translation cache
 * - Purge old log entries
 * - Optimize database tables
 * - Generate cleanup reports
 *
 * @since 2.0.0
 */
class Cleanup_Manager {

    /**
     * Run all cleanup tasks
     *
     * Called by the cleanup cron job.
     *
     * @since 2.0.0
     * @return array Cleanup statistics.
     */
    public static function run_all_cleanups() {
        $stats = [];

        // Clean discovery queue
        $stats['queue'] = self::cleanup_queue();

        // Clean translation cache
        $stats['cache'] = self::cleanup_translation_cache();

        // Clean logs
        $stats['logs'] = self::cleanup_logs();

        // Clean temporary data
        $stats['temp'] = self::cleanup_temporary_data();

        // Optimize database tables
        $stats['optimize'] = self::optimize_tables();

        // Log cleanup summary
        Logger::log(0, 'info', 'cleanup', 'Cleanup completed', $stats);

        return $stats;
    }

    /**
     * Cleanup old discovery queue items
     *
     * Removes completed and failed items older than retention period.
     *
     * @since 2.0.0
     * @return array Cleanup statistics.
     */
    public static function cleanup_queue() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'abc_discovery_queue';
        $retention_days = (int) get_option('abc_queue_retention_days', 30);
        
        $stats = [
            'completed_deleted' => 0,
            'failed_deleted' => 0,
            'skipped_deleted' => 0,
        ];

        // Delete completed items older than retention period
        $completed = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} 
                 WHERE status = 'completed' 
                 AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retention_days
            )
        );
        $stats['completed_deleted'] = (int) $completed;

        // Delete failed items older than retention period
        $failed = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} 
                 WHERE status = 'failed' 
                 AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retention_days
            )
        );
        $stats['failed_deleted'] = (int) $failed;

        // Delete skipped items older than 7 days (shorter retention)
        $skipped = $wpdb->query(
            "DELETE FROM {$table} 
             WHERE status = 'skipped' 
             AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $stats['skipped_deleted'] = (int) $skipped;

        return $stats;
    }

    /**
     * Cleanup expired translation cache
     *
     * Removes cache entries past their expiration date.
     *
     * @since 2.0.0
     * @return array Cleanup statistics.
     */
    public static function cleanup_translation_cache() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'abc_translation_cache';
        
        $deleted = $wpdb->query(
            "DELETE FROM {$table} WHERE expires_at < NOW()"
        );

        // Also delete least-used cache if table is too large
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $max_cache_size = 100000; // 100k entries max
        
        $stats = [
            'expired_deleted' => (int) $deleted,
            'lru_deleted' => 0,
        ];

        if ($count > $max_cache_size) {
            $to_delete = $count - $max_cache_size;
            $lru_deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} 
                     ORDER BY last_used_at ASC 
                     LIMIT %d",
                    $to_delete
                )
            );
            $stats['lru_deleted'] = (int) $lru_deleted;
        }

        return $stats;
    }

    /**
     * Cleanup old log entries
     *
     * Removes log entries older than retention period.
     *
     * @since 2.0.0
     * @return array Cleanup statistics.
     */
    public static function cleanup_logs() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'abc_logs';
        $retention_days = (int) get_option('abc_log_retention_days', 30);
        
        $stats = [
            'debug_deleted' => 0,
            'info_deleted' => 0,
            'warning_deleted' => 0,
            'error_deleted' => 0,
        ];

        // Keep errors longer (double retention period)
        $error_retention = $retention_days * 2;

        // Delete debug logs (shortest retention - 7 days)
        $debug = $wpdb->query(
            "DELETE FROM {$table} 
             WHERE level = 'debug' 
             AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $stats['debug_deleted'] = (int) $debug;

        // Delete info logs (normal retention)
        $info = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} 
                 WHERE level = 'info' 
                 AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retention_days
            )
        );
        $stats['info_deleted'] = (int) $info;

        // Delete warning logs (normal retention)
        $warning = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} 
                 WHERE level = 'warning' 
                 AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retention_days
            )
        );
        $stats['warning_deleted'] = (int) $warning;

        // Delete error logs (longer retention)
        $error = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} 
                 WHERE level = 'error' 
                 AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $error_retention
            )
        );
        $stats['error_deleted'] = (int) $error;

        return $stats;
    }

    /**
     * Cleanup temporary data
     *
     * Removes transients, orphaned postmeta, and other temporary data.
     *
     * @since 2.0.0
     * @return array Cleanup statistics.
     */
    public static function cleanup_temporary_data() {
        global $wpdb;
        
        $stats = [
            'transients_deleted' => 0,
            'orphaned_meta_deleted' => 0,
        ];

        // Delete expired transients
        $transients = $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_abc_%' 
             AND option_value < UNIX_TIMESTAMP()"
        );
        $stats['transients_deleted'] = (int) $transients;

        // Delete orphaned campaign meta (where campaign post no longer exists)
        $orphaned = $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.ID IS NULL 
             AND pm.meta_key LIKE '_campaign_%'"
        );
        $stats['orphaned_meta_deleted'] = (int) $orphaned;

        return $stats;
    }

    /**
     * Optimize database tables
     *
     * Runs OPTIMIZE TABLE on all plugin tables.
     *
     * @since 2.0.0
     * @return array Optimization statistics.
     */
    public static function optimize_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'abc_discovery_queue',
            $wpdb->prefix . 'abc_api_keys',
            $wpdb->prefix . 'abc_campaign_ai_config',
            $wpdb->prefix . 'abc_translation_cache',
            $wpdb->prefix . 'abc_logs',
            $wpdb->prefix . 'abc_seo_settings',
        ];

        $stats = [
            'tables_optimized' => 0,
            'errors' => [],
        ];

        foreach ($tables as $table) {
            // Check if table exists
            $exists = $wpdb->get_var(
                $wpdb->prepare("SHOW TABLES LIKE %s", $table)
            );

            if (!$exists) {
                continue;
            }

            // Optimize table
            $result = $wpdb->query("OPTIMIZE TABLE {$table}");
            
            if ($result !== false) {
                $stats['tables_optimized']++;
            } else {
                $stats['errors'][] = $table;
            }
        }

        return $stats;
    }

    /**
     * Get cleanup statistics
     *
     * Returns current database statistics for cleanup monitoring.
     *
     * @since 2.0.0
     * @return array Current statistics.
     */
    public static function get_statistics() {
        global $wpdb;
        
        return [
            'queue_total' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}abc_discovery_queue"
            ),
            'queue_pending' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}abc_discovery_queue WHERE status = 'pending'"
            ),
            'queue_completed' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}abc_discovery_queue WHERE status = 'completed'"
            ),
            'queue_failed' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}abc_discovery_queue WHERE status = 'failed'"
            ),
            'cache_entries' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}abc_translation_cache"
            ),
            'log_entries' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}abc_logs"
            ),
            'log_errors' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}abc_logs WHERE level = 'error'"
            ),
        ];
    }

    /**
     * Manual cleanup trigger
     *
     * Allows admins to manually trigger cleanup from settings page.
     *
     * @since 2.0.0
     * @return array Cleanup results.
     */
    public static function manual_cleanup() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            return [
                'error' => __('Insufficient permissions', 'autoblogcraft'),
            ];
        }

        return self::run_all_cleanups();
    }
}
