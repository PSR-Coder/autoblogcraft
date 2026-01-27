<?php
declare(strict_types=1);
/**
 * Post Publisher
 *
 * Creates and publishes WordPress posts from processed content.
 * Handles post creation, taxonomy, meta, and SEO integration.
 *
 * @package AutoBlogCraft\Processing
 * @since 2.0.0
 */

namespace AutoBlogCraft\Processing;

use AutoBlogCraft\Core\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post Publisher class
 *
 * Responsibilities:
 * - Create WordPress posts
 * - Assign categories and tags
 * - Set featured images
 * - Handle post meta
 * - Integrate with SEO plugins
 * - Manage post status
 *
 * @since 2.0.0
 */
class Post_Publisher {

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
     * Publish post
     *
     * @since 2.0.0
     * @param array $post_data Post data from processor.
     * @param object $campaign Campaign instance.
     * @return int|WP_Error Post ID or error.
     */
    public function publish($post_data, $campaign) {
        $this->logger->debug("Publishing post: {$post_data['title']}");

        // Prepare post arguments
        $post_args = $this->prepare_post_args($post_data, $campaign);

        // Insert post
        $post_id = wp_insert_post($post_args, true);

        if (is_wp_error($post_id)) {
            $this->logger->error("Failed to insert post: " . $post_id->get_error_message());
            return $post_id;
        }

        // Set featured image
        if (!empty($post_data['featured_image_id'])) {
            set_post_thumbnail($post_id, $post_data['featured_image_id']);
        }

        // Assign categories
        if (!empty($post_data['categories'])) {
            wp_set_post_categories($post_id, $post_data['categories'], false);
        }

        // Assign tags
        if (!empty($post_data['tags'])) {
            wp_set_post_tags($post_id, $post_data['tags'], false);
        }

        // Set custom taxonomies
        if (!empty($post_data['taxonomies'])) {
            $this->set_custom_taxonomies($post_id, $post_data['taxonomies']);
        }

        // Store post meta
        if (!empty($post_data['meta'])) {
            $this->store_post_meta($post_id, $post_data['meta']);
        }

        // Store campaign reference
        update_post_meta($post_id, '_abc_campaign_id', $campaign->get_id());
        update_post_meta($post_id, '_abc_source_type', $post_data['source_type'] ?? '');
        update_post_meta($post_id, '_abc_source_url', $post_data['source_url'] ?? '');
        update_post_meta($post_id, '_abc_created_at', current_time('mysql'));

        // Handle SEO
        $this->handle_seo($post_id, $post_data, $campaign);

        // Handle schema
        $this->handle_schema($post_id, $post_data, $campaign);

        // Fire action
        do_action('abc_post_published', $post_id, $post_data, $campaign);

        $status = $post_args['post_status'];
        $this->logger->info("Post published: ID={$post_id}, Status={$status}");

        return $post_id;
    }

    /**
     * Update existing post
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param array $post_data Post data.
     * @param object $campaign Campaign instance.
     * @return int|WP_Error Post ID or error.
     */
    public function update($post_id, $post_data, $campaign) {
        $this->logger->debug("Updating post: ID={$post_id}");

        // Prepare post arguments
        $post_args = $this->prepare_post_args($post_data, $campaign);
        $post_args['ID'] = $post_id;

        // Update post
        $result = wp_update_post($post_args, true);

        if (is_wp_error($result)) {
            $this->logger->error("Failed to update post: " . $result->get_error_message());
            return $result;
        }

        // Update featured image
        if (!empty($post_data['featured_image_id'])) {
            set_post_thumbnail($post_id, $post_data['featured_image_id']);
        }

        // Update categories
        if (isset($post_data['categories'])) {
            wp_set_post_categories($post_id, $post_data['categories'], false);
        }

        // Update tags
        if (isset($post_data['tags'])) {
            wp_set_post_tags($post_id, $post_data['tags'], false);
        }

        // Update post meta
        if (!empty($post_data['meta'])) {
            $this->store_post_meta($post_id, $post_data['meta']);
        }

        // Update timestamp
        update_post_meta($post_id, '_abc_updated_at', current_time('mysql'));

        // Handle SEO
        $this->handle_seo($post_id, $post_data, $campaign);

        $this->logger->info("Post updated: ID={$post_id}");

        return $post_id;
    }

    /**
     * Prepare post arguments
     *
     * @since 2.0.0
     * @param array $post_data Post data.
     * @param object $campaign Campaign instance.
     * @return array Post arguments.
     */
    private function prepare_post_args($post_data, $campaign) {
        // Get campaign settings
        $post_status = $campaign->get_meta('post_status', 'draft');
        $post_author = $campaign->get_meta('post_author', get_current_user_id());
        $post_type = $campaign->get_meta('post_type', 'post');
        $comment_status = $campaign->get_meta('comment_status', 'open');
        $ping_status = $campaign->get_meta('ping_status', 'open');

        // Handle post date
        $post_date = '';
        $post_date_gmt = '';
        
        if (!empty($post_data['publish_date'])) {
            $post_date = $post_data['publish_date'];
        } elseif ($campaign->get_meta('use_original_date', false) && !empty($post_data['source_date'])) {
            $post_date = $post_data['source_date'];
        }

        // Build post args
        $args = [
            'post_title' => wp_strip_all_tags($post_data['title']),
            'post_content' => $post_data['content'],
            'post_status' => $post_status,
            'post_author' => $post_author,
            'post_type' => $post_type,
            'comment_status' => $comment_status,
            'ping_status' => $ping_status,
        ];

        // Add excerpt if provided
        if (!empty($post_data['excerpt'])) {
            $args['post_excerpt'] = $post_data['excerpt'];
        }

        // Add date if set
        if (!empty($post_date)) {
            $args['post_date'] = $post_date;
            $args['post_date_gmt'] = get_gmt_from_date($post_date);
        }

        // Add custom post name/slug
        if (!empty($post_data['post_name'])) {
            $args['post_name'] = sanitize_title($post_data['post_name']);
        }

        return apply_filters('abc_post_args', $args, $post_data, $campaign);
    }

    /**
     * Set custom taxonomies
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param array $taxonomies Taxonomies data.
     */
    private function set_custom_taxonomies($post_id, $taxonomies) {
        foreach ($taxonomies as $taxonomy => $terms) {
            if (taxonomy_exists($taxonomy)) {
                wp_set_object_terms($post_id, $terms, $taxonomy, false);
            }
        }
    }

    /**
     * Store post meta
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param array $meta Meta data.
     */
    private function store_post_meta($post_id, $meta) {
        foreach ($meta as $key => $value) {
            // Skip empty values
            if ($value === '' || $value === null) {
                continue;
            }

            update_post_meta($post_id, $key, $value);
        }
    }

    /**
     * Handle SEO integration
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param array $post_data Post data.
     * @param object $campaign Campaign instance.
     */
    private function handle_seo($post_id, $post_data, $campaign) {
        // Check if SEO enabled
        if (!$campaign->get_meta('enable_seo', true)) {
            return;
        }

        // Detect SEO plugin
        $seo_plugin = $this->detect_seo_plugin();

        if (!$seo_plugin) {
            $this->logger->debug("No SEO plugin detected");
            return;
        }

        $this->logger->debug("Handling SEO with plugin: {$seo_plugin}");

        // Get SEO data
        $seo_title = !empty($post_data['seo_title']) ? $post_data['seo_title'] : $post_data['title'];
        $seo_description = !empty($post_data['seo_description']) ? $post_data['seo_description'] : $post_data['excerpt'];
        $focus_keyword = $post_data['focus_keyword'] ?? '';

        // Integrate with SEO plugin
        switch ($seo_plugin) {
            case 'yoast':
                $this->handle_yoast_seo($post_id, $seo_title, $seo_description, $focus_keyword);
                break;

            case 'rank-math':
                $this->handle_rank_math_seo($post_id, $seo_title, $seo_description, $focus_keyword);
                break;

            case 'aioseo':
                $this->handle_aioseo($post_id, $seo_title, $seo_description, $focus_keyword);
                break;
        }
    }

    /**
     * Detect active SEO plugin
     *
     * @since 2.0.0
     * @return string|false SEO plugin slug or false.
     */
    private function detect_seo_plugin() {
        if (defined('WPSEO_VERSION')) {
            return 'yoast';
        }

        if (defined('RANK_MATH_VERSION')) {
            return 'rank-math';
        }

        if (defined('AIOSEO_VERSION')) {
            return 'aioseo';
        }

        return false;
    }

    /**
     * Handle Yoast SEO
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param string $title SEO title.
     * @param string $description SEO description.
     * @param string $keyword Focus keyword.
     */
    private function handle_yoast_seo($post_id, $title, $description, $keyword) {
        update_post_meta($post_id, '_yoast_wpseo_title', $title);
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $description);
        
        if (!empty($keyword)) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', $keyword);
        }
    }

    /**
     * Handle Rank Math SEO
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param string $title SEO title.
     * @param string $description SEO description.
     * @param string $keyword Focus keyword.
     */
    private function handle_rank_math_seo($post_id, $title, $description, $keyword) {
        update_post_meta($post_id, 'rank_math_title', $title);
        update_post_meta($post_id, 'rank_math_description', $description);
        
        if (!empty($keyword)) {
            update_post_meta($post_id, 'rank_math_focus_keyword', $keyword);
        }
    }

    /**
     * Handle All in One SEO
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param string $title SEO title.
     * @param string $description SEO description.
     * @param string $keyword Focus keyword.
     */
    private function handle_aioseo($post_id, $title, $description, $keyword) {
        update_post_meta($post_id, '_aioseo_title', $title);
        update_post_meta($post_id, '_aioseo_description', $description);
        
        if (!empty($keyword)) {
            update_post_meta($post_id, '_aioseo_keywords', $keyword);
        }
    }

    /**
     * Handle schema markup
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param array $post_data Post data.
     * @param object $campaign Campaign instance.
     */
    private function handle_schema($post_id, $post_data, $campaign) {
        // Check if schema enabled
        if (!$campaign->get_meta('enable_schema', false)) {
            return;
        }

        $schema_type = $campaign->get_meta('schema_type', 'Article');

        // Build schema
        $schema = $this->build_schema($post_id, $post_data, $schema_type);

        // Store schema
        update_post_meta($post_id, '_abc_schema', $schema);

        // Allow filtering
        $schema = apply_filters('abc_post_schema', $schema, $post_id, $campaign);

        // Add to head via hook
        add_action('wp_head', function() use ($schema) {
            echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
        });
    }

    /**
     * Build schema markup
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param array $post_data Post data.
     * @param string $type Schema type.
     * @return array Schema data.
     */
    private function build_schema($post_id, $post_data, $type) {
        $post = get_post($post_id);
        $author = get_user_by('id', $post->post_author);
        $permalink = get_permalink($post_id);
        $site_name = get_bloginfo('name');

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $type,
            'headline' => $post_data['title'],
            'description' => $post_data['excerpt'] ?? '',
            'url' => $permalink,
            'datePublished' => get_the_date('c', $post_id),
            'dateModified' => get_the_modified_date('c', $post_id),
            'author' => [
                '@type' => 'Person',
                'name' => $author->display_name,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $site_name,
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => get_site_icon_url(),
                ],
            ],
        ];

        // Add image if available
        if (!empty($post_data['featured_image_id'])) {
            $image_url = wp_get_attachment_image_url($post_data['featured_image_id'], 'full');
            if ($image_url) {
                $schema['image'] = $image_url;
            }
        }

        return $schema;
    }

    /**
     * Get post by source URL
     *
     * @since 2.0.0
     * @param string $source_url Source URL.
     * @return int|false Post ID or false.
     */
    public function get_post_by_source($source_url) {
        global $wpdb;

        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = '_abc_source_url' 
            AND meta_value = %s 
            LIMIT 1",
            $source_url
        ));

        return $post_id ? (int) $post_id : false;
    }

    /**
     * Delete post
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param bool $force_delete Bypass trash.
     * @return WP_Post|false|null Post data on success, false or null on failure.
     */
    public function delete($post_id, $force_delete = false) {
        $this->logger->info("Deleting post: ID={$post_id}, Force={$force_delete}");

        return wp_delete_post($post_id, $force_delete);
    }
}
