<?php
/**
 * Base AI Provider
 *
 * Abstract base class for all AI providers.
 * Uses dependency injection - no internal key storage.
 *
 * @package AutoBlogCraft\AI\Providers
 * @since 2.0.0
 */

namespace AutoBlogCraft\AI\Providers;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\Core\Rate_Limiter;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base Provider abstract class
 *
 * Clean architecture principles:
 * - No API key storage (injected via methods)
 * - Single responsibility (API communication only)
 * - Template method pattern for common operations
 * - Consistent error handling
 */
abstract class Base_Provider {
    /**
     * Provider name
     *
     * @var string
     */
    protected $provider_name = '';

    /**
     * Provider label (human-readable)
     *
     * @var string
     */
    protected $provider_label = '';

    /**
     * Default model
     *
     * @var string
     */
    protected $default_model = '';

    /**
     * Logger instance
     *
     * @var Logger
     */
    protected $logger;

    /**
     * Rate limiter instance
     *
     * @var Rate_Limiter
     */
    protected $rate_limiter;

    /**
     * Supported features
     *
     * @var array
     */
    protected $supports = [
        'content_rewrite' => true,
        'translation' => false,
        'humanization' => false,
        'streaming' => false,
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = Logger::instance();
        $this->rate_limiter = Rate_Limiter::instance();
    }

    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name() {
        return $this->provider_name;
    }

    /**
     * Get provider label
     *
     * @return string
     */
    public function get_label() {
        return $this->provider_label;
    }

    /**
     * Get default model
     *
     * @return string
     */
    public function get_default_model() {
        return $this->default_model;
    }

    /**
     * Check if provider supports a feature
     *
     * @param string $feature Feature name
     * @return bool
     */
    public function supports($feature) {
        return !empty($this->supports[$feature]);
    }

    // ========================================
    // Main API Methods
    // ========================================

    /**
     * Rewrite content using AI
     *
     * @param string $content Original content
     * @param array $options {
     *     Rewrite options
     *
     *     @type string $api_key       API key (REQUIRED - injected)
     *     @type string $model         Model name
     *     @type string $title         Source title
     *     @type int    $min_words     Minimum words
     *     @type int    $max_words     Maximum words
     *     @type string $tone          Tone (professional, casual, etc.)
     *     @type string $language      Target language
     *     @type float  $temperature   Temperature (0-1)
     *     @type string $custom_prompt Custom instructions
     * }
     * @return array|WP_Error {
     *     @type string $content       Rewritten HTML content
     *     @type array  $seo           SEO metadata
     *     @type int    $tokens_used   Tokens consumed
     * }
     */
    abstract public function rewrite_content($content, $options = []);

    /**
     * Translate content
     *
     * Only implemented by providers that support translation.
     *
     * @param string $content Content to translate
     * @param array $options {
     *     @type string $api_key       API key (REQUIRED - injected)
     *     @type string $model         Model name
     *     @type string $from_language Source language
     *     @type string $to_language   Target language
     * }
     * @return array|WP_Error {
     *     @type string $content       Translated content
     *     @type int    $tokens_used   Tokens consumed
     * }
     */
    public function translate($content, $options = []) {
        if (!$this->supports('translation')) {
            return new WP_Error(
                'feature_not_supported',
                sprintf(
                    __('%s does not support translation', 'autoblogcraft-ai'),
                    $this->provider_label
                )
            );
        }

        return $this->do_translate($content, $options);
    }

    /**
     * Humanize AI-generated content
     *
     * Only implemented by providers that support humanization.
     *
     * @param string $content AI-generated content
     * @param array $options {
     *     @type string $api_key API key (REQUIRED - injected)
     *     @type string $model   Model name
     * }
     * @return array|WP_Error {
     *     @type string $content       Humanized content
     *     @type int    $tokens_used   Tokens consumed
     * }
     */
    public function humanize($content, $options = []) {
        if (!$this->supports('humanization')) {
            return new WP_Error(
                'feature_not_supported',
                sprintf(
                    __('%s does not support humanization', 'autoblogcraft-ai'),
                    $this->provider_label
                )
            );
        }

        return $this->do_humanize($content, $options);
    }

    // ========================================
    // Protected Helper Methods
    // ========================================

    /**
     * Validate required options
     *
     * @param array $options Options array
     * @param array $required Required keys
     * @return true|WP_Error
     */
    protected function validate_options($options, $required = ['api_key']) {
        foreach ($required as $key) {
            if (empty($options[$key])) {
                return new WP_Error(
                    'missing_required_option',
                    sprintf(
                        __('Missing required option: %s', 'autoblogcraft-ai'),
                        $key
                    )
                );
            }
        }

        return true;
    }

    /**
     * Make HTTP request to API
     *
     * Includes rate limiting to prevent runaway API costs.
     *
     * @param string $url URL
     * @param array $args Request arguments
     * @param int $timeout Timeout in seconds
     * @return array|WP_Error Response body or error
     */
    protected function make_request($url, $args = [], $timeout = 60) {
        // Check rate limits with retry logic
        $max_retries = 3;
        $retry_count = 0;
        
        while (!$this->rate_limiter->can_make_ai_call() && $retry_count < $max_retries) {
            $retry_count++;
            $this->logger->warning(
                null,
                'ai',
                sprintf(
                    '%s API call rate limit reached, waiting... (retry %d/%d)',
                    $this->provider_label,
                    $retry_count,
                    $max_retries
                )
            );
            
            // Wait briefly and retry (exponential backoff)
            sleep($retry_count);
        }
        
        // If still can't make call after retries, return error
        if (!$this->rate_limiter->can_make_ai_call()) {
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __('%s API rate limit exceeded. Too many concurrent API calls. Please try again later.', 'autoblogcraft-ai'),
                    $this->provider_label
                )
            );
        }
        
        // Acquire AI call slot
        $this->rate_limiter->start_ai_call();
        
        try {
            $defaults = [
                'timeout' => $timeout,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ];

            $args = wp_parse_args($args, $defaults);

            $response = wp_remote_post($url, $args);

            if (is_wp_error($response)) {
                $this->logger->error(
                    null,
                    'ai',
                    sprintf(
                        '%s API request failed: %s',
                        $this->provider_label,
                        $response->get_error_message()
                    )
                );
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($status_code !== 200) {
                $error_message = $this->parse_error_response($body, $status_code);
                
                $this->logger->error(
                    null,
                    'ai',
                    sprintf('%s API error (%d): %s', $this->provider_label, $status_code, $error_message)
                );

                return new WP_Error(
                    'api_error',
                    sprintf(
                        __('%s API error (%d): %s', 'autoblogcraft-ai'),
                        $this->provider_label,
                        $status_code,
                        $error_message
                    )
                );
            }

            return $this->parse_response($body);
            
        } finally {
            // Always release AI call slot
            $this->rate_limiter->finish_ai_call();
        }
    }

    /**
     * Parse successful API response
     *
     * Override in child classes for provider-specific parsing.
     *
     * @param string $body Response body
     * @return array|WP_Error Parsed data or error
     */
    protected function parse_response($body) {
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'json_decode_error',
                sprintf(
                    __('Failed to decode JSON response: %s', 'autoblogcraft-ai'),
                    json_last_error_msg()
                )
            );
        }

        return $decoded;
    }

    /**
     * Parse error response
     *
     * Override in child classes for provider-specific error formats.
     *
     * @param string $body Response body
     * @param int $status_code HTTP status code
     * @return string Error message
     */
    protected function parse_error_response($body, $status_code) {
        $decoded = json_decode($body, true);

        if ($decoded && isset($decoded['error'])) {
            if (is_string($decoded['error'])) {
                return $decoded['error'];
            }
            if (isset($decoded['error']['message'])) {
                return $decoded['error']['message'];
            }
        }

        return sprintf(__('HTTP %d error', 'autoblogcraft-ai'), $status_code);
    }

    /**
     * Build default rewrite options
     *
     * @param array $options User-provided options
     * @return array Merged options with defaults
     */
    protected function get_rewrite_options($options) {
        $defaults = [
            'model' => $this->default_model,
            'title' => '',
            'min_words' => 600,
            'max_words' => 1000,
            'tone' => 'professional',
            'language' => 'English',
            'temperature' => 0.7,
            'custom_prompt' => '',
        ];

        return wp_parse_args($options, $defaults);
    }

    /**
     * Estimate token count
     *
     * Rough estimation: 1 token ≈ 4 characters for English.
     * Override for more accurate provider-specific counting.
     *
     * @param string $text Text to count
     * @return int Estimated tokens
     */
    protected function estimate_tokens($text) {
        return intval(strlen($text) / 4);
    }

    // ========================================
    // Template Methods (Override in Children)
    // ========================================

    /**
     * Implement translation
     *
     * Override in child classes that support translation.
     *
     * @param string $content Content to translate
     * @param array $options Options
     * @return array|WP_Error
     */
    protected function do_translate($content, $options) {
        return new WP_Error(
            'not_implemented',
            __('Translation not implemented for this provider', 'autoblogcraft-ai')
        );
    }

    /**
     * Implement humanization
     *
     * Override in child classes that support humanization.
     *
     * @param string $content Content to humanize
     * @param array $options Options
     * @return array|WP_Error
     */
    protected function do_humanize($content, $options) {
        return new WP_Error(
            'not_implemented',
            __('Humanization not implemented for this provider', 'autoblogcraft-ai')
        );
    }
}
