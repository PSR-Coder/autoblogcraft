<?php
/**
 * GPT-4o Humanizer
 *
 * Uses GPT-4o for advanced content humanization with anti-detection patterns.
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
 * GPT-4o Humanizer class
 *
 * Advanced humanization using GPT-4o with custom prompts.
 *
 * @since 2.0.0
 */
class GPT4o_Humanizer {

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
    private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

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
        $api_key = $this->ai_settings->get_api_key('openai', $campaign_id);
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'OpenAI API key not configured');
        }

        $this->logger->debug('Humanizing with GPT-4o', [
            'level' => $level,
            'length' => strlen($content),
        ]);

        $prompt = $this->build_humanization_prompt($content, $level);

        $response = wp_remote_post($this->api_endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->get_system_prompt($level),
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => $this->get_temperature($level),
                'top_p' => 0.95,
                'frequency_penalty' => $this->get_frequency_penalty($level),
                'presence_penalty' => $this->get_presence_penalty($level),
            ]),
            'timeout' => 120,
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('GPT-4o request failed', [
                'error' => $response->get_error_message(),
            ]);
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            $this->logger->error('GPT-4o error', [
                'error' => $body['error']['message'],
            ]);
            return new WP_Error('api_error', $body['error']['message']);
        }

        if (!isset($body['choices'][0]['message']['content'])) {
            return new WP_Error('invalid_response', 'Invalid response from OpenAI');
        }

        $humanized = trim($body['choices'][0]['message']['content']);

        $this->logger->info('Content humanized with GPT-4o', [
            'original_length' => strlen($content),
            'humanized_length' => strlen($humanized),
            'tokens_used' => $body['usage']['total_tokens'] ?? 0,
        ]);

        return $humanized;
    }

    /**
     * Build humanization prompt
     *
     * @since 2.0.0
     * @param string $content Content.
     * @param int $level Level.
     * @return string Prompt.
     */
    private function build_humanization_prompt($content, $level) {
        $instructions = $this->get_level_instructions($level);
        
        return <<<PROMPT
Humanize the following content to make it sound natural and undetectable as AI-generated.

{$instructions}

CONTENT TO HUMANIZE:
{$content}

HUMANIZED VERSION:
PROMPT;
    }

    /**
     * Get system prompt
     *
     * @since 2.0.0
     * @param int $level Level.
     * @return string System prompt.
     */
    private function get_system_prompt($level) {
        $base = "You are an expert content humanizer. Your task is to rewrite AI-generated content to make it indistinguishable from human-written text.";

        $rules = [
            "- Vary sentence structure and length naturally",
            "- Use conversational language where appropriate",
            "- Include transitional phrases",
            "- Add subtle imperfections that humans make",
            "- Maintain the original meaning and key information",
            "- Preserve factual accuracy",
        ];

        if ($level >= 5) {
            $rules[] = "- Add personal touches and opinions";
            $rules[] = "- Use idiomatic expressions";
        }

        if ($level >= 7) {
            $rules[] = "- Include rhetorical questions";
            $rules[] = "- Add anecdotal elements";
            $rules[] = "- Vary tone throughout";
        }

        if ($level >= 9) {
            $rules[] = "- Include intentional minor grammatical variations";
            $rules[] = "- Add colloquialisms";
            $rules[] = "- Use creative metaphors";
        }

        return $base . "\n\nRules:\n" . implode("\n", $rules);
    }

    /**
     * Get level-specific instructions
     *
     * @since 2.0.0
     * @param int $level Level.
     * @return string Instructions.
     */
    private function get_level_instructions($level) {
        $instructions = [
            1 => "Light touch: Only fix obvious AI patterns. Keep changes minimal.",
            2 => "Minor adjustments: Improve flow and add natural transitions.",
            3 => "Light humanization: Add some variety in sentence structure.",
            4 => "Moderate: Include conversational elements and varied phrasing.",
            5 => "Balanced: Mix formal and informal tones naturally.",
            6 => "Enhanced: Add personal touches and relatable examples.",
            7 => "Strong: Include opinions, questions, and engaging elements.",
            8 => "Very strong: Add personality, humor, and creative flair.",
            9 => "Maximum: Transform into highly personal, opinionated content.",
            10 => "Ultra: Complete rewrite with maximum human-like qualities.",
        ];

        return $instructions[$level] ?? $instructions[5];
    }

    /**
     * Get temperature for level
     *
     * @since 2.0.0
     * @param int $level Level.
     * @return float Temperature.
     */
    private function get_temperature($level) {
        // Map level to temperature (0.3 to 1.0)
        return 0.3 + ($level * 0.07);
    }

    /**
     * Get frequency penalty
     *
     * @since 2.0.0
     * @param int $level Level.
     * @return float Frequency penalty.
     */
    private function get_frequency_penalty($level) {
        // Higher levels = more penalty to avoid repetition
        return min(2.0, $level * 0.15);
    }

    /**
     * Get presence penalty
     *
     * @since 2.0.0
     * @param int $level Level.
     * @return float Presence penalty.
     */
    private function get_presence_penalty($level) {
        // Higher levels = more diverse topics
        return min(2.0, $level * 0.12);
    }

    /**
     * Humanize in chunks
     *
     * For very long content, split into chunks and humanize separately.
     *
     * @since 2.0.0
     * @param string $content Content.
     * @param int $level Level.
     * @param int $campaign_id Campaign ID.
     * @return string|WP_Error Humanized content or error.
     */
    public function humanize_long_content($content, $level = 5, $campaign_id = 0) {
        $max_chunk_size = 3000; // characters
        
        if (strlen($content) <= $max_chunk_size) {
            return $this->humanize($content, $level, $campaign_id);
        }

        $this->logger->info('Humanizing long content in chunks', [
            'total_length' => strlen($content),
            'chunk_size' => $max_chunk_size,
        ]);

        // Split by paragraphs
        $paragraphs = explode("\n\n", $content);
        $chunks = [];
        $current_chunk = '';

        foreach ($paragraphs as $para) {
            if (strlen($current_chunk . $para) > $max_chunk_size && !empty($current_chunk)) {
                $chunks[] = $current_chunk;
                $current_chunk = $para;
            } else {
                $current_chunk .= ($current_chunk ? "\n\n" : '') . $para;
            }
        }

        if (!empty($current_chunk)) {
            $chunks[] = $current_chunk;
        }

        // Humanize each chunk
        $humanized_chunks = [];
        foreach ($chunks as $index => $chunk) {
            $this->logger->debug("Humanizing chunk " . ($index + 1) . "/" . count($chunks));
            
            $humanized = $this->humanize($chunk, $level, $campaign_id);
            
            if (is_wp_error($humanized)) {
                return $humanized;
            }

            $humanized_chunks[] = $humanized;
        }

        return implode("\n\n", $humanized_chunks);
    }

    /**
     * Check if API is available
     *
     * @since 2.0.0
     * @return bool True if available.
     */
    public function is_available() {
        $api_key = get_option('abc_openai_api_key', '');
        return !empty($api_key);
    }

    /**
     * Test humanization
     *
     * @since 2.0.0
     * @return array|WP_Error Test result or error.
     */
    public function test() {
        $test_content = "Artificial intelligence has revolutionized content creation. It enables automated generation of high-quality articles. This technology is increasingly being adopted by businesses worldwide.";
        
        $result = $this->humanize($test_content, 5);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'success' => true,
            'original' => $test_content,
            'humanized' => $result,
            'original_length' => strlen($test_content),
            'humanized_length' => strlen($result),
        ];
    }

    /**
     * Detect AI-generated content
     *
     * Analyze content for AI patterns (for testing).
     *
     * @since 2.0.0
     * @param string $content Content to analyze.
     * @return array Detection results.
     */
    public function detect_ai_patterns($content) {
        $patterns = [
            'repetitive_structure' => preg_match_all('/(\w+ly,\s+\w+ly,\s+\w+ly)/', $content),
            'generic_transitions' => preg_match_all('/\b(Moreover|Furthermore|Additionally|In conclusion)\b/', $content),
            'lack_of_contractions' => (substr_count($content, ' ') > 50) && (substr_count($content, "'") < 3),
            'perfect_grammar' => strlen($content) > 500 && !preg_match('/[,;]\s*[,;]/', $content),
        ];

        $score = 0;
        foreach ($patterns as $pattern => $count) {
            if ($count || $count === true) {
                $score++;
            }
        }

        return [
            'ai_score' => min(100, $score * 25),
            'patterns_detected' => array_filter($patterns),
            'likely_ai' => $score >= 2,
        ];
    }
}
