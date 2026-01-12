<?php
/**
 * Discovery Job
 *
 * Scheduled job for discovering new content from all active campaigns.
 * Runs periodically to check for new content sources.
 *
 * @package AutoBlogCraft\Cron
 * @since 2.0.0
 */

namespace AutoBlogCraft\Cron;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\Campaigns\Campaign_Factory;
use AutoBlogCraft\Discovery\Discovery_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Discovery Job class
 *
 * Responsibilities:
 * - Find active campaigns
 * - Check if discovery is due
 * - Trigger discovery process
 * - Track discovery statistics
 *
 * @since 2.0.0
 */
class Discovery_Job {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Campaign factory
     *
     * @var Campaign_Factory
     */
    private $factory;

    /**
     * Discovery manager
     *
     * @var Discovery_Manager
     */
    private $discovery;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->logger = Logger::instance();
        $this->factory = new Campaign_Factory();
        $this->discovery = new Discovery_Manager();
    }

    /**
     * Execute discovery job
     *
     * @since 2.0.0
     * @return array Execution results.
     */
    public function execute() {
        $this->logger->info("Discovery job started");

        $start_time = microtime(true);

        // Get active campaigns
        $campaigns = $this->get_active_campaigns();

        $stats = [
            'campaigns_total' => count($campaigns),
            'campaigns_processed' => 0,
            'items_discovered' => 0,
            'errors' => 0,
        ];

        // Process each campaign
        foreach ($campaigns as $campaign) {
            try {
                $result = $this->process_campaign($campaign);

                $stats['campaigns_processed']++;
                $stats['items_discovered'] += $result['discovered'];

            } catch (\Exception $e) {
                $stats['errors']++;
                $this->logger->error("Campaign discovery failed: ID={$campaign->get_id()} - " . $e->getMessage());
            }
        }

        $duration = round(microtime(true) - $start_time, 2);

        $this->logger->info("Discovery job completed", [
            'duration' => $duration,
            'stats' => $stats,
        ]);

        return $stats;
    }

    /**
     * Get active campaigns
     *
     * @since 2.0.0
     * @return array Campaign instances.
     */
    private function get_active_campaigns() {
        $query = new \WP_Query([
            'post_type' => 'abc_campaign',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_abc_status',
                    'value' => 'active',
                    'compare' => '=',
                ],
            ],
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        $campaigns = [];

        foreach ($query->posts as $post) {
            try {
                $campaign = $this->factory->get_campaign($post->ID);
                
                if ($campaign) {
                    $campaigns[] = $campaign;
                }
            } catch (\Exception $e) {
                $this->logger->error("Failed to load campaign: ID={$post->ID} - " . $e->getMessage());
            }
        }

        $this->logger->debug("Found " . count($campaigns) . " active campaigns");

        return $campaigns;
    }

    /**
     * Process single campaign
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @return array Processing result.
     */
    private function process_campaign($campaign) {
        $campaign_id = $campaign->get_id();

        // Check if discovery is due
        if (!$this->is_discovery_due($campaign)) {
            $this->logger->debug("Discovery not due for campaign: ID={$campaign_id}");
            return ['discovered' => 0];
        }

        $this->logger->info("Discovering content for campaign: ID={$campaign_id}");

        // Run discovery
        $result = $this->discovery->discover($campaign);

        // Update last discovery time
        $campaign->update_meta('last_discovery_at', current_time('mysql'));

        // Update next discovery time
        $interval = $campaign->get_discovery_interval();
        $next_run = time() + $interval;
        $campaign->update_meta('next_discovery_at', date('Y-m-d H:i:s', $next_run));

        $this->logger->info("Discovery completed for campaign: ID={$campaign_id}", [
            'discovered' => $result['discovered'],
            'next_run' => date('Y-m-d H:i:s', $next_run),
        ]);

        return $result;
    }

    /**
     * Check if discovery is due for campaign
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @return bool True if due.
     */
    private function is_discovery_due($campaign) {
        // Get next discovery time
        $next_discovery = $campaign->get_meta('next_discovery_at');

        // If never run, it's due
        if (empty($next_discovery)) {
            return true;
        }

        // Check if time has passed
        $next_timestamp = strtotime($next_discovery);
        $now = time();

        return $now >= $next_timestamp;
    }

    /**
     * Trigger discovery for specific campaign
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return array Discovery result.
     */
    public function discover_campaign($campaign_id) {
        $this->logger->info("Manual discovery triggered: Campaign ID={$campaign_id}");

        try {
            $campaign = $this->factory->get_campaign($campaign_id);

            if (!$campaign) {
                throw new \Exception("Campaign not found: ID={$campaign_id}");
            }

            $result = $this->process_campaign($campaign);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error("Manual discovery failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get discovery schedule for campaign
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return array Schedule information.
     */
    public function get_schedule($campaign_id) {
        try {
            $campaign = $this->factory->get_campaign($campaign_id);

            if (!$campaign) {
                return null;
            }

            $last_run = $campaign->get_meta('last_discovery_at');
            $next_run = $campaign->get_meta('next_discovery_at');
            $interval = $campaign->get_discovery_interval();

            return [
                'last_run' => $last_run,
                'last_run_human' => $last_run ? human_time_diff(strtotime($last_run)) . ' ago' : 'Never',
                'next_run' => $next_run,
                'next_run_human' => $next_run ? human_time_diff(strtotime($next_run)) : 'Not scheduled',
                'interval' => $interval,
                'interval_human' => $this->format_interval($interval),
                'is_due' => $this->is_discovery_due($campaign),
            ];

        } catch (\Exception $e) {
            $this->logger->error("Failed to get schedule: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Format interval in human-readable format
     *
     * @since 2.0.0
     * @param int $seconds Interval in seconds.
     * @return string Formatted interval.
     */
    private function format_interval($seconds) {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        }

        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        }

        if ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '');
        }

        $days = floor($seconds / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '');
    }
}
