<?php
/**
 * WPML Integration
 *
 * Integrates with WPML multilingual plugin.
 *
 * @package AutoBlogCraft\Translation
 * @since 2.0.0
 */

namespace AutoBlogCraft\Modules\Translation;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPML Integration class
 *
 * @since 2.0.0
 */
class WPML_Integration {

    /**
     * Get available languages
     *
     * @since 2.0.0
     * @return array Language codes.
     */
    public function get_languages() {
        if (!function_exists('icl_get_languages')) {
            return [];
        }

        $languages = icl_get_languages('skip_missing=0');
        
        if (empty($languages)) {
            return [];
        }

        return array_keys($languages);
    }

    /**
     * Get default language
     *
     * @since 2.0.0
     * @return string Language code.
     */
    public function get_default_language() {
        global $sitepress;

        if (!$sitepress) {
            return get_locale();
        }

        return $sitepress->get_default_language();
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
        global $sitepress;

        if (!$sitepress) {
            return false;
        }

        $post_type = get_post_type($post_id);
        
        $sitepress->set_element_language_details(
            $post_id,
            'post_' . $post_type,
            null,
            $language
        );

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
        if (!function_exists('wpml_get_language_information')) {
            return get_locale();
        }

        $language_info = wpml_get_language_information($post_id);

        if (is_wp_error($language_info) || empty($language_info['language_code'])) {
            return get_locale();
        }

        return $language_info['language_code'];
    }

    /**
     * Link translated posts
     *
     * @since 2.0.0
     * @param array $translations Array of post_id => language_code.
     * @return bool True on success.
     */
    public function link_translations($translations) {
        global $sitepress;

        if (!$sitepress || empty($translations)) {
            return false;
        }

        // Get post type from first post
        $first_post_id = key($translations);
        $post_type = get_post_type($first_post_id);
        $element_type = 'post_' . $post_type;

        // Get or create trid (translation group ID)
        $trid = $sitepress->get_element_trid($first_post_id, $element_type);
        
        if (!$trid) {
            $trid = $sitepress->get_element_trid($first_post_id, $element_type, true);
        }

        // Link all translations to the same trid
        foreach ($translations as $post_id => $language) {
            $sitepress->set_element_language_details(
                $post_id,
                $element_type,
                $trid,
                $language
            );
        }

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
        global $sitepress;

        if (!$sitepress) {
            return [];
        }

        $post_type = get_post_type($post_id);
        $trid = $sitepress->get_element_trid($post_id, 'post_' . $post_type);

        if (!$trid) {
            return [];
        }

        $translations = $sitepress->get_element_translations($trid, 'post_' . $post_type);
        
        $result = [];
        foreach ($translations as $language => $translation) {
            if (isset($translation->element_id)) {
                $result[$language] = $translation->element_id;
            }
        }

        return $result;
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
