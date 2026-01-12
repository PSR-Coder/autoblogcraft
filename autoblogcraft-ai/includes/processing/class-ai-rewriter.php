<?php
/**
 * AI Rewriter
 *
 * Separate rewriting logic with multiple AI providers, rewriting strategies,
 * tone control, and quality validation.
 *
 * @package AutoBlogCraft\Processing
 * @since 2.0.0
 */

namespace AutoBlogCraft\Processing;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\AI\AI_Manager;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Rewriter class
 *
 * Handles AI-powered content rewriting with sophisticated strategies,
 * tone control, and quality validation.
 *
 * @since 2.0.0
 */
class AI_Rewriter {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * AI manager instance
     *
     * @var AI_Manager
     */
    private $ai_manager;

    /**
     * Rewriting strategies
     *
     * @var array
     */
    private $strategies = [
        'light' => 'Minimal rewriting, preserve original structure and key phrases',
        'medium' => 'Moderate rewriting, maintain core message but improve clarity and flow',
        'heavy' => 'Complete rewrite, preserve facts but transform presentation entirely',
        'creative' => 'Creative transformation with new angles and perspectives',
        'seo' => 'SEO-optimized rewriting with keyword integration',
    ];

    /**
     * Tone options
     *
     * @var array
     */
    private $tones = [
        'professional' => 'Professional and authoritative',
        'casual' => 'Casual and conversational',
        'enthusiastic' => 'Enthusiastic and energetic',
        'informative' => 'Clear and informative',
        'persuasive' => 'Persuasive and compelling',
        'neutral' => 'Neutral and balanced',
    ];

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->logger = Logger::instance();
        $this->ai_manager = AI_Manager::get_instance();
    }

    /**
     * Rewrite content
     *
     * Main entry point for AI rewriting with full configuration.
     *
     * @since 2.0.0
     * @param string $content Original content to rewrite.
     * @param array $settings Rewriting settings.
     * @return string|WP_Error Rewritten content or error.
     */
    public function rewrite($content, $settings = []) {
        // Validate content
        if (empty($content)) {
            return new WP_Error('empty_content', 'Content cannot be empty');
        }

        // Parse settings
        $strategy = !empty($settings['strategy']) ? $settings['strategy'] : 'medium';
        $tone = !empty($settings['tone']) ? $settings['tone'] : 'professional';
        $provider = !empty($settings['provider']) ? $settings['provider'] : null;
        $max_length = !empty($settings['max_length']) ? (int) $settings['max_length'] : null;
        $keywords = !empty($settings['keywords']) ? $settings['keywords'] : [];
        $campaign_id = !empty($settings['campaign_id']) ? $settings['campaign_id'] : null;

        $this->logger->info('Starting AI rewrite', [
            'strategy' => $strategy,
            'tone' => $tone,
            'content_length' => strlen($content),
            'campaign_id' => $campaign_id,
        ]);

        // Build rewriting prompt
        $prompt = $this->build_rewrite_prompt($content, $strategy, $tone, $keywords, $max_length);

        // Get AI response
        $rewritten = $this->ai_manager->generate_text($prompt, [
            'campaign_id' => $campaign_id,
            'provider' => $provider,
            'max_tokens' => $this->calculate_max_tokens($content, $max_length),
            'temperature' => $this->get_temperature_for_strategy($strategy),
        ]);

        if (is_wp_error($rewritten)) {
            $this->logger->error('AI rewrite failed: ' . $rewritten->get_error_message());
            return $rewritten;
        }

        // Post-process rewritten content
        $rewritten = $this->post_process($rewritten, $settings);

        // Validate quality
        $quality_check = $this->validate_quality($content, $rewritten, $settings);
        if (is_wp_error($quality_check)) {
            $this->logger->warning('Quality validation failed: ' . $quality_check->get_error_message());
            
            // Return original if quality is too low and fallback enabled
            if (!empty($settings['fallback_to_original'])) {
                return $content;
            }
        }

        $this->logger->info('AI rewrite completed', [
            'original_length' => strlen($content),
            'rewritten_length' => strlen($rewritten),
            'quality_score' => $quality_check['score'] ?? 0,
        ]);

        return $rewritten;
    }

    /**
     * Build rewriting prompt
     *
     * @since 2.0.0
     * @param string $content Original content.
     * @param string $strategy Rewriting strategy.
     * @param string $tone Desired tone.
     * @param array $keywords Keywords to include.
     * @param int|null $max_length Maximum length.
     * @return string Formatted prompt.
     */
    private function build_rewrite_prompt($content, $strategy, $tone, $keywords = [], $max_length = null) {
        $strategy_desc = $this->strategies[$strategy] ?? $this->strategies['medium'];
        $tone_desc = $this->tones[$tone] ?? $this->tones['professional'];

        $prompt = "You are a professional content rewriter. Your task is to rewrite the following content.\n\n";
        
        $prompt .= "**Rewriting Strategy**: {$strategy_desc}\n";
        $prompt .= "**Tone**: {$tone_desc}\n\n";

        if (!empty($keywords)) {
            $keyword_list = implode(', ', $keywords);
            $prompt .= "**Important Keywords** (integrate naturally): {$keyword_list}\n\n";
        }

        if ($max_length) {
            $prompt .= "**Maximum Length**: Approximately {$max_length} words\n\n";
        }

        $prompt .= "**Requirements**:\n";
        $prompt .= "- Maintain factual accuracy\n";
        $prompt .= "- Keep the core message intact\n";
        $prompt .= "- Use natural language\n";
        $prompt .= "- Avoid plagiarism\n";
        $prompt .= "- Preserve important statistics and quotes\n";
        $prompt .= "- Return ONLY the rewritten content (no meta-commentary)\n\n";

        $prompt .= "**Original Content**:\n\n{$content}\n\n";
        $prompt .= "**Rewritten Content**:";

        return $prompt;
    }

    /**
     * Calculate max tokens based on content length
     *
     * @since 2.0.0
     * @param string $content Original content.
     * @param int|null $max_length Maximum word length.
     * @return int Maximum tokens.
     */
    private function calculate_max_tokens($content, $max_length = null) {
        // Estimate: 1 token ≈ 0.75 words
        if ($max_length) {
            return (int) ($max_length / 0.75) + 500; // Add buffer
        }

        // Default: 150% of original content length
        $word_count = str_word_count($content);
        return (int) (($word_count * 1.5) / 0.75);
    }

    /**
     * Get temperature for strategy
     *
     * @since 2.0.0
     * @param string $strategy Rewriting strategy.
     * @return float Temperature value (0.0-1.0).
     */
    private function get_temperature_for_strategy($strategy) {
        $temperatures = [
            'light' => 0.3,      // Low creativity, stay close to original
            'medium' => 0.5,     // Balanced
            'heavy' => 0.7,      // Higher creativity
            'creative' => 0.9,   // Maximum creativity
            'seo' => 0.4,        // Structured, keyword-focused
        ];

        return $temperatures[$strategy] ?? 0.5;
    }

    /**
     * Post-process rewritten content
     *
     * @since 2.0.0
     * @param string $content Rewritten content.
     * @param array $settings Rewriting settings.
     * @return string Processed content.
     */
    private function post_process($content, $settings = []) {
        // Remove common AI artifacts
        $content = $this->remove_ai_artifacts($content);

        // Fix formatting
        $content = $this->fix_formatting($content);

        // Apply word limit if specified
        if (!empty($settings['max_length'])) {
            $content = $this->enforce_word_limit($content, $settings['max_length']);
        }

        // Preserve HTML if original had it
        if (!empty($settings['preserve_html']) && !empty($settings['original_html'])) {
            $content = wpautop($content);
        }

        return $content;
    }

    /**
     * Remove AI artifacts
     *
     * @since 2.0.0
     * @param string $content Content to clean.
     * @return string Cleaned content.
     */
    private function remove_ai_artifacts($content) {
        // Remove meta-commentary
        $patterns = [
            '/^(Here is|Here\'s|This is|I have|I\'ve) (the )?(rewritten|revised|rewrite|content|article|text).*?:\s*/im',
            '/^Rewritten (content|version|text|article):\s*/im',
            '/\[.*?\]/i', // Remove bracketed notes
            '/^Note:.*$/im', // Remove note lines
        ];

        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }

        return trim($content);
    }

    /**
     * Fix formatting issues
     *
     * @since 2.0.0
     * @param string $content Content to fix.
     * @return string Fixed content.
     */
    private function fix_formatting($content) {
        // Fix multiple spaces
        $content = preg_replace('/[ ]{2,}/', ' ', $content);

        // Fix multiple line breaks
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        // Fix spaces before punctuation
        $content = preg_replace('/\s+([.,;:!?])/', '$1', $content);

        // Fix quotes
        $content = str_replace(['"', '"', ''', '''], ['"', '"', "'", "'"], $content);

        return trim($content);
    }

    /**
     * Enforce word limit
     *
     * @since 2.0.0
     * @param string $content Content to limit.
     * @param int $max_words Maximum number of words.
     * @return string Limited content.
     */
    private function enforce_word_limit($content, $max_words) {
        $words = preg_split('/\s+/', $content);
        
        if (count($words) <= $max_words) {
            return $content;
        }

        // Truncate to word limit
        $truncated = array_slice($words, 0, $max_words);
        $content = implode(' ', $truncated);

        // Try to end at a sentence
        $last_period = strrpos($content, '.');
        if ($last_period !== false && $last_period > ($max_words * 0.8)) {
            $content = substr($content, 0, $last_period + 1);
        }

        return $content;
    }

    /**
     * Validate rewriting quality
     *
     * @since 2.0.0
     * @param string $original Original content.
     * @param string $rewritten Rewritten content.
     * @param array $settings Rewriting settings.
     * @return array|WP_Error Quality score or error.
     */
    private function validate_quality($original, $rewritten, $settings = []) {
        $issues = [];
        $score = 100;

        // Check if content is too similar (potential plagiarism)
        $similarity = $this->calculate_similarity($original, $rewritten);
        if ($similarity > 0.8) {
            $issues[] = 'Content too similar to original (possible plagiarism)';
            $score -= 30;
        }

        // Check if content is too short
        $original_words = str_word_count($original);
        $rewritten_words = str_word_count($rewritten);
        
        if ($rewritten_words < ($original_words * 0.3)) {
            $issues[] = 'Rewritten content is too short';
            $score -= 20;
        }

        // Check if content is excessively long
        if (!empty($settings['max_length']) && $rewritten_words > ($settings['max_length'] * 1.2)) {
            $issues[] = 'Rewritten content exceeds length limit';
            $score -= 15;
        }

        // Check for keyword inclusion
        if (!empty($settings['keywords'])) {
            $missing_keywords = [];
            foreach ($settings['keywords'] as $keyword) {
                if (stripos($rewritten, $keyword) === false) {
                    $missing_keywords[] = $keyword;
                }
            }
            
            if (!empty($missing_keywords)) {
                $issues[] = 'Missing required keywords: ' . implode(', ', $missing_keywords);
                $score -= (count($missing_keywords) * 10);
            }
        }

        // Check for common AI patterns (if enabled)
        if (!empty($settings['check_ai_patterns'])) {
            $ai_pattern_score = $this->detect_ai_patterns($rewritten);
            if ($ai_pattern_score > 0.7) {
                $issues[] = 'High likelihood of AI-generated patterns detected';
                $score -= 25;
            }
        }

        if ($score < 50) {
            return new WP_Error('low_quality', 'Rewritten content quality too low', [
                'score' => $score,
                'issues' => $issues,
            ]);
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues,
            'similarity' => $similarity,
        ];
    }

    /**
     * Calculate similarity between two texts
     *
     * @since 2.0.0
     * @param string $text1 First text.
     * @param string $text2 Second text.
     * @return float Similarity score (0.0-1.0).
     */
    private function calculate_similarity($text1, $text2) {
        // Simple n-gram similarity
        $ngrams1 = $this->get_ngrams($text1, 3);
        $ngrams2 = $this->get_ngrams($text2, 3);

        if (empty($ngrams1) || empty($ngrams2)) {
            return 0;
        }

        $intersection = count(array_intersect($ngrams1, $ngrams2));
        $union = count(array_unique(array_merge($ngrams1, $ngrams2)));

        return $union > 0 ? $intersection / $union : 0;
    }

    /**
     * Get n-grams from text
     *
     * @since 2.0.0
     * @param string $text Text to analyze.
     * @param int $n N-gram size.
     * @return array N-grams.
     */
    private function get_ngrams($text, $n = 3) {
        $text = strtolower(preg_replace('/[^a-z0-9\s]/i', '', $text));
        $words = preg_split('/\s+/', $text);
        $ngrams = [];

        for ($i = 0; $i <= count($words) - $n; $i++) {
            $ngrams[] = implode(' ', array_slice($words, $i, $n));
        }

        return $ngrams;
    }

    /**
     * Detect AI-generated patterns
     *
     * @since 2.0.0
     * @param string $content Content to analyze.
     * @return float AI pattern score (0.0-1.0).
     */
    private function detect_ai_patterns($content) {
        $score = 0;
        $patterns = [
            '/\b(In conclusion|To summarize|In summary|Overall|Furthermore|Moreover|Additionally|Consequently)\b/i',
            '/\b(delve|explore|landscape|realm|navigate|embark)\b/i',
            '/\b(cutting-edge|state-of-the-art|revolutionary|game-changing|paradigm)\b/i',
        ];

        foreach ($patterns as $pattern) {
            $matches = preg_match_all($pattern, $content);
            $score += ($matches / 100); // Adjust scoring
        }

        return min(1.0, $score);
    }

    /**
     * Rewrite with fallback
     *
     * Attempts rewriting with primary provider, falls back to alternative if it fails.
     *
     * @since 2.0.0
     * @param string $content Original content.
     * @param array $settings Rewriting settings.
     * @return string|WP_Error Rewritten content or error.
     */
    public function rewrite_with_fallback($content, $settings = []) {
        $providers = ['openai', 'gemini', 'claude', 'deepseek'];
        
        // Try specified provider first
        if (!empty($settings['provider'])) {
            $result = $this->rewrite($content, $settings);
            
            if (!is_wp_error($result)) {
                return $result;
            }
            
            $this->logger->warning("Primary provider failed: " . $result->get_error_message());
            
            // Remove failed provider from fallback list
            $providers = array_diff($providers, [$settings['provider']]);
        }

        // Try fallback providers
        foreach ($providers as $provider) {
            $settings['provider'] = $provider;
            $result = $this->rewrite($content, $settings);
            
            if (!is_wp_error($result)) {
                $this->logger->info("Fallback provider succeeded: {$provider}");
                return $result;
            }
        }

        return new WP_Error('all_providers_failed', 'All AI providers failed to rewrite content');
    }

    /**
     * Get available strategies
     *
     * @since 2.0.0
     * @return array Available rewriting strategies.
     */
    public function get_strategies() {
        return $this->strategies;
    }

    /**
     * Get available tones
     *
     * @since 2.0.0
     * @return array Available tones.
     */
    public function get_tones() {
        return $this->tones;
    }
}
