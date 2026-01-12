<?php
/**
 * Discovery Manager
 *
 * Coordinates content discovery across all campaign types.
 * Main orchestrator for the discovery system.
 *
 * @package AutoBlogCraft\Discovery
 * @since 2.0.0
 */

namespace AutoBlogCraft\Discovery;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\Campaigns\Campaign_Factory;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Discovery Manager class
 *
 * Responsibilities:
 * - Run discovery for campaigns
 * - Coordinate discoverers
 * - Handle scheduling
 * - Track discovery status
 * - Error handling and recovery
 *
 * @since 2.0.0
 */
class Discovery_Manager {

    /**
     * Singleton instance
     *
     * @var Discovery_Manager
     */
    private static $instance = null;

    /**
     * Queue manager instance
     *
     * @var Queue_Manager
     */
    private $queue_manager;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Registered discoverers
     *
     * @var array
     */
    private $discoverers = [];

    /**
     * Get singleton instance
     *
     * @since 2.0.0
     * @return Discovery_Manager
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    private function __construct() {
        $this->queue_manager = new Queue_Manager();
        $this->logger = Logger::instance();
        $this->register_discoverers();
    }

    /**
     * Register all discoverers
     *
     * Maps campaign types to discoverer classes.
     *
     * @since 2.0.0
     */
    private function register_discoverers() {
        $this->discoverers = [
            'rss' => RSS_Discoverer::class,
            'sitemap' => Sitemap_Discoverer::class,
            'web' => Web_Discoverer::class,
            'youtube_channel' => YouTube_Discoverer::class,
            'youtube_playlist' => YouTube_Discoverer::class,
            'news' => News_Discoverer::class,
        ];

        /**
         * Filter registered discoverers
         *
         * Allows plugins to add custom discoverers.
         *
         * @since 2.0.0
         * @param array $discoverers Discoverer map.
         */
        $this->discoverers = apply_filters('abc_registered_discoverers', $this->discoverers);
    }

    /**
     * Run discovery for campaign
     *
     * Main entry point for discovery process.
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return array|WP_Error Discovery result or error.
     */
    public function discover($campaign_id) {
        $campaign_id = absint($campaign_id);

        $this->logger->info("Starting discovery for campaign: ID={$campaign_id}");

        // Load campaign
        $campaign = Campaign_Factory::create($campaign_id);
        if (is_wp_error($campaign)) {
            $this->logger->error("Failed to load campaign: {$campaign->get_error_message()}");
            return $campaign;
        }

        // Check if campaign is active
        if ($campaign->get_status() !== 'active') {
            $error = new WP_Error(
                'campaign_inactive',
                'Campaign is not active'
            );
            $this->logger->warning("Campaign is inactive: ID={$campaign_id}");
            return $error;
        }

        // Check if discovery is due
        if (!$campaign->should_discover()) {
            $this->logger->debug("Discovery not due yet for campaign: ID={$campaign_id}");
            return new WP_Error(
                'discovery_not_due',
                'Discovery interval has not elapsed'
            );
        }

        // Get discoverer
        $discoverer = $this->get_discoverer($campaign);
        if (is_wp_error($discoverer)) {
            $this->logger->error("No discoverer for campaign type: {$campaign->get_type()}");
            return $discoverer;
        }

        // Update discovery status
        update_post_meta($campaign_id, '_abc_discovery_in_progress', 1);
        update_post_meta($campaign_id, '_abc_last_discovery_start', current_time('mysql'));

        // Run discovery
        try {
            $result = $discoverer->discover($campaign);

            if (is_wp_error($result)) {
                // Discovery failed
                $this->handle_discovery_error($campaign_id, $result);
                return $result;
            }

            // Discovery successful
            $this->handle_discovery_success($campaign_id, $result);

            return $result;

        } catch (\Exception $e) {
            $error = new WP_Error(
                'discovery_exception',
                $e->getMessage()
            );
            $this->handle_discovery_error($campaign_id, $error);
            return $error;
        }
    }

    /**
     * Run discovery for all active campaigns
     *
     * Used by cron jobs to discover content automatically.
     *
     * @since 2.0.0
     * @return array Summary of discovery results.
     */
    public function discover_all() {
        $this->logger->info("Running discovery for all campaigns");

        $results = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'items_found' => 0,
        ];

        // Get all active campaigns
        $campaigns = get_posts([
            'post_type' => 'abc_campaign',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_abc_status',
                    'value' => 'active',
                ],
            ],
        ]);

        foreach ($campaigns as $campaign_post) {
            $results['total']++;

            $result = $this->discover($campaign_post->ID);

            if (is_wp_error($result)) {
                if ($result->get_error_code() === 'discovery_not_due') {
                    $results['skipped']++;
                } else {
                    $results['failed']++;
                }
            } else {
                $results['success']++;
                $results['items_found'] += $result['items_found'];
            }

            // Prevent timeout on large batches
            if ($results['total'] % 10 === 0) {
                sleep(1);
            }
        }

        $this->logger->info(
            "Discovery complete: {$results['success']} succeeded, {$results['failed']} failed, " .
            "{$results['skipped']} skipped, {$results['items_found']} items found"
        );

        return $results;
    }

    /**
     * Get discoverer for campaign
     *
     * Instantiates the appropriate discoverer class.
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @return object|WP_Error Discoverer instance or error.
     */
    private function get_discoverer($campaign) {
        $type = $campaign->get_type();

        if (!isset($this->discoverers[$type])) {
            return new WP_Error(
                'no_discoverer',
                sprintf('No discoverer registered for type: %s', $type)
            );
        }

        $class = $this->discoverers[$type];

        if (!class_exists($class)) {
            return new WP_Error(
                'discoverer_not_found',
                sprintf('Discoverer class not found: %s', $class)
            );
        }

        return new $class($this->queue_manager);
    }

    /**
     * Handle discovery success
     *
     * Updates campaign metadata after successful discovery.
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @param array $result Discovery result.
     */
    private function handle_discovery_success($campaign_id, $result) {
        update_post_meta($campaign_id, '_abc_discovery_in_progress', 0);
        update_post_meta($campaign_id, '_abc_last_discovery_end', current_time('mysql'));
        update_post_meta($campaign_id, '_abc_last_discovery_status', 'success');
        update_post_meta($campaign_id, '_abc_last_discovery_items', $result['items_found']);

        // Increment total discoveries count
        $total = (int) get_post_meta($campaign_id, '_abc_total_discoveries', true);
        update_post_meta($campaign_id, '_abc_total_discoveries', $total + 1);

        // Clear error count on success
        delete_post_meta($campaign_id, '_abc_discovery_error_count');

        $this->logger->info(
            "Discovery successful: Campaign={$campaign_id}, Items={$result['items_found']}"
        );
    }

    /**
     * Handle discovery error
     *
     * Updates campaign metadata and implements error handling.
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @param WP_Error $error Error object.
     */
    private function handle_discovery_error($campaign_id, $error) {
        update_post_meta($campaign_id, '_abc_discovery_in_progress', 0);
        update_post_meta($campaign_id, '_abc_last_discovery_end', current_time('mysql'));
        update_post_meta($campaign_id, '_abc_last_discovery_status', 'error');
        update_post_meta($campaign_id, '_abc_last_discovery_error', $error->get_error_message());

        // Increment error count
        $error_count = (int) get_post_meta($campaign_id, '_abc_discovery_error_count', true);
        $error_count++;
        update_post_meta($campaign_id, '_abc_discovery_error_count', $error_count);

        // Auto-pause campaign after 5 consecutive errors
        if ($error_count >= 5) {
            update_post_meta($campaign_id, '_abc_status', 'paused');
            $this->logger->error(
                "Campaign auto-paused after {$error_count} consecutive errors: ID={$campaign_id}"
            );

            /**
             * Fires when campaign is auto-paused due to errors
             *
             * @since 2.0.0
             * @param int $campaign_id Campaign ID.
             * @param int $error_count Number of consecutive errors.
             */
            do_action('abc_campaign_auto_paused', $campaign_id, $error_count);
        }

        $this->logger->error(
            "Discovery failed: Campaign={$campaign_id}, Error={$error->get_error_message()}"
        );
    }

    /**
     * Get discovery status for campaign
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return array Discovery status information.
     */
    public function get_discovery_status($campaign_id) {
        $campaign_id = absint($campaign_id);

        return [
            'in_progress' => (bool) get_post_meta($campaign_id, '_abc_discovery_in_progress', true),
            'last_start' => get_post_meta($campaign_id, '_abc_last_discovery_start', true),
            'last_end' => get_post_meta($campaign_id, '_abc_last_discovery_end', true),
            'last_status' => get_post_meta($campaign_id, '_abc_last_discovery_status', true),
            'last_items' => (int) get_post_meta($campaign_id, '_abc_last_discovery_items', true),
            'last_error' => get_post_meta($campaign_id, '_abc_last_discovery_error', true),
            'total_discoveries' => (int) get_post_meta($campaign_id, '_abc_total_discoveries', true),
            'error_count' => (int) get_post_meta($campaign_id, '_abc_discovery_error_count', true),
        ];
    }

    /**
     * Force discovery for campaign
     *
     * Bypasses interval check and runs discovery immediately.
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return array|WP_Error Discovery result or error.
     */
    public function force_discover($campaign_id) {
        $campaign_id = absint($campaign_id);

        // Reset last discovery time to force discovery
        delete_post_meta($campaign_id, '_abc_last_discovery_end');

        return $this->discover($campaign_id);
    }

    /**
     * Reset stuck discoveries
     *
     * Resets campaigns stuck in 'in_progress' state.
     *
     * @since 2.0.0
     * @param int $minutes Age threshold in minutes (default 30).
     * @return int Number of campaigns reset.
     */
    public function reset_stuck_discoveries($minutes = 30) {
        global $wpdb;

        $threshold = gmdate('Y-m-d H:i:s', time() - ($minutes * 60));

        // Find stuck campaigns
        $stuck = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} 
                WHERE meta_key = '_abc_discovery_in_progress' 
                AND meta_value = '1'
                AND post_id IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = '_abc_last_discovery_start'
                    AND meta_value < %s
                )",
                $threshold
            )
        );

        $count = 0;
        foreach ($stuck as $campaign_id) {
            update_post_meta($campaign_id, '_abc_discovery_in_progress', 0);
            $this->logger->warning("Reset stuck discovery: Campaign={$campaign_id}");
            $count++;
        }

        if ($count > 0) {
            $this->logger->info("Reset {$count} stuck discoveries");
        }

        return $count;
    }

    /**
     * Get queue manager
     *
     * @since 2.0.0
     * @return Queue_Manager
     */
    public function get_queue_manager() {
        return $this->queue_manager;
    }
}
