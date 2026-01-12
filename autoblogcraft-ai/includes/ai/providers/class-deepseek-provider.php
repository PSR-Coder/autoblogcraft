<?php
/**
 * DeepSeek Provider
 *
 * Clean implementation using dependency injection.
 *
 * @package AutoBlogCraft\AI\Providers
 * @since 2.0.0
 */

namespace AutoBlogCraft\AI\Providers;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * DeepSeek Provider class
 *
 * Implements DeepSeek integration (OpenAI-compatible API).
 */
class DeepSeek_Provider extends Base_Provider {
    /**
     * API base URL
     *
     * @var string
     */
    protected $api_base = 'https://api.deepseek.com/v1';

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        $this->provider_name = 'deepseek';
        $this->provider_label = 'DeepSeek';
        $this->default_model = 'deepseek-chat';

        $this->supports = [
            'content_rewrite' => true,
            'translation' => true,
            'humanization' => true,
            'streaming' => false,
        ];
    }

    /**
     * Rewrite content using DeepSeek
     *
     * @param string $content Original content
     * @param array $options Options (must include 'api_key')
     * @return array|WP_Error
     */
    public function rewrite_content($content, $options = []) {
        $validation = $this->validate_options($options);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $opts = $this->get_rewrite_options($options);
        $prompt = $this->build_rewrite_prompt($content, $opts);

        // DeepSeek uses OpenAI-compatible format
        $request_body = [
            'model' => $opts['model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->get_system_prompt($opts),
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => floatval($opts['temperature']),
            'response_format' => ['type' => 'json_object'],
        ];

        $response = $this->make_request(
            $this->api_base . '/chat/completions',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $options['api_key'],
                ],
                'body' => wp_json_encode($request_body),
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->parse_rewrite_response($response);
    }

    /**
     * Translate content
     *
     * @param string $content Content to translate
     * @param array $options Options
     * @return array|WP_Error
     */
    protected function do_translate($content, $options) {
        $validation = $this->validate_options($options, ['api_key', 'from_language', 'to_language']);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $model = !empty($options['model']) ? $options['model'] : $this->default_model;
        $prompt = sprintf(
            "Translate from %s to %s. Preserve HTML formatting. Return only translated content.\n\n%s",
            $options['from_language'],
            $options['to_language'],
            $content
        );

        $request_body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a professional translator. Translate accurately while preserving HTML.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.3,
        ];

        $response = $this->make_request(
            $this->api_base . '/chat/completions',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $options['api_key'],
                ],
                'body' => wp_json_encode($request_body),
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $translated = $this->extract_message_content($response);
        if (is_wp_error($translated)) {
            return $translated;
        }

        return [
            'content' => $translated,
            'tokens_used' => $this->get_token_usage($response),
        ];
    }

    /**
     * Humanize content
     *
     * @param string $content AI content
     * @param array $options Options
     * @return array|WP_Error
     */
    protected function do_humanize($content, $options) {
        $validation = $this->validate_options($options);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $model = !empty($options['model']) ? $options['model'] : $this->default_model;
        $prompt = "Rewrite this AI content to sound more natural and human-written. Vary sentence structure, remove robotic phrases.\n\n" . $content;

        $request_body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert editor who makes AI content sound natural.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.8,
        ];

        $response = $this->make_request(
            $this->api_base . '/chat/completions',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $options['api_key'],
                ],
                'body' => wp_json_encode($request_body),
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $humanized = $this->extract_message_content($response);
        if (is_wp_error($humanized)) {
            return $humanized;
        }

        return [
            'content' => $humanized,
            'tokens_used' => $this->get_token_usage($response),
        ];
    }

    /**
     * Build rewrite prompt
     *
     * @param string $content Original content
     * @param array $opts Options
     * @return string
     */
    private function build_rewrite_prompt($content, $opts) {
        $title = !empty($opts['title']) ? $opts['title'] : '';
        $prompt = "Rewrite this content to make it unique.\n\n";

        if ($title) {
            $prompt .= "Title: {$title}\n\n";
        }

        $prompt .= "Requirements:\n";
        $prompt .= "- {$opts['min_words']}-{$opts['max_words']} words\n";
        $prompt .= "- Tone: {$opts['tone']}\n";
        $prompt .= "- Language: {$opts['language']}\n";
        $prompt .= "- HTML formatting\n\n";

        if (!empty($opts['custom_prompt'])) {
            $prompt .= "{$opts['custom_prompt']}\n\n";
        }

        $prompt .= "Content:\n{$content}\n\n";
        $prompt .= 'Return JSON: {"htmlContent":"<p>...</p>","seo":{"focusKeyphrase":"","seoTitle":"","metaDescription":"","slug":""}}';

        return $prompt;
    }

    /**
     * Get system prompt
     *
     * @param array $opts Options
     * @return string
     */
    private function get_system_prompt($opts) {
        return sprintf(
            'You are a professional %s content writer. Write naturally and engagingly.',
            $opts['language']
        );
    }

    /**
     * Parse rewrite response
     *
     * @param array $response API response
     * @return array|WP_Error
     */
    private function parse_rewrite_response($response) {
        $text = $this->extract_message_content($response);
        if (is_wp_error($text)) {
            return $text;
        }

        $data = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['htmlContent'])) {
            return new WP_Error('json_parse_error', __('Failed to parse DeepSeek response', 'autoblogcraft-ai'));
        }

        return [
            'content' => $data['htmlContent'],
            'seo' => isset($data['seo']) ? $data['seo'] : [],
            'tokens_used' => $this->get_token_usage($response),
        ];
    }

    /**
     * Extract message content
     *
     * @param array $response API response
     * @return string|WP_Error
     */
    private function extract_message_content($response) {
        if (empty($response['choices'][0]['message']['content'])) {
            return new WP_Error('empty_response', __('DeepSeek returned empty response', 'autoblogcraft-ai'));
        }

        return $response['choices'][0]['message']['content'];
    }

    /**
     * Get token usage
     *
     * @param array $response API response
     * @return int
     */
    private function get_token_usage($response) {
        if (isset($response['usage']['total_tokens'])) {
            return intval($response['usage']['total_tokens']);
        }
        return 0;
    }
}
