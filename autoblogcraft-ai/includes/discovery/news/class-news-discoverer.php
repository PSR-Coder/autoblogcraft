<?php
/**
 * News Discoverer
 *
 * Discovers news articles using various providers (NewsAPI, SerpAPI/Google News).
 * Implements robust filtering, exclusions, and parameter mapping.
 *
 * @package AutoBlogCraft\Discovery\News
 * @since 2.1.0
 */

namespace AutoBlogCraft\Discovery\News;

use AutoBlogCraft\Discovery\Base_Discoverer;
use AutoBlogCraft\Core\API_Key_Manager;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * News Discoverer class
 */
class News_Discoverer extends Base_Discoverer
{

    private $api_manager;

    public function __construct($queue_manager)
    {
        parent::__construct($queue_manager);
        $this->api_manager = new API_Key_Manager();
    }

    /**
     * Perform discovery logic
     */
    protected function do_discover($campaign, $source_config)
    {
        // 1. Get Keywords
        $keywords = $source_config['keywords'] ?? '';
        if (empty($keywords)) {
            return new WP_Error('missing_keywords', 'No keywords configured for News campaign.');
        }

        // 2. Get AI/Provider Config
        // News API key might be stored in a dedicated field or reused from main AI keys? 
        // Architecture suggests separate keys. For now, we look for a specific 'news_api_key_id' 
        // OR rely on the fact that the Campaign Editor might need a "News Provider" section.
        // fallback to retrieving from global options or specific meta if not in generic config.

        // For this implementation, we will assume the user has a globally defined key 
        // OR we add a selector to the campaign.
        // Let's check for a provider specific key in the source config or global.

        $provider = $source_config['provider'] ?? 'newsapi'; // Default to NewsAPI
        $api_key = $this->get_provider_key($campaign, $provider);

        if (is_wp_error($api_key)) {
            return $api_key;
        }

        // 3. Map Parameters (Freshness, Geo)
        $params = $this->map_parameters($source_config, $provider);
        $params['q'] = $keywords;

        // 4. Fetch Results
        $raw_items = [];
        switch ($provider) {
            case 'newsapi':
                $raw_items = $this->fetch_newsapi($api_key, $params);
                break;
            case 'serpapi':
                $raw_items = $this->fetch_serpapi($api_key, $params);
                break;
            default:
                return new WP_Error('invalid_provider', "Provider $provider not supported.");
        }

        if (is_wp_error($raw_items)) {
            return $raw_items;
        }

        // 5. Filter Results (Exclusions, Source Blocklists)
        $filtered_items = $this->filter_results($raw_items, $source_config);

        return [
            'items' => $filtered_items,
            'provider' => $provider
        ];
    }

    /**
     * Get API Key with fallback
     */
    private function get_provider_key($campaign, $provider)
    {
        // First try campaign specific meta if it exists (custom field)
        $key = $campaign->get_meta("_{$provider}_key", true); // e.g. _newsapi_key

        if ($key)
            return $key;

        // Fallback to global options (simplified for now as we don't have global settings page yet)
        $key = get_option("abc_{$provider}_key");

        if (!$key) {
            // Temporary hard fallback or error
            return new WP_Error('missing_key', "API Key for $provider is missing. Please configure it in global settings.");
        }

        return $key;
    }

    /**
     * Map unified settings to provider-specific params
     */
    private function map_parameters($config, $provider)
    {
        $params = [];

        // Freshness Mapping
        $freshness = $config['freshness'] ?? '24h';
        $params['from'] = date('Y-m-d', strtotime("-1 day")); // Default

        if ($provider === 'newsapi') {
            if ($freshness === '1h')
                $params['from'] = date('c', strtotime("-1 hour"));
            elseif ($freshness === '24h')
                $params['from'] = date('c', strtotime("-24 hours"));
            elseif ($freshness === '7d')
                $params['from'] = date('c', strtotime("-7 days"));
            $params['sortBy'] = 'publishedAt';
            $params['language'] = 'en'; // Default to English
        } elseif ($provider === 'serpapi') {
            if ($freshness === '1h')
                $params['tbs'] = 'qdr:h';
            elseif ($freshness === '24h')
                $params['tbs'] = 'qdr:d';
            elseif ($freshness === '7d')
                $params['tbs'] = 'qdr:w';

            // Geo
            $geo = $config['geotargeting'] ?? 'us';
            $params['gl'] = $geo;
            $params['hl'] = 'en';
        }

        return $params;
    }

    /**
     * Filter results post-fetch
     */
    private function filter_results($items, $config)
    {
        $filtered = [];

        $exclude_keywords_str = $config['exclude_keywords'] ?? '';
        $exclude_keywords = array_filter(array_map('trim', explode(',', $exclude_keywords_str)));

        $source_mode = $config['source_mode'] ?? 'block';
        $source_list_str = $config['source_list'] ?? '';
        $source_list = array_filter(array_map('trim', explode("\n", $source_list_str)));

        foreach ($items as $item) {
            // 1. Keyword Exclusion
            foreach ($exclude_keywords as $neg) {
                if (stripos($item['title'], $neg) !== false || stripos($item['excerpt'], $neg) !== false) {
                    continue 2; // Skip this item
                }
            }

            // 2. Source Filtering
            $domain = parse_url($item['url'], PHP_URL_HOST);
            $domain = str_ireplace('www.', '', $domain);

            if (!empty($source_list)) {
                $match = false;
                foreach ($source_list as $src) {
                    if (stripos($domain, $src) !== false) {
                        $match = true;
                        break;
                    }
                }

                if ($source_mode === 'allow' && !$match)
                    continue;
                if ($source_mode === 'block' && $match)
                    continue;
            }

            $filtered[] = $item;
        }

        return $filtered;
    }

    /**
     * Fetch from NewsAPI
     */
    private function fetch_newsapi($key, $params)
    {
        $url = add_query_arg(
            array_merge([
                'apiKey' => $key,
                'pageSize' => 20,
            ], $params),
            'https://newsapi.org/v2/everything'
        );

        $response = wp_remote_get($url);
        if (is_wp_error($response))
            return $response;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (($body['status'] ?? '') !== 'ok') {
            return new WP_Error('newsapi_error', $body['message'] ?? 'Unknown error');
        }

        $results = [];
        foreach ($body['articles'] as $article) {
            $results[] = [
                'title' => $article['title'],
                'url' => $article['url'],
                'excerpt' => $article['description'],
                'date' => $article['publishedAt'],
                'source_name' => $article['source']['name'] ?? '',
                'image' => $article['urlToImage'] ?? ''
            ];
        }
        return $results;
    }

    /**
     * Fetch from SerpAPI
     */
    private function fetch_serpapi($key, $params)
    {
        $url = add_query_arg(
            array_merge([
                'api_key' => $key,
                'engine' => 'google_news',
                'num' => 20
            ], $params),
            'https://serpapi.com/search'
        );

        $response = wp_remote_get($url);
        if (is_wp_error($response))
            return $response;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) {
            return new WP_Error('serpapi_error', $body['error']);
        }

        $results = [];
        if (!empty($body['news_results'])) {
            foreach ($body['news_results'] as $article) {
                $results[] = [
                    'title' => $article['title'],
                    'url' => $article['link'],
                    'excerpt' => $article['snippet'] ?? '', // Google doesn't always provide snippets
                    'date' => $article['date'] ?? '',
                    'source_name' => $article['source']['title'] ?? '',
                    'image' => $article['thumbnail'] ?? ''
                ];
            }
        }
        return $results;
    }
}
