<?php
/**
 * Claude (Anthropic) Provider
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
 * Claude Provider class
 *
 * Implements Anthropic Claude integration.
 */
class Claude_Provider extends Base_Provider {
    /**
     * API base URL
     *
     * @var string
     */
    protected $api_base = 'https://api.anthropic.com/v1';

    /**
     * API version
     *
     * @var string
     */
    protected $api_version = '2023-06-01';

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        $this->provider_name = 'claude';
        $this->provider_label = 'Claude (Anthropic)';
        $this->default_model = 'claude-3-5-sonnet-20241022';

        $this->supports = [
            'content_rewrite' => true,
            'translation' => true,
            'humanization' => true,
            'streaming' => false,
        ];
    }

    /**
     * Rewrite content using Claude
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

        $request_body = [
            'model' => $opts['model'],
            'max_tokens' => 4096,
            'temperature' => floatval($opts['temperature']),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];

        $response = $this->make_request(
            $this->api_base . '/messages',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-api-key' => $options['api_key'],
                    'anthropic-version' => $this->api_version,
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
            "Translate from %s to %s. Preserve all HTML formatting. Return only the translated content without explanations.\n\n%s",
            $options['from_language'],
            $options['to_language'],
            $content
        );

        $request_body = [
            'model' => $model,
            'max_tokens' => 4096,
            'temperature' => 0.3,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        $response = $this->make_request(
            $this->api_base . '/messages',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-api-key' => $options['api_key'],
                    'anthropic-version' => $this->api_version,
                ],
                'body' => wp_json_encode($request_body),
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $translated = $this->extract_text($response);
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
        $prompt = "Rewrite this AI-generated content to sound more natural and human-written. Vary sentence structure, add subtle personality, remove robotic phrases. Preserve HTML formatting.\n\n" . $content;

        $request_body = [
            'model' => $model,
            'max_tokens' => 4096,
            'temperature' => 0.8,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        $response = $this->make_request(
            $this->api_base . '/messages',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-api-key' => $options['api_key'],
                    'anthropic-version' => $this->api_version,
                ],
                'body' => wp_json_encode($request_body),
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $humanized = $this->extract_text($response);
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
        $prompt = "Rewrite this content to make it unique and engaging.\n\n";

        if ($title) {
            $prompt .= "Source Title: {$title}\n\n";
        }

        $prompt .= "Requirements:\n";
        $prompt .= "- Word count: {$opts['min_words']} to {$opts['max_words']} words\n";
        $prompt .= "- Tone: {$opts['tone']}\n";
        $prompt .= "- Language: {$opts['language']}\n";
        $prompt .= "- Preserve all facts\n";
        $prompt .= "- Use HTML formatting (<p>, <h2>, <h3>, <ul>, <li>)\n\n";

        if (!empty($opts['custom_prompt'])) {
            $prompt .= "Additional Instructions:\n{$opts['custom_prompt']}\n\n";
        }

        $prompt .= "Source Content:\n{$content}\n\n";
        $prompt .= 'Return ONLY a JSON object: {"htmlContent":"<p>...</p>","seo":{"focusKeyphrase":"","seoTitle":"","metaDescription":"","slug":""}}';

        return $prompt;
    }

    /**
     * Parse rewrite response
     *
     * @param array $response API response
     * @return array|WP_Error
     */
    private function parse_rewrite_response($response) {
        $text = $this->extract_text($response);
        if (is_wp_error($text)) {
            return $text;
        }

        $data = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['htmlContent'])) {
            return new WP_Error('json_parse_error', __('Failed to parse Claude response as JSON', 'autoblogcraft-ai'));
        }

        return [
            'content' => $data['htmlContent'],
            'seo' => isset($data['seo']) ? $data['seo'] : [],
            'tokens_used' => $this->get_token_usage($response),
        ];
    }

    /**
     * Extract text from Claude response
     *
     * @param array $response API response
     * @return string|WP_Error
     */
    private function extract_text($response) {
        if (empty($response['content'][0]['text'])) {
            return new WP_Error('empty_response', __('Claude returned empty response', 'autoblogcraft-ai'));
        }

        return $response['content'][0]['text'];
    }

    /**
     * Get token usage
     *
     * @param array $response API response
     * @return int
     */
    private function get_token_usage($response) {
        $input_tokens = isset($response['usage']['input_tokens']) ? intval($response['usage']['input_tokens']) : 0;
        $output_tokens = isset($response['usage']['output_tokens']) ? intval($response['usage']['output_tokens']) : 0;
        
        return $input_tokens + $output_tokens;
    }
}
