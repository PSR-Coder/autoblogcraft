<?php
/**
 * News Discoverer
 *
 * Discovers news articles using SERP APIs.
 * Supports multiple news search providers.
 *
 * @package AutoBlogCraft\Discovery
 * @since 2.0.0
 */

namespace AutoBlogCraft\Discovery\News;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * News Discoverer class
 *
 * Searches for news articles based on keywords.
 *
 * Supported providers:
 * - NewsAPI.org
 * - Google News (via SerpApi)
 * - Bing News API
 *
 * @since 2.0.0
 */
class News_Discoverer extends Base_Discoverer {

    /**
     * Perform news discovery
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @param array $source Source configuration.
     * @return array|WP_Error Array with 'items' or error.
     */
    protected function do_discover($campaign, $source) {
        // Get provider
        $provider = isset($source['provider']) ? $source['provider'] : 'newsapi';

        // Get API key
        $api_key = $this->get_api_key($campaign, $provider);
        if (is_wp_error($api_key)) {
            return $api_key;
        }

        // Get keywords
        $keywords = isset($source['keywords']) ? $source['keywords'] : '';
        if (empty($keywords)) {
            return new WP_Error('missing_keywords', 'Keywords are required for news discovery');
        }

        // Discover based on provider
        switch ($provider) {
            case 'newsapi':
                return $this->discover_newsapi($keywords, $api_key, $source);
            
            case 'serpapi':
                return $this->discover_serpapi($keywords, $api_key, $source);
            
            case 'bing':
                return $this->discover_bing($keywords, $api_key, $source);
            
            default:
                return new WP_Error('invalid_provider', "Unsupported news provider: {$provider}");
        }
    }

    /**
     * Discover using NewsAPI.org
     *
     * @since 2.0.0
     * @param string $keywords Search keywords.
     * @param string $api_key API key.
     * @param array $source Source config.
     * @return array|WP_Error Discovery result or error.
     */
    private function discover_newsapi($keywords, $api_key, $source) {
        $url = 'https://newsapi.org/v2/everything';

        $params = [
            'q' => $keywords,
            'apiKey' => $api_key,
            'pageSize' => isset($source['max_results']) ? min($source['max_results'], 100) : 50,
            'sortBy' => isset($source['sort_by']) ? $source['sort_by'] : 'publishedAt',
        ];

        // Language filter
        if (!empty($source['language'])) {
            $params['language'] = $source['language'];
        }

        // Date range
        if (!empty($source['from_date'])) {
            $params['from'] = $source['from_date'];
        }

        $url = add_query_arg($params, $url);

        $response = $this->fetch_url($url);
        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($data['status']) || $data['status'] !== 'ok') {
            $error_msg = isset($data['message']) ? $data['message'] : 'Unknown API error';
            return new WP_Error('newsapi_error', $error_msg);
        }

        $items = [];

        if (isset($data['articles']) && is_array($data['articles'])) {
            foreach ($data['articles'] as $article) {
                $item = $this->parse_newsapi_article($article);
                if ($item !== null) {
                    $items[] = $item;
                }
            }
        }

        return [
            'items' => $items,
            'provider' => 'newsapi',
        ];
    }

    /**
     * Parse NewsAPI article
     *
     * @since 2.0.0
     * @param array $article Article data.
     * @return array|null Item data or null if invalid.
     */
    private function parse_newsapi_article($article) {
        // URL (required)
        $url = isset($article['url']) ? $article['url'] : '';
        if (empty($url)) {
            return null;
        }

        // Title (required)
        $title = isset($article['title']) ? $article['title'] : '';
        if (empty($title)) {
            return null;
        }

        // Description
        $description = isset($article['description']) ? $article['description'] : '';

        // Content preview
        $content = isset($article['content']) ? $article['content'] : '';

        $excerpt = !empty($content) ? $content : $description;

        return [
            'url' => esc_url_raw($url),
            'title' => sanitize_text_field($title),
            'excerpt' => wp_kses_post($excerpt),
            'date' => isset($article['publishedAt']) ? $article['publishedAt'] : '',
            'author' => isset($article['author']) ? sanitize_text_field($article['author']) : '',
            'image' => isset($article['urlToImage']) ? esc_url_raw($article['urlToImage']) : '',
            'categories' => [],
            'raw_data' => [
                'source' => isset($article['source']['name']) ? $article['source']['name'] : '',
            ],
        ];
    }

    /**
     * Discover using SerpAPI (Google News)
     *
     * @since 2.0.0
     * @param string $keywords Search keywords.
     * @param string $api_key API key.
     * @param array $source Source config.
     * @return array|WP_Error Discovery result or error.
     */
    private function discover_serpapi($keywords, $api_key, $source) {
        $url = 'https://serpapi.com/search';

        $params = [
            'q' => $keywords,
            'api_key' => $api_key,
            'engine' => 'google',
            'tbm' => 'nws', // News search
            'num' => isset($source['max_results']) ? min($source['max_results'], 100) : 50,
        ];

        // Language/region
        if (!empty($source['language'])) {
            $params['hl'] = $source['language'];
        }
        if (!empty($source['region'])) {
            $params['gl'] = $source['region'];
        }

        $url = add_query_arg($params, $url);

        $response = $this->fetch_url($url);
        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($data['error'])) {
            return new WP_Error('serpapi_error', $data['error']);
        }

        $items = [];

        if (isset($data['news_results']) && is_array($data['news_results'])) {
            foreach ($data['news_results'] as $article) {
                $item = $this->parse_serpapi_article($article);
                if ($item !== null) {
                    $items[] = $item;
                }
            }
        }

        return [
            'items' => $items,
            'provider' => 'serpapi',
        ];
    }

    /**
     * Parse SerpAPI article
     *
     * @since 2.0.0
     * @param array $article Article data.
     * @return array|null Item data or null if invalid.
     */
    private function parse_serpapi_article($article) {
        // Link (required)
        $link = isset($article['link']) ? $article['link'] : '';
        if (empty($link)) {
            return null;
        }

        // Title (required)
        $title = isset($article['title']) ? $article['title'] : '';
        if (empty($title)) {
            return null;
        }

        // Snippet
        $snippet = isset($article['snippet']) ? $article['snippet'] : '';

        return [
            'url' => esc_url_raw($link),
            'title' => sanitize_text_field($title),
            'excerpt' => wp_kses_post($snippet),
            'date' => isset($article['date']) ? $article['date'] : '',
            'author' => '',
            'image' => isset($article['thumbnail']) ? esc_url_raw($article['thumbnail']) : '',
            'categories' => [],
            'raw_data' => [
                'source' => isset($article['source']) ? $article['source'] : '',
            ],
        ];
    }

    /**
     * Discover using Bing News API
     *
     * @since 2.0.0
     * @param string $keywords Search keywords.
     * @param string $api_key API key.
     * @param array $source Source config.
     * @return array|WP_Error Discovery result or error.
     */
    private function discover_bing($keywords, $api_key, $source) {
        $url = 'https://api.bing.microsoft.com/v7.0/news/search';

        $params = [
            'q' => $keywords,
            'count' => isset($source['max_results']) ? min($source['max_results'], 100) : 50,
        ];

        // Market/language
        if (!empty($source['market'])) {
            $params['mkt'] = $source['market'];
        }

        // Freshness
        if (!empty($source['freshness'])) {
            $params['freshness'] = $source['freshness']; // Day, Week, Month
        }

        $url = add_query_arg($params, $url);

        $response = $this->fetch_url($url, [
            'headers' => [
                'Ocp-Apim-Subscription-Key' => $api_key,
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($data['error'])) {
            $error_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
            return new WP_Error('bing_error', $error_msg);
        }

        $items = [];

        if (isset($data['value']) && is_array($data['value'])) {
            foreach ($data['value'] as $article) {
                $item = $this->parse_bing_article($article);
                if ($item !== null) {
                    $items[] = $item;
                }
            }
        }

        return [
            'items' => $items,
            'provider' => 'bing',
        ];
    }

    /**
     * Parse Bing News article
     *
     * @since 2.0.0
     * @param array $article Article data.
     * @return array|null Item data or null if invalid.
     */
    private function parse_bing_article($article) {
        // URL (required)
        $url = isset($article['url']) ? $article['url'] : '';
        if (empty($url)) {
            return null;
        }

        // Name/Title (required)
        $title = isset($article['name']) ? $article['name'] : '';
        if (empty($title)) {
            return null;
        }

        // Description
        $description = isset($article['description']) ? $article['description'] : '';

        // Image
        $image = '';
        if (isset($article['image']['thumbnail']['contentUrl'])) {
            $image = $article['image']['thumbnail']['contentUrl'];
        }

        return [
            'url' => esc_url_raw($url),
            'title' => sanitize_text_field($title),
            'excerpt' => wp_kses_post($description),
            'date' => isset($article['datePublished']) ? $article['datePublished'] : '',
            'author' => '',
            'image' => esc_url_raw($image),
            'categories' => isset($article['category']) ? [$article['category']] : [],
            'raw_data' => [
                'source' => isset($article['provider'][0]['name']) ? $article['provider'][0]['name'] : '',
            ],
        ];
    }

    /**
     * Get API key for provider
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @param string $provider Provider name.
     * @return string|WP_Error API key or error.
     */
    private function get_api_key($campaign, $provider) {
        // Check campaign-specific key
        $meta_key = $provider . '_api_key';
        $api_key = $campaign->get_meta($meta_key, '');

        // Fallback to global setting
        if (empty($api_key)) {
            $option_key = 'abc_' . $provider . '_api_key';
            $api_key = get_option($option_key, '');
        }

        if (empty($api_key)) {
            return new WP_Error(
                'missing_api_key',
                sprintf('%s API key is not configured', ucfirst($provider))
            );
        }

        return $api_key;
    }

    /**
     * Get source type identifier
     *
     * @since 2.0.0
     * @return string Source type.
     */
    protected function get_source_type() {
        return 'news';
    }

    /**
     * Validate campaign configuration
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @return bool|WP_Error True if valid, error otherwise.
     */
    protected function validate_campaign($campaign) {
        $parent_valid = parent::validate_campaign($campaign);
        if (is_wp_error($parent_valid)) {
            return $parent_valid;
        }

        $sources = $this->get_sources($campaign);
        if (empty($sources)) {
            return new WP_Error(
                'no_news_sources',
                'No news sources configured for campaign'
            );
        }

        // Validate first source (check API key)
        $first_source = reset($sources);
        $provider = isset($first_source['provider']) ? $first_source['provider'] : 'newsapi';
        
        $api_key = $this->get_api_key($campaign, $provider);
        if (is_wp_error($api_key)) {
            return $api_key;
        }

        return true;
    }
}
