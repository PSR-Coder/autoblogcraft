<?php
/**
 * YouTube Campaign Class
 *
 * @package AutoBlogCraft
 * @since 2.0.0
 */

namespace AutoBlogCraft\Campaigns;

use AutoBlogCraft\Helpers\Validation;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * YouTube campaign type
 *
 * Handles YouTube channels and playlists.
 */
class YouTube_Campaign extends Campaign_Base {
    /**
     * Constructor
     *
     * @param int $campaign_id Campaign ID
     */
    public function __construct($campaign_id) {
        parent::__construct($campaign_id);
        $this->campaign_type = 'youtube';
    }

    /**
     * Get discoverer class name
     *
     * @return string
     */
    public function get_discoverer_class() {
        return 'AutoBlogCraft\\Discovery\\YouTube\\YouTube_Discoverer';
    }

    /**
     * Get processor class name
     *
     * @return string
     */
    public function get_processor_class() {
        return 'AutoBlogCraft\\Processing\\Processors\\YouTube_Processor';
    }

    /**
     * Validate source data
     *
     * @param array $source Source data
     * @return bool|WP_Error
     */
    public function validate_source($source) {
        if (!isset($source['type']) || !isset($source['url'])) {
            return new WP_Error(
                'invalid_source',
                __('Source must have type and url fields', 'autoblogcraft-ai')
            );
        }

        $valid_types = ['channel', 'playlist'];
        
        if (!in_array($source['type'], $valid_types, true)) {
            return new WP_Error(
                'invalid_source_type',
                sprintf(
                    __('Invalid source type. Must be one of: %s', 'autoblogcraft-ai'),
                    implode(', ', $valid_types)
                )
            );
        }

        // Validate URL based on type
        switch ($source['type']) {
            case 'channel':
                if (!Validation::is_valid_youtube_channel($source['url'])) {
                    return new WP_Error(
                        'invalid_channel_url',
                        __('Invalid YouTube channel URL', 'autoblogcraft-ai')
                    );
                }
                break;

            case 'playlist':
                if (!Validation::is_valid_youtube_playlist($source['url'])) {
                    return new WP_Error(
                        'invalid_playlist_url',
                        __('Invalid YouTube playlist URL', 'autoblogcraft-ai')
                    );
                }
                break;
        }

        return true;
    }

    /**
     * Get default configuration
     *
     * @return array
     */
    public function get_default_config() {
        return [
            '_discovery_interval' => '2hr',
            '_max_queue_size' => 50,
            '_max_posts_per_day' => 5,
            '_batch_size' => 3,
            '_delay_seconds' => 120,
            '_video_filters' => [
                'min_duration' => 60, // seconds
                'max_duration' => 0, // 0 = no limit
                'max_age_days' => 30, // Only videos from last 30 days
                'require_captions' => false,
            ],
            '_youtube_api_key_id' => null, // FK to wp_abc_api_keys
        ];
    }

    /**
     * Get video filters
     *
     * @return array
     */
    public function get_video_filters() {
        $filters = $this->get_meta('_video_filters', []);
        return wp_parse_args($filters, $this->get_default_config()['_video_filters']);
    }

    /**
     * Get YouTube API key ID
     *
     * @return int|null
     */
    public function get_youtube_api_key_id() {
        return absint($this->get_meta('_youtube_api_key_id')) ?: null;
    }
}
