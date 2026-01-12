<?php
/**
 * Amazon Campaign Class
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
 * Amazon campaign type
 *
 * Handles Amazon product searches, categories, and bestsellers.
 */
class Amazon_Campaign extends Campaign_Base {
    /**
     * Constructor
     *
     * @param int $campaign_id Campaign ID
     */
    public function __construct($campaign_id) {
        parent::__construct($campaign_id);
        $this->campaign_type = 'amazon';
    }

    /**
     * Get discoverer class name
     *
     * @return string
     */
    public function get_discoverer_class() {
        return 'AutoBlogCraft\\Discovery\\Amazon\\Amazon_Discoverer';
    }

    /**
     * Get processor class name
     *
     * @return string
     */
    public function get_processor_class() {
        return 'AutoBlogCraft\\Processing\\Processors\\Amazon_Processor';
    }

    /**
     * Validate source data
     *
     * @param array $source Source data
     * @return bool|WP_Error
     */
    public function validate_source($source) {
        if (!isset($source['type'])) {
            return new WP_Error(
                'invalid_source',
                __('Source must have type field', 'autoblogcraft-ai')
            );
        }

        $valid_types = ['search', 'category', 'bestseller'];
        
        if (!in_array($source['type'], $valid_types, true)) {
            return new WP_Error(
                'invalid_source_type',
                sprintf(
                    __('Invalid source type. Must be one of: %s', 'autoblogcraft-ai'),
                    implode(', ', $valid_types)
                )
            );
        }

        // Validate based on type
        switch ($source['type']) {
            case 'search':
                if (empty($source['keywords'])) {
                    return new WP_Error(
                        'missing_keywords',
                        __('Search source requires keywords', 'autoblogcraft-ai')
                    );
                }
                break;

            case 'category':
            case 'bestseller':
                if (empty($source['category_id'])) {
                    return new WP_Error(
                        'missing_category',
                        __('Category/Bestseller source requires category_id', 'autoblogcraft-ai')
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
            '_discovery_interval' => '6hr', // Amazon updates less frequently
            '_max_queue_size' => 30,
            '_max_posts_per_day' => 3,
            '_batch_size' => 2,
            '_delay_seconds' => 180, // 3 minutes between posts
            '_amazon_associate_id' => '', // Amazon Associate ID
            '_amazon_api_key_id' => null, // FK to wp_abc_api_keys (PA-API)
            '_product_filters' => [
                'min_price' => 0,
                'max_price' => 0, // 0 = no limit
                'min_rating' => 0, // 0-5
                'min_reviews' => 0,
                'prime_only' => false,
            ],
            '_content_template' => 'review', // review|comparison|roundup
        ];
    }

    /**
     * Get Amazon Associate ID
     *
     * @return string
     */
    public function get_amazon_associate_id() {
        return sanitize_text_field($this->get_meta('_amazon_associate_id', ''));
    }

    /**
     * Get Amazon API key ID
     *
     * @return int|null
     */
    public function get_amazon_api_key_id() {
        return absint($this->get_meta('_amazon_api_key_id')) ?: null;
    }

    /**
     * Get product filters
     *
     * @return array
     */
    public function get_product_filters() {
        $filters = $this->get_meta('_product_filters', []);
        return wp_parse_args($filters, $this->get_default_config()['_product_filters']);
    }

    /**
     * Get content template type
     *
     * @return string review|comparison|roundup
     */
    public function get_content_template() {
        return sanitize_text_field($this->get_meta('_content_template', 'review'));
    }
}
