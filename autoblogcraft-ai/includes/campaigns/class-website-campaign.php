<?php
/**
 * Website Campaign Class
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
 * Website campaign type
 *
 * Handles RSS feeds, sitemaps, and direct website URLs.
 */
class Website_Campaign extends Campaign_Base {
    /**
     * Constructor
     *
     * @param int $campaign_id Campaign ID
     */
    public function __construct($campaign_id) {
        parent::__construct($campaign_id);
        $this->campaign_type = 'website';
    }

    /**
     * Get discoverer class name
     *
     * @return string
     */
    public function get_discoverer_class() {
        return 'AutoBlogCraft\\Discovery\\Website\\Website_Discoverer';
    }

    /**
     * Get processor class name
     *
     * @return string
     */
    public function get_processor_class() {
        return 'AutoBlogCraft\\Processing\\Processors\\Website_Processor';
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

        $valid_types = ['rss', 'sitemap', 'web'];
        
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
            case 'rss':
                if (!Validation::is_valid_rss_url($source['url'])) {
                    return new WP_Error(
                        'invalid_rss_url',
                        __('Invalid RSS feed URL', 'autoblogcraft-ai')
                    );
                }
                break;

            case 'sitemap':
                if (!Validation::is_valid_xml_url($source['url'])) {
                    return new WP_Error(
                        'invalid_sitemap_url',
                        __('Invalid sitemap URL', 'autoblogcraft-ai')
                    );
                }
                break;

            case 'web':
                if (!Validation::is_valid_url($source['url'])) {
                    return new WP_Error(
                        'invalid_web_url',
                        __('Invalid website URL', 'autoblogcraft-ai')
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
            '_discovery_interval' => '1hr',
            '_max_queue_size' => 100,
            '_max_posts_per_day' => 10,
            '_batch_size' => 5,
            '_delay_seconds' => 60,
            '_crawl_depth' => 1, // For web scraping
            '_content_filters' => [
                'min_words' => 300,
                'max_words' => 0, // 0 = no limit
                'exclude_keywords' => [],
                'required_keywords' => [],
            ],
        ];
    }

    /**
     * Get crawl depth
     *
     * @return int
     */
    public function get_crawl_depth() {
        return absint($this->get_meta('_crawl_depth', 1));
    }

    /**
     * Get content filters
     *
     * @return array
     */
    public function get_content_filters() {
        $filters = $this->get_meta('_content_filters', []);
        return wp_parse_args($filters, $this->get_default_config()['_content_filters']);
    }
}
