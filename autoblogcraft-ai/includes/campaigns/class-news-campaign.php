<?php
/**
 * News Campaign Class
 *
 * @package AutoBlogCraft
 * @since 2.0.0
 */

namespace AutoBlogCraft\Campaigns;

use AutoBlogCraft\Helpers\Validation;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * News campaign type
 *
 * Handles SERP-based news discovery with real-time freshness.
 */
class News_Campaign extends Campaign_Base {
    /**
     * Constructor
     *
     * @param int $campaign_id Campaign ID
     */
    public function __construct($campaign_id) {
        parent::__construct($campaign_id);
        $this->campaign_type = 'news';
    }

    /**
     * Get discoverer class name
     *
     * @return string
     */
    public function get_discoverer_class() {
        return 'AutoBlogCraft\\Discovery\\News\\SERP_Discoverer';
    }

    /**
     * Get processor class name
     *
     * @return string
     */
    public function get_processor_class() {
        return 'AutoBlogCraft\\Processing\\Processors\\News_Processor';
    }

    /**
     * Validate source data
     *
     * News campaigns don't have traditional "sources" - they use keywords.
     * This method validates keyword configuration.
     *
     * @param array $source Source data (keyword config)
     * @return bool|WP_Error
     */
    public function validate_source($source) {
        // For news campaigns, "source" is keyword configuration
        if (isset($source['keywords']) && !is_array($source['keywords'])) {
            return new WP_Error(
                'invalid_keywords',
                __('Keywords must be an array', 'autoblogcraft-ai')
            );
        }

        return true;
    }

    /**
     * Get default configuration
     *
     * @return array
     */
    public function get_default_config() {
        return [
            '_discovery_interval' => '1hr', // News needs frequent checking
            '_max_queue_size' => 50,
            '_max_posts_per_day' => 10,
            '_batch_size' => 5,
            '_delay_seconds' => 60,
            '_news_keywords' => [], // ["AI", "Machine Learning"]
            '_news_exclude_keywords' => [], // ["COVID", "war"]
            '_news_freshness' => '24h', // 1h|6h|24h|7d
            '_news_geotargeting' => '', // Country code: US, DE, JP
            '_news_source_mode' => 'allow', // allow|block
            '_news_source_list' => [], // Domain list
            '_skip_if_no_news' => true,
            '_content_summarize_only' => false, // Snippet + AI summary vs full scrape
            '_serp_provider' => 'google_news', // google_news|serpapi|newsapi
            '_serp_fallback_providers' => ['serpapi', 'newsapi'], // Fallback order
            '_attribution_style' => 'inline', // inline|footnote|endnote|none
            '_author_persona' => '', // "Tech journalist", "Industry analyst"
            '_internal_links' => [], // [{url, anchor_text, placement}]
        ];
    }

    /**
     * Get news keywords
     *
     * @return array
     */
    public function get_keywords() {
        $keywords = $this->get_meta('_news_keywords', []);
        return is_array($keywords) ? $keywords : [];
    }

    /**
     * Get exclude keywords
     *
     * @return array
     */
    public function get_exclude_keywords() {
        $keywords = $this->get_meta('_news_exclude_keywords', []);
        return is_array($keywords) ? $keywords : [];
    }

    /**
     * Get freshness filter
     *
     * @return string 1h|6h|24h|7d
     */
    public function get_freshness() {
        return sanitize_text_field($this->get_meta('_news_freshness', '24h'));
    }

    /**
     * Get freshness in seconds
     *
     * @return int
     */
    public function get_freshness_seconds() {
        $freshness = $this->get_freshness();

        $map = [
            '1h' => HOUR_IN_SECONDS,
            '6h' => 6 * HOUR_IN_SECONDS,
            '24h' => DAY_IN_SECONDS,
            '7d' => 7 * DAY_IN_SECONDS,
        ];

        return $map[$freshness] ?? DAY_IN_SECONDS;
    }

    /**
     * Get geotargeting country code
     *
     * @return string
     */
    public function get_geotargeting() {
        return sanitize_text_field($this->get_meta('_news_geotargeting', ''));
    }

    /**
     * Get source control mode
     *
     * @return string allow|block
     */
    public function get_source_mode() {
        return sanitize_text_field($this->get_meta('_news_source_mode', 'allow'));
    }

    /**
     * Get source list (domains)
     *
     * @return array
     */
    public function get_source_list() {
        $list = $this->get_meta('_news_source_list', []);
        return is_array($list) ? $list : [];
    }

    /**
     * Check if should skip when no news found
     *
     * @return bool
     */
    public function skip_if_no_news() {
        return (bool) $this->get_meta('_skip_if_no_news', true);
    }

    /**
     * Check if should summarize only (no full scrape)
     *
     * @return bool
     */
    public function summarize_only() {
        return (bool) $this->get_meta('_content_summarize_only', false);
    }

    /**
     * Get SERP provider
     *
     * @return string google_news|serpapi|newsapi
     */
    public function get_serp_provider() {
        return sanitize_text_field($this->get_meta('_serp_provider', 'google_news'));
    }

    /**
     * Get fallback SERP providers
     *
     * @return array
     */
    public function get_fallback_providers() {
        $providers = $this->get_meta('_serp_fallback_providers', ['serpapi', 'newsapi']);
        return is_array($providers) ? $providers : [];
    }

    /**
     * Get attribution style
     *
     * @return string inline|footnote|endnote|none
     */
    public function get_attribution_style() {
        return sanitize_text_field($this->get_meta('_attribution_style', 'inline'));
    }

    /**
     * Get author persona
     *
     * @return string
     */
    public function get_author_persona() {
        return sanitize_text_field($this->get_meta('_author_persona', ''));
    }

    /**
     * Get internal links configuration
     *
     * @return array
     */
    public function get_internal_links() {
        $links = $this->get_meta('_internal_links', []);
        return is_array($links) ? $links : [];
    }

    /**
     * Check if source domain is allowed
     *
     * @param string $domain Domain name
     * @return bool
     */
    public function is_source_allowed($domain) {
        $mode = $this->get_source_mode();
        $list = $this->get_source_list();

        if (empty($list)) {
            return true; // No restrictions
        }

        $domain_in_list = in_array($domain, $list, true);

        // Allow mode: domain must be in list
        // Block mode: domain must NOT be in list
        return $mode === 'allow' ? $domain_in_list : !$domain_in_list;
    }
}
