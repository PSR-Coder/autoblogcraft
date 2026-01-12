<?php
/**
 * Translation Module
 *
 * Orchestrates all translation functionality including multi-language support,
 * caching, and language detection.
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
 * Translation Module class
 *
 * Main controller for translation features.
 *
 * @since 2.0.0
 */
class Translation_Module {

    /**
     * Instance of this class
     *
     * @var Translation_Module
     */
    private static $instance = null;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Translator instance
     *
     * @var Translator
     */
    private $translator;

    /**
     * Translation cache instance
     *
     * @var Translation_Cache
     */
    private $cache;

    /**
     * Language detector instance
     *
     * @var Language_Detector
     */
    private $detector;

    /**
     * Language switcher instance
     *
     * @var Language_Switcher
     */
    private $switcher;

    /**
     * Get instance
     *
     * @since 2.0.0
     * @return Translation_Module
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    private function __construct() {
        $this->logger = Logger::instance();
        $this->translator = new Translator();
        $this->cache = new Translation_Cache();
        $this->detector = new Language_Detector();
        $this->switcher = new Language_Switcher();
    }

    /**
     * Initialize translation module
     *
     * @since 2.0.0
     */
    public function init() {
        // Register language switcher widget
        add_action('widgets_init', [$this->switcher, 'register_widget']);

        // Register language switcher shortcode
        add_shortcode('abc_language_switcher', [$this->switcher, 'render']);

        // Add language meta to posts
        add_action('add_meta_boxes', [$this, 'add_language_meta_box']);
        add_action('save_post', [$this, 'save_language_meta']);

        // Add hreflang tags
        add_action('wp_head', [$this, 'output_hreflang_tags']);

        // Cleanup expired cache daily
        if (!wp_next_scheduled('abc_cleanup_translation_cache')) {
            wp_schedule_event(time(), 'daily', 'abc_cleanup_translation_cache');
        }
        add_action('abc_cleanup_translation_cache', [$this->cache, 'cleanup_expired']);

        $this->logger->info('Translation module initialized');
    }

    /**
     * Check if translation is enabled
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return bool True if enabled.
     */
    public function is_enabled($campaign_id) {
        $enabled = get_post_meta($campaign_id, '_translation_enabled', true);
        return (bool) $enabled;
    }

    /**
     * Translate content
     *
     * @since 2.0.0
     * @param string $text Text to translate.
     * @param string $target_language Target language code.
     * @param string $source_language Source language code (optional).
     * @param int $campaign_id Campaign ID (optional).
     * @return string|WP_Error Translated text or error.
     */
    public function translate($text, $target_language, $source_language = '', $campaign_id = 0) {
        // Auto-detect source language if not provided
        if (empty($source_language)) {
            $source_language = $this->detector->detect($text);
            if (is_wp_error($source_language)) {
                $source_language = 'en'; // Default to English
            }
        }

        // Check if same language
        if ($source_language === $target_language) {
            return $text;
        }

        // Try cache first
        $cached = $this->cache->get($text, $source_language, $target_language);
        if ($cached !== null) {
            $this->logger->debug('Translation cache hit', [
                'from' => $source_language,
                'to' => $target_language,
            ]);
            return $cached;
        }

        // Translate
        $translated = $this->translator->translate($text, $source_language, $target_language, $campaign_id);

        if (is_wp_error($translated)) {
            $this->logger->error('Translation failed', [
                'error' => $translated->get_error_message(),
                'from' => $source_language,
                'to' => $target_language,
            ]);
            return $translated;
        }

        // Cache result
        $this->cache->set($text, $source_language, $target_language, $translated);

        return $translated;
    }

    /**
     * Batch translate multiple texts
     *
     * @since 2.0.0
     * @param array $texts Array of texts to translate.
     * @param string $target_language Target language code.
     * @param string $source_language Source language code (optional).
     * @param int $campaign_id Campaign ID (optional).
     * @return array|WP_Error Array of translations or error.
     */
    public function batch_translate($texts, $target_language, $source_language = '', $campaign_id = 0) {
        if (empty($texts)) {
            return [];
        }

        // Filter texts that need translation
        $to_translate = [];
        $translations = [];

        foreach ($texts as $index => $text) {
            // Check cache
            $cached = $this->cache->get($text, $source_language, $target_language);
            if ($cached !== null) {
                $translations[$index] = $cached;
            } else {
                $to_translate[$index] = $text;
            }
        }

        // Batch translate remaining texts
        if (!empty($to_translate)) {
            $batch_results = $this->translator->batch_translate(
                array_values($to_translate),
                $source_language,
                $target_language,
                $campaign_id
            );

            if (is_wp_error($batch_results)) {
                return $batch_results;
            }

            // Merge results and cache
            $result_index = 0;
            foreach ($to_translate as $index => $text) {
                if (isset($batch_results[$result_index])) {
                    $translated = $batch_results[$result_index];
                    $translations[$index] = $translated;
                    
                    // Cache
                    $this->cache->set($text, $source_language, $target_language, $translated);
                }
                $result_index++;
            }
        }

        // Return in original order
        ksort($translations);
        return array_values($translations);
    }

    /**
     * Get translation statistics
     *
     * @since 2.0.0
     * @return array Statistics.
     */
    public function get_stats() {
        return [
            'cache_size' => $this->cache->get_size(),
            'cache_hits' => $this->cache->get_hit_count(),
            'cache_misses' => $this->cache->get_miss_count(),
            'supported_languages' => $this->translator->get_supported_languages(),
        ];
    }

    /**
     * Add language meta box
     *
     * @since 2.0.0
     */
    public function add_language_meta_box() {
        add_meta_box(
            'abc_language_meta',
            __('Language & Translation', 'autoblogcraft'),
            [$this, 'render_language_meta_box'],
            'post',
            'side',
            'default'
        );
    }

    /**
     * Render language meta box
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     */
    public function render_language_meta_box($post) {
        $language = get_post_meta($post->ID, '_abc_language', true);
        $original_id = get_post_meta($post->ID, '_abc_original_post_id', true);
        $translations = get_post_meta($post->ID, '_abc_translations', true);

        wp_nonce_field('abc_language_meta', 'abc_language_meta_nonce');
        ?>
        <p>
            <label for="abc_language"><?php esc_html_e('Post Language:', 'autoblogcraft'); ?></label>
            <select name="abc_language" id="abc_language" class="widefat">
                <option value=""><?php esc_html_e('Auto-detect', 'autoblogcraft'); ?></option>
                <?php foreach ($this->translator->get_supported_languages() as $code => $name) : ?>
                    <option value="<?php echo esc_attr($code); ?>" <?php selected($language, $code); ?>>
                        <?php echo esc_html($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <?php if ($original_id) : ?>
            <p>
                <strong><?php esc_html_e('Translation of:', 'autoblogcraft'); ?></strong>
                <a href="<?php echo esc_url(get_edit_post_link($original_id)); ?>">
                    <?php echo esc_html(get_the_title($original_id)); ?>
                </a>
            </p>
        <?php endif; ?>

        <?php if (!empty($translations) && is_array($translations)) : ?>
            <p>
                <strong><?php esc_html_e('Available Translations:', 'autoblogcraft'); ?></strong>
            </p>
            <ul>
                <?php foreach ($translations as $lang_code => $post_id) : ?>
                    <li>
                        <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>">
                            <?php echo esc_html($this->get_language_name($lang_code)); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php
    }

    /**
     * Save language meta
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     */
    public function save_language_meta($post_id) {
        // Check nonce
        if (!isset($_POST['abc_language_meta_nonce']) || 
            !wp_verify_nonce($_POST['abc_language_meta_nonce'], 'abc_language_meta')) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save language
        if (isset($_POST['abc_language'])) {
            $language = sanitize_text_field($_POST['abc_language']);
            
            if (empty($language)) {
                // Auto-detect
                $post = get_post($post_id);
                $language = $this->detector->detect($post->post_content);
                if (is_wp_error($language)) {
                    $language = 'en';
                }
            }

            update_post_meta($post_id, '_abc_language', $language);
        }
    }

    /**
     * Output hreflang tags
     *
     * @since 2.0.0
     */
    public function output_hreflang_tags() {
        if (!is_single()) {
            return;
        }

        global $post;
        
        $language = get_post_meta($post->ID, '_abc_language', true);
        $translations = get_post_meta($post->ID, '_abc_translations', true);

        if (empty($language)) {
            return;
        }

        // Self reference
        echo sprintf(
            '<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
            esc_attr($language),
            esc_url(get_permalink($post))
        );

        // Translations
        if (!empty($translations) && is_array($translations)) {
            foreach ($translations as $lang_code => $post_id) {
                $url = get_permalink($post_id);
                if ($url) {
                    echo sprintf(
                        '<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
                        esc_attr($lang_code),
                        esc_url($url)
                    );
                }
            }
        }
    }

    /**
     * Get language name
     *
     * @since 2.0.0
     * @param string $code Language code.
     * @return string Language name.
     */
    private function get_language_name($code) {
        $languages = $this->translator->get_supported_languages();
        return $languages[$code] ?? $code;
    }

    /**
     * Clear all translation cache
     *
     * @since 2.0.0
     * @return bool Success.
     */
    public function clear_cache() {
        return $this->cache->clear_all();
    }
}
