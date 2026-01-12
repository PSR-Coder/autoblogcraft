<?php
/**
 * Provider Fallback Handler
 *
 * Manages fallback logic when primary SERP provider fails.
 * Tries multiple providers in sequence until success.
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
 * Provider Fallback class
 *
 * Responsibilities:
 * - Try multiple SERP providers in sequence
 * - Track provider success rates
 * - Implement circuit breaker pattern
 * - Cache provider availability status
 *
 * @since 2.0.0
 */
class Provider_Fallback {

    /**
     * Campaign ID
     *
     * @var int
     */
    private $campaign_id;

    /**
     * Available providers
     *
     * @var array
     */
    private $providers = [];

    /**
     * Provider priority order
     *
     * @var array
     */
    private $priority_order;

    /**
     * Constructor
     *
     * @since 2.0.0
     * @param int   $campaign_id     Campaign ID.
     * @param array $priority_order  Provider priority order.
     */
    public function __construct($campaign_id, $priority_order = []) {
        $this->campaign_id = $campaign_id;
        $this->priority_order = !empty($priority_order) ? $priority_order : $this->get_default_order();
        $this->initialize_providers();
    }

    /**
     * Get default provider priority order
     *
     * @since 2.0.0
     * @return array
     */
    private function get_default_order() {
        return ['serpapi', 'newsapi', 'google_news'];
    }

    /**
     * Initialize provider instances
     *
     * @since 2.0.0
     * @return void
     */
    private function initialize_providers() {
        foreach ($this->priority_order as $provider_name) {
            if (!$this->is_provider_available($provider_name)) {
                continue;
            }

            $provider = $this->create_provider($provider_name);
            if ($provider) {
                $this->providers[$provider_name] = $provider;
            }
        }
    }

    /**
     * Create provider instance
     *
     * @since 2.0.0
     * @param string $provider_name Provider name.
     * @return object|null Provider instance.
     */
    private function create_provider($provider_name) {
        switch ($provider_name) {
            case 'newsapi':
                return new NewsAPI_Provider($this->campaign_id);

            case 'serpapi':
                return new SerpAPI_Provider($this->campaign_id);

            case 'google_news':
                return $this->create_google_news_provider();

            default:
                return null;
        }
    }

    /**
     * Create Google News RSS provider
     *
     * @since 2.0.0
     * @return object
     */
    private function create_google_news_provider() {
        return new class($this->campaign_id) {
            private $campaign_id;

            public function __construct($campaign_id) {
                $this->campaign_id = $campaign_id;
            }

            public function search($keyword, $params = []) {
                $url = 'https://news.google.com/rss/search?q=' . urlencode($keyword);
                
                if (!empty($params['country'])) {
                    $url .= '&hl=' . strtolower($params['country']);
                }

                $response = wp_remote_get($url, ['timeout' => 30]);

                if (is_wp_error($response)) {
                    return [];
                }

                $xml = wp_remote_retrieve_body($response);
                return $this->parse_rss($xml, $params['max_results'] ?? 5);
            }

            private function parse_rss($xml, $max_results) {
                $articles = [];
                
                libxml_use_internal_errors(true);
                $rss = simplexml_load_string($xml);
                libxml_clear_errors();

                if (!$rss || !isset($rss->channel->item)) {
                    return [];
                }

                $count = 0;
                foreach ($rss->channel->item as $item) {
                    if ($count >= $max_results) {
                        break;
                    }

                    $articles[] = [
                        'title' => (string) $item->title,
                        'url' => (string) $item->link,
                        'description' => (string) $item->description,
                        'published_at' => strtotime((string) $item->pubDate),
                        'source' => (string) $item->source,
                    ];

                    $count++;
                }

                return $articles;
            }
        };
    }

    /**
     * Get news with fallback
     *
     * @since 2.0.0
     * @param string $keyword Search keyword.
     * @param array  $params  Search parameters.
     * @return array Articles.
     */
    public function get_news($keyword, $params = []) {
        $last_error = null;

        foreach ($this->providers as $provider_name => $provider) {
            // Check circuit breaker
            if ($this->is_circuit_open($provider_name)) {
                Logger::log($this->campaign_id, 'warning', 'discovery', "Circuit breaker open for provider: {$provider_name}");
                continue;
            }

            Logger::log($this->campaign_id, 'debug', 'discovery', "Trying provider: {$provider_name}");

            try {
                $articles = $provider->search($keyword, $params);

                if (!empty($articles)) {
                    $this->record_success($provider_name);
                    Logger::log($this->campaign_id, 'info', 'discovery', "Provider succeeded: {$provider_name}", [
                        'articles_count' => count($articles),
                    ]);
                    return $articles;
                }

                $this->record_failure($provider_name, 'No results');
            } catch (\Exception $e) {
                $last_error = $e->getMessage();
                $this->record_failure($provider_name, $last_error);
                Logger::log($this->campaign_id, 'error', 'discovery', "Provider failed: {$provider_name}", [
                    'error' => $last_error,
                ]);
            }
        }

        // All providers failed
        Logger::log($this->campaign_id, 'error', 'discovery', 'All SERP providers failed', [
            'keyword' => $keyword,
            'last_error' => $last_error,
        ]);

        return [];
    }

    /**
     * Check if provider is available (has API key)
     *
     * @since 2.0.0
     * @param string $provider_name Provider name.
     * @return bool
     */
    private function is_provider_available($provider_name) {
        if ($provider_name === 'google_news') {
            return true; // No API key needed
        }

        global $wpdb;

        $key_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}abc_api_keys 
             WHERE provider = %s AND status = 'active'",
            $provider_name
        ));

        return $key_exists > 0;
    }

    /**
     * Check if circuit breaker is open
     *
     * @since 2.0.0
     * @param string $provider_name Provider name.
     * @return bool
     */
    private function is_circuit_open($provider_name) {
        $circuit_key = 'abc_circuit_' . $provider_name . '_' . $this->campaign_id;
        $circuit_data = get_transient($circuit_key);

        if (!$circuit_data) {
            return false;
        }

        // Circuit is open if failure rate > 80% in last 10 attempts
        $failure_rate = $circuit_data['failures'] / max(1, $circuit_data['attempts']);
        return $failure_rate > 0.8 && $circuit_data['attempts'] >= 10;
    }

    /**
     * Record provider success
     *
     * @since 2.0.0
     * @param string $provider_name Provider name.
     * @return void
     */
    private function record_success($provider_name) {
        $stats_key = 'abc_provider_stats_' . $provider_name . '_' . $this->campaign_id;
        $stats = get_option($stats_key, [
            'successes' => 0,
            'failures' => 0,
            'last_success' => null,
        ]);

        $stats['successes']++;
        $stats['last_success'] = current_time('mysql');

        update_option($stats_key, $stats);

        // Reset circuit breaker
        $circuit_key = 'abc_circuit_' . $provider_name . '_' . $this->campaign_id;
        delete_transient($circuit_key);
    }

    /**
     * Record provider failure
     *
     * @since 2.0.0
     * @param string $provider_name Provider name.
     * @param string $error         Error message.
     * @return void
     */
    private function record_failure($provider_name, $error) {
        // Update stats
        $stats_key = 'abc_provider_stats_' . $provider_name . '_' . $this->campaign_id;
        $stats = get_option($stats_key, [
            'successes' => 0,
            'failures' => 0,
            'last_failure' => null,
            'last_error' => null,
        ]);

        $stats['failures']++;
        $stats['last_failure'] = current_time('mysql');
        $stats['last_error'] = $error;

        update_option($stats_key, $stats);

        // Update circuit breaker
        $circuit_key = 'abc_circuit_' . $provider_name . '_' . $this->campaign_id;
        $circuit_data = get_transient($circuit_key);

        if (!$circuit_data) {
            $circuit_data = ['attempts' => 0, 'failures' => 0];
        }

        $circuit_data['attempts']++;
        $circuit_data['failures']++;

        // Store for 1 hour
        set_transient($circuit_key, $circuit_data, 3600);
    }

    /**
     * Get provider statistics
     *
     * @since 2.0.0
     * @return array Statistics for all providers.
     */
    public function get_statistics() {
        $stats = [];

        foreach ($this->priority_order as $provider_name) {
            $stats_key = 'abc_provider_stats_' . $provider_name . '_' . $this->campaign_id;
            $provider_stats = get_option($stats_key, [
                'successes' => 0,
                'failures' => 0,
            ]);

            $total = $provider_stats['successes'] + $provider_stats['failures'];
            $success_rate = $total > 0 ? ($provider_stats['successes'] / $total) * 100 : 0;

            $stats[$provider_name] = [
                'successes' => $provider_stats['successes'],
                'failures' => $provider_stats['failures'],
                'success_rate' => round($success_rate, 2),
                'circuit_open' => $this->is_circuit_open($provider_name),
            ];
        }

        return $stats;
    }

    /**
     * Reset all provider statistics
     *
     * @since 2.0.0
     * @return void
     */
    public function reset_statistics() {
        foreach ($this->priority_order as $provider_name) {
            $stats_key = 'abc_provider_stats_' . $provider_name . '_' . $this->campaign_id;
            delete_option($stats_key);

            $circuit_key = 'abc_circuit_' . $provider_name . '_' . $this->campaign_id;
            delete_transient($circuit_key);
        }

        Logger::log($this->campaign_id, 'info', 'discovery', 'Provider statistics reset');
    }
}
