<?php
/**
 * Global Rate Limiter
 *
 * Prevents API bill explosions by limiting concurrent campaigns and AI calls
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
 * Class Rate_Limiter
 *
 * Global rate limiting using WordPress Object Cache (Redis/Memcached compatible)
 */
class Rate_Limiter {

    /**
     * Singleton instance
     *
     * @var Rate_Limiter
     */
    protected static $instance = null;

    /**
     * Max concurrent campaigns processing
     *
     * @var int
     */
    private $max_concurrent_campaigns;

    /**
     * Max concurrent AI API calls
     *
     * @var int
     */
    private $max_concurrent_ai_calls;

    /**
     * Cache group
     *
     * @var string
     */
    private $cache_group = 'autoblogcraft';

    /**
     * Get singleton instance
     *
     * @return Rate_Limiter
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->max_concurrent_campaigns = get_option('abc_max_concurrent_campaigns', 3);
        $this->max_concurrent_ai_calls = get_option('abc_max_concurrent_ai_calls', 10);
    }

    /**
     * Check if we can start processing a campaign
     *
     * @param int $campaign_id Campaign ID
     * @return bool
     */
    public function can_start_campaign($campaign_id) {
        $running_campaigns = $this->get_running_campaigns();

        // Check if already running
        if (in_array($campaign_id, $running_campaigns, true)) {
            return true; // Already claimed
        }

        // Check if global limit reached
        if (count($running_campaigns) >= $this->max_concurrent_campaigns) {
            return false;
        }

        return true;
    }

    /**
     * Mark campaign as running
     *
     * @param int $campaign_id Campaign ID
     * @return bool Success
     */
    public function start_campaign($campaign_id) {
        $running_campaigns = $this->get_running_campaigns();

        if (!in_array($campaign_id, $running_campaigns, true)) {
            $running_campaigns[] = $campaign_id;
            return set_transient('abc_running_campaigns', $running_campaigns, 600); // 10 min TTL
        }
        return true;
    }

    /**
     * Mark campaign as finished
     *
     * @param int $campaign_id Campaign ID
     * @return bool Success
     */
    public function finish_campaign($campaign_id) {
        $running_campaigns = $this->get_running_campaigns();

        $running_campaigns = array_filter($running_campaigns, function($id) use ($campaign_id) {
            return $id !== $campaign_id;
        });

        // Re-index array
        $running_campaigns = array_values($running_campaigns);

        return set_transient('abc_running_campaigns', $running_campaigns, 600);
    }

    /**
     * Get running campaigns
     *
     * @return array
     */
    private function get_running_campaigns() {
        $running = wp_cache_get('abc_running_campaigns', $this->cache_group);
        return $running !== false ? $running : [];
    }

    /**
     * Check if we can make an AI API call
     *
     * @return bool
     */
    public function can_make_ai_call() {
        $current_calls = $this->get_current_ai_calls();
        return $current_calls < $this->max_concurrent_ai_calls;
    }

    /**
     * Start tracking an AI call
     *
     * Should be called before making an API request.
     *
     * @return bool Success
     */
    public function start_ai_call() {
        return $this->increment_ai_calls();
    }

    /**
     * Finish tracking an AI call
     *
     * Should be called after API request completes (in finally block).
     *
     * @return bool Success
     */
    public function finish_ai_call() {
        return $this->decrement_ai_calls();
    }

    /**
     * Increment AI call counter
     *
     * @return bool Success
     */
    public function increment_ai_calls() {
        $current_calls = $this->get_current_ai_calls();
        return set_transient('abc_concurrent_ai_calls', $current_calls + 1, 600);
    }

    /**
     * Decrement AI call counter
     *
     * @return bool Success
     */
    public function decrement_ai_calls() {
        $current_calls = $this->get_current_ai_calls();
        $new_count = max(0, $current_calls - 1);
        return set_transient('abc_concurrent_ai_calls', $new_count, 600);
    }

    /**
     * Get current AI call count
     *
     * @return int
     */
    private function get_current_ai_calls() {
        $count = wp_cache_get('abc_concurrent_ai_calls', $this->cache_group);
        return $count !== false ? (int) $count : 0;
    }

    /**
     * Cleanup stale campaign locks
     *
     * Removes campaigns that have been running longer than the expected max duration.
     * Should be called periodically by cron.
     *
     * @param int $max_duration Maximum expected campaign duration in seconds (default 3600 = 1 hour)
     * @return int Number of stale locks cleaned
     */
    public function cleanup_stale_locks($max_duration = 3600) {
        global $wpdb;
        
        $running_campaigns = $this->get_running_campaigns();
        $cleaned = 0;

        foreach ($running_campaigns as $campaign_id) {
            // Check campaign's last activity timestamp
            $last_activity = get_post_meta($campaign_id, '_abc_processing_started', true);
            
            if ($last_activity && (time() - $last_activity) > $max_duration) {
                // Campaign has been running too long, release lock
                $this->finish_campaign($campaign_id);
                $cleaned++;
                
                // Log cleanup
                if (class_exists('AutoBlogCraft\Core\Logger')) {
                    $logger = \AutoBlogCraft\Core\Logger::instance();
                    $logger->warning(
                        null,
                        'rate_limiter',
                        sprintf('Cleaned stale campaign lock: ID=%d (inactive for %d seconds)', 
                            $campaign_id, 
                            time() - $last_activity
                        )
                    );
                }
            }
        }

        return $cleaned;
    }

    /**
     * Reset all rate limits (for testing/debugging)
     *
     * @return void
     */
    public function reset_all() {
        wp_cache_delete('abc_running_campaigns', $this->cache_group);
        wp_cache_delete('abc_concurrent_ai_calls', $this->cache_group);
    }

    /**
     * Get current stats
     *
     * @return array
     */
    public function get_stats() {
        return [
            'running_campaigns' => count($this->get_running_campaigns()),
            'max_campaigns' => $this->max_concurrent_campaigns,
            'current_ai_calls' => $this->get_current_ai_calls(),
            'max_ai_calls' => $this->max_concurrent_ai_calls,
        ];
    }
}
