<?php
/**
 * Campaign Factory
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
 * Factory class for creating campaign instances
 *
 * Implements the Factory pattern to instantiate the correct campaign type.
 */
class Campaign_Factory {
    /**
     * Registered campaign types
     *
     * @var array
     */
    private static $registered_types = [
        'website' => 'AutoBlogCraft\\Campaigns\\Website_Campaign',
        'youtube' => 'AutoBlogCraft\\Campaigns\\YouTube_Campaign',
        'amazon' => 'AutoBlogCraft\\Campaigns\\Amazon_Campaign',
        'news' => 'AutoBlogCraft\\Campaigns\\News_Campaign',
    ];

    /**
     * Logger instance
     *
     * @var Logger
     */
    private static $logger;

    /**
     * Create campaign instance
     *
     * @param int $campaign_id Campaign ID
     * @return Campaign_Base|WP_Error
     */
    public static function create($campaign_id) {
        if (!self::$logger) {
            self::$logger = Logger::instance();
        }

        $campaign_id = absint($campaign_id);

        // Verify post exists and is correct type
        $post = get_post($campaign_id);
        
        if (!$post) {
            return new WP_Error(
                'invalid_campaign',
                sprintf('Campaign ID %d does not exist', $campaign_id)
            );
        }

        if ($post->post_type !== 'abc_campaign') {
            return new WP_Error(
                'invalid_post_type',
                sprintf('Post %d is not an abc_campaign', $campaign_id)
            );
        }

        // Get campaign type
        $campaign_type = get_post_meta($campaign_id, '_campaign_type', true);

        if (empty($campaign_type)) {
            self::$logger->error(
                $campaign_id,
                'campaign',
                'Campaign has no _campaign_type meta field'
            );

            return new WP_Error(
                'missing_campaign_type',
                sprintf('Campaign %d has no type defined', $campaign_id)
            );
        }

        // Validate type is registered
        if (!isset(self::$registered_types[$campaign_type])) {
            self::$logger->error(
                $campaign_id,
                'campaign',
                sprintf('Unknown campaign type: %s', $campaign_type)
            );

            return new WP_Error(
                'unknown_campaign_type',
                sprintf('Campaign type "%s" is not registered', $campaign_type)
            );
        }

        // Get class name
        $class_name = self::$registered_types[$campaign_type];

        // Verify class exists
        if (!class_exists($class_name)) {
            self::$logger->error(
                $campaign_id,
                'campaign',
                sprintf('Campaign class does not exist: %s', $class_name)
            );

            return new WP_Error(
                'missing_campaign_class',
                sprintf('Campaign class %s not found', $class_name)
            );
        }

        // Instantiate and return
        try {
            $instance = new $class_name($campaign_id);
            
            self::$logger->debug(
                $campaign_id,
                'campaign',
                sprintf('Created %s campaign instance', $campaign_type)
            );

            return $instance;
        } catch (\Exception $e) {
            self::$logger->error(
                $campaign_id,
                'campaign',
                sprintf('Failed to instantiate campaign: %s', $e->getMessage())
            );

            return new WP_Error(
                'campaign_instantiation_failed',
                $e->getMessage()
            );
        }
    }

    /**
     * Create campaign from type string
     *
     * Creates a new WordPress post and campaign instance.
     *
     * @param string $type Campaign type
     * @param array $args Campaign arguments
     * @return Campaign_Base|WP_Error
     */
    public static function create_new($type, $args = []) {
        if (!self::$logger) {
            self::$logger = Logger::instance();
        }

        // Validate type
        if (!isset(self::$registered_types[$type])) {
            return new WP_Error(
                'invalid_campaign_type',
                sprintf('Campaign type "%s" is not valid', $type)
            );
        }

        // Default arguments
        $defaults = [
            'post_title' => sprintf('New %s Campaign', ucfirst($type)),
            'post_status' => 'publish',
            'post_type' => 'abc_campaign',
        ];

        $post_args = wp_parse_args($args, $defaults);

        // Create post
        $campaign_id = wp_insert_post($post_args, true);

        if (is_wp_error($campaign_id)) {
            self::$logger->error(
                null,
                'campaign',
                sprintf('Failed to create campaign post: %s', $campaign_id->get_error_message())
            );

            return $campaign_id;
        }

        // Set campaign type (IMMUTABLE)
        update_post_meta($campaign_id, '_campaign_type', $type);
        update_post_meta($campaign_id, '_campaign_status', 'active');
        update_post_meta($campaign_id, '_campaign_owner', get_current_user_id());

        self::$logger->info(
            $campaign_id,
            'campaign',
            sprintf('Created new %s campaign', $type)
        );

        // Return campaign instance
        return self::create($campaign_id);
    }

    /**
     * Get registered campaign types
     *
     * @return array
     */
    public static function get_registered_types() {
        return array_keys(self::$registered_types);
    }

    /**
     * Register custom campaign type
     *
     * Allows extending with custom campaign types.
     *
     * @param string $type Campaign type slug
     * @param string $class_name Fully qualified class name
     * @return bool
     */
    public static function register_type($type, $class_name) {
        if (isset(self::$registered_types[$type])) {
            return false; // Already registered
        }

        if (!class_exists($class_name)) {
            return false; // Class doesn't exist
        }

        self::$registered_types[$type] = $class_name;
        return true;
    }

    /**
     * Get campaign type label
     *
     * @param string $type Campaign type
     * @return string
     */
    public static function get_type_label($type) {
        $labels = [
            'website' => __('Auto Blog from Website', 'autoblogcraft-ai'),
            'youtube' => __('Auto Blog from YouTube', 'autoblogcraft-ai'),
            'amazon' => __('Auto Affiliate Blog for Amazon', 'autoblogcraft-ai'),
            'news' => __('AI News Intelligence', 'autoblogcraft-ai'),
        ];

        return $labels[$type] ?? ucfirst($type);
    }

    /**
     * Get campaign type description
     *
     * @param string $type Campaign type
     * @return string
     */
    public static function get_type_description($type) {
        $descriptions = [
            'website' => __('Create content from RSS feeds, sitemaps, and web scraping', 'autoblogcraft-ai'),
            'youtube' => __('Turn YouTube videos into blog posts automatically', 'autoblogcraft-ai'),
            'amazon' => __('Generate affiliate content from Amazon products', 'autoblogcraft-ai'),
            'news' => __('Fresh content from real-time news sources (SERP-based)', 'autoblogcraft-ai'),
        ];

        return $descriptions[$type] ?? '';
    }
}
