<?php
/**
 * Admin AJAX Handlers
 *
 * Handles all admin AJAX requests.
 *
 * @package AutoBlogCraft\Admin
 * @since 2.0.0
 */

namespace AutoBlogCraft\Admin;

use AutoBlogCraft\Campaigns\Campaign_Factory;
use AutoBlogCraft\AI\Key_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handlers class
 *
 * @since 2.0.0
 */
class AJAX_Handlers {

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        // Campaign actions
        add_action('wp_ajax_abc_validate_rss_feed', [$this, 'validate_rss_feed']);
        add_action('wp_ajax_abc_validate_url', [$this, 'validate_url']);
        add_action('wp_ajax_abc_validate_rss', [$this, 'validate_rss_feed']); // Alias for consistency
        add_action('wp_ajax_abc_validate_youtube', [$this, 'validate_youtube']);
        add_action('wp_ajax_abc_validate_api_key', [$this, 'validate_api_key']);
        add_action('wp_ajax_abc_campaign_pause', [$this, 'pause_campaign']);
        add_action('wp_ajax_abc_campaign_activate', [$this, 'activate_campaign']);
        add_action('wp_ajax_abc_bulk_campaign_action', [$this, 'bulk_campaign_action']);
        
        // API key validation
        add_action('wp_ajax_abc_test_api_key', [$this, 'test_api_key']);
    }

    /**
     * Validate RSS feed
     *
     * @since 2.0.0
     */
    public function validate_rss_feed() {
        check_ajax_referer('abc_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'autoblogcraft')]);
        }

        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';

        if (empty($url)) {
            wp_send_json_error(['message' => __('URL is required.', 'autoblogcraft')]);
        }

        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(['message' => __('Invalid URL format.', 'autoblogcraft')]);
        }

        // Try to fetch the feed
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $body = wp_remote_retrieve_body($response);
        
        // Try to parse as XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        
        if ($xml === false) {
            wp_send_json_error(['message' => __('Not a valid RSS/Atom feed.', 'autoblogcraft')]);
        }

        // Check if it's RSS or Atom
        $is_rss = isset($xml->channel) || isset($xml->item);
        $is_atom = isset($xml->entry);

        if (!$is_rss && !$is_atom) {
            wp_send_json_error(['message' => __('Not a valid RSS/Atom feed.', 'autoblogcraft')]);
        }

        // Get feed info
        $title = '';
        $item_count = 0;

        if ($is_rss) {
            $title = isset($xml->channel->title) ? (string) $xml->channel->title : '';
            $item_count = isset($xml->channel->item) ? count($xml->channel->item) : 0;
            if ($item_count === 0 && isset($xml->item)) {
                $item_count = count($xml->item);
            }
        } else {
            $title = isset($xml->title) ? (string) $xml->title : '';
            $item_count = isset($xml->entry) ? count($xml->entry) : 0;
        }

        wp_send_json_success([
            'message' => __('Valid RSS feed!', 'autoblogcraft'),
            'feed_title' => $title,
            'item_count' => $item_count,
            'feed_type' => $is_rss ? 'RSS' : 'Atom',
        ]);
    }

    /**
     * Validate URL
     *
     * @since 2.0.0
     */
    public function validate_url() {
        check_ajax_referer('abc_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'autoblogcraft')]);
        }

        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';

        if (empty($url)) {
            wp_send_json_error(['message' => __('URL is required.', 'autoblogcraft')]);
        }

        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(['message' => __('Invalid URL format.', 'autoblogcraft')]);
        }

        // Try to fetch the URL
        $response = wp_remote_head($url, [
            'timeout' => 10,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            wp_send_json_error(['message' => sprintf(__('URL returned status code: %d', 'autoblogcraft'), $status_code)]);
        }

        wp_send_json_success(['message' => __('URL is accessible!', 'autoblogcraft')]);
    }

    /**
     * Pause campaign
     *
     * @since 2.0.0
     */
    public function pause_campaign() {
        check_ajax_referer('abc_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'autoblogcraft')]);
        }

        $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;

        if (!$campaign_id) {
            wp_send_json_error(['message' => __('Invalid campaign ID.', 'autoblogcraft')]);
        }

        $factory = new Campaign_Factory();
        $campaign = $factory->get_campaign($campaign_id);

        if (!$campaign) {
            wp_send_json_error(['message' => __('Campaign not found.', 'autoblogcraft')]);
        }

        $campaign->update_meta('status', 'paused');

        wp_send_json_success(['message' => __('Campaign paused.', 'autoblogcraft')]);
    }

    /**
     * Activate campaign
     *
     * @since 2.0.0
     */
    public function activate_campaign() {
        check_ajax_referer('abc_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'autoblogcraft')]);
        }

        $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;

        if (!$campaign_id) {
            wp_send_json_error(['message' => __('Invalid campaign ID.', 'autoblogcraft')]);
        }

        $factory = new Campaign_Factory();
        $campaign = $factory->get_campaign($campaign_id);

        if (!$campaign) {
            wp_send_json_error(['message' => __('Campaign not found.', 'autoblogcraft')]);
        }

        $campaign->update_meta('status', 'active');

        wp_send_json_success(['message' => __('Campaign activated.', 'autoblogcraft')]);
    }

    /**
     * Bulk campaign action
     *
     * @since 2.0.0
     */
    public function bulk_campaign_action() {
        check_ajax_referer('abc_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'autoblogcraft')]);
        }

        $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
        $campaign_ids = isset($_POST['campaign_ids']) ? array_map('absint', $_POST['campaign_ids']) : [];

        if (empty($action) || empty($campaign_ids)) {
            wp_send_json_error(['message' => __('Invalid request.', 'autoblogcraft')]);
        }

        $factory = new Campaign_Factory();
        $count = 0;

        foreach ($campaign_ids as $campaign_id) {
            $campaign = $factory->get_campaign($campaign_id);
            if (!$campaign) {
                continue;
            }

            switch ($action) {
                case 'pause':
                    $campaign->update_meta('status', 'paused');
                    $count++;
                    break;

                case 'resume':
                    $campaign->update_meta('status', 'active');
                    $count++;
                    break;

                case 'delete':
                    wp_delete_post($campaign_id, true);
                    $count++;
                    break;
            }
        }

        wp_send_json_success([
            'message' => sprintf(__('%d campaigns %s.', 'autoblogcraft'), $count, $action === 'delete' ? 'deleted' : ($action . 'd')),
            'count' => $count,
        ]);
    }

    /**
     * Test API key
     *
     * @since 2.0.0
     */
    public function test_api_key() {
        check_ajax_referer('abc_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'autoblogcraft')]);
        }

        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        if (empty($provider) || empty($api_key)) {
            wp_send_json_error(['message' => __('Provider and API key are required.', 'autoblogcraft')]);
        }

        // Test the API key based on provider
        $test_result = $this->test_provider_key($provider, $api_key);

        if (is_wp_error($test_result)) {
            wp_send_json_error(['message' => $test_result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('API key is valid!', 'autoblogcraft')]);
    }

    /**
     * Test provider API key
     *
     * @since 2.0.0
     * @param string $provider Provider name.
     * @param string $api_key API key.
     * @return true|\WP_Error True on success, WP_Error on failure.
     */
    private function test_provider_key($provider, $api_key) {
        switch ($provider) {
            case 'openai':
                return $this->test_openai_key($api_key);
            
            case 'gemini':
                return $this->test_gemini_key($api_key);
            
            case 'claude':
                return $this->test_claude_key($api_key);
            
            default:
                return new \WP_Error('unsupported', __('Provider not supported.', 'autoblogcraft'));
        }
    }

    /**
     * Test OpenAI API key
     *
     * @since 2.0.0
     * @param string $api_key API key.
     * @return true|\WP_Error
     */
    private function test_openai_key($api_key) {
        $response = wp_remote_get('https://api.openai.com/v1/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            return new \WP_Error('invalid_key', __('Invalid API key.', 'autoblogcraft'));
        }

        return true;
    }

    /**
     * Test Gemini API key
     *
     * @since 2.0.0
     * @param string $api_key API key.
     * @return true|\WP_Error
     */
    private function test_gemini_key($api_key) {
        $response = wp_remote_get('https://generativelanguage.googleapis.com/v1/models?key=' . $api_key, [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            return new \WP_Error('invalid_key', __('Invalid API key.', 'autoblogcraft'));
        }

        return true;
    }

    /**
     * Test Claude API key
     *
     * @since 2.0.0
     * @param string $api_key API key.
     * @return true|\WP_Error
     */
    private function test_claude_key($api_key) {
        // Claude doesn't have a simple validation endpoint
        // We'll just check if the key format is correct
        if (strpos($api_key, 'sk-ant-') !== 0) {
            return new \WP_Error('invalid_key', __('Invalid API key format.', 'autoblogcraft'));
        }

        return true;
    }

    /**
     * Validate YouTube channel or playlist
     *
     * @since 2.0.0
     */
    public function validate_youtube() {
        check_ajax_referer('abc_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'autoblogcraft')]);
        }

        $value = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : '';
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'channel';

        if (empty($value)) {
            wp_send_json_error(['message' => __('Value is required.', 'autoblogcraft')]);
        }

        // Extract ID from URL if needed
        $id = $this->extract_youtube_id($value, $type);

        if (!$id) {
            wp_send_json_error(['message' => __('Invalid YouTube ' . $type . ' format.', 'autoblogcraft')]);
        }

        // For now, just validate the format
        // In production, you would make an API call to YouTube Data API
        $message = sprintf(
            __('%s ID validated: %s', 'autoblogcraft'),
            ucfirst($type),
            $id
        );

        wp_send_json_success(['message' => $message, 'id' => $id]);
    }

    /**
     * Extract YouTube ID from URL or return ID if already in correct format
     *
     * @since 2.0.0
     * @param string $value URL or ID.
     * @param string $type Type: 'channel' or 'playlist'.
     * @return string|false ID on success, false on failure.
     */
    private function extract_youtube_id($value, $type) {
        if ($type === 'channel') {
            // Channel ID format: UC...
            if (preg_match('/^UC[\w-]{22}$/', $value)) {
                return $value;
            }

            // Extract from URL
            if (preg_match('/youtube\.com\/channel\/(UC[\w-]{22})/', $value, $matches)) {
                return $matches[1];
            }

            // Extract from @handle URL
            if (preg_match('/youtube\.com\/@([\w-]+)/', $value, $matches)) {
                return '@' . $matches[1];
            }

            // Extract from user URL
            if (preg_match('/youtube\.com\/user\/([\w-]+)/', $value, $matches)) {
                return 'user:' . $matches[1];
            }
        } elseif ($type === 'playlist') {
            // Playlist ID format: PL...
            if (preg_match('/^PL[\w-]{32}$/', $value)) {
                return $value;
            }

            // Extract from URL
            if (preg_match('/[?&]list=(PL[\w-]{32})/', $value, $matches)) {
                return $matches[1];
            }
        }

        return false;
    }

    /**
     * Validate API key (generic wrapper for test_api_key)
     *
     * @since 2.0.0
     */
    public function validate_api_key() {
        check_ajax_referer('abc_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'autoblogcraft')]);
        }

        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        if (empty($api_key)) {
            wp_send_json_error(['message' => __('API key is required.', 'autoblogcraft')]);
        }

        // Provider-specific validation
        switch ($provider) {
            case 'youtube':
                $result = $this->validate_youtube_api_key($api_key);
                break;

            case 'newsapi':
                $result = $this->validate_newsapi_key($api_key);
                break;

            case 'serpapi':
                $result = $this->validate_serpapi_key($api_key);
                break;

            case 'openai':
            case 'gemini':
            case 'claude':
                $result = $this->test_provider_key($provider, $api_key);
                break;

            default:
                wp_send_json_error(['message' => __('Unknown provider.', 'autoblogcraft')]);
                return;
        }

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('API key is valid!', 'autoblogcraft')]);
    }

    /**
     * Validate YouTube API key
     *
     * @since 2.0.0
     * @param string $api_key API key.
     * @return true|\WP_Error
     */
    private function validate_youtube_api_key($api_key) {
        $response = wp_remote_get('https://www.googleapis.com/youtube/v3/channels?part=id&mine=true&key=' . $api_key, [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            $data = json_decode($body, true);
            $message = isset($data['error']['message']) ? $data['error']['message'] : __('Invalid API key.', 'autoblogcraft');
            return new \WP_Error('invalid_key', $message);
        }

        return true;
    }

    /**
     * Validate NewsAPI key
     *
     * @since 2.0.0
     * @param string $api_key API key.
     * @return true|\WP_Error
     */
    private function validate_newsapi_key($api_key) {
        $response = wp_remote_get('https://newsapi.org/v2/top-headlines?country=us&pageSize=1&apiKey=' . $api_key, [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            $data = json_decode($body, true);
            $message = isset($data['message']) ? $data['message'] : __('Invalid API key.', 'autoblogcraft');
            return new \WP_Error('invalid_key', $message);
        }

        return true;
    }

    /**
     * Validate SerpAPI key
     *
     * @since 2.0.0
     * @param string $api_key API key.
     * @return true|\WP_Error
     */
    private function validate_serpapi_key($api_key) {
        $response = wp_remote_get('https://serpapi.com/account.json?api_key=' . $api_key, [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            return new \WP_Error('invalid_key', __('Invalid API key.', 'autoblogcraft'));
        }

        $data = json_decode($body, true);
        if (isset($data['error'])) {
            return new \WP_Error('invalid_key', $data['error']);
        }

        return true;
    }
}
