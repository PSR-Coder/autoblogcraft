<?php
/**
 * Language Detector
 *
 * Automatically detects the language of text content.
 *
 * @package AutoBlogCraft\Modules\Translation
 * @since 2.0.0
 */

namespace AutoBlogCraft\Modules\Translation;

use AutoBlogCraft\Core\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Language Detector class
 *
 * Detects language using pattern matching and API services.
 *
 * @since 2.0.0
 */
class Language_Detector {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Language patterns
     *
     * @var array
     */
    private $patterns = [
        'en' => '/\b(the|is|are|was|were|have|has|had|do|does|did|will|would|can|could|should|may|might)\b/i',
        'es' => '/\b(el|la|los|las|un|una|de|del|y|es|en|por|para|con|que|esto|esta)\b/i',
        'fr' => '/\b(le|la|les|un|une|de|du|et|est|dans|pour|avec|que|ce|cette)\b/i',
        'de' => '/\b(der|die|das|den|dem|ein|eine|und|ist|in|zu|mit|von|für|auf)\b/i',
        'it' => '/\b(il|la|i|le|un|una|di|da|e|è|in|per|con|che|questo|questa)\b/i',
        'pt' => '/\b(o|a|os|as|um|uma|de|da|e|é|em|por|para|com|que|isto|esta)\b/i',
        'ru' => '/[а-яА-ЯёЁ]/u',
        'ja' => '/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FFF}]/u',
        'ko' => '/[\x{AC00}-\x{D7AF}\x{1100}-\x{11FF}\x{3130}-\x{318F}]/u',
        'zh' => '/[\x{4E00}-\x{9FFF}]/u',
        'ar' => '/[\x{0600}-\x{06FF}]/u',
    ];

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->logger = Logger::instance();
    }

    /**
     * Detect language
     *
     * @since 2.0.0
     * @param string $text Text to analyze.
     * @param bool $use_api Whether to use API for detection.
     * @return string|WP_Error Detected language code or error.
     */
    public function detect($text, $use_api = false) {
        // Clean text
        $text = wp_strip_all_tags($text);
        $text = substr($text, 0, 1000); // Analyze first 1000 chars

        if (empty($text)) {
            return new WP_Error('empty_text', 'Cannot detect language of empty text');
        }

        // Try pattern matching first
        $detected = $this->detect_by_pattern($text);
        
        if ($detected) {
            $this->logger->debug("Language detected by pattern: {$detected}");
            return $detected;
        }

        // Fall back to API if enabled
        if ($use_api) {
            return $this->detect_by_api($text);
        }

        // Default to English
        return 'en';
    }

    /**
     * Detect language by pattern matching
     *
     * @since 2.0.0
     * @param string $text Text to analyze.
     * @return string|null Language code or null.
     */
    private function detect_by_pattern($text) {
        $scores = [];

        foreach ($this->patterns as $lang => $pattern) {
            preg_match_all($pattern, $text, $matches);
            $scores[$lang] = count($matches[0]);
        }

        // Get language with highest score
        arsort($scores);
        $top_lang = key($scores);
        $top_score = $scores[$top_lang];

        // Return if score is significant (at least 3 matches)
        if ($top_score >= 3) {
            return $top_lang;
        }

        return null;
    }

    /**
     * Detect language using API
     *
     * @since 2.0.0
     * @param string $text Text to analyze.
     * @return string|WP_Error Language code or error.
     */
    private function detect_by_api($text) {
        $api_key = get_option('abc_google_translate_api_key');
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'Google Translate API key not configured');
        }

        $url = add_query_arg([
            'key' => $api_key,
            'q' => rawurlencode($text),
        ], 'https://translation.googleapis.com/language/translate/v2/detect');

        $response = wp_remote_get($url, ['timeout' => 10]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('google_error', $body['error']['message']);
        }

        if (!isset($body['data']['detections'][0][0]['language'])) {
            return new WP_Error('invalid_response', 'Invalid response from Google Translate');
        }

        $language = $body['data']['detections'][0][0]['language'];
        $confidence = $body['data']['detections'][0][0]['confidence'] ?? 0;

        $this->logger->debug("Language detected by API: {$language} (confidence: {$confidence})");

        return $language;
    }

    /**
     * Detect multiple languages in text
     *
     * @since 2.0.0
     * @param string $text Text to analyze.
     * @return array Array of detected languages with scores.
     */
    public function detect_multiple($text) {
        $text = wp_strip_all_tags($text);
        $scores = [];

        foreach ($this->patterns as $lang => $pattern) {
            preg_match_all($pattern, $text, $matches);
            $count = count($matches[0]);
            
            if ($count > 0) {
                $scores[$lang] = $count;
            }
        }

        arsort($scores);
        return $scores;
    }

    /**
     * Check if text is in specific language
     *
     * @since 2.0.0
     * @param string $text Text to check.
     * @param string $language Language code.
     * @param int $min_matches Minimum pattern matches required.
     * @return bool True if language matches.
     */
    public function is_language($text, $language, $min_matches = 3) {
        if (!isset($this->patterns[$language])) {
            return false;
        }

        $text = wp_strip_all_tags($text);
        preg_match_all($this->patterns[$language], $text, $matches);
        
        return count($matches[0]) >= $min_matches;
    }

    /**
     * Get confidence score for language detection
     *
     * @since 2.0.0
     * @param string $text Text to analyze.
     * @param string $language Language code.
     * @return float Confidence score (0-1).
     */
    public function get_confidence($text, $language) {
        if (!isset($this->patterns[$language])) {
            return 0;
        }

        $text = wp_strip_all_tags($text);
        $words = str_word_count($text, 1);
        $total_words = count($words);

        if ($total_words === 0) {
            return 0;
        }

        preg_match_all($this->patterns[$language], $text, $matches);
        $matches_count = count($matches[0]);

        return min(1, $matches_count / $total_words);
    }

    /**
     * Detect language from URL
     *
     * @since 2.0.0
     * @param string $url URL to analyze.
     * @return string|null Language code or null.
     */
    public function detect_from_url($url) {
        // Check for language code in URL
        // e.g., example.com/en/, example.com/es/page
        if (preg_match('#/([a-z]{2})/#i', $url, $matches)) {
            $lang = strtolower($matches[1]);
            
            if (isset($this->patterns[$lang])) {
                return $lang;
            }
        }

        // Check for subdomain
        // e.g., en.example.com, es.example.com
        if (preg_match('#^([a-z]{2})\.#i', parse_url($url, PHP_URL_HOST), $matches)) {
            $lang = strtolower($matches[1]);
            
            if (isset($this->patterns[$lang])) {
                return $lang;
            }
        }

        return null;
    }

    /**
     * Get supported languages
     *
     * @since 2.0.0
     * @return array Language codes.
     */
    public function get_supported_languages() {
        return array_keys($this->patterns);
    }

    /**
     * Add custom language pattern
     *
     * @since 2.0.0
     * @param string $lang_code Language code.
     * @param string $pattern Regex pattern.
     * @return bool Success.
     */
    public function add_pattern($lang_code, $pattern) {
        if (empty($lang_code) || empty($pattern)) {
            return false;
        }

        $this->patterns[$lang_code] = $pattern;
        return true;
    }
}
