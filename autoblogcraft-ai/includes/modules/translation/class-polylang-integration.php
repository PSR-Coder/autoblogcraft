<?php
/**
 * Polylang Integration
 *
 * Integrates with Polylang multilingual plugin.
 *
 * @package AutoBlogCraft\Translation
 * @since 2.0.0
 */

namespace AutoBlogCraft\Modules\Translation;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Polylang Integration class
 *
 * @since 2.0.0
 */
class Polylang_Integration {

    /**
     * Get available languages
     *
     * @since 2.0.0
     * @return array Language codes.
     */
    public function get_languages() {
        if (!function_exists('pll_languages_list')) {
            return [];
        }

        return pll_languages_list();
    }

    /**
     * Get default language
     *
     * @since 2.0.0
     * @return string Language code.
     */
    public function get_default_language() {
        if (!function_exists('pll_default_language')) {
            return get_locale();
        }

        return pll_default_language();
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
        if (!function_exists('pll_set_post_language')) {
            return false;
        }

        pll_set_post_language($post_id, $language);
        return true;
    }

    /**
     * Get post language
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @return string Language code.
     */
    public function get_post_language($post_id) {
        if (!function_exists('pll_get_post_language')) {
            return get_locale();
        }

        $language = pll_get_post_language($post_id);
        return $language ?: get_locale();
    }

    /**
     * Link translated posts
     *
     * @since 2.0.0
     * @param array $translations Array of post_id => language_code.
     * @return bool True on success.
     */
    public function link_translations($translations) {
        if (!function_exists('pll_save_post_translations')) {
            return false;
        }

        // Polylang expects language => post_id format
        $formatted = [];
        foreach ($translations as $post_id => $language) {
            $formatted[$language] = $post_id;
        }

        pll_save_post_translations($formatted);
        return true;
    }

    /**
     * Get post translations
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @return array Array of language_code => post_id.
     */
    public function get_post_translations($post_id) {
        if (!function_exists('pll_get_post_translations')) {
            return [];
        }

        return pll_get_post_translations($post_id);
    }

    /**
     * Get translation URL
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param string $language Language code.
     * @return string|false Translation URL or false.
     */
    public function get_translation_url($post_id, $language) {
        $translations = $this->get_post_translations($post_id);

        if (isset($translations[$language])) {
            return get_permalink($translations[$language]);
        }

        return false;
    }
}
