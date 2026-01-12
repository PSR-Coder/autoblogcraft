<?php
/**
 * Meta Generator
 *
 * Generates SEO meta tags including titles, descriptions, and keywords.
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
 * Meta Generator class
 *
 * Handles generation of SEO metadata for posts.
 *
 * @since 2.0.0
 */
class Meta_Generator {

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
     * Generate SEO title
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @param array $settings SEO settings.
     * @return string|WP_Error Generated title or error.
     */
    public function generate_title($post, $settings = []) {
        // Use template if provided
        if (!empty($settings['title_template'])) {
            return $this->apply_title_template($post, $settings['title_template']);
        }

        // Default: Post title + Site name
        $title = get_the_title($post);
        $site_name = get_bloginfo('name');

        // Optimize title length (max 60 characters for SEO)
        $max_length = 60;
        $separator = ' - ';

        if (strlen($title . $separator . $site_name) > $max_length) {
            // Truncate title to fit
            $available_length = $max_length - strlen($separator . $site_name);
            if ($available_length > 20) {
                $title = wp_trim_words($title, 5, '...');
            }
        }

        return $title . $separator . $site_name;
    }

    /**
     * Apply title template
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @param string $template Title template.
     * @return string Generated title.
     */
    private function apply_title_template($post, $template) {
        $replacements = [
            '{title}' => get_the_title($post),
            '{site_name}' => get_bloginfo('name'),
            '{category}' => $this->get_primary_category($post),
            '{date}' => get_the_date('', $post),
            '{author}' => get_the_author_meta('display_name', $post->post_author),
            '{excerpt}' => wp_trim_words($post->post_content, 10, '...'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Generate meta description
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @param array $settings SEO settings.
     * @return string|WP_Error Generated description or error.
     */
    public function generate_description($post, $settings = []) {
        $length = !empty($settings['description_length']) ? (int) $settings['description_length'] : 155;

        // Try excerpt first
        if (!empty($post->post_excerpt)) {
            $description = $post->post_excerpt;
        } else {
            // Generate from content
            $description = wp_strip_all_tags($post->post_content);
            $description = preg_replace('/\s+/', ' ', $description); // Normalize whitespace
        }

        // Truncate to desired length
        if (strlen($description) > $length) {
            $description = substr($description, 0, $length);
            
            // Try to end at last complete word
            $last_space = strrpos($description, ' ');
            if ($last_space !== false && $last_space > ($length * 0.8)) {
                $description = substr($description, 0, $last_space);
            }
            
            $description .= '...';
        }

        // Ensure description starts with a capital letter
        $description = ucfirst(trim($description));

        return $description;
    }

    /**
     * Generate keywords
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @param array $settings SEO settings.
     * @return array|WP_Error Array of keywords or error.
     */
    public function generate_keywords($post, $settings = []) {
        $count = !empty($settings['keyword_count']) ? (int) $settings['keyword_count'] : 5;

        $keywords = [];

        // Extract from categories
        $categories = get_the_category($post->ID);
        foreach ($categories as $category) {
            $keywords[] = $category->name;
        }

        // Extract from tags
        $tags = get_the_tags($post->ID);
        if ($tags) {
            foreach ($tags as $tag) {
                $keywords[] = $tag->name;
            }
        }

        // Extract from title
        $title_words = $this->extract_keywords_from_text(get_the_title($post), 3);
        $keywords = array_merge($keywords, $title_words);

        // Extract from content
        $content_words = $this->extract_keywords_from_text($post->post_content, $count * 2);
        $keywords = array_merge($keywords, $content_words);

        // Remove duplicates and limit
        $keywords = array_unique($keywords);
        $keywords = array_slice($keywords, 0, $count);

        return $keywords;
    }

    /**
     * Extract keywords from text
     *
     * @since 2.0.0
     * @param string $text Text to analyze.
     * @param int $limit Maximum keywords.
     * @return array Keywords.
     */
    private function extract_keywords_from_text($text, $limit = 10) {
        // Strip HTML and normalize
        $text = wp_strip_all_tags($text);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/i', ' ', $text);

        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Remove stop words
        $stop_words = $this->get_stop_words();
        $words = array_diff($words, $stop_words);

        // Filter short words (< 4 characters)
        $words = array_filter($words, function ($word) {
            return strlen($word) >= 4;
        });

        // Count frequency
        $frequency = array_count_values($words);
        arsort($frequency);

        // Return top keywords
        return array_slice(array_keys($frequency), 0, $limit);
    }

    /**
     * Get stop words
     *
     * @since 2.0.0
     * @return array Stop words.
     */
    private function get_stop_words() {
        return [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
            'of', 'with', 'by', 'from', 'about', 'as', 'into', 'through', 'during',
            'before', 'after', 'above', 'below', 'between', 'under', 'again', 'further',
            'then', 'once', 'here', 'there', 'when', 'where', 'why', 'how', 'all',
            'both', 'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no',
            'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 'can',
            'will', 'just', 'should', 'now', 'this', 'that', 'these', 'those', 'am',
            'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had',
            'having', 'do', 'does', 'did', 'doing', 'would', 'could', 'ought',
        ];
    }

    /**
     * Get primary category
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @return string Primary category name.
     */
    private function get_primary_category($post) {
        $categories = get_the_category($post->ID);
        
        if (empty($categories)) {
            return '';
        }

        // Check if Yoast primary category is set
        if (class_exists('WPSEO_Primary_Term')) {
            $primary_term = new \WPSEO_Primary_Term('category', $post->ID);
            $primary_id = $primary_term->get_primary_term();
            
            if ($primary_id) {
                $primary_cat = get_category($primary_id);
                if ($primary_cat) {
                    return $primary_cat->name;
                }
            }
        }

        // Return first category
        return $categories[0]->name;
    }

    /**
     * Generate focus keyword
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @param array $settings SEO settings.
     * @return string|WP_Error Focus keyword or error.
     */
    public function generate_focus_keyword($post, $settings = []) {
        $keywords = $this->generate_keywords($post, $settings);
        
        if (is_wp_error($keywords) || empty($keywords)) {
            return new WP_Error('no_keywords', 'Could not generate keywords');
        }

        // Return most frequent keyword
        return $keywords[0];
    }

    /**
     * Validate meta data
     *
     * @since 2.0.0
     * @param string $type Meta type (title, description, keywords).
     * @param mixed $value Meta value.
     * @return array|WP_Error Validation result or error.
     */
    public function validate_meta($type, $value) {
        $issues = [];

        switch ($type) {
            case 'title':
                if (strlen($value) > 60) {
                    $issues[] = 'Title too long (> 60 characters)';
                }
                if (strlen($value) < 30) {
                    $issues[] = 'Title too short (< 30 characters)';
                }
                break;

            case 'description':
                if (strlen($value) > 160) {
                    $issues[] = 'Description too long (> 160 characters)';
                }
                if (strlen($value) < 120) {
                    $issues[] = 'Description too short (< 120 characters)';
                }
                break;

            case 'keywords':
                if (!is_array($value)) {
                    $issues[] = 'Keywords must be an array';
                } elseif (count($value) > 10) {
                    $issues[] = 'Too many keywords (> 10)';
                } elseif (count($value) < 3) {
                    $issues[] = 'Too few keywords (< 3)';
                }
                break;
        }

        if (!empty($issues)) {
            return new WP_Error('invalid_meta', 'Meta validation failed', ['issues' => $issues]);
        }

        return ['valid' => true];
    }

    /**
     * Optimize title for CTR
     *
     * Adds power words and optimizes structure for better click-through rates.
     *
     * @since 2.0.0
     * @param string $title Original title.
     * @return string Optimized title.
     */
    public function optimize_title_for_ctr($title) {
        $power_words = [
            'Ultimate', 'Essential', 'Complete', 'Proven', 'Powerful',
            'Advanced', 'Expert', 'Professional', 'Comprehensive', 'Definitive',
        ];

        // Check if title already has a power word
        foreach ($power_words as $word) {
            if (stripos($title, $word) !== false) {
                return $title; // Already optimized
            }
        }

        // Add power word to beginning if space allows
        $random_power_word = $power_words[array_rand($power_words)];
        $new_title = $random_power_word . ' ' . $title;

        // Check length
        if (strlen($new_title) <= 60) {
            return $new_title;
        }

        // Add to end with colon
        $new_title = $title . ': ' . $random_power_word . ' Guide';
        
        if (strlen($new_title) <= 60) {
            return $new_title;
        }

        // Return original if can't optimize
        return $title;
    }

    /**
     * Generate meta for specific post type
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @param string $post_type Post type.
     * @param array $settings Settings.
     * @return array Meta data.
     */
    public function generate_for_post_type($post, $post_type, $settings = []) {
        $meta = [
            'title' => '',
            'description' => '',
            'keywords' => [],
            'focus_keyword' => '',
        ];

        switch ($post_type) {
            case 'product':
                $meta['title'] = $this->generate_product_title($post, $settings);
                $meta['description'] = $this->generate_product_description($post, $settings);
                break;

            case 'video':
                $meta['title'] = $this->generate_video_title($post, $settings);
                $meta['description'] = $this->generate_video_description($post, $settings);
                break;

            default:
                $meta['title'] = $this->generate_title($post, $settings);
                $meta['description'] = $this->generate_description($post, $settings);
        }

        $meta['keywords'] = $this->generate_keywords($post, $settings);
        $meta['focus_keyword'] = !empty($meta['keywords']) ? $meta['keywords'][0] : '';

        return $meta;
    }

    /**
     * Generate product title
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @param array $settings Settings.
     * @return string Product title.
     */
    private function generate_product_title($post, $settings = []) {
        $title = get_the_title($post);
        $price = get_post_meta($post->ID, '_abc_amazon_price', true);

        if ($price) {
            return $title . ' - ' . $price . ' | ' . get_bloginfo('name');
        }

        return $title . ' - Review & Price Guide | ' . get_bloginfo('name');
    }

    /**
     * Generate product description
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @param array $settings Settings.
     * @return string Product description.
     */
    private function generate_product_description($post, $settings = []) {
        $title = get_the_title($post);
        $rating = get_post_meta($post->ID, '_abc_amazon_rating', true);

        $description = "Discover {$title}";
        
        if ($rating) {
            $description .= " with {$rating}/5 stars rating";
        }

        $description .= ". Read our comprehensive review, compare prices, and find the best deals.";

        return $description;
    }

    /**
     * Generate video title
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @param array $settings Settings.
     * @return string Video title.
     */
    private function generate_video_title($post, $settings = []) {
        return get_the_title($post) . ' [Video] | ' . get_bloginfo('name');
    }

    /**
     * Generate video description
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     * @param array $settings Settings.
     * @return string Video description.
     */
    private function generate_video_description($post, $settings = []) {
        $title = get_the_title($post);
        return "Watch {$title}. Video content, analysis, and insights.";
    }
}
