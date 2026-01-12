<?php
/**
 * Translator
 *
 * Core translation logic with multi-provider support.
 *
 * @package AutoBlogCraft\Modules\Translation
 * @since 2.0.0
 */

namespace AutoBlogCraft\Modules\Translation;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\AI\AI_Settings;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Translator class
 *
 * Handles translation using various providers.
 *
 * @since 2.0.0
 */
class Translator {

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
     * Supported languages
     *
     * @var array
     */
    private $supported_languages = [
        'en' => 'English',
        'es' => 'Spanish',
        'fr' => 'French',
        'de' => 'German',
        'it' => 'Italian',
        'pt' => 'Portuguese',
        'ru' => 'Russian',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'zh' => 'Chinese',
        'ar' => 'Arabic',
        'hi' => 'Hindi',
        'nl' => 'Dutch',
        'sv' => 'Swedish',
        'no' => 'Norwegian',
        'da' => 'Danish',
        'fi' => 'Finnish',
        'pl' => 'Polish',
        'tr' => 'Turkish',
        'el' => 'Greek',
    ];

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
     * Translate text
     *
     * @since 2.0.0
     * @param string $text Text to translate.
     * @param string $source_lang Source language code.
     * @param string $target_lang Target language code.
     * @param int $campaign_id Campaign ID (optional).
     * @return string|WP_Error Translated text or error.
     */
    public function translate($text, $source_lang, $target_lang, $campaign_id = 0) {
        // Validate languages
        if (!$this->is_language_supported($target_lang)) {
            return new WP_Error('unsupported_language', "Target language '{$target_lang}' is not supported");
        }

        // Get translation provider
        $provider = $this->get_translation_provider($campaign_id);

        $this->logger->debug('Translating text', [
            'provider' => $provider,
            'from' => $source_lang,
            'to' => $target_lang,
            'length' => strlen($text),
        ]);

        // Translate based on provider
        switch ($provider) {
            case 'openai':
                return $this->translate_with_openai($text, $source_lang, $target_lang, $campaign_id);

            case 'anthropic':
                return $this->translate_with_anthropic($text, $source_lang, $target_lang, $campaign_id);

            case 'google':
                return $this->translate_with_google($text, $source_lang, $target_lang);

            default:
                return new WP_Error('invalid_provider', "Translation provider '{$provider}' is not valid");
        }
    }

    /**
     * Batch translate multiple texts
     *
     * @since 2.0.0
     * @param array $texts Array of texts to translate.
     * @param string $source_lang Source language code.
     * @param string $target_lang Target language code.
     * @param int $campaign_id Campaign ID (optional).
     * @return array|WP_Error Array of translations or error.
     */
    public function batch_translate($texts, $source_lang, $target_lang, $campaign_id = 0) {
        if (empty($texts)) {
            return [];
        }

        $translations = [];
        $provider = $this->get_translation_provider($campaign_id);

        // Some providers support batch translation
        if ($provider === 'google') {
            return $this->batch_translate_google($texts, $source_lang, $target_lang);
        }

        // Otherwise, translate individually
        foreach ($texts as $text) {
            $translated = $this->translate($text, $source_lang, $target_lang, $campaign_id);
            
            if (is_wp_error($translated)) {
                return $translated;
            }

            $translations[] = $translated;
        }

        return $translations;
    }

    /**
     * Translate with OpenAI
     *
     * @since 2.0.0
     * @param string $text Text to translate.
     * @param string $source_lang Source language.
     * @param string $target_lang Target language.
     * @param int $campaign_id Campaign ID.
     * @return string|WP_Error Translated text or error.
     */
    private function translate_with_openai($text, $source_lang, $target_lang, $campaign_id) {
        $api_key = $this->ai_settings->get_api_key('openai', $campaign_id);
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'OpenAI API key not configured');
        }

        $target_language_name = $this->supported_languages[$target_lang] ?? $target_lang;

        $prompt = "Translate the following text from {$source_lang} to {$target_language_name}. Only provide the translation, no explanations:\n\n{$text}";

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('openai_error', $body['error']['message']);
        }

        if (!isset($body['choices'][0]['message']['content'])) {
            return new WP_Error('invalid_response', 'Invalid response from OpenAI');
        }

        return trim($body['choices'][0]['message']['content']);
    }

    /**
     * Translate with Anthropic
     *
     * @since 2.0.0
     * @param string $text Text to translate.
     * @param string $source_lang Source language.
     * @param string $target_lang Target language.
     * @param int $campaign_id Campaign ID.
     * @return string|WP_Error Translated text or error.
     */
    private function translate_with_anthropic($text, $source_lang, $target_lang, $campaign_id) {
        $api_key = $this->ai_settings->get_api_key('anthropic', $campaign_id);
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'Anthropic API key not configured');
        }

        $target_language_name = $this->supported_languages[$target_lang] ?? $target_lang;

        $prompt = "Translate the following text from {$source_lang} to {$target_language_name}. Only provide the translation, no explanations:\n\n{$text}";

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 4096,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('anthropic_error', $body['error']['message']);
        }

        if (!isset($body['content'][0]['text'])) {
            return new WP_Error('invalid_response', 'Invalid response from Anthropic');
        }

        return trim($body['content'][0]['text']);
    }

    /**
     * Translate with Google Translate
     *
     * @since 2.0.0
     * @param string $text Text to translate.
     * @param string $source_lang Source language.
     * @param string $target_lang Target language.
     * @return string|WP_Error Translated text or error.
     */
    private function translate_with_google($text, $source_lang, $target_lang) {
        $api_key = get_option('abc_google_translate_api_key');
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'Google Translate API key not configured');
        }

        $url = add_query_arg([
            'key' => $api_key,
            'source' => $source_lang,
            'target' => $target_lang,
            'q' => rawurlencode($text),
        ], 'https://translation.googleapis.com/language/translate/v2');

        $response = wp_remote_get($url, ['timeout' => 30]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('google_error', $body['error']['message']);
        }

        if (!isset($body['data']['translations'][0]['translatedText'])) {
            return new WP_Error('invalid_response', 'Invalid response from Google Translate');
        }

        return html_entity_decode($body['data']['translations'][0]['translatedText'], ENT_QUOTES, 'UTF-8');
    }

    /**
     * Batch translate with Google
     *
     * @since 2.0.0
     * @param array $texts Texts to translate.
     * @param string $source_lang Source language.
     * @param string $target_lang Target language.
     * @return array|WP_Error Translations or error.
     */
    private function batch_translate_google($texts, $source_lang, $target_lang) {
        $api_key = get_option('abc_google_translate_api_key');
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'Google Translate API key not configured');
        }

        $response = wp_remote_post('https://translation.googleapis.com/language/translate/v2', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'key' => $api_key,
                'source' => $source_lang,
                'target' => $target_lang,
                'q' => $texts,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('google_error', $body['error']['message']);
        }

        if (!isset($body['data']['translations'])) {
            return new WP_Error('invalid_response', 'Invalid response from Google Translate');
        }

        $translations = [];
        foreach ($body['data']['translations'] as $translation) {
            $translations[] = html_entity_decode($translation['translatedText'], ENT_QUOTES, 'UTF-8');
        }

        return $translations;
    }

    /**
     * Get translation provider
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return string Provider name.
     */
    private function get_translation_provider($campaign_id = 0) {
        if ($campaign_id > 0) {
            $provider = get_post_meta($campaign_id, '_translation_provider', true);
            if (!empty($provider)) {
                return $provider;
            }
        }

        return get_option('abc_translation_provider', 'openai');
    }

    /**
     * Check if language is supported
     *
     * @since 2.0.0
     * @param string $code Language code.
     * @return bool True if supported.
     */
    public function is_language_supported($code) {
        return isset($this->supported_languages[$code]);
    }

    /**
     * Get supported languages
     *
     * @since 2.0.0
     * @return array Supported languages.
     */
    public function get_supported_languages() {
        return $this->supported_languages;
    }
}
