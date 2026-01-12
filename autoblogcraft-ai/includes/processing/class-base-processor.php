<?php
/**
 * Base Content Processor
 *
 * Abstract base class for all content processors.
 * Implements Template Method pattern for processing workflow.
 *
 * @package AutoBlogCraft\Processing
 * @since 2.0.0
 */

namespace AutoBlogCraft\Processing;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\AI\AI_Manager;
use AutoBlogCraft\Helpers\Duplicate_Detector;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base Processor class
 *
 * Template Method Pattern:
 * - process() - orchestrates the entire processing workflow
 * - fetch_content() - implemented by child classes
 * - extract_metadata() - implemented by child classes
 *
 * @since 2.0.0
 */
abstract class Base_Processor {

    /**
     * Logger instance
     *
     * @var Logger
     */
    protected $logger;

    /**
     * AI Manager instance
     *
     * @var AI_Manager
     */
    protected $ai_manager;

    /**
     * Content Cleaner instance
     *
     * @var Content_Cleaner
     */
    protected $content_cleaner;

    /**
     * Duplicate Detector instance
     *
     * @var Duplicate_Detector
     */
    protected $duplicate_detector;

    /**
     * Post Publisher instance
     *
     * @var Post_Publisher
     */
    protected $post_publisher;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->logger = Logger::instance();
        $this->ai_manager = AI_Manager::instance();
        $this->content_cleaner = new Content_Cleaner();
        $this->duplicate_detector = new Duplicate_Detector();
        $this->post_publisher = new Post_Publisher();
    }

    /**
     * Process queue item
     *
     * Template method that orchestrates the entire processing workflow.
     *
     * @since 2.0.0
     * @param array $queue_item Queue item data.
     * @param object $campaign Campaign instance.
     * @return int|WP_Error Post ID on success, error otherwise.
     */
    public function process($queue_item, $campaign) {
        $queue_id = $queue_item['id'];
        $source_url = $queue_item['source_url'];
        $campaign_id = $campaign->get_id();

        $this->logger->info("Processing queue item: ID={$queue_id}, URL={$source_url}");

        try {
            // Step 1: Validate item
            $validation = $this->validate_item($queue_item, $campaign);
            if (is_wp_error($validation)) {
                return $validation;
            }

            // Step 2: Check duplicates (final check before processing)
            if ($this->is_duplicate($queue_item)) {
                return new WP_Error(
                    'duplicate_content',
                    'Content already exists in database'
                );
            }

            // Step 3: Fetch full content
            $content_data = $this->fetch_content($queue_item);
            if (is_wp_error($content_data)) {
                return $content_data;
            }

            // Step 4: Clean content
            $cleaned = $this->clean_content($content_data);
            if (is_wp_error($cleaned)) {
                return $cleaned;
            }

            // Step 5: Extract metadata
            $metadata = $this->extract_metadata($content_data, $queue_item);

            // Step 6: Rewrite content with AI
            $rewritten = $this->rewrite_content($campaign, $cleaned, $metadata);
            if (is_wp_error($rewritten)) {
                return $rewritten;
            }

            // Step 7: Generate featured image
            $featured_image = $this->generate_featured_image($campaign, $rewritten, $metadata);

            // Step 8: Prepare post data
            $post_data = $this->prepare_post_data($campaign, $rewritten, $metadata, $featured_image);

            // Step 9: Publish post
            $post_id = $this->post_publisher->publish($post_data, $campaign);
            if (is_wp_error($post_id)) {
                return $post_id;
            }

            // Step 10: Store source reference
            $this->store_source_reference($post_id, $queue_item);

            $this->logger->info("Processing complete: Queue={$queue_id}, Post={$post_id}");

            return $post_id;

        } catch (\Exception $e) {
            $error = new WP_Error('processing_exception', $e->getMessage());
            $this->logger->error("Processing exception: {$e->getMessage()}");
            return $error;
        }
    }

    /**
     * Fetch full content from source
     *
     * Must be implemented by child classes.
     *
     * @since 2.0.0
     * @param array $queue_item Queue item data.
     * @return array|WP_Error Content data with 'title', 'content', 'html' keys or error.
     */
    abstract protected function fetch_content($queue_item);

    /**
     * Extract metadata from content
     *
     * Can be overridden by child classes for source-specific metadata.
     *
     * @since 2.0.0
     * @param array $content_data Fetched content.
     * @param array $queue_item Queue item.
     * @return array Metadata array.
     */
    protected function extract_metadata($content_data, $queue_item) {
        return [
            'source_url' => $queue_item['source_url'],
            'source_title' => isset($content_data['title']) ? $content_data['title'] : $queue_item['title'],
            'source_author' => isset($content_data['author']) ? $content_data['author'] : '',
            'source_date' => isset($content_data['date']) ? $content_data['date'] : '',
            'source_image' => isset($content_data['image']) ? $content_data['image'] : '',
            'source_excerpt' => $queue_item['excerpt'],
            'source_data' => $queue_item['source_data'],
        ];
    }

    /**
     * Validate queue item
     *
     * Can be overridden for processor-specific validation.
     *
     * @since 2.0.0
     * @param array $queue_item Queue item.
     * @param object $campaign Campaign instance.
     * @return bool|WP_Error True if valid, error otherwise.
     */
    protected function validate_item($queue_item, $campaign) {
        if (empty($queue_item['source_url'])) {
            return new WP_Error('missing_url', 'Source URL is required');
        }

        if (empty($queue_item['title'])) {
            return new WP_Error('missing_title', 'Title is required');
        }

        return true;
    }

    /**
     * Check if content is duplicate
     *
     * @since 2.0.0
     * @param array $queue_item Queue item.
     * @return bool True if duplicate, false otherwise.
     */
    protected function is_duplicate($queue_item) {
        // Check URL
        if ($this->duplicate_detector->url_exists($queue_item['source_url'])) {
            $this->logger->debug("Duplicate URL detected: {$queue_item['source_url']}");
            return true;
        }

        // Check title similarity
        $similar = $this->duplicate_detector->find_similar_title($queue_item['title'], 90);
        if (!empty($similar)) {
            $this->logger->debug("Duplicate title detected: {$queue_item['title']}");
            return true;
        }

        return false;
    }

    /**
     * Clean content
     *
     * @since 2.0.0
     * @param array $content_data Content data.
     * @return string|WP_Error Cleaned content or error.
     */
    protected function clean_content($content_data) {
        if (empty($content_data['content'])) {
            return new WP_Error('empty_content', 'Content is empty');
        }

        $content = $content_data['content'];

        // Clean HTML
        $cleaned = $this->content_cleaner->clean($content);

        // Validate minimum length
        $word_count = str_word_count(strip_tags($cleaned));
        if ($word_count < 100) {
            return new WP_Error(
                'content_too_short',
                "Content too short: {$word_count} words (minimum 100)"
            );
        }

        return $cleaned;
    }

    /**
     * Rewrite content with AI
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @param string $content Original content.
     * @param array $metadata Metadata.
     * @return array|WP_Error Rewritten content data or error.
     */
    protected function rewrite_content($campaign, $content, $metadata) {
        $campaign_id = $campaign->get_id();

        // Get AI configuration
        $min_words = $campaign->get_meta('min_words', 600);
        $max_words = $campaign->get_meta('max_words', 1200);
        $tone = $campaign->get_meta('tone', 'professional');
        $language = $campaign->get_meta('language', 'English');

        // Rewrite with AI
        $result = $this->ai_manager->rewrite_content($campaign_id, $content, [
            'title' => $metadata['source_title'],
            'min_words' => $min_words,
            'max_words' => $max_words,
            'tone' => $tone,
            'language' => $language,
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        // Apply humanization if enabled
        if ($campaign->get_meta('enable_humanization', false)) {
            $result = $this->apply_humanization($campaign, $result);
            if (is_wp_error($result)) {
                $this->logger->warning("Humanization failed, using original content");
            }
        }

        // Apply translation if needed
        $target_language = $campaign->get_meta('target_language', '');
        if (!empty($target_language) && $target_language !== $language) {
            $result = $this->apply_translation($campaign, $result, $language, $target_language);
            if (is_wp_error($result)) {
                $this->logger->warning("Translation failed, using original language");
            }
        }

        return [
            'title' => $result['seo']['title'],
            'content' => $result['content'],
            'excerpt' => $result['seo']['meta_description'],
            'seo' => $result['seo'],
            'tokens_used' => $result['tokens_used'],
        ];
    }

    /**
     * Generate featured image
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @param array $rewritten Rewritten content.
     * @param array $metadata Metadata.
     * @return int|null Attachment ID or null if no image.
     */
    protected function generate_featured_image($campaign, $rewritten, $metadata) {
        // Check if featured images are enabled
        if (!$campaign->get_meta('featured_image_enabled', true)) {
            return null;
        }

        $image_generator = new Image_Generator();

        // Try source image first
        if (!empty($metadata['source_image'])) {
            $attachment_id = $image_generator->download_image(
                $metadata['source_image'],
                $rewritten['title']
            );

            if (!is_wp_error($attachment_id)) {
                return $attachment_id;
            }
        }

        // Fallback to AI-generated image (if enabled)
        if ($campaign->get_meta('ai_image_enabled', false)) {
            $attachment_id = $image_generator->generate_from_text(
                $campaign,
                $rewritten['title']
            );

            if (!is_wp_error($attachment_id)) {
                return $attachment_id;
            }
        }

        return null;
    }

    /**
     * Prepare post data
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @param array $rewritten Rewritten content.
     * @param array $metadata Metadata.
     * @param int|null $featured_image Featured image ID.
     * @return array Post data.
     */
    protected function prepare_post_data($campaign, $rewritten, $metadata, $featured_image) {
        return [
            'title' => $rewritten['title'],
            'content' => $rewritten['content'],
            'excerpt' => $rewritten['excerpt'],
            'featured_image' => $featured_image,
            'seo' => $rewritten['seo'],
            'metadata' => $metadata,
            'status' => $campaign->get_meta('post_status', 'draft'),
            'author' => $campaign->get_meta('post_author', get_current_user_id()),
            'category' => $campaign->get_meta('post_category', []),
            'tags' => isset($rewritten['seo']['keywords']) ? $rewritten['seo']['keywords'] : [],
        ];
    }

    /**
     * Store source reference
     *
     * Links published post to original source.
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param array $queue_item Queue item.
     */
    protected function store_source_reference($post_id, $queue_item) {
        update_post_meta($post_id, '_abc_source_url', $queue_item['source_url']);
        update_post_meta($post_id, '_abc_source_type', $queue_item['source_type']);
        update_post_meta($post_id, '_abc_campaign_id', $queue_item['campaign_id']);
        update_post_meta($post_id, '_abc_queue_id', $queue_item['id']);
        update_post_meta($post_id, '_abc_processed_at', current_time('mysql'));

        // Store full source data
        if (!empty($queue_item['source_data'])) {
            update_post_meta($post_id, '_abc_source_data', $queue_item['source_data']);
        }
    }

    /**
     * Apply humanization to AI-generated content
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @param array $result AI rewrite result.
     * @return array|WP_Error Humanized result or error.
     */
    protected function apply_humanization($campaign, $result) {
        if (!class_exists('AutoBlogCraft\Modules\Humanizer\Humanizer_Module')) {
            require_once ABC_PLUGIN_DIR . 'includes/modules/humanizer/class-humanizer-module.php';
        }

        $humanizer = \AutoBlogCraft\Modules\Humanizer\Humanizer_Module::get_instance();
        
        $campaign_id = $campaign->get_id();
        $provider = $campaign->get_meta('humanizer_provider', 'gpt4o'); // gpt4o or undetectable
        
        $this->logger->info("Applying humanization with provider: {$provider}");
        
        $humanized = $humanizer->humanize($result['content'], $campaign_id, [
            'provider' => $provider,
            'title' => $result['seo']['title'],
        ]);
        
        if (is_wp_error($humanized)) {
            return $humanized;
        }
        
        // Update content with humanized version
        $result['content'] = $humanized;
        
        return $result;
    }

    /**
     * Apply translation to content
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @param array $result AI rewrite result.
     * @param string $source_lang Source language.
     * @param string $target_lang Target language.
     * @return array|WP_Error Translated result or error.
     */
    protected function apply_translation($campaign, $result, $source_lang, $target_lang) {
        if (!class_exists('AutoBlogCraft\Modules\Translation\Translation_Module')) {
            require_once ABC_PLUGIN_DIR . 'includes/modules/translation/class-translation-module.php';
        }

        $translator = \AutoBlogCraft\Modules\Translation\Translation_Module::get_instance();
        
        $campaign_id = $campaign->get_id();
        
        $this->logger->info("Translating content from {$source_lang} to {$target_lang}");
        
        // Translate title
        $translated_title = $translator->translate(
            $result['seo']['title'],
            $source_lang,
            $target_lang,
            $campaign_id
        );
        
        if (is_wp_error($translated_title)) {
            return $translated_title;
        }
        
        // Translate content
        $translated_content = $translator->translate(
            $result['content'],
            $source_lang,
            $target_lang,
            $campaign_id
        );
        
        if (is_wp_error($translated_content)) {
            return $translated_content;
        }
        
        // Translate excerpt/meta description
        $translated_excerpt = $translator->translate(
            $result['seo']['meta_description'],
            $source_lang,
            $target_lang,
            $campaign_id
        );
        
        if (is_wp_error($translated_excerpt)) {
            return $translated_excerpt;
        }
        
        // Update result with translations
        $result['seo']['title'] = $translated_title;
        $result['content'] = $translated_content;
        $result['seo']['meta_description'] = $translated_excerpt;
        
        return $result;
    }

    /**
     * Get processor type
     *
     * Must be implemented by child classes.
     *
     * @since 2.0.0
     * @return string Processor type identifier.
     */
    abstract protected function get_processor_type();
}
