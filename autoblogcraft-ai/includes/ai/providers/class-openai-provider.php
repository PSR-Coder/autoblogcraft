<?php
/**
 * OpenAI Provider
 *
 * Clean implementation using dependency injection.
 * No internal API key storage.
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
 * OpenAI Provider class
 *
 * Implements ChatGPT (GPT-4, GPT-3.5) integration.
 */
class OpenAI_Provider extends Base_Provider {
    /**
     * API endpoint base URL
     *
     * @var string
     */
    protected $api_base = 'https://api.openai.com/v1';

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        $this->provider_name = 'openai';
        $this->provider_label = 'OpenAI';
        $this->default_model = 'gpt-4o-mini';

        $this->supports = [
            'content_rewrite' => true,
            'translation' => true,
            'humanization' => true,
            'streaming' => false,
        ];
    }

    /**
     * Rewrite content using OpenAI
     *
     * @param string $content Original content
     * @param array $options Options (must include 'api_key')
     * @return array|WP_Error
     */
    public function rewrite_content($content, $options = []) {
        // Validate options
        $validation = $this->validate_options($options);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Get merged options with defaults
        $opts = $this->get_rewrite_options($options);

        // Build prompt
        $prompt = $this->build_rewrite_prompt($content, $opts);

        // Prepare request
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
            'response_format' => ['type' => 'json_object'], // Force JSON output
        ];

        // Make API call
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

        // Parse response
        return $this->parse_rewrite_response($response, $content);
    }

    /**
     * Translate content using OpenAI
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
            "Translate the following content from %s to %s. Preserve all HTML tags and formatting. Return only the translated content without explanations.\n\n%s",
            $options['from_language'],
            $options['to_language'],
            $content
        );

        $request_body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a professional translator. Translate content accurately while preserving HTML formatting.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.3, // Low temperature for accuracy
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

        $tokens_used = $this->get_token_usage($response);

        return [
            'content' => $translated,
            'tokens_used' => $tokens_used,
        ];
    }

    /**
     * Humanize AI content using OpenAI
     *
     * @param string $content AI-generated content
     * @param array $options Options
     * @return array|WP_Error
     */
    protected function do_humanize($content, $options) {
        $validation = $this->validate_options($options);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $model = !empty($options['model']) ? $options['model'] : $this->default_model;

        $prompt = "Rewrite the following AI-generated content to make it sound more natural and human-written. Remove robotic phrases, vary sentence structure, and add subtle personality while maintaining the core message and HTML formatting.\n\n" . $content;

        $request_body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert editor who makes AI-generated content sound more natural and human-written.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.8, // Higher for more variation
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

        $tokens_used = $this->get_token_usage($response);

        return [
            'content' => $humanized,
            'tokens_used' => $tokens_used,
        ];
    }

    // ========================================
    // Private Helper Methods
    // ========================================

    /**
     * Build rewrite prompt
     *
     * @param string $content Original content
     * @param array $opts Options
     * @return string Prompt
     */
    private function build_rewrite_prompt($content, $opts) {
        $title = !empty($opts['title']) ? $opts['title'] : '';

        $prompt = "Rewrite the following content to make it unique and engaging.\n\n";

        if ($title) {
            $prompt .= "Source Title: {$title}\n\n";
        }

        $prompt .= "Requirements:\n";
        $prompt .= "- Word count: {$opts['min_words']} to {$opts['max_words']} words\n";
        $prompt .= "- Tone: {$opts['tone']}\n";
        $prompt .= "- Language: {$opts['language']}\n";
        $prompt .= "- Preserve all facts and key information\n";
        $prompt .= "- Use HTML formatting (<p>, <h2>, <h3>, <ul>, <li>)\n";
        $prompt .= "- Do NOT use <h1>, <html>, or <body> tags\n\n";

        if (!empty($opts['custom_prompt'])) {
            $prompt .= "Additional Instructions:\n{$opts['custom_prompt']}\n\n";
        }

        $prompt .= "Source Content:\n{$content}\n\n";

        $prompt .= "Return ONLY a JSON object with this structure:\n";
        $prompt .= '{"htmlContent":"<p>...</p>","seo":{"focusKeyphrase":"","seoTitle":"","metaDescription":"","slug":""}}';

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
            'You are a professional content writer creating %s content. Write naturally and engagingly while being factual and accurate.',
            $opts['language']
        );
    }

    /**
     * Parse rewrite response
     *
     * @param array $response API response
     * @param string $original_content Original content (fallback)
     * @return array|WP_Error
     */
    private function parse_rewrite_response($response, $original_content) {
        $message_content = $this->extract_message_content($response);

        if (is_wp_error($message_content)) {
            return $message_content;
        }

        // Parse JSON response
        $data = json_decode($message_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'json_parse_error',
                sprintf(
                    __('Failed to parse AI response as JSON: %s', 'autoblogcraft-ai'),
                    json_last_error_msg()
                )
            );
        }

        // Validate required fields
        if (empty($data['htmlContent'])) {
            return new WP_Error(
                'missing_content',
                __('AI response missing htmlContent field', 'autoblogcraft-ai')
            );
        }

        $tokens_used = $this->get_token_usage($response);

        return [
            'content' => $data['htmlContent'],
            'seo' => isset($data['seo']) ? $data['seo'] : [],
            'tokens_used' => $tokens_used,
        ];
    }

    /**
     * Extract message content from response
     *
     * @param array $response API response
     * @return string|WP_Error
     */
    private function extract_message_content($response) {
        if (empty($response['choices'][0]['message']['content'])) {
            return new WP_Error(
                'empty_response',
                __('OpenAI returned an empty response', 'autoblogcraft-ai')
            );
        }

        return $response['choices'][0]['message']['content'];
    }

    /**
     * Get token usage from response
     *
     * @param array $response API response
     * @return int Token count
     */
    private function get_token_usage($response) {
        if (isset($response['usage']['total_tokens'])) {
            return intval($response['usage']['total_tokens']);
        }

        return 0;
    }

    /**
     * Parse error response from OpenAI
     *
     * @param string $body Response body
     * @param int $status_code HTTP status code
     * @return string Error message
     */
    protected function parse_error_response($body, $status_code) {
        $decoded = json_decode($body, true);

        if ($decoded && isset($decoded['error']['message'])) {
            return $decoded['error']['message'];
        }

        return parent::parse_error_response($body, $status_code);
    }
}
