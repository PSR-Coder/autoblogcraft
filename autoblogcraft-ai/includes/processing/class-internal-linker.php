<?php
/**
 * Internal Linker
 *
 * Contextual link injection system with keyword matching, anchor text variation,
 * link density control, and relevance scoring.
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
 * Internal Linker class
 *
 * Handles intelligent internal linking with contextual placement,
 * keyword matching, and link density control.
 *
 * @since 2.0.0
 */
class Internal_Linker {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Link placement modes
     *
     * @var array
     */
    private $modes = ['regex', 'ai_contextual', 'manual'];

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->logger = Logger::instance();
    }

    /**
     * Inject internal links
     *
     * Main entry point for link injection.
     *
     * @since 2.0.0
     * @param string $content Content to process.
     * @param array $links Array of links to inject.
     * @param array $settings Link injection settings.
     * @return string|WP_Error Modified content or error.
     */
    public function inject_links($content, $links, $settings = []) {
        if (empty($content)) {
            return new WP_Error('empty_content', 'Content cannot be empty');
        }

        if (empty($links)) {
            return $content; // No links to inject
        }

        $mode = !empty($settings['mode']) ? $settings['mode'] : 'regex';
        $max_links = !empty($settings['max_links']) ? (int) $settings['max_links'] : 5;
        $max_density = !empty($settings['max_density']) ? (float) $settings['max_density'] : 0.02;

        $this->logger->info('Injecting internal links', [
            'mode' => $mode,
            'link_count' => count($links),
            'max_links' => $max_links,
        ]);

        // Normalize links array
        $links = $this->normalize_links($links);

        // Check current link density
        $current_density = $this->calculate_link_density($content);
        if ($current_density >= $max_density) {
            $this->logger->warning("Link density already at limit ({$current_density} >= {$max_density})");
            return $content;
        }

        // Inject based on mode
        switch ($mode) {
            case 'regex':
                $modified = $this->inject_regex($content, $links, $max_links, $settings);
                break;
            
            case 'ai_contextual':
                $modified = $this->inject_ai_contextual($content, $links, $max_links, $settings);
                break;
            
            case 'manual':
                $modified = $this->inject_manual($content, $links, $settings);
                break;
            
            default:
                return new WP_Error('invalid_mode', "Invalid link injection mode: {$mode}");
        }

        // Verify link density doesn't exceed limit
        $new_density = $this->calculate_link_density($modified);
        if ($new_density > $max_density) {
            $this->logger->warning("Link density exceeded ({$new_density} > {$max_density}), reverting");
            return $content;
        }

        $injected_count = $this->count_links($modified) - $this->count_links($content);
        $this->logger->info("Successfully injected {$injected_count} internal links");

        return $modified;
    }

    /**
     * Inject links using regex matching
     *
     * @since 2.0.0
     * @param string $content Content to process.
     * @param array $links Normalized links.
     * @param int $max_links Maximum links to inject.
     * @param array $settings Settings.
     * @return string Modified content.
     */
    private function inject_regex($content, $links, $max_links, $settings = []) {
        $injected = 0;
        $used_keywords = []; // Track used keywords to avoid duplicates

        // Sort links by keyword length (longest first for better matching)
        usort($links, function ($a, $b) {
            return strlen($b['keyword']) - strlen($a['keyword']);
        });

        foreach ($links as $link) {
            if ($injected >= $max_links) {
                break;
            }

            $keyword = $link['keyword'];
            $url = $link['url'];
            $anchor_text = !empty($link['anchor_text']) ? $link['anchor_text'] : $keyword;

            // Skip if keyword already used
            if (in_array(strtolower($keyword), $used_keywords, true)) {
                continue;
            }

            // Check if keyword exists in content (case-insensitive, whole word)
            $pattern = '/\b(' . preg_quote($keyword, '/') . ')\b/i';
            
            if (preg_match($pattern, $content)) {
                // Replace first occurrence only
                $content = preg_replace(
                    $pattern,
                    '<a href="' . esc_url($url) . '">' . esc_html($anchor_text) . '</a>',
                    $content,
                    1 // Only first occurrence
                );

                $injected++;
                $used_keywords[] = strtolower($keyword);
                
                $this->logger->debug("Injected link for keyword: {$keyword}");
            }
        }

        return $content;
    }

    /**
     * Inject links using AI contextual placement
     *
     * @since 2.0.0
     * @param string $content Content to process.
     * @param array $links Normalized links.
     * @param int $max_links Maximum links to inject.
     * @param array $settings Settings.
     * @return string Modified content.
     */
    private function inject_ai_contextual($content, $links, $max_links, $settings = []) {
        // For AI contextual placement, we need to:
        // 1. Analyze content semantically
        // 2. Find best placement for each link
        // 3. Use natural anchor text variations

        $injected = 0;
        $sentences = $this->split_into_sentences($content);

        foreach ($links as $link) {
            if ($injected >= $max_links) {
                break;
            }

            $keyword = $link['keyword'];
            $url = $link['url'];

            // Find most relevant sentence
            $best_sentence_idx = $this->find_best_sentence($keyword, $sentences);
            
            if ($best_sentence_idx === null) {
                continue; // No good match
            }

            // Generate contextual anchor text
            $anchor_text = $this->generate_contextual_anchor($keyword, $sentences[$best_sentence_idx]);

            // Inject link into sentence
            $original_sentence = $sentences[$best_sentence_idx];
            $modified_sentence = $this->inject_into_sentence($original_sentence, $anchor_text, $url);

            if ($modified_sentence !== $original_sentence) {
                $sentences[$best_sentence_idx] = $modified_sentence;
                $injected++;
                
                $this->logger->debug("Contextually injected link for: {$keyword}");
            }
        }

        // Reconstruct content
        return implode(' ', $sentences);
    }

    /**
     * Inject links at manual placements
     *
     * @since 2.0.0
     * @param string $content Content to process.
     * @param array $links Normalized links with placements.
     * @param array $settings Settings.
     * @return string Modified content.
     */
    private function inject_manual($content, $links, $settings = []) {
        foreach ($links as $link) {
            if (empty($link['placement'])) {
                continue;
            }

            $placement = $link['placement'];
            $url = $link['url'];
            $anchor_text = !empty($link['anchor_text']) ? $link['anchor_text'] : $link['keyword'];

            // Replace placeholder
            $link_html = '<a href="' . esc_url($url) . '">' . esc_html($anchor_text) . '</a>';
            $content = str_replace($placement, $link_html, $content);
        }

        return $content;
    }

    /**
     * Normalize links array
     *
     * @since 2.0.0
     * @param array $links Raw links array.
     * @return array Normalized links.
     */
    private function normalize_links($links) {
        $normalized = [];

        foreach ($links as $link) {
            // Handle different input formats
            if (is_string($link)) {
                // Format: "keyword|url"
                $parts = explode('|', $link);
                if (count($parts) === 2) {
                    $normalized[] = [
                        'keyword' => trim($parts[0]),
                        'url' => trim($parts[1]),
                    ];
                }
            } elseif (is_array($link)) {
                // Already normalized
                if (!empty($link['keyword']) && !empty($link['url'])) {
                    $normalized[] = $link;
                }
            }
        }

        return $normalized;
    }

    /**
     * Calculate link density
     *
     * @since 2.0.0
     * @param string $content Content to analyze.
     * @return float Link density (0.0-1.0).
     */
    private function calculate_link_density($content) {
        $total_words = str_word_count(wp_strip_all_tags($content));
        
        if ($total_words === 0) {
            return 0;
        }

        // Extract all links
        preg_match_all('/<a[^>]*>([^<]+)<\/a>/i', $content, $matches);
        $link_words = 0;

        foreach ($matches[1] as $link_text) {
            $link_words += str_word_count($link_text);
        }

        return $total_words > 0 ? $link_words / $total_words : 0;
    }

    /**
     * Count links in content
     *
     * @since 2.0.0
     * @param string $content Content to analyze.
     * @return int Number of links.
     */
    private function count_links($content) {
        return preg_match_all('/<a[^>]+href=["\'][^"\']+["\'][^>]*>/i', $content);
    }

    /**
     * Split content into sentences
     *
     * @since 2.0.0
     * @param string $content Content to split.
     * @return array Array of sentences.
     */
    private function split_into_sentences($content) {
        // Remove HTML tags
        $text = wp_strip_all_tags($content);

        // Split on sentence boundaries
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $sentences;
    }

    /**
     * Find best sentence for keyword
     *
     * @since 2.0.0
     * @param string $keyword Keyword to find.
     * @param array $sentences Array of sentences.
     * @return int|null Sentence index or null.
     */
    private function find_best_sentence($keyword, $sentences) {
        $best_score = 0;
        $best_idx = null;

        foreach ($sentences as $idx => $sentence) {
            $score = $this->calculate_relevance_score($keyword, $sentence);
            
            if ($score > $best_score) {
                $best_score = $score;
                $best_idx = $idx;
            }
        }

        // Require minimum relevance threshold
        return $best_score >= 0.3 ? $best_idx : null;
    }

    /**
     * Calculate relevance score
     *
     * @since 2.0.0
     * @param string $keyword Keyword.
     * @param string $sentence Sentence.
     * @return float Relevance score (0.0-1.0).
     */
    private function calculate_relevance_score($keyword, $sentence) {
        $keyword_lower = strtolower($keyword);
        $sentence_lower = strtolower($sentence);

        // Exact match gets highest score
        if (strpos($sentence_lower, $keyword_lower) !== false) {
            return 1.0;
        }

        // Calculate word overlap
        $keyword_words = preg_split('/\s+/', $keyword_lower);
        $sentence_words = preg_split('/\s+/', $sentence_lower);

        $overlap = count(array_intersect($keyword_words, $sentence_words));
        $total = count($keyword_words);

        return $total > 0 ? $overlap / $total : 0;
    }

    /**
     * Generate contextual anchor text
     *
     * @since 2.0.0
     * @param string $keyword Base keyword.
     * @param string $sentence Context sentence.
     * @return string Anchor text.
     */
    private function generate_contextual_anchor($keyword, $sentence) {
        // Check if exact keyword exists
        if (stripos($sentence, $keyword) !== false) {
            return $keyword;
        }

        // Use partial match if available
        $keyword_words = preg_split('/\s+/', $keyword);
        foreach ($keyword_words as $word) {
            if (strlen($word) > 3 && stripos($sentence, $word) !== false) {
                return $word;
            }
        }

        // Default to full keyword
        return $keyword;
    }

    /**
     * Inject link into sentence
     *
     * @since 2.0.0
     * @param string $sentence Sentence to modify.
     * @param string $anchor_text Anchor text.
     * @param string $url Link URL.
     * @return string Modified sentence.
     */
    private function inject_into_sentence($sentence, $anchor_text, $url) {
        // Find anchor text in sentence (case-insensitive)
        $pattern = '/\b(' . preg_quote($anchor_text, '/') . ')\b/i';
        
        if (preg_match($pattern, $sentence)) {
            return preg_replace(
                $pattern,
                '<a href="' . esc_url($url) . '">' . esc_html('$1') . '</a>',
                $sentence,
                1 // Only first occurrence
            );
        }

        return $sentence;
    }

    /**
     * Get related posts for linking
     *
     * Finds related posts based on keywords/categories for automatic linking.
     *
     * @since 2.0.0
     * @param array $context Context (keywords, categories, etc.).
     * @param int $limit Maximum number of posts to return.
     * @return array Array of related posts with URLs and titles.
     */
    public function get_related_posts($context = [], $limit = 10) {
        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        // Filter by keywords if provided
        if (!empty($context['keywords'])) {
            $args['s'] = implode(' ', array_slice($context['keywords'], 0, 3));
        }

        // Filter by categories if provided
        if (!empty($context['categories'])) {
            $args['category__in'] = $context['categories'];
        }

        // Exclude current post
        if (!empty($context['exclude_post_id'])) {
            $args['post__not_in'] = [$context['exclude_post_id']];
        }

        $posts = get_posts($args);
        $related = [];

        foreach ($posts as $post) {
            $related[] = [
                'url' => get_permalink($post),
                'title' => get_the_title($post),
                'keyword' => get_the_title($post), // Use title as default keyword
                'excerpt' => get_the_excerpt($post),
            ];
        }

        return $related;
    }

    /**
     * Build link suggestions from existing content
     *
     * Analyzes existing site content to suggest relevant internal links.
     *
     * @since 2.0.0
     * @param string $content Content to analyze.
     * @param int $limit Maximum suggestions.
     * @return array Array of link suggestions.
     */
    public function suggest_links($content, $limit = 5) {
        // Extract keywords from content
        $keywords = $this->extract_keywords($content, 20);

        if (empty($keywords)) {
            return [];
        }

        $suggestions = [];

        // Search for posts matching keywords
        foreach ($keywords as $keyword) {
            if (count($suggestions) >= $limit) {
                break;
            }

            $posts = get_posts([
                'post_type' => 'post',
                'post_status' => 'publish',
                's' => $keyword,
                'posts_per_page' => 1,
            ]);

            if (!empty($posts)) {
                $post = $posts[0];
                $suggestions[] = [
                    'keyword' => $keyword,
                    'url' => get_permalink($post),
                    'anchor_text' => get_the_title($post),
                    'relevance' => 0.8, // Placeholder score
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Extract keywords from content
     *
     * @since 2.0.0
     * @param string $content Content to analyze.
     * @param int $limit Maximum keywords.
     * @return array Array of keywords.
     */
    private function extract_keywords($content, $limit = 20) {
        // Strip HTML and get text
        $text = wp_strip_all_tags($content);
        $text = strtolower($text);

        // Remove punctuation
        $text = preg_replace('/[^a-z0-9\s]/i', ' ', $text);

        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Remove stop words
        $stop_words = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'is', 'was', 'are', 'be', 'this', 'that'];
        $words = array_diff($words, $stop_words);

        // Filter short words
        $words = array_filter($words, function ($word) {
            return strlen($word) > 3;
        });

        // Count frequency
        $frequency = array_count_values($words);
        arsort($frequency);

        // Return top keywords
        return array_slice(array_keys($frequency), 0, $limit);
    }

    /**
     * Get supported modes
     *
     * @since 2.0.0
     * @return array Supported link injection modes.
     */
    public function get_modes() {
        return $this->modes;
    }
}
