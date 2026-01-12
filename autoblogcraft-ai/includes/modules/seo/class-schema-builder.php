<?php
/**
 * Schema Builder
 *
 * Generates Schema.org JSON-LD markup for different content types.
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
 * Schema Builder class
 *
 * Handles generation of Schema.org structured data.
 *
 * @since 2.0.0
 */
class Schema_Builder {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Schema context
     *
     * @var string
     */
    private $context = 'https://schema.org';

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->logger = Logger::instance();
    }

    /**
     * Build schema
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @param string $type Schema type (article, product, video).
     * @param array $settings Schema settings.
     * @return array|WP_Error Schema array or error.
     */
    public function build($post, $type = 'article', $settings = []) {
        switch ($type) {
            case 'article':
                return $this->build_article($post, $settings);

            case 'product':
                return $this->build_product($post, $settings);

            case 'video':
                return $this->build_video($post, $settings);

            case 'faq':
                return $this->build_faq($post, $settings);

            case 'breadcrumb':
                return $this->build_breadcrumb($post, $settings);

            default:
                return new WP_Error('invalid_schema_type', "Schema type '{$type}' is not supported");
        }
    }

    /**
     * Build Article schema
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @param array $settings Settings.
     * @return array Article schema.
     */
    public function build_article($post, $settings = []) {
        $schema = [
            '@context' => $this->context,
            '@type' => 'Article',
            'headline' => get_the_title($post),
            'description' => get_the_excerpt($post),
            'datePublished' => get_the_date('c', $post),
            'dateModified' => get_the_modified_date('c', $post),
            'author' => $this->get_author_schema($post),
            'publisher' => $this->get_publisher_schema(),
        ];

        // Add image
        $image = $this->get_image_schema($post);
        if ($image) {
            $schema['image'] = $image;
        }

        // Add main entity of page
        $schema['mainEntityOfPage'] = [
            '@type' => 'WebPage',
            '@id' => get_permalink($post),
        ];

        // Add article section
        $categories = get_the_category($post->ID);
        if (!empty($categories)) {
            $schema['articleSection'] = $categories[0]->name;
        }

        // Add word count
        $content = wp_strip_all_tags($post->post_content);
        $word_count = str_word_count($content);
        if ($word_count > 0) {
            $schema['wordCount'] = $word_count;
        }

        return $schema;
    }

    /**
     * Build Product schema
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @param array $settings Settings.
     * @return array Product schema.
     */
    public function build_product($post, $settings = []) {
        $schema = [
            '@context' => $this->context,
            '@type' => 'Product',
            'name' => get_the_title($post),
            'description' => get_the_excerpt($post),
        ];

        // Add image
        $image = $this->get_image_schema($post);
        if ($image) {
            $schema['image'] = $image;
        }

        // Add brand
        $brand = get_post_meta($post->ID, '_abc_amazon_brand', true);
        if ($brand) {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name' => $brand,
            ];
        }

        // Add SKU
        $asin = get_post_meta($post->ID, '_abc_amazon_asin', true);
        if ($asin) {
            $schema['sku'] = $asin;
        }

        // Add offers
        $price = get_post_meta($post->ID, '_abc_amazon_price', true);
        if ($price) {
            $schema['offers'] = [
                '@type' => 'Offer',
                'priceCurrency' => 'USD',
                'price' => floatval($price),
                'availability' => 'https://schema.org/InStock',
                'url' => get_post_meta($post->ID, '_abc_amazon_url', true),
                'seller' => [
                    '@type' => 'Organization',
                    'name' => 'Amazon',
                ],
            ];
        }

        // Add aggregate rating
        $rating = get_post_meta($post->ID, '_abc_amazon_rating', true);
        $review_count = get_post_meta($post->ID, '_abc_amazon_reviews', true);
        
        if ($rating && $review_count) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => floatval($rating),
                'reviewCount' => intval($review_count),
                'bestRating' => '5',
                'worstRating' => '1',
            ];
        }

        return $schema;
    }

    /**
     * Build VideoObject schema
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @param array $settings Settings.
     * @return array Video schema.
     */
    public function build_video($post, $settings = []) {
        $schema = [
            '@context' => $this->context,
            '@type' => 'VideoObject',
            'name' => get_the_title($post),
            'description' => get_the_excerpt($post),
            'uploadDate' => get_the_date('c', $post),
        ];

        // Add thumbnail
        $thumbnail = get_the_post_thumbnail_url($post, 'full');
        if ($thumbnail) {
            $schema['thumbnailUrl'] = $thumbnail;
        }

        // Add duration
        $duration = get_post_meta($post->ID, '_abc_youtube_duration', true);
        if ($duration) {
            $schema['duration'] = $this->convert_duration_to_iso8601($duration);
        }

        // Add embed URL
        $video_id = get_post_meta($post->ID, '_abc_youtube_video_id', true);
        if ($video_id) {
            $schema['embedUrl'] = "https://www.youtube.com/embed/{$video_id}";
            $schema['contentUrl'] = "https://www.youtube.com/watch?v={$video_id}";
        }

        // Add publisher
        $schema['publisher'] = $this->get_publisher_schema();

        return $schema;
    }

    /**
     * Build FAQ schema
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @param array $settings Settings.
     * @return array|null FAQ schema or null if no FAQs found.
     */
    public function build_faq($post, $settings = []) {
        // Extract FAQ from content
        $faqs = $this->extract_faqs_from_content($post->post_content);

        if (empty($faqs)) {
            return null;
        }

        $schema = [
            '@context' => $this->context,
            '@type' => 'FAQPage',
            'mainEntity' => [],
        ];

        foreach ($faqs as $faq) {
            $schema['mainEntity'][] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ];
        }

        return $schema;
    }

    /**
     * Build BreadcrumbList schema
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @param array $settings Settings.
     * @return array Breadcrumb schema.
     */
    public function build_breadcrumb($post, $settings = []) {
        $schema = [
            '@context' => $this->context,
            '@type' => 'BreadcrumbList',
            'itemListElement' => [],
        ];

        $position = 1;

        // Home
        $schema['itemListElement'][] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => 'Home',
            'item' => home_url('/'),
        ];

        // Category
        $categories = get_the_category($post->ID);
        if (!empty($categories)) {
            $category = $categories[0];
            $schema['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $category->name,
                'item' => get_category_link($category->term_id),
            ];
        }

        // Current post
        $schema['itemListElement'][] = [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => get_the_title($post),
            'item' => get_permalink($post),
        ];

        return $schema;
    }

    /**
     * Get author schema
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @return array Author schema.
     */
    private function get_author_schema($post) {
        $author_id = $post->post_author;
        $author_name = get_the_author_meta('display_name', $author_id);
        $author_url = get_author_posts_url($author_id);

        return [
            '@type' => 'Person',
            'name' => $author_name,
            'url' => $author_url,
        ];
    }

    /**
     * Get publisher schema
     *
     * @since 2.0.0
     * @return array Publisher schema.
     */
    private function get_publisher_schema() {
        $publisher = [
            '@type' => 'Organization',
            'name' => get_bloginfo('name'),
            'url' => home_url('/'),
        ];

        // Add logo
        $logo = get_option('abc_publisher_logo');
        if (!$logo) {
            // Try site icon
            $logo = get_site_icon_url();
        }

        if ($logo) {
            $publisher['logo'] = [
                '@type' => 'ImageObject',
                'url' => $logo,
            ];
        }

        return $publisher;
    }

    /**
     * Get image schema
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @return array|null Image schema or null.
     */
    private function get_image_schema($post) {
        $image_url = get_the_post_thumbnail_url($post, 'full');
        
        if (!$image_url) {
            return null;
        }

        $image_id = get_post_thumbnail_id($post);
        $image_meta = wp_get_attachment_metadata($image_id);

        $schema = [
            '@type' => 'ImageObject',
            'url' => $image_url,
        ];

        if (!empty($image_meta['width'])) {
            $schema['width'] = $image_meta['width'];
        }

        if (!empty($image_meta['height'])) {
            $schema['height'] = $image_meta['height'];
        }

        return $schema;
    }

    /**
     * Extract FAQs from content
     *
     * @since 2.0.0
     * @param string $content Post content.
     * @return array FAQs.
     */
    private function extract_faqs_from_content($content) {
        $faqs = [];

        // Pattern to match FAQ blocks
        // Looks for headings with "?" followed by content
        preg_match_all('/<h[2-4][^>]*>(.*?\?)<\/h[2-4]>\s*<p>(.*?)<\/p>/is', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $question = wp_strip_all_tags($match[1]);
            $answer = wp_strip_all_tags($match[2]);

            if (!empty($question) && !empty($answer)) {
                $faqs[] = [
                    'question' => $question,
                    'answer' => $answer,
                ];
            }
        }

        return $faqs;
    }

    /**
     * Convert duration to ISO 8601 format
     *
     * @since 2.0.0
     * @param string $duration Duration (e.g., "10:30" or "1:05:45").
     * @return string ISO 8601 duration (e.g., "PT10M30S").
     */
    private function convert_duration_to_iso8601($duration) {
        $parts = explode(':', $duration);
        $seconds = 0;
        $minutes = 0;
        $hours = 0;

        if (count($parts) === 3) {
            // H:M:S
            $hours = (int) $parts[0];
            $minutes = (int) $parts[1];
            $seconds = (int) $parts[2];
        } elseif (count($parts) === 2) {
            // M:S
            $minutes = (int) $parts[0];
            $seconds = (int) $parts[1];
        } else {
            // Just seconds
            $seconds = (int) $parts[0];
        }

        $iso = 'PT';
        if ($hours > 0) {
            $iso .= $hours . 'H';
        }
        if ($minutes > 0) {
            $iso .= $minutes . 'M';
        }
        if ($seconds > 0) {
            $iso .= $seconds . 'S';
        }

        return $iso !== 'PT' ? $iso : 'PT0S';
    }

    /**
     * Validate schema
     *
     * @since 2.0.0
     * @param array $schema Schema array.
     * @return array|WP_Error Validation result or error.
     */
    public function validate_schema($schema) {
        $issues = [];

        // Check required fields
        if (empty($schema['@context'])) {
            $issues[] = 'Missing @context';
        }

        if (empty($schema['@type'])) {
            $issues[] = 'Missing @type';
        }

        // Type-specific validation
        switch ($schema['@type'] ?? '') {
            case 'Article':
                if (empty($schema['headline'])) {
                    $issues[] = 'Article missing headline';
                }
                if (empty($schema['author'])) {
                    $issues[] = 'Article missing author';
                }
                break;

            case 'Product':
                if (empty($schema['name'])) {
                    $issues[] = 'Product missing name';
                }
                break;

            case 'VideoObject':
                if (empty($schema['name'])) {
                    $issues[] = 'Video missing name';
                }
                if (empty($schema['uploadDate'])) {
                    $issues[] = 'Video missing uploadDate';
                }
                break;
        }

        if (!empty($issues)) {
            return new WP_Error('invalid_schema', 'Schema validation failed', ['issues' => $issues]);
        }

        return ['valid' => true];
    }

    /**
     * Generate JSON-LD script tag
     *
     * @since 2.0.0
     * @param array $schema Schema array.
     * @return string Script tag HTML.
     */
    public function generate_json_ld($schema) {
        if (empty($schema)) {
            return '';
        }

        $json = wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        return sprintf(
            '<script type="application/ld+json">%s</script>',
            $json
        );
    }

    /**
     * Build multiple schemas
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @param array $types Schema types to build.
     * @param array $settings Settings.
     * @return array Multiple schemas.
     */
    public function build_multiple($post, $types = [], $settings = []) {
        $schemas = [];

        foreach ($types as $type) {
            $schema = $this->build($post, $type, $settings);
            
            if (!is_wp_error($schema) && !empty($schema)) {
                $schemas[] = $schema;
            }
        }

        return $schemas;
    }
}
