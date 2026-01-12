<?php
/**
 * SEO Module
 *
 * Main SEO module orchestrator that initializes and coordinates all SEO features.
 *
 * @package AutoBlogCraft\Modules\SEO
 * @since 2.0.0
 */

namespace AutoBlogCraft\Modules\SEO;

use AutoBlogCraft\Core\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SEO Module class
 *
 * Coordinates SEO functionality including meta generation, schema markup,
 * and third-party plugin integrations.
 *
 * @since 2.0.0
 */
class SEO_Module {

    /**
     * Singleton instance
     *
     * @var SEO_Module
     */
    private static $instance = null;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Meta generator instance
     *
     * @var Meta_Generator
     */
    private $meta_generator;

    /**
     * Schema builder instance
     *
     * @var Schema_Builder
     */
    private $schema_builder;

    /**
     * Get singleton instance
     *
     * @since 2.0.0
     * @return SEO_Module
     */
    public static function get_instance() {
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
        $this->logger = Logger::instance();
        $this->meta_generator = new Meta_Generator();
        $this->schema_builder = new Schema_Builder();
    }

    /**
     * Initialize SEO module
     *
     * Hooks into WordPress to apply SEO features.
     *
     * @since 2.0.0
     * @return void
     */
    public function init() {
        // Hook into post save to generate SEO meta
        add_action('save_post', [$this, 'on_save_post'], 10, 2);

        // Add schema markup to post content
        add_filter('the_content', [$this, 'add_schema_markup'], 20);

        // Add meta tags to head
        add_action('wp_head', [$this, 'output_meta_tags'], 5);

        // Modify title tag
        add_filter('pre_get_document_title', [$this, 'modify_title'], 10, 1);

        $this->logger->info('SEO Module initialized');
    }

    /**
     * Check if SEO is enabled for campaign
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return bool True if enabled, false otherwise.
     */
    public function is_enabled($campaign_id) {
        $enabled = get_post_meta($campaign_id, '_seo_enabled', true);
        return !empty($enabled);
    }

    /**
     * Handle post save event
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param WP_Post $post Post object.
     * @return void
     */
    public function on_save_post($post_id, $post) {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Check if this is an ABC-generated post
        $campaign_id = get_post_meta($post_id, '_abc_campaign_id', true);
        if (!$campaign_id) {
            return;
        }

        // Check if SEO is enabled
        if (!$this->is_enabled($campaign_id)) {
            return;
        }

        // Generate and save SEO meta
        $this->generate_seo_meta($post_id, $campaign_id);
    }

    /**
     * Generate SEO meta for post
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param int $campaign_id Campaign ID.
     * @return void
     */
    private function generate_seo_meta($post_id, $campaign_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return;
        }

        // Get SEO settings
        $settings = $this->get_seo_settings($campaign_id);

        // Generate title
        if (empty(get_post_meta($post_id, '_yoast_wpseo_title', true))) {
            $title = $this->meta_generator->generate_title($post, $settings);
            if (!is_wp_error($title)) {
                update_post_meta($post_id, '_abc_seo_title', $title);
                update_post_meta($post_id, '_yoast_wpseo_title', $title);
            }
        }

        // Generate description
        if (empty(get_post_meta($post_id, '_yoast_wpseo_metadesc', true))) {
            $description = $this->meta_generator->generate_description($post, $settings);
            if (!is_wp_error($description)) {
                update_post_meta($post_id, '_abc_seo_description', $description);
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $description);
            }
        }

        // Generate keywords
        $keywords = $this->meta_generator->generate_keywords($post, $settings);
        if (!is_wp_error($keywords)) {
            update_post_meta($post_id, '_abc_seo_keywords', implode(', ', $keywords));
        }

        // Generate focus keyword (first keyword)
        if (!empty($keywords) && empty(get_post_meta($post_id, '_yoast_wpseo_focuskw', true))) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', $keywords[0]);
        }

        $this->logger->info("Generated SEO meta for post {$post_id}");
    }

    /**
     * Get SEO settings for campaign
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return array SEO settings.
     */
    private function get_seo_settings($campaign_id) {
        return [
            'title_template' => get_post_meta($campaign_id, '_seo_title_template', true),
            'description_length' => (int) get_post_meta($campaign_id, '_seo_description_length', true) ?: 155,
            'keyword_count' => (int) get_post_meta($campaign_id, '_seo_keyword_count', true) ?: 5,
            'schema_enabled' => get_post_meta($campaign_id, '_seo_schema_enabled', true),
            'schema_type' => get_post_meta($campaign_id, '_seo_schema_type', true) ?: 'article',
        ];
    }

    /**
     * Add schema markup to content
     *
     * @since 2.0.0
     * @param string $content Post content.
     * @return string Modified content.
     */
    public function add_schema_markup($content) {
        if (!is_single()) {
            return $content;
        }

        global $post;

        $campaign_id = get_post_meta($post->ID, '_abc_campaign_id', true);
        if (!$campaign_id) {
            return $content;
        }

        if (!$this->is_enabled($campaign_id)) {
            return $content;
        }

        $settings = $this->get_seo_settings($campaign_id);
        
        if (empty($settings['schema_enabled'])) {
            return $content;
        }

        // Build schema
        $schema = $this->schema_builder->build($post, $settings['schema_type']);
        
        if (!is_wp_error($schema)) {
            // Add schema as JSON-LD script
            $schema_script = '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>' . "\n";
            $content = $schema_script . $content;
        }

        return $content;
    }

    /**
     * Output meta tags in head
     *
     * @since 2.0.0
     * @return void
     */
    public function output_meta_tags() {
        if (!is_single()) {
            return;
        }

        global $post;

        $campaign_id = get_post_meta($post->ID, '_abc_campaign_id', true);
        if (!$campaign_id) {
            return;
        }

        if (!$this->is_enabled($campaign_id)) {
            return;
        }

        // Output Open Graph tags
        $this->output_og_tags($post);

        // Output Twitter Card tags
        $this->output_twitter_tags($post);
    }

    /**
     * Output Open Graph tags
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @return void
     */
    private function output_og_tags($post) {
        $title = get_post_meta($post->ID, '_abc_seo_title', true) ?: get_the_title($post);
        $description = get_post_meta($post->ID, '_abc_seo_description', true) ?: wp_trim_words($post->post_content, 30);
        $image = get_the_post_thumbnail_url($post, 'large');

        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
        echo '<meta property="og:type" content="article">' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_permalink($post)) . '">' . "\n";
        
        if ($image) {
            echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
        }
    }

    /**
     * Output Twitter Card tags
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @return void
     */
    private function output_twitter_tags($post) {
        $title = get_post_meta($post->ID, '_abc_seo_title', true) ?: get_the_title($post);
        $description = get_post_meta($post->ID, '_abc_seo_description', true) ?: wp_trim_words($post->post_content, 30);
        $image = get_the_post_thumbnail_url($post, 'large');

        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
        
        if ($image) {
            echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
        }
    }

    /**
     * Modify document title
     *
     * @since 2.0.0
     * @param string $title Document title.
     * @return string Modified title.
     */
    public function modify_title($title) {
        if (!is_single()) {
            return $title;
        }

        global $post;

        $campaign_id = get_post_meta($post->ID, '_abc_campaign_id', true);
        if (!$campaign_id) {
            return $title;
        }

        if (!$this->is_enabled($campaign_id)) {
            return $title;
        }

        $custom_title = get_post_meta($post->ID, '_abc_seo_title', true);
        
        return $custom_title ?: $title;
    }

    /**
     * Generate SEO meta for multiple posts
     *
     * Batch operation for generating SEO meta.
     *
     * @since 2.0.0
     * @param array $post_ids Array of post IDs.
     * @param int $campaign_id Campaign ID.
     * @return array Results with success/failure counts.
     */
    public function generate_bulk_seo($post_ids, $campaign_id) {
        $success = 0;
        $failed = 0;

        foreach ($post_ids as $post_id) {
            try {
                $this->generate_seo_meta($post_id, $campaign_id);
                $success++;
            } catch (\Exception $e) {
                $this->logger->error("Failed to generate SEO for post {$post_id}: " . $e->getMessage());
                $failed++;
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'total' => count($post_ids),
        ];
    }

    /**
     * Validate SEO configuration
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return array|WP_Error Validation results or error.
     */
    public function validate_configuration($campaign_id) {
        $issues = [];

        $settings = $this->get_seo_settings($campaign_id);

        // Check title template
        if (empty($settings['title_template'])) {
            $issues[] = 'No title template configured';
        }

        // Check description length
        if ($settings['description_length'] < 50 || $settings['description_length'] > 160) {
            $issues[] = 'Description length should be between 50-160 characters';
        }

        // Check schema configuration
        if (!empty($settings['schema_enabled']) && empty($settings['schema_type'])) {
            $issues[] = 'Schema enabled but no type specified';
        }

        if (!empty($issues)) {
            return new WP_Error('invalid_seo_config', 'SEO configuration has issues', ['issues' => $issues]);
        }

        return ['valid' => true, 'message' => 'SEO configuration is valid'];
    }
}
