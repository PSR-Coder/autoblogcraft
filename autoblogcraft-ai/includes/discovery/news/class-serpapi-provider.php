<?php
/**
 * SerpAPI Provider
 *
 * Integration with SerpAPI for Google News results.
 * Provides real-time SERP data from Google News.
 *
 * @package AutoBlogCraft\Discovery\News
 * @since 2.0.0
 */

namespace AutoBlogCraft\Discovery\News;

use AutoBlogCraft\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SerpAPI Provider class
 *
 * Responsibilities:
 * - Query SerpAPI endpoints
 * - Parse Google News results
 * - Handle API rate limits
 * - Support location targeting
 *
 * @since 2.0.0
 */
class SerpAPI_Provider {

    /**
     * Campaign ID
     *
     * @var int
     */
    private $campaign_id;

    /**
     * API key
     *
     * @var string
     */
    private $api_key;

    /**
     * API base URL
     *
     * @var string
     */
    private $api_base = 'https://serpapi.com/search';

    /**
     * Constructor
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     */
    public function __construct($campaign_id) {
        $this->campaign_id = $campaign_id;
        $this->load_api_key();
    }

    /**
     * Load SerpAPI key
     *
     * @since 2.0.0
     * @return void
     */
    private function load_api_key() {
        global $wpdb;

        $key = $wpdb->get_var(
            "SELECT api_key FROM {$wpdb->prefix}abc_api_keys 
             WHERE provider = 'serpapi' AND status = 'active' 
             LIMIT 1"
        );

        $this->api_key = $key ?: '';
    }

    /**
     * Search Google News
     *
     * @since 2.0.0
     * @param string $keyword Search keyword.
     * @param array  $params  Search parameters.
     * @return array Articles.
     */
    public function search($keyword, $params = []) {
        if (empty($this->api_key)) {
            Logger::log($this->campaign_id, 'error', 'discovery', 'SerpAPI key not configured');
            return [];
        }

        $defaults = [
            'country' => 'us',
            'language' => 'en',
            'max_results' => 10,
            'time_filter' => null, // qdr:h (hour), qdr:d (day), qdr:w (week)
        ];

        $params = array_merge($defaults, $params);

        $query_params = [
            'api_key' => $this->api_key,
            'engine' => 'google_news',
            'q' => $keyword,
            'gl' => strtolower($params['country']),
            'hl' => strtolower($params['language']),
            'num' => $params['max_results'],
        ];

        if ($params['time_filter']) {
            $query_params['tbs'] = $params['time_filter'];
        }

        $url = add_query_arg($query_params, $this->api_base);

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            Logger::log($this->campaign_id, 'error', 'discovery', 'SerpAPI request failed', [
                'error' => $response->get_error_message(),
            ]);
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || isset($data['error'])) {
            $error = $data['error'] ?? 'Unknown error';
            Logger::log($this->campaign_id, 'error', 'discovery', 'SerpAPI error', [
                'error' => $error,
            ]);
            return [];
        }

        return $this->parse_news_results($data);
    }

    /**
     * Parse SerpAPI news results
     *
     * @since 2.0.0
     * @param array $data API response data.
     * @return array Parsed articles.
     */
    private function parse_news_results($data) {
        $articles = [];

        // Parse top stories
        if (isset($data['top_stories'])) {
            foreach ($data['top_stories'] as $story) {
                $articles[] = $this->parse_story($story);
            }
        }

        // Parse news results
        if (isset($data['news_results'])) {
            foreach ($data['news_results'] as $result) {
                $articles[] = $this->parse_news_result($result);
            }
        }

        return $articles;
    }

    /**
     * Parse top story
     *
     * @since 2.0.0
     * @param array $story Story data.
     * @return array Parsed article.
     */
    private function parse_story($story) {
        return [
            'title' => $story['title'] ?? '',
            'description' => $story['snippet'] ?? '',
            'url' => $story['link'] ?? '',
            'published_at' => $this->parse_date($story['date'] ?? ''),
            'source' => $story['source'] ?? '',
            'source_url' => $this->extract_domain($story['link'] ?? ''),
            'image_url' => $story['thumbnail'] ?? '',
        ];
    }

    /**
     * Parse news result
     *
     * @since 2.0.0
     * @param array $result Result data.
     * @return array Parsed article.
     */
    private function parse_news_result($result) {
        return [
            'title' => $result['title'] ?? '',
            'description' => $result['snippet'] ?? '',
            'url' => $result['link'] ?? '',
            'published_at' => $this->parse_date($result['date'] ?? ''),
            'source' => $result['source']['name'] ?? '',
            'source_url' => $this->extract_domain($result['link'] ?? ''),
            'image_url' => $result['thumbnail'] ?? '',
        ];
    }

    /**
     * Parse date string
     *
     * @since 2.0.0
     * @param string $date_string Date string.
     * @return int Unix timestamp.
     */
    private function parse_date($date_string) {
        if (empty($date_string)) {
            return time();
        }

        // Handle relative dates like "2 hours ago"
        if (preg_match('/(\d+)\s+(hour|day|week)s?\s+ago/i', $date_string, $matches)) {
            $value = intval($matches[1]);
            $unit = strtolower($matches[2]);

            $seconds_map = [
                'hour' => 3600,
                'day' => 86400,
                'week' => 604800,
            ];

            $seconds = $seconds_map[$unit] ?? 3600;
            return time() - ($value * $seconds);
        }

        // Try to parse as standard date
        $timestamp = strtotime($date_string);
        return $timestamp ?: time();
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
        return $parsed['host'] ?? '';
    }

    /**
     * Get trending news
     *
     * @since 2.0.0
     * @param string $country Country code.
     * @return array Articles.
     */
    public function get_trending($country = 'us') {
        if (empty($this->api_key)) {
            return [];
        }

        $url = add_query_arg([
            'api_key' => $this->api_key,
            'engine' => 'google_news',
            'gl' => strtolower($country),
        ], $this->api_base);

        $response = wp_remote_get($url, ['timeout' => 30]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || isset($data['error'])) {
            return [];
        }

        return $this->parse_news_results($data);
    }
}
