<?php
/**
 * Undetectable Provider
 *
 * Integration with Undetectable AI API for content humanization.
 *
 * @package AutoBlogCraft\Modules\Humanizer
 * @since 2.0.0
 */

namespace AutoBlogCraft\Modules\Humanizer;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\AI\AI_Settings;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Undetectable Provider class
 *
 * Handles API calls to Undetectable AI service.
 *
 * @since 2.0.0
 */
class Undetectable_Provider {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * AI settings
     *
     * @var AI_Settings
     */
    private $ai_settings;

    /**
     * API endpoint
     *
     * @var string
     */
    private $api_endpoint = 'https://api.undetectable.ai/submit';

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->logger = Logger::instance();
        $this->ai_settings = new AI_Settings();
    }

    /**
     * Humanize content
     *
     * @since 2.0.0
     * @param string $content Content to humanize.
     * @param int $level Humanization level (1-10).
     * @param int $campaign_id Campaign ID (optional).
     * @return string|WP_Error Humanized content or error.
     */
    public function humanize($content, $level = 5, $campaign_id = 0) {
        $api_key = $this->get_api_key($campaign_id);
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'Undetectable AI API key not configured');
        }

        $this->logger->debug('Calling Undetectable AI', [
            'level' => $level,
            'length' => strlen($content),
        ]);

        // Map level to readability and purpose
        $readability = $this->map_level_to_readability($level);
        $purpose = $this->get_purpose($campaign_id);

        $response = wp_remote_post($this->api_endpoint, [
            'headers' => [
                'api-key' => $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'content' => $content,
                'readability' => $readability,
                'purpose' => $purpose,
                'strength' => $this->map_level_to_strength($level),
            ]),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('Undetectable AI request failed', [
                'error' => $response->get_error_message(),
            ]);
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            $error_message = $body['error'] ?? 'Unknown error';
            $this->logger->error('Undetectable AI error', [
                'status' => $status_code,
                'error' => $error_message,
            ]);
            return new WP_Error('api_error', $error_message);
        }

        if (!isset($body['id'])) {
            return new WP_Error('invalid_response', 'Invalid response from Undetectable AI');
        }

        // Poll for result
        $result = $this->poll_result($body['id'], $api_key);
        
        if (is_wp_error($result)) {
            return $result;
        }

        $this->logger->info('Content humanized successfully', [
            'original_length' => strlen($content),
            'humanized_length' => strlen($result),
        ]);

        return $result;
    }

    /**
     * Poll for result
     *
     * @since 2.0.0
     * @param string $job_id Job ID.
     * @param string $api_key API key.
     * @return string|WP_Error Result or error.
     */
    private function poll_result($job_id, $api_key) {
        $max_attempts = 30;
        $attempt = 0;
        $delay = 2; // seconds

        while ($attempt < $max_attempts) {
            sleep($delay);

            $response = wp_remote_get(
                "https://api.undetectable.ai/document/{$job_id}",
                [
                    'headers' => [
                        'api-key' => $api_key,
                    ],
                    'timeout' => 30,
                ]
            );

            if (is_wp_error($response)) {
                return $response;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($body['status'])) {
                if ($body['status'] === 'done') {
                    return $body['output'] ?? new WP_Error('no_output', 'No output in response');
                } elseif ($body['status'] === 'error') {
                    return new WP_Error('processing_error', $body['error'] ?? 'Processing failed');
                }
                // Status is 'processing', continue polling
            }

            $attempt++;
        }

        return new WP_Error('timeout', 'Humanization timed out');
    }

    /**
     * Map level to readability
     *
     * @since 2.0.0
     * @param int $level Level (1-10).
     * @return string Readability level.
     */
    private function map_level_to_readability($level) {
        if ($level <= 3) {
            return 'High School';
        } elseif ($level <= 6) {
            return 'University';
        } elseif ($level <= 8) {
            return 'Doctorate';
        } else {
            return 'Journalist';
        }
    }

    /**
     * Map level to strength
     *
     * @since 2.0.0
     * @param int $level Level (1-10).
     * @return string Strength.
     */
    private function map_level_to_strength($level) {
        if ($level <= 3) {
            return 'more_readable';
        } elseif ($level <= 7) {
            return 'balanced';
        } else {
            return 'more_human';
        }
    }

    /**
     * Get purpose
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return string Purpose.
     */
    private function get_purpose($campaign_id = 0) {
        if ($campaign_id > 0) {
            $purpose = get_post_meta($campaign_id, '_humanizer_purpose', true);
            if (!empty($purpose)) {
                return $purpose;
            }
        }

        return 'General Writing';
    }

    /**
     * Get API key
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return string API key.
     */
    private function get_api_key($campaign_id = 0) {
        if ($campaign_id > 0) {
            $api_key = get_post_meta($campaign_id, '_undetectable_api_key', true);
            if (!empty($api_key)) {
                return $api_key;
            }
        }

        return get_option('abc_undetectable_api_key', '');
    }

    /**
     * Check if API is available
     *
     * @since 2.0.0
     * @return bool True if available.
     */
    public function is_available() {
        $api_key = get_option('abc_undetectable_api_key', '');
        return !empty($api_key);
    }

    /**
     * Test API connection
     *
     * @since 2.0.0
     * @return array|WP_Error Test result or error.
     */
    public function test_connection() {
        $api_key = get_option('abc_undetectable_api_key', '');
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'API key not configured');
        }

        // Test with short text
        $test_content = 'This is a test sentence to verify the API connection.';
        $result = $this->humanize($test_content, 5);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'success' => true,
            'message' => 'API connection successful',
        ];
    }

    /**
     * Get usage statistics
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return array|WP_Error Statistics or error.
     */
    public function get_usage_stats($campaign_id = 0) {
        $api_key = $this->get_api_key($campaign_id);
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'API key not configured');
        }

        $response = wp_remote_get(
            'https://api.undetectable.ai/usage',
            [
                'headers' => [
                    'api-key' => $api_key,
                ],
                'timeout' => 30,
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['usage'])) {
            return new WP_Error('invalid_response', 'Invalid usage statistics response');
        }

        return $body['usage'];
    }

    /**
     * Validate content before humanization
     *
     * @since 2.0.0
     * @param string $content Content to validate.
     * @return array|WP_Error Validation result or error.
     */
    public function validate_content($content) {
        $issues = [];

        // Check length
        if (strlen($content) < 50) {
            $issues[] = 'Content too short (minimum 50 characters)';
        }

        if (strlen($content) > 50000) {
            $issues[] = 'Content too long (maximum 50,000 characters)';
        }

        // Check for spam patterns
        if (preg_match('/(.{10,})\1{3,}/', $content)) {
            $issues[] = 'Content contains repetitive patterns';
        }

        if (!empty($issues)) {
            return new WP_Error('validation_failed', 'Content validation failed', ['issues' => $issues]);
        }

        return ['valid' => true];
    }
}
