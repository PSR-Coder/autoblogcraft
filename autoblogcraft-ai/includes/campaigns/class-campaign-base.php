<?php
/**
 * Abstract Campaign Base Class
 *
 * @package AutoBlogCraft
 * @since 2.0.0
 */

namespace AutoBlogCraft\Campaigns;

use AutoBlogCraft\Core\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract base class for all campaign types
 *
 * Provides common functionality and enforces type-specific implementations.
 */
abstract class Campaign_Base {
    /**
     * Campaign ID (WordPress post ID)
     *
     * @var int
     */
    protected $campaign_id;

    /**
     * Campaign type
     *
     * @var string
     */
    protected $campaign_type;

    /**
     * Campaign meta data
     *
     * @var array
     */
    protected $meta = [];

    /**
     * Logger instance
     *
     * @var Logger
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param int $campaign_id Campaign ID
     */
    public function __construct($campaign_id) {
        $this->campaign_id = absint($campaign_id);
        $this->logger = Logger::instance();
        $this->load_meta();
    }

    /**
     * Load campaign meta data
     *
     * @return void
     */
    protected function load_meta() {
        $post = get_post($this->campaign_id);
        
        if (!$post || $post->post_type !== 'abc_campaign') {
            return;
        }

        // Load all post meta
        $this->meta = [
            '_campaign_type' => get_post_meta($this->campaign_id, '_campaign_type', true),
            '_campaign_status' => get_post_meta($this->campaign_id, '_campaign_status', true) ?: 'active',
            '_campaign_owner' => get_post_meta($this->campaign_id, '_campaign_owner', true) ?: get_current_user_id(),
            '_discovery_interval' => get_post_meta($this->campaign_id, '_discovery_interval', true) ?: '1hr',
            '_last_discovery_run' => get_post_meta($this->campaign_id, '_last_discovery_run', true),
            '_last_processing_run' => get_post_meta($this->campaign_id, '_last_processing_run', true),
            '_seo_enabled' => (bool) get_post_meta($this->campaign_id, '_seo_enabled', true),
            '_translation_enabled' => (bool) get_post_meta($this->campaign_id, '_translation_enabled', true),
            '_max_queue_size' => absint(get_post_meta($this->campaign_id, '_max_queue_size', true)) ?: 100,
            '_max_posts_per_day' => absint(get_post_meta($this->campaign_id, '_max_posts_per_day', true)) ?: 10,
            '_batch_size' => absint(get_post_meta($this->campaign_id, '_batch_size', true)) ?: 5,
            '_delay_seconds' => absint(get_post_meta($this->campaign_id, '_delay_seconds', true)) ?: 60,
        ];

        $this->campaign_type = $this->meta['_campaign_type'];
    }

    /**
     * Get campaign ID
     *
     * @return int
     */
    public function get_id() {
        return $this->campaign_id;
    }

    /**
     * Get campaign type
     *
     * @return string
     */
    public function get_type() {
        return $this->campaign_type;
    }

    /**
     * Get campaign status
     *
     * @return string active|paused|archived
     */
    public function get_status() {
        return $this->meta['_campaign_status'];
    }

    /**
     * Check if campaign is active
     *
     * @return bool
     */
    public function is_active() {
        return $this->get_status() === 'active';
    }

    /**
     * Get meta value
     *
     * @param string $key Meta key
     * @param mixed $default Default value
     * @return mixed
     */
    public function get_meta($key, $default = null) {
        return $this->meta[$key] ?? $default;
    }

    /**
     * Update meta value
     *
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return bool
     */
    public function update_meta($key, $value) {
        // Prevent changing campaign type after creation
        if ($key === '_campaign_type' && !empty($this->meta['_campaign_type'])) {
            $this->logger->error(
                $this->campaign_id,
                'campaign',
                'Attempted to change campaign type (locked after creation)'
            );
            return false;
        }

        $updated = update_post_meta($this->campaign_id, $key, $value);
        
        if ($updated) {
            $this->meta[$key] = $value;
        }

        return (bool) $updated;
    }

    /**
     * Get discovery interval in seconds
     *
     * @return int
     */
    public function get_discovery_interval() {
        $interval = $this->get_meta('_discovery_interval', '1hr');
        return $this->parse_interval($interval);
    }

    /**
     * Parse interval string to seconds
     *
     * @param string $interval Interval string (e.g., '5min', '1hr', '2hr')
     * @return int Seconds
     */
    protected function parse_interval($interval) {
        if (is_numeric($interval)) {
            return absint($interval);
        }

        // Parse custom interval strings
        if (preg_match('/^(\d+)(min|hr|day)$/', $interval, $matches)) {
            $value = absint($matches[1]);
            $unit = $matches[2];

            switch ($unit) {
                case 'min':
                    return $value * MINUTE_IN_SECONDS;
                case 'hr':
                    return $value * HOUR_IN_SECONDS;
                case 'day':
                    return $value * DAY_IN_SECONDS;
            }
        }

        // Default to 1 hour
        return HOUR_IN_SECONDS;
    }

    /**
     * Check if discovery should run
     *
     * @return bool
     */
    public function should_discover() {
        if (!$this->is_active()) {
            return false;
        }

        $last_run = $this->get_meta('_last_discovery_run');
        
        if (empty($last_run)) {
            return true; // Never run before
        }

        $interval = $this->get_discovery_interval();
        $next_run = $last_run + $interval;

        return time() >= $next_run;
    }

    /**
     * Update last discovery run timestamp
     *
     * @return bool
     */
    public function update_discovery_timestamp() {
        return $this->update_meta('_last_discovery_run', time());
    }

    /**
     * Update last processing run timestamp
     *
     * @return bool
     */
    public function update_processing_timestamp() {
        return $this->update_meta('_last_processing_run', time());
    }

    /**
     * Get campaign sources
     *
     * @return array
     */
    public function get_sources() {
        $sources = get_post_meta($this->campaign_id, '_campaign_sources', true);
        return is_array($sources) ? $sources : [];
    }

    /**
     * Add source to campaign
     *
     * @param array $source Source data
     * @return bool
     */
    public function add_source($source) {
        $sources = $this->get_sources();
        $sources[] = $source;
        return update_post_meta($this->campaign_id, '_campaign_sources', $sources);
    }

    /**
     * Remove source from campaign
     *
     * @param int $index Source index
     * @return bool
     */
    public function remove_source($index) {
        $sources = $this->get_sources();
        
        if (!isset($sources[$index])) {
            return false;
        }

        unset($sources[$index]);
        $sources = array_values($sources); // Re-index array
        
        return update_post_meta($this->campaign_id, '_campaign_sources', $sources);
    }

    // ========================================
    // Abstract Methods (Type-specific)
    // ========================================

    /**
     * Get discoverer class name
     *
     * @return string
     */
    abstract public function get_discoverer_class();

    /**
     * Get processor class name
     *
     * @return string
     */
    abstract public function get_processor_class();

    /**
     * Validate source data
     *
     * @param array $source Source data
     * @return bool|WP_Error
     */
    abstract public function validate_source($source);

    /**
     * Get default configuration
     *
     * @return array
     */
    abstract public function get_default_config();
}
