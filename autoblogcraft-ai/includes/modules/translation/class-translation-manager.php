<?php
/**
 * Translation Manager
 *
 * Manages translation integration with multilingual WordPress plugins.
 *
 * @package AutoBlogCraft\Modules\Translation
 * @since 2.0.0
 */

namespace AutoBlogCraft\Modules\Translation;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\Core\Module_Base;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Translation Manager class
 *
 * Responsibilities:
 * - Detect active translation plugin
 * - Manage post translations
 * - Coordinate language relationships
 * - Generate hreflang tags
 *
 * @since 2.0.0
 */
class Translation_Manager extends Module_Base {

    /**
     * Active translation plugin
     *
     * @var string|null
     */
    private $active_plugin = null;

    /**
     * Translation plugin integration instance
     *
     * @var object|null
     */
    private $integration = null;

    /**
     * Hreflang manager instance
     *
     * @var Hreflang_Manager
     */
    private $hreflang;

    /**
     * Get module name
     *
     * @since 2.0.0
     * @return string Module name.
     */
    protected function get_module_name() {
        return 'translation_manager';
    }

    /**
     * Initialize module
     *
     * @since 2.0.0
     * @return void
     */
    protected function init() {
        $this->detect_translation_plugin();
        $this->init_integration();
        $this->hreflang = new Hreflang_Manager();
    }

    /**
     * Detect active translation plugin
     *
     * @since 2.0.0
     * @return string|null Plugin name or null if none detected.
     */
    private function detect_translation_plugin() {
        // Check for Polylang
        if (function_exists('pll_languages_list')) {
            $this->active_plugin = 'polylang';
            return 'polylang';
        }

        // Check for WPML
        if (defined('ICL_SITEPRESS_VERSION')) {
            $this->active_plugin = 'wpml';
            return 'wpml';
        }

        $this->log_info('No translation plugin detected');
        return null;
    }

    /**
     * Initialize translation plugin integration
     *
     * @since 2.0.0
     */
    private function init_integration() {
        if (!$this->active_plugin) {
            return;
        }

        switch ($this->active_plugin) {
            case 'polylang':
                $this->integration = new Polylang_Integration();
                break;

            case 'wpml':
                $this->integration = new WPML_Integration();
                break;
        }

        if ($this->integration) {
            $this->log_info('Translation integration initialized', ['plugin' => $this->active_plugin]);
        }
    }

    /**
     * Get active translation plugin
     *
     * @since 2.0.0
     * @return string|null Plugin name or null.
     */
    public function get_active_plugin() {
        return $this->active_plugin;
    }

    /**
     * Check if multilingual site
     *
     * @since 2.0.0
     * @return bool True if multilingual.
     */
    public function is_multilingual() {
        return !empty($this->active_plugin);
    }

    /**
     * Get available languages
     *
     * @since 2.0.0
     * @return array Language codes.
     */
    public function get_languages() {
        if (!$this->integration) {
            return [get_locale()];
        }

        return $this->integration->get_languages();
    }

    /**
     * Get default language
     *
     * @since 2.0.0
     * @return string Language code.
     */
    public function get_default_language() {
        if (!$this->integration) {
            return get_locale();
        }

        return $this->integration->get_default_language();
    }

    /**
     * Set post language
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param string $language Language code.
     * @return bool True on success.
     */
    public function set_post_language($post_id, $language) {
        if (!$this->integration) {
            return false;
        }

        try {
            return $this->integration->set_post_language($post_id, $language);
        } catch (\Exception $e) {
            $this->logger->error('Failed to set post language', [
                'post_id' => $post_id,
                'language' => $language,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Link translated posts
     *
     * @since 2.0.0
     * @param array $translations Array of post_id => language_code.
     * @return bool True on success.
     */
    public function link_translations($translations) {
        if (!$this->integration) {
            return false;
        }

        try {
            return $this->integration->link_translations($translations);
        } catch (\Exception $e) {
            $this->logger->error('Failed to link translations', [
                'translations' => $translations,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get post translations
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @return array Array of language_code => post_id.
     */
    public function get_post_translations($post_id) {
        if (!$this->integration) {
            return [];
        }

        try {
            return $this->integration->get_post_translations($post_id);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get post translations', [
                'post_id' => $post_id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Translate content using AI
     *
     * @since 2.0.0
     * @param string $content Content to translate.
     * @param string $from_language Source language.
     * @param string $to_language Target language.
     * @return string|false Translated content or false on failure.
     */
    public function translate_content($content, $from_language, $to_language) {
        // This would integrate with the AI Manager to translate content
        // For now, return false - will be implemented when AI translation is added
        $this->logger->info('AI translation requested', [
            'from' => $from_language,
            'to' => $to_language,
            'length' => strlen($content),
        ]);

        return false;
    }

    /**
     * Get hreflang manager
     *
     * @since 2.0.0
     * @return Hreflang_Manager
     */
    public function get_hreflang_manager() {
        return $this->hreflang;
    }

    /**
     * Duplicate post to another language
     *
     * @since 2.0.0
     * @param int $post_id Source post ID.
     * @param string $target_language Target language code.
     * @param bool $translate_content Whether to translate content.
     * @return int|false New post ID or false on failure.
     */
    public function duplicate_to_language($post_id, $target_language, $translate_content = false) {
        $source_post = get_post($post_id);
        
        if (!$source_post) {
            return false;
        }

        // Get source language
        $source_language = $this->get_post_language($post_id);

        // Create duplicate
        $new_post = [
            'post_title' => $source_post->post_title,
            'post_content' => $source_post->post_content,
            'post_excerpt' => $source_post->post_excerpt,
            'post_status' => 'draft', // Start as draft
            'post_type' => $source_post->post_type,
            'post_author' => $source_post->post_author,
        ];

        // Translate if requested
        if ($translate_content) {
            $translated_title = $this->translate_content($source_post->post_title, $source_language, $target_language);
            $translated_content = $this->translate_content($source_post->post_content, $source_language, $target_language);
            
            if ($translated_title) {
                $new_post['post_title'] = $translated_title;
            }
            
            if ($translated_content) {
                $new_post['post_content'] = $translated_content;
            }
        }

        $new_post_id = wp_insert_post($new_post);

        if (is_wp_error($new_post_id)) {
            $this->logger->error('Failed to duplicate post', [
                'post_id' => $post_id,
                'target_language' => $target_language,
                'error' => $new_post_id->get_error_message(),
            ]);
            return false;
        }

        // Set language
        $this->set_post_language($new_post_id, $target_language);

        // Link translations
        $this->link_translations([
            $post_id => $source_language,
            $new_post_id => $target_language,
        ]);

        // Copy post meta
        $this->copy_post_meta($post_id, $new_post_id);

        // Copy featured image
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            set_post_thumbnail($new_post_id, $thumbnail_id);
        }

        $this->logger->info('Post duplicated to language', [
            'source_post_id' => $post_id,
            'new_post_id' => $new_post_id,
            'language' => $target_language,
        ]);

        return $new_post_id;
    }

    /**
     * Get post language
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @return string Language code.
     */
    public function get_post_language($post_id) {
        if (!$this->integration) {
            return get_locale();
        }

        return $this->integration->get_post_language($post_id);
    }

    /**
     * Copy post meta
     *
     * @since 2.0.0
     * @param int $source_id Source post ID.
     * @param int $target_id Target post ID.
     */
    private function copy_post_meta($source_id, $target_id) {
        // Get all meta
        $meta = get_post_meta($source_id);

        // Skip certain meta keys
        $skip_keys = ['_edit_lock', '_edit_last'];

        foreach ($meta as $key => $values) {
            if (in_array($key, $skip_keys)) {
                continue;
            }

            foreach ($values as $value) {
                add_post_meta($target_id, $key, maybe_unserialize($value));
            }
        }
    }
}
