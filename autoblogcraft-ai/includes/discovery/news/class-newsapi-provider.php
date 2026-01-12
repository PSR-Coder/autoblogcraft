<?php
/**
 * NewsAPI Provider
 *
 * Integration with NewsAPI.org for real-time news discovery.
 * Provides access to 80,000+ news sources worldwide.
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
 * NewsAPI Provider class
 *
 * Responsibilities:
 * - Query NewsAPI.org endpoints
 * - Parse NewsAPI responses
 * - Handle API rate limits
 * - Provide source filtering
 * - Support multiple endpoints (everything, top-headlines)
 *
 * @since 2.0.0
 */
class NewsAPI_Provider {

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
    private $api_base = 'https://newsapi.org/v2';

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
     * Load NewsAPI key
     *
     * @since 2.0.0
     * @return void
     */
    private function load_api_key() {
        global $wpdb;

        $key = $wpdb->get_var(
            "SELECT api_key FROM {$wpdb->prefix}abc_api_keys 
             WHERE provider = 'newsapi' AND status = 'active' 
             LIMIT 1"
        );

        $this->api_key = $key ?: '';
    }

    /**
     * Search for news articles
     *
     * @since 2.0.0
     * @param string $keyword Search keyword.
     * @param array  $params  Search parameters.
     * @return array Articles.
     */
    public function search($keyword, $params = []) {
        if (empty($this->api_key)) {
            Logger::log($this->campaign_id, 'error', 'discovery', 'NewsAPI key not configured');
            return [];
        }

        $defaults = [
            'country' => 'us',
            'language' => 'en',
            'max_results' => 10,
            'sort_by' => 'publishedAt', // publishedAt, relevancy, popularity
            'from_date' => null,
            'to_date' => null,
        ];

        $params = array_merge($defaults, $params);

        // Use "everything" endpoint for keyword search
        $endpoint = $this->api_base . '/everything';

        $query_params = [
            'q' => $keyword,
            'apiKey' => $this->api_key,
            'sortBy' => $params['sort_by'],
            'pageSize' => $params['max_results'],
            'language' => $params['language'],
        ];

        if ($params['from_date']) {
            $query_params['from'] = date('Y-m-d', $params['from_date']);
        }

        if ($params['to_date']) {
            $query_params['to'] = date('Y-m-d', $params['to_date']);
        }

        $url = add_query_arg($query_params, $endpoint);

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            Logger::log($this->campaign_id, 'error', 'discovery', 'NewsAPI request failed', [
                'error' => $response->get_error_message(),
            ]);
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || $data['status'] !== 'ok') {
            $error = $data['message'] ?? 'Unknown error';
            Logger::log($this->campaign_id, 'error', 'discovery', 'NewsAPI error', [
                'error' => $error,
            ]);
            return [];
        }

        return $this->parse_articles($data['articles'] ?? []);
    }

    /**
     * Get top headlines
     *
     * @since 2.0.0
     * @param array $params Parameters.
     * @return array Articles.
     */
    public function get_top_headlines($params = []) {
        if (empty($this->api_key)) {
            return [];
        }

        $defaults = [
            'country' => 'us',
            'category' => null, // business, entertainment, general, health, science, sports, technology
            'max_results' => 10,
        ];

        $params = array_merge($defaults, $params);

        $endpoint = $this->api_base . '/top-headlines';

        $query_params = [
            'apiKey' => $this->api_key,
            'country' => $params['country'],
            'pageSize' => $params['max_results'],
        ];

        if ($params['category']) {
            $query_params['category'] = $params['category'];
        }

        $url = add_query_arg($query_params, $endpoint);

        $response = wp_remote_get($url, ['timeout' => 30]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || $data['status'] !== 'ok') {
            return [];
        }

        return $this->parse_articles($data['articles'] ?? []);
    }

    /**
     * Parse NewsAPI articles
     *
     * @since 2.0.0
     * @param array $articles Raw articles from API.
     * @return array Parsed articles.
     */
    private function parse_articles($articles) {
        $parsed = [];

        foreach ($articles as $article) {
            $parsed[] = [
                'title' => $article['title'] ?? '',
                'description' => $article['description'] ?? '',
                'url' => $article['url'] ?? '',
                'published_at' => strtotime($article['publishedAt'] ?? 'now'),
                'source' => $article['source']['name'] ?? '',
                'source_url' => $this->extract_domain($article['url'] ?? ''),
                'author' => $article['author'] ?? '',
                'image_url' => $article['urlToImage'] ?? '',
                'content_preview' => $article['content'] ?? '',
            ];
        }

        return $parsed;
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
     * Get available sources
     *
     * @since 2.0.0
     * @param string $country Country code.
     * @param string $language Language code.
     * @return array Sources.
     */
    public function get_sources($country = 'us', $language = 'en') {
        if (empty($this->api_key)) {
            return [];
        }

        $endpoint = $this->api_base . '/sources';

        $url = add_query_arg([
            'apiKey' => $this->api_key,
            'country' => $country,
            'language' => $language,
        ], $endpoint);

        $response = wp_remote_get($url, ['timeout' => 30]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || $data['status'] !== 'ok') {
            return [];
        }

        return $data['sources'] ?? [];
    }
}
