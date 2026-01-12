<?php
/**
 * Source Validator
 *
 * Validates news sources against whitelist/blacklist.
 * Manages allow/block modes for source filtering.
 *
 * @package AutoBlogCraft\Discovery\News
 * @since 2.0.0
 */

namespace AutoBlogCraft\Discovery\News;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Source Validator class
 *
 * Responsibilities:
 * - Validate sources against allow/block lists
 * - Extract domains from URLs
 * - Support wildcard patterns
 * - Track source reputation
 *
 * @since 2.0.0
 */
class Source_Validator {

    /**
     * Validation mode
     *
     * @var string 'allow' or 'block'
     */
    private $mode;

    /**
     * Source list (domains)
     *
     * @var array
     */
    private $source_list;

    /**
     * Constructor
     *
     * @since 2.0.0
     * @param string $mode        Validation mode ('allow' or 'block').
     * @param array  $source_list List of domains.
     */
    public function __construct($mode = 'allow', $source_list = []) {
        $this->mode = in_array($mode, ['allow', 'block']) ? $mode : 'allow';
        $this->source_list = is_array($source_list) ? array_map('strtolower', $source_list) : [];
    }

    /**
     * Validate source URL
     *
     * @since 2.0.0
     * @param string $url Article URL.
     * @return bool True if source is valid.
     */
    public function validate($url) {
        // If no source list, accept all in allow mode, reject all in block mode
        if (empty($this->source_list)) {
            return $this->mode === 'allow';
        }

        $domain = $this->extract_domain($url);

        if (empty($domain)) {
            return false;
        }

        $is_in_list = $this->is_domain_in_list($domain);

        // Allow mode: accept if in list
        // Block mode: reject if in list
        return $this->mode === 'allow' ? $is_in_list : !$is_in_list;
    }

    /**
     * Extract domain from URL
     *
     * @since 2.0.0
     * @param string $url URL.
     * @return string Domain.
     */
    private function extract_domain($url) {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';

        // Remove www prefix
        $host = preg_replace('/^www\./', '', $host);

        return strtolower($host);
    }

    /**
     * Check if domain is in source list
     *
     * @since 2.0.0
     * @param string $domain Domain to check.
     * @return bool
     */
    private function is_domain_in_list($domain) {
        foreach ($this->source_list as $pattern) {
            if ($this->matches_pattern($domain, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if domain matches pattern
     *
     * @since 2.0.0
     * @param string $domain  Domain.
     * @param string $pattern Pattern (supports wildcards).
     * @return bool
     */
    private function matches_pattern($domain, $pattern) {
        // Exact match
        if ($domain === $pattern) {
            return true;
        }

        // Wildcard support
        if (strpos($pattern, '*') !== false) {
            $regex = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/';
            return preg_match($regex, $domain) === 1;
        }

        // Subdomain match (pattern ends with domain)
        if (strpos($domain, '.' . $pattern) !== false) {
            return true;
        }

        return false;
    }

    /**
     * Add source to list
     *
     * @since 2.0.0
     * @param string $domain Domain to add.
     * @return void
     */
    public function add_source($domain) {
        $domain = strtolower(trim($domain));
        if (!empty($domain) && !in_array($domain, $this->source_list)) {
            $this->source_list[] = $domain;
        }
    }

    /**
     * Remove source from list
     *
     * @since 2.0.0
     * @param string $domain Domain to remove.
     * @return void
     */
    public function remove_source($domain) {
        $domain = strtolower(trim($domain));
        $this->source_list = array_diff($this->source_list, [$domain]);
    }

    /**
     * Get current source list
     *
     * @since 2.0.0
     * @return array
     */
    public function get_source_list() {
        return $this->source_list;
    }

    /**
     * Get validation mode
     *
     * @since 2.0.0
     * @return string
     */
    public function get_mode() {
        return $this->mode;
    }

    /**
     * Set validation mode
     *
     * @since 2.0.0
     * @param string $mode Mode ('allow' or 'block').
     * @return void
     */
    public function set_mode($mode) {
        if (in_array($mode, ['allow', 'block'])) {
            $this->mode = $mode;
        }
    }

    /**
     * Filter articles by source validation
     *
     * @since 2.0.0
     * @param array $articles Articles to filter.
     * @return array Filtered articles.
     */
    public function filter_articles($articles) {
        return array_filter($articles, function($article) {
            $url = $article['url'] ?? '';
            return $this->validate($url);
        });
    }

    /**
     * Get recommended sources for news categories
     *
     * @since 2.0.0
     * @param string $category News category.
     * @return array Domain list.
     */
    public static function get_recommended_sources($category = 'general') {
        $sources = [
            'general' => [
                'bbc.com',
                'reuters.com',
                'apnews.com',
                'cnn.com',
                'nytimes.com',
                'theguardian.com',
            ],
            'tech' => [
                'techcrunch.com',
                'theverge.com',
                'wired.com',
                'arstechnica.com',
                'engadget.com',
                'zdnet.com',
            ],
            'business' => [
                'wsj.com',
                'ft.com',
                'bloomberg.com',
                'forbes.com',
                'businessinsider.com',
                'cnbc.com',
            ],
            'sports' => [
                'espn.com',
                'bleacherreport.com',
                'si.com',
                'skysports.com',
            ],
            'health' => [
                'healthline.com',
                'webmd.com',
                'mayoclinic.org',
                'nih.gov',
            ],
            'science' => [
                'nature.com',
                'sciencemag.org',
                'newscientist.com',
                'scientificamerican.com',
            ],
        ];

        return $sources[$category] ?? $sources['general'];
    }

    /**
     * Get common spam/low-quality sources to block
     *
     * @since 2.0.0
     * @return array
     */
    public static function get_spam_sources() {
        return [
            'clickbait.com',
            'viral*.com',
            '*tabloid.com',
            // Add more patterns as needed
        ];
    }

    /**
     * Validate source quality
     *
     * @since 2.0.0
     * @param string $url Source URL.
     * @return array Quality metrics.
     */
    public function validate_quality($url) {
        $domain = $this->extract_domain($url);

        $quality = [
            'domain' => $domain,
            'is_trusted' => $this->is_trusted_source($domain),
            'reputation_score' => $this->get_reputation_score($domain),
            'warnings' => [],
        ];

        // Check for suspicious patterns
        if (preg_match('/\d{4,}/', $domain)) {
            $quality['warnings'][] = 'Domain contains numbers';
        }

        if (strlen($domain) > 30) {
            $quality['warnings'][] = 'Unusually long domain';
        }

        if (substr_count($domain, '-') > 3) {
            $quality['warnings'][] = 'Multiple hyphens in domain';
        }

        return $quality;
    }

    /**
     * Check if source is trusted
     *
     * @since 2.0.0
     * @param string $domain Domain.
     * @return bool
     */
    private function is_trusted_source($domain) {
        $trusted_sources = array_merge(
            self::get_recommended_sources('general'),
            self::get_recommended_sources('tech'),
            self::get_recommended_sources('business')
        );

        return in_array($domain, $trusted_sources);
    }

    /**
     * Get reputation score for domain
     *
     * @since 2.0.0
     * @param string $domain Domain.
     * @return int Score (0-100).
     */
    private function get_reputation_score($domain) {
        // Simplified scoring based on domain characteristics
        $score = 50; // Neutral

        // Boost for known sources
        if ($this->is_trusted_source($domain)) {
            $score += 40;
        }

        // Boost for .gov and .edu
        if (preg_match('/\.(gov|edu)$/', $domain)) {
            $score += 30;
        }

        // Penalize for suspicious patterns
        if (preg_match('/\d{3,}/', $domain)) {
            $score -= 20;
        }

        if (substr_count($domain, '-') > 2) {
            $score -= 15;
        }

        return max(0, min(100, $score));
    }
}
