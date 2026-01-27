declare(strict_types=1);
<?php
/**
 * Content Fetcher
 *
 * Fetches full content from URLs with retry logic and error handling.
 * Handles various content types and sources.
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
 * Content Fetcher class
 *
 * Responsibilities:
 * - Fetch content from URLs
 * - Retry on failures
 * - Handle redirects
 * - Detect content type
 * - Cache responses
 *
 * @since 2.0.0
 */
class Content_Fetcher {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Maximum redirects to follow
     *
     * @var int
     */
    private $max_redirects = 5;

    /**
     * Request timeout in seconds
     *
     * @var int
     */
    private $timeout = 30;

    /**
     * Maximum retries on failure
     *
     * @var int
     */
    private $max_retries = 3;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->logger = Logger::instance();
    }

    /**
     * Fetch content from URL
     *
     * @since 2.0.0
     * @param string $url URL to fetch.
     * @param array $options Fetch options.
     * @return array|WP_Error Content data or error.
     */
    public function fetch($url, $options = []) {
        $defaults = [
            'method' => 'GET',
            'headers' => [],
            'timeout' => $this->timeout,
            'retries' => $this->max_retries,
            'follow_redirects' => true,
            'cache' => true,
            'cache_ttl' => HOUR_IN_SECONDS,
        ];

        $options = wp_parse_args($options, $defaults);

        $this->logger->debug("Fetching URL: {$url}");

        // Check cache
        if ($options['cache']) {
            $cached = $this->get_cached($url);
            if ($cached !== false) {
                $this->logger->debug("Cache hit: {$url}");
                return $cached;
            }
        }

        // Fetch with retries
        $response = $this->fetch_with_retry($url, $options);

        if (is_wp_error($response)) {
            return $response;
        }

        // Parse response
        $content_data = $this->parse_response($response, $url);

        // Cache result
        if ($options['cache'] && !is_wp_error($content_data)) {
            $this->cache_response($url, $content_data, $options['cache_ttl']);
        }

        return $content_data;
    }

    /**
     * Fetch with retry logic
     *
     * @since 2.0.0
     * @param string $url URL to fetch.
     * @param array $options Fetch options.
     * @return array|WP_Error Response or error.
     */
    private function fetch_with_retry($url, $options) {
        $retries = $options['retries'];
        $attempt = 0;
        $last_error = null;

        while ($attempt < $retries) {
            $attempt++;

            $response = $this->make_request($url, $options);

            if (!is_wp_error($response)) {
                return $response;
            }

            $last_error = $response;

            // Log retry
            $this->logger->debug("Fetch attempt {$attempt}/{$retries} failed: {$response->get_error_message()}");

            // Wait before retry (exponential backoff)
            if ($attempt < $retries) {
                sleep(pow(2, $attempt - 1)); // 1s, 2s, 4s...
            }
        }

        return $last_error;
    }

    /**
     * Make HTTP request
     *
     * @since 2.0.0
     * @param string $url URL to fetch.
     * @param array $options Request options.
     * @return array|WP_Error Response or error.
     */
    private function make_request($url, $options) {
        $args = [
            'method' => $options['method'],
            'timeout' => $options['timeout'],
            'redirection' => $options['follow_redirects'] ? $this->max_redirects : 0,
            'user-agent' => 'AutoBlogCraft/2.0 (+' . home_url() . ')',
            'sslverify' => true,
            'headers' => array_merge([
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ], $options['headers']),
        ];

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);

        // Check response code
        if ($code < 200 || $code >= 400) {
            return new WP_Error(
                'http_error',
                sprintf('HTTP %d: %s', $code, wp_remote_retrieve_response_message($response))
            );
        }

        return $response;
    }

    /**
     * Parse response
     *
     * @since 2.0.0
     * @param array $response HTTP response.
     * @param string $url Original URL.
     * @return array|WP_Error Parsed content data or error.
     */
    private function parse_response($response, $url) {
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);

        if (empty($body)) {
            return new WP_Error('empty_response', 'Response body is empty');
        }

        // Detect content type
        $content_type = isset($headers['content-type']) ? $headers['content-type'] : '';
        
        if (strpos($content_type, 'application/json') !== false) {
            return $this->parse_json($body, $url);
        } elseif (strpos($content_type, 'text/html') !== false || empty($content_type)) {
            return $this->parse_html($body, $url);
        } else {
            return new WP_Error(
                'unsupported_content_type',
                "Unsupported content type: {$content_type}"
            );
        }
    }

    /**
     * Parse HTML response
     *
     * @since 2.0.0
     * @param string $html HTML content.
     * @param string $url Source URL.
     * @return array Content data.
     */
    private function parse_html($html, $url) {
        // Extract title
        $title = $this->extract_title($html);

        // Extract meta description
        $description = $this->extract_meta_description($html);

        // Extract author
        $author = $this->extract_author($html);

        // Extract publish date
        $date = $this->extract_publish_date($html);

        // Extract featured image
        $image = $this->extract_featured_image($html, $url);

        return [
            'url' => $url,
            'title' => $title,
            'content' => $html,
            'html' => $html,
            'description' => $description,
            'author' => $author,
            'date' => $date,
            'image' => $image,
            'type' => 'html',
        ];
    }

    /**
     * Parse JSON response
     *
     * @since 2.0.0
     * @param string $json JSON content.
     * @param string $url Source URL.
     * @return array|WP_Error Content data or error.
     */
    private function parse_json($json, $url) {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Failed to parse JSON: ' . json_last_error_msg());
        }

        return [
            'url' => $url,
            'data' => $data,
            'type' => 'json',
        ];
    }

    /**
     * Extract title from HTML
     *
     * @since 2.0.0
     * @param string $html HTML content.
     * @return string Title.
     */
    private function extract_title($html) {
        // Try <title> tag
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Try og:title
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\'](.*?)["\']/is', $html, $matches)) {
            return html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Try h1
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            return html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }

    /**
     * Extract meta description
     *
     * @since 2.0.0
     * @param string $html HTML content.
     * @return string Description.
     */
    private function extract_meta_description($html) {
        // Try meta description
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/is', $html, $matches)) {
            return html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Try og:description
        if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\'](.*?)["\']/is', $html, $matches)) {
            return html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }

    /**
     * Extract author
     *
     * @since 2.0.0
     * @param string $html HTML content.
     * @return string Author name.
     */
    private function extract_author($html) {
        // Try meta author
        if (preg_match('/<meta[^>]+name=["\']author["\'][^>]+content=["\'](.*?)["\']/is', $html, $matches)) {
            return trim($matches[1]);
        }

        // Try article:author
        if (preg_match('/<meta[^>]+property=["\']article:author["\'][^>]+content=["\'](.*?)["\']/is', $html, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * Extract publish date
     *
     * @since 2.0.0
     * @param string $html HTML content.
     * @return string Date string.
     */
    private function extract_publish_date($html) {
        // Try article:published_time
        if (preg_match('/<meta[^>]+property=["\']article:published_time["\'][^>]+content=["\'](.*?)["\']/is', $html, $matches)) {
            return trim($matches[1]);
        }

        // Try datePublished
        if (preg_match('/"datePublished":\s*"([^"]+)"/i', $html, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * Extract featured image
     *
     * @since 2.0.0
     * @param string $html HTML content.
     * @param string $base_url Base URL for relative URLs.
     * @return string Image URL.
     */
    private function extract_featured_image($html, $base_url) {
        // Try og:image
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\'](.*?)["\']/is', $html, $matches)) {
            return $this->make_absolute_url($matches[1], $base_url);
        }

        // Try twitter:image
        if (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\'](.*?)["\']/is', $html, $matches)) {
            return $this->make_absolute_url($matches[1], $base_url);
        }

        return '';
    }

    /**
     * Make URL absolute
     *
     * @since 2.0.0
     * @param string $url URL (may be relative).
     * @param string $base Base URL.
     * @return string Absolute URL.
     */
    private function make_absolute_url($url, $base) {
        // Already absolute
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        $base_parts = parse_url($base);
        if (!$base_parts) {
            return $url;
        }

        $scheme = isset($base_parts['scheme']) ? $base_parts['scheme'] : 'http';
        $host = isset($base_parts['host']) ? $base_parts['host'] : '';

        // Protocol-relative
        if (substr($url, 0, 2) === '//') {
            return $scheme . ':' . $url;
        }

        // Absolute path
        if (substr($url, 0, 1) === '/') {
            return $scheme . '://' . $host . $url;
        }

        // Relative path
        $path = isset($base_parts['path']) ? dirname($base_parts['path']) : '/';
        return $scheme . '://' . $host . $path . '/' . $url;
    }

    /**
     * Get cached response
     *
     * @since 2.0.0
     * @param string $url URL.
     * @return array|false Cached data or false.
     */
    private function get_cached($url) {
        $cache_key = 'abc_fetch_' . md5($url);
        return get_transient($cache_key);
    }

    /**
     * Cache response
     *
     * @since 2.0.0
     * @param string $url URL.
     * @param array $data Content data.
     * @param int $ttl Time to live in seconds.
     */
    private function cache_response($url, $data, $ttl) {
        $cache_key = 'abc_fetch_' . md5($url);
        set_transient($cache_key, $data, $ttl);
    }

    /**
     * Clear cache for URL
     *
     * @since 2.0.0
     * @param string $url URL.
     */
    public function clear_cache($url) {
        $cache_key = 'abc_fetch_' . md5($url);
        delete_transient($cache_key);
    }

    /**
     * Set request timeout
     *
     * @since 2.0.0
     * @param int $timeout Timeout in seconds.
     */
    public function set_timeout($timeout) {
        $this->timeout = absint($timeout);
    }

    /**
     * Set maximum retries
     *
     * @since 2.0.0
     * @param int $retries Maximum retries.
     */
    public function set_max_retries($retries) {
        $this->max_retries = absint($retries);
    }
}
