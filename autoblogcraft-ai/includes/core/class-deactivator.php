<?php
/**
 * Plugin Deactivation Handler
 *
 * Handles all tasks that need to be performed when the plugin is deactivated.
 *
 * @package AutoBlogCraft\Core
 * @since 2.0.0
 */

namespace AutoBlogCraft\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Deactivator class
 *
 * Responsibilities:
 * - Unschedule cron jobs
 * - Clear transients
 * - Log deactivation
 * - Pause active campaigns
 *
 * Note: Does NOT delete database tables or options.
 * Use uninstall.php for complete cleanup.
 *
 * @since 2.0.0
 */
class Deactivator {

    /**
     * Run deactivation tasks
     *
     * Called when the plugin is deactivated.
     *
     * @since 2.0.0
     * @return void
     */
    public static function deactivate() {
        // Unschedule all cron jobs
        self::unschedule_cron_jobs();

        // Clear all transients
        self::clear_transients();

        // Pause all active campaigns
        self::pause_active_campaigns();

        // Clear rewrite rules
        flush_rewrite_rules();

        // Set deactivation timestamp
        update_option('abc_deactivated_at', time());

        // Log deactivation
        if (class_exists('AutoBlogCraft\Core\Logger')) {
            Logger::log(0, 'info', 'system', 'Plugin deactivated', [
                'version' => ABC_VERSION,
                'active_campaigns' => self::count_active_campaigns(),
            ]);
        }
    }

    /**
     * Unschedule all cron jobs
     *
     * Removes all scheduled Action Scheduler actions.
     *
     * @since 2.0.0
     * @return void
     */
    private static function unschedule_cron_jobs() {
        // Check if Action Scheduler is available
        if (!function_exists('as_unschedule_all_actions')) {
            return;
        }

        // Unschedule all AutoBlogCraft cron jobs
        $actions = [
            'abc_discovery_cron',
            'abc_processing_cron',
            'abc_cleanup_cron',
            'abc_rate_limit_reset_cron',
        ];

        foreach ($actions as $action) {
            as_unschedule_all_actions($action);
        }

        // Also unschedule any campaign-specific actions
        as_unschedule_all_actions('', [], 'autoblogcraft');
    }

    /**
     * Clear all plugin transients
     *
     * @since 2.0.0
     * @return void
     */
    private static function clear_transients() {
        global $wpdb;

        // Delete all transients starting with abc_
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_abc_%' 
             OR option_name LIKE '_transient_timeout_abc_%'"
        );

        // Clear object cache
        wp_cache_flush();
    }

    /**
     * Pause all active campaigns
     *
     * Prevents campaigns from running after deactivation.
     *
     * @since 2.0.0
     * @return void
     */
    private static function pause_active_campaigns() {
        $campaigns = get_posts([
            'post_type' => 'abc_campaign',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_campaign_status',
                    'value' => 'active',
                ],
            ],
        ]);

        foreach ($campaigns as $campaign) {
            update_post_meta($campaign->ID, '_campaign_status', 'paused');
            update_post_meta($campaign->ID, '_paused_by_deactivation', true);
            update_post_meta($campaign->ID, '_paused_at', time());
        }
    }

    /**
     * Count active campaigns
     *
     * @since 2.0.0
     * @return int Number of active campaigns.
     */
    private static function count_active_campaigns() {
        $campaigns = get_posts([
            'post_type' => 'abc_campaign',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_campaign_status',
                    'value' => 'active',
                ],
            ],
            'fields' => 'ids',
        ]);

        return count($campaigns);
    }

    /**
     * Restore campaigns that were auto-paused
     *
     * Call this on reactivation to restore campaigns that were
     * automatically paused during deactivation.
     *
     * @since 2.0.0
     * @return int Number of campaigns restored.
     */
    public static function restore_auto_paused_campaigns() {
        $campaigns = get_posts([
            'post_type' => 'abc_campaign',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_paused_by_deactivation',
                    'value' => true,
                ],
            ],
        ]);

        $restored = 0;

        foreach ($campaigns as $campaign) {
            update_post_meta($campaign->ID, '_campaign_status', 'active');
            delete_post_meta($campaign->ID, '_paused_by_deactivation');
            delete_post_meta($campaign->ID, '_paused_at');
            $restored++;
        }

        return $restored;
    }

    /**
     * Get deactivation timestamp
     *
     * @since 2.0.0
     * @return int|false Timestamp or false if never deactivated.
     */
    public static function get_deactivation_time() {
        return get_option('abc_deactivated_at');
    }

    /**
     * Check if plugin was recently deactivated
     *
     * @since 2.0.0
     * @param int $seconds Seconds to check (default: 1 hour).
     * @return bool
     */
    public static function was_recently_deactivated($seconds = 3600) {
        $deactivated_at = self::get_deactivation_time();
        
        if (!$deactivated_at) {
            return false;
        }

        return (time() - $deactivated_at) < $seconds;
    }
}
