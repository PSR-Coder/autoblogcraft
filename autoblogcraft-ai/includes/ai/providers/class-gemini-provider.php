<?php
/**
 * Google Gemini Provider
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
 * Gemini Provider class
 *
 * Implements Google Gemini (1.5 Pro, 1.5 Flash) integration.
 */
class Gemini_Provider extends Base_Provider {
    /**
     * API base URL
     *
     * @var string
     */
    protected $api_base = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        $this->provider_name = 'gemini';
        $this->provider_label = 'Google Gemini';
        $this->default_model = 'gemini-2.0-flash-exp';

        $this->supports = [
            'content_rewrite' => true,
            'translation' => true,
            'humanization' => true,
            'streaming' => false,
        ];
    }

    /**
     * Rewrite content using Gemini
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

        // Gemini request format
        $request_body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $this->get_system_prompt($opts) . "\n\n" . $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => floatval($opts['temperature']),
                'responseMimeType' => 'application/json',
            ],
        ];

        $url = sprintf(
            '%s/models/%s:generateContent?key=%s',
            $this->api_base,
            $opts['model'],
            $options['api_key']
        );

        $response = $this->make_request($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($request_body),
        ]);

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
            "Translate from %s to %s. Preserve HTML. Return only translated content.\n\n%s",
            $options['from_language'],
            $options['to_language'],
            $content
        );

        $request_body = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => ['temperature' => 0.3],
        ];

        $url = sprintf('%s/models/%s:generateContent?key=%s', $this->api_base, $model, $options['api_key']);
        $response = $this->make_request($url, [
            'body' => wp_json_encode($request_body),
        ]);

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
        $prompt = "Make this AI content sound more natural and human. Vary sentence structure, remove robotic phrases.\n\n" . $content;

        $request_body = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.8],
        ];

        $url = sprintf('%s/models/%s:generateContent?key=%s', $this->api_base, $model, $options['api_key']);
        $response = $this->make_request($url, [
            'body' => wp_json_encode($request_body),
        ]);

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
        return sprintf('You are a professional %s content writer. Write naturally and engagingly.', $opts['language']);
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
            return new WP_Error('json_parse_error', __('Failed to parse Gemini response', 'autoblogcraft-ai'));
        }

        return [
            'content' => $data['htmlContent'],
            'seo' => isset($data['seo']) ? $data['seo'] : [],
            'tokens_used' => $this->get_token_usage($response),
        ];
    }

    /**
     * Extract text from Gemini response
     *
     * @param array $response API response
     * @return string|WP_Error
     */
    private function extract_text($response) {
        if (empty($response['candidates'][0]['content']['parts'][0]['text'])) {
            return new WP_Error('empty_response', __('Gemini returned empty response', 'autoblogcraft-ai'));
        }

        return $response['candidates'][0]['content']['parts'][0]['text'];
    }

    /**
     * Get token usage
     *
     * @param array $response API response
     * @return int
     */
    private function get_token_usage($response) {
        if (isset($response['usageMetadata']['totalTokenCount'])) {
            return intval($response['usageMetadata']['totalTokenCount']);
        }
        return 0;
    }
}
