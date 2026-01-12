<?php
/**
 * Campaign Cloner
 *
 * Handles duplication of campaigns with all settings and metadata.
 * Provides functionality to clone campaigns for reuse or testing.
 *
 * @package AutoBlogCraft\Campaigns
 * @since 2.0.0
 */

namespace AutoBlogCraft\Campaigns;

use AutoBlogCraft\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Campaign Cloner class
 *
 * Responsibilities:
 * - Clone campaigns with all settings
 * - Copy campaign metadata
 * - Duplicate AI configuration
 * - Preserve campaign structure
 * - Reset cloned campaign statistics
 *
 * @since 2.0.0
 */
class Campaign_Cloner {

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
     * Clone a campaign
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID to clone.
     * @return int|WP_Error New campaign ID or error.
     */
    public function clone_campaign($campaign_id) {
        // Validate campaign exists
        $campaign = get_post($campaign_id);
        if (!$campaign || $campaign->post_type !== 'abc_campaign') {
            return new \WP_Error(
                'invalid_campaign',
                'Campaign not found or invalid type.'
            );
        }

        $this->logger->info("Starting campaign clone: ID {$campaign_id}", [
            'campaign_id' => $campaign_id,
            'title' => $campaign->post_title,
        ]);

        // Prepare cloned campaign data
        $clone_data = [
            'post_type' => 'abc_campaign',
            'post_status' => 'draft', // Start as draft
            'post_title' => $campaign->post_title . ' (Copy)',
            'post_content' => $campaign->post_content,
            'post_excerpt' => $campaign->post_excerpt,
            'post_author' => get_current_user_id(),
        ];

        // Create the cloned campaign
        $new_campaign_id = wp_insert_post($clone_data);

        if (is_wp_error($new_campaign_id)) {
            $this->logger->error("Failed to clone campaign: {$new_campaign_id->get_error_message()}", [
                'campaign_id' => $campaign_id,
            ]);
            return $new_campaign_id;
        }

        // Clone all metadata
        $this->clone_meta($campaign_id, $new_campaign_id);

        // Clone AI configuration
        $this->clone_ai_config($campaign_id, $new_campaign_id);

        // Clone sources
        $this->clone_sources($campaign_id, $new_campaign_id);

        // Reset statistics
        $this->reset_statistics($new_campaign_id);

        // Log success
        $this->logger->info("Campaign cloned successfully: Old ID {$campaign_id} → New ID {$new_campaign_id}", [
            'source_campaign_id' => $campaign_id,
            'new_campaign_id' => $new_campaign_id,
        ]);

        do_action('abc_campaign_cloned', $new_campaign_id, $campaign_id);

        return $new_campaign_id;
    }

    /**
     * Clone campaign metadata
     *
     * @since 2.0.0
     * @param int $source_id Source campaign ID.
     * @param int $target_id Target campaign ID.
     * @return void
     */
    public function clone_meta($source_id, $target_id) {
        // Get all meta from source
        $all_meta = get_post_meta($source_id);

        if (empty($all_meta)) {
            return;
        }

        // Meta keys to exclude from cloning
        $exclude_keys = [
            '_last_run',
            '_next_run',
            '_total_posts',
            '_total_discovered',
            '_total_processed',
            '_last_error',
            '_queue_count',
            '_created_at',
            '_updated_at',
        ];

        foreach ($all_meta as $meta_key => $meta_values) {
            // Skip excluded keys
            if (in_array($meta_key, $exclude_keys)) {
                continue;
            }

            // Clone each meta value
            foreach ($meta_values as $meta_value) {
                $meta_value = maybe_unserialize($meta_value);
                update_post_meta($target_id, $meta_key, $meta_value);
            }
        }

        $this->logger->debug("Cloned metadata: {$target_id}", [
            'source_id' => $source_id,
            'target_id' => $target_id,
            'meta_count' => count($all_meta),
        ]);
    }

    /**
     * Clone AI configuration
     *
     * @since 2.0.0
     * @param int $source_id Source campaign ID.
     * @param int $target_id Target campaign ID.
     * @return void
     */
    public function clone_ai_config($source_id, $target_id) {
        $ai_config = get_post_meta($source_id, '_ai_config', true);

        if (empty($ai_config)) {
            return;
        }

        // Clone AI settings
        update_post_meta($target_id, '_ai_config', $ai_config);

        // Clone AI prompts
        $ai_prompts = get_post_meta($source_id, '_ai_prompts', true);
        if (!empty($ai_prompts)) {
            update_post_meta($target_id, '_ai_prompts', $ai_prompts);
        }

        // Clone AI provider settings
        $ai_provider = get_post_meta($source_id, '_ai_provider', true);
        if (!empty($ai_provider)) {
            update_post_meta($target_id, '_ai_provider', $ai_provider);
        }

        // Clone AI model settings
        $ai_model = get_post_meta($source_id, '_ai_model', true);
        if (!empty($ai_model)) {
            update_post_meta($target_id, '_ai_model', $ai_model);
        }

        $this->logger->debug("Cloned AI configuration: {$target_id}", [
            'source_id' => $source_id,
            'target_id' => $target_id,
        ]);
    }

    /**
     * Clone campaign sources
     *
     * @since 2.0.0
     * @param int $source_id Source campaign ID.
     * @param int $target_id Target campaign ID.
     * @return void
     */
    private function clone_sources($source_id, $target_id) {
        $sources = get_post_meta($source_id, '_sources', true);

        if (empty($sources)) {
            return;
        }

        update_post_meta($target_id, '_sources', $sources);

        $this->logger->debug("Cloned sources: {$target_id}", [
            'source_id' => $source_id,
            'target_id' => $target_id,
            'source_count' => is_array($sources) ? count($sources) : 0,
        ]);
    }

    /**
     * Reset cloned campaign statistics
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return void
     */
    private function reset_statistics($campaign_id) {
        // Reset all statistics to zero
        $stats_to_reset = [
            '_total_posts' => 0,
            '_total_discovered' => 0,
            '_total_processed' => 0,
            '_queue_count' => 0,
            '_last_run' => '',
            '_next_run' => '',
            '_last_error' => '',
            '_error_count' => 0,
            '_created_at' => current_time('mysql'),
        ];

        foreach ($stats_to_reset as $key => $value) {
            update_post_meta($campaign_id, $key, $value);
        }

        // Set campaign status to draft
        update_post_meta($campaign_id, '_campaign_status', 'draft');

        $this->logger->debug("Reset statistics for cloned campaign: {$campaign_id}");
    }

    /**
     * Clone campaign with sources only (lightweight)
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID to clone.
     * @param array $options Clone options.
     * @return int|WP_Error New campaign ID or error.
     */
    public function clone_lightweight($campaign_id, $options = []) {
        $defaults = [
            'clone_meta' => true,
            'clone_ai_config' => true,
            'clone_sources' => true,
            'clone_queue' => false,
            'new_title' => '',
        ];

        $options = wp_parse_args($options, $defaults);

        $campaign = get_post($campaign_id);
        if (!$campaign || $campaign->post_type !== 'abc_campaign') {
            return new \WP_Error('invalid_campaign', 'Campaign not found.');
        }

        // Prepare clone data
        $clone_data = [
            'post_type' => 'abc_campaign',
            'post_status' => 'draft',
            'post_title' => !empty($options['new_title']) ? $options['new_title'] : $campaign->post_title . ' (Copy)',
            'post_content' => $campaign->post_content,
            'post_author' => get_current_user_id(),
        ];

        $new_campaign_id = wp_insert_post($clone_data);

        if (is_wp_error($new_campaign_id)) {
            return $new_campaign_id;
        }

        // Clone based on options
        if ($options['clone_meta']) {
            $this->clone_meta($campaign_id, $new_campaign_id);
        }

        if ($options['clone_ai_config']) {
            $this->clone_ai_config($campaign_id, $new_campaign_id);
        }

        if ($options['clone_sources']) {
            $this->clone_sources($campaign_id, $new_campaign_id);
        }

        // Always reset statistics
        $this->reset_statistics($new_campaign_id);

        return $new_campaign_id;
    }

    /**
     * Batch clone campaigns
     *
     * @since 2.0.0
     * @param array $campaign_ids Array of campaign IDs to clone.
     * @return array Array of results with success/error for each.
     */
    public function batch_clone($campaign_ids) {
        $results = [];

        foreach ($campaign_ids as $campaign_id) {
            $new_id = $this->clone_campaign($campaign_id);
            
            if (is_wp_error($new_id)) {
                $results[$campaign_id] = [
                    'success' => false,
                    'error' => $new_id->get_error_message(),
                ];
            } else {
                $results[$campaign_id] = [
                    'success' => true,
                    'new_id' => $new_id,
                ];
            }
        }

        return $results;
    }

    /**
     * Get cloneable campaign data summary
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return array|WP_Error Campaign summary or error.
     */
    public function get_clone_preview($campaign_id) {
        $campaign = get_post($campaign_id);
        if (!$campaign || $campaign->post_type !== 'abc_campaign') {
            return new \WP_Error('invalid_campaign', 'Campaign not found.');
        }

        $campaign_type = get_post_meta($campaign_id, '_campaign_type', true);
        $sources = get_post_meta($campaign_id, '_sources', true);
        $ai_config = get_post_meta($campaign_id, '_ai_config', true);

        return [
            'title' => $campaign->post_title,
            'type' => $campaign_type,
            'source_count' => is_array($sources) ? count($sources) : 0,
            'has_ai_config' => !empty($ai_config),
            'status' => get_post_meta($campaign_id, '_campaign_status', true),
            'created' => $campaign->post_date,
        ];
    }
}
