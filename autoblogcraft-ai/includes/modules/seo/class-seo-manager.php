<?php
/**
 * SEO Manager
 *
 * Manages SEO integration with popular WordPress SEO plugins.
 *
 * @package AutoBlogCraft\Modules\SEO
 * @since 2.0.0
 */

namespace AutoBlogCraft\Modules\SEO;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\Core\Module_Base;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SEO Manager class
 *
 * Responsibilities:
 * - Detect active SEO plugin
 * - Manage SEO meta data for campaign posts
 * - Generate schema markup
 * - Coordinate with SEO plugin integrations
 *
 * @since 2.0.0
 */
class SEO_Manager extends Module_Base {

    /**
     * Active SEO plugin
     *
     * @var string|null
     */
    private $active_plugin = null;

    /**
     * SEO plugin integration instance
     *
     * @var object|null
     */
    private $integration = null;

    /**
     * Get module name
     *
     * @since 2.0.0
     * @return string Module name.
     */
    protected function get_module_name() {
        return 'seo_manager';
    }

    /**
     * Initialize module
     *
     * @since 2.0.0
     * @return void
     */
    protected function init() {
        $this->detect_seo_plugin();
        $this->init_integration();
    }

    /**
     * Detect active SEO plugin
     *
     * @since 2.0.0
     * @return string|null Plugin name or null if none detected.
     */
    private function detect_seo_plugin() {
        // Check for Yoast SEO
        if (defined('WPSEO_VERSION')) {
            $this->active_plugin = 'yoast';
            return 'yoast';
        }

        // Check for Rank Math
        if (defined('RANK_MATH_VERSION')) {
            $this->active_plugin = 'rank-math';
            return 'rank-math';
        }

        // Check for All in One SEO
        if (defined('AIOSEO_VERSION')) {
            $this->active_plugin = 'aioseo';
            return 'aioseo';
        }

        $this->log_info('No SEO plugin detected');
        return null;
    }

    /**
     * Initialize SEO plugin integration
     *
     * @since 2.0.0
     */
    private function init_integration() {
        if (!$this->active_plugin) {
            return;
        }

        switch ($this->active_plugin) {
            case 'yoast':
                $this->integration = new Yoast_Integration();
                break;

            case 'rank-math':
                $this->integration = new Rank_Math_Integration();
                break;

            case 'aioseo':
                $this->integration = new AIOSEO_Integration();
                break;
        }

        if ($this->integration) {
            $this->log_info('SEO integration initialized', ['plugin' => $this->active_plugin]);
        }
    }

    /**
     * Get active SEO plugin
     *
     * @since 2.0.0
     * @return string|null Plugin name or null.
     */
    public function get_active_plugin() {
        return $this->active_plugin;
    }

    /**
     * Set SEO meta data for a post
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param array $seo_data SEO data array.
     * @return bool True on success, false on failure.
     */
    public function set_post_seo($post_id, $seo_data) {
        if (!$this->integration) {
            // Set basic WordPress meta if no SEO plugin
            return $this->set_basic_seo($post_id, $seo_data);
        }

        try {
            return $this->integration->set_post_seo($post_id, $seo_data);
        } catch (\Exception $e) {
            $this->log_error('Failed to set SEO data', [
                'post_id' => $post_id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Set basic SEO meta (no plugin)
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param array $seo_data SEO data.
     * @return bool True on success.
     */
    private function set_basic_seo($post_id, $seo_data) {
        if (isset($seo_data['meta_description'])) {
            update_post_meta($post_id, '_abc_meta_description', sanitize_text_field($seo_data['meta_description']));
        }

        if (isset($seo_data['focus_keyword'])) {
            update_post_meta($post_id, '_abc_focus_keyword', sanitize_text_field($seo_data['focus_keyword']));
        }

        if (isset($seo_data['canonical_url'])) {
            update_post_meta($post_id, '_abc_canonical_url', esc_url_raw($seo_data['canonical_url']));
        }

        // Add basic meta description to head
        add_action('wp_head', function() use ($post_id, $seo_data) {
            if (is_single($post_id) && isset($seo_data['meta_description'])) {
                echo '<meta name="description" content="' . esc_attr($seo_data['meta_description']) . '">' . "\n";
            }
        });

        return true;
    }

    /**
     * Generate SEO meta data using AI
     *
     * @since 2.0.0
     * @param string $title Post title.
     * @param string $content Post content.
     * @param string $focus_keyword Optional focus keyword.
     * @return array SEO data array.
     */
    public function generate_seo_data($title, $content, $focus_keyword = '') {
        // Extract keywords if not provided
        if (empty($focus_keyword)) {
            $focus_keyword = $this->extract_keyword($title, $content);
        }

        // Generate meta description
        $meta_description = $this->generate_meta_description($content, $focus_keyword);

        // Generate OpenGraph data
        $og_data = [
            'og_title' => $title,
            'og_description' => $meta_description,
            'og_type' => 'article',
        ];

        // Generate Twitter Card data
        $twitter_data = [
            'twitter_card' => 'summary_large_image',
            'twitter_title' => $title,
            'twitter_description' => $meta_description,
        ];

        return [
            'meta_description' => $meta_description,
            'focus_keyword' => $focus_keyword,
            'og_data' => $og_data,
            'twitter_data' => $twitter_data,
        ];
    }

    /**
     * Extract focus keyword from content
     *
     * @since 2.0.0
     * @param string $title Post title.
     * @param string $content Post content.
     * @return string Focus keyword.
     */
    private function extract_keyword($title, $content) {
        // Simple keyword extraction: take the most common meaningful word from title
        $words = str_word_count(strtolower($title), 1);
        
        // Filter out common stop words
        $stop_words = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
        $words = array_diff($words, $stop_words);

        if (empty($words)) {
            return '';
        }

        // Get word frequencies
        $word_counts = array_count_values($words);
        arsort($word_counts);

        return key($word_counts);
    }

    /**
     * Generate meta description from content
     *
     * @since 2.0.0
     * @param string $content Post content.
     * @param string $focus_keyword Focus keyword.
     * @return string Meta description (max 160 characters).
     */
    private function generate_meta_description($content, $focus_keyword = '') {
        // Strip HTML tags
        $text = wp_strip_all_tags($content);

        // Find the first sentence containing the keyword if provided
        if (!empty($focus_keyword)) {
            $sentences = preg_split('/[.!?]+/', $text);
            foreach ($sentences as $sentence) {
                if (stripos($sentence, $focus_keyword) !== false) {
                    $description = trim($sentence);
                    if (strlen($description) <= 160) {
                        return $description;
                    }
                    break;
                }
            }
        }

        // Fallback: take first 160 characters
        $description = substr($text, 0, 160);
        
        // Cut at last complete word
        if (strlen($text) > 160) {
            $description = substr($description, 0, strrpos($description, ' ')) . '...';
        }

        return trim($description);
    }

    /**
     * Generate JSON-LD schema markup
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param string $schema_type Schema type (Article, BlogPosting, NewsArticle).
     * @return array Schema markup array.
     */
    public function generate_schema($post_id, $schema_type = 'Article') {
        $post = get_post($post_id);
        if (!$post) {
            return [];
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $schema_type,
            'headline' => $post->post_title,
            'datePublished' => get_the_date('c', $post_id),
            'dateModified' => get_the_modified_date('c', $post_id),
            'author' => [
                '@type' => 'Person',
                'name' => get_the_author_meta('display_name', $post->post_author),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => get_site_icon_url(),
                ],
            ],
        ];

        // Add featured image if available
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            $image = wp_get_attachment_image_src($thumbnail_id, 'full');
            if ($image) {
                $schema['image'] = [
                    '@type' => 'ImageObject',
                    'url' => $image[0],
                    'width' => $image[1],
                    'height' => $image[2],
                ];
            }
        }

        // Add article body
        $schema['articleBody'] = wp_strip_all_tags($post->post_content);

        return $schema;
    }

    /**
     * Output schema markup in post head
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param string $schema_type Schema type.
     */
    public function output_schema($post_id, $schema_type = 'Article') {
        $schema = $this->generate_schema($post_id, $schema_type);
        
        if (empty($schema)) {
            return;
        }

        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        echo '</script>' . "\n";
    }

    /**
     * Calculate SEO score for content
     *
     * @since 2.0.0
     * @param string $title Post title.
     * @param string $content Post content.
     * @param string $focus_keyword Focus keyword.
     * @return array Score data with percentage and recommendations.
     */
    public function calculate_seo_score($title, $content, $focus_keyword = '') {
        $score = 0;
        $max_score = 100;
        $recommendations = [];

        // Title length check (50-60 characters ideal)
        $title_length = strlen($title);
        if ($title_length >= 50 && $title_length <= 60) {
            $score += 15;
        } else {
            $recommendations[] = 'Title should be between 50-60 characters';
        }

        // Keyword in title
        if (!empty($focus_keyword) && stripos($title, $focus_keyword) !== false) {
            $score += 15;
        } elseif (!empty($focus_keyword)) {
            $recommendations[] = 'Include focus keyword in title';
        }

        // Content length (min 300 words)
        $word_count = str_word_count(wp_strip_all_tags($content));
        if ($word_count >= 300) {
            $score += 20;
        } else {
            $recommendations[] = 'Content should be at least 300 words (currently ' . $word_count . ')';
        }

        // Keyword density (1-3% ideal)
        if (!empty($focus_keyword)) {
            $keyword_count = substr_count(strtolower($content), strtolower($focus_keyword));
            $density = ($keyword_count / $word_count) * 100;
            
            if ($density >= 1 && $density <= 3) {
                $score += 15;
            } else {
                $recommendations[] = 'Keyword density should be 1-3% (currently ' . number_format($density, 2) . '%)';
            }
        }

        // Headings check
        if (preg_match('/<h[1-6]/', $content)) {
            $score += 10;
        } else {
            $recommendations[] = 'Add headings (H2, H3) to structure content';
        }

        // Images check
        if (preg_match('/<img/', $content)) {
            $score += 10;
        } else {
            $recommendations[] = 'Add images to enhance content';
        }

        // Internal links
        $internal_links = preg_match_all('/<a[^>]+href=["\']' . preg_quote(home_url(), '/') . '/', $content);
        if ($internal_links > 0) {
            $score += 10;
        } else {
            $recommendations[] = 'Add internal links to related content';
        }

        // Meta description
        $score += 5; // Assume we always generate this

        return [
            'score' => $score,
            'percentage' => ($score / $max_score) * 100,
            'grade' => $this->get_seo_grade($score, $max_score),
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Get SEO grade based on score
     *
     * @since 2.0.0
     * @param int $score Current score.
     * @param int $max_score Maximum possible score.
     * @return string Grade (A, B, C, D, F).
     */
    private function get_seo_grade($score, $max_score) {
        $percentage = ($score / $max_score) * 100;

        if ($percentage >= 90) {
            return 'A';
        } elseif ($percentage >= 80) {
            return 'B';
        } elseif ($percentage >= 70) {
            return 'C';
        } elseif ($percentage >= 60) {
            return 'D';
        } else {
            return 'F';
        }
    }
}
