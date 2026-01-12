<?php
/**
 * Hreflang Manager
 *
 * Manages hreflang tags for multilingual SEO.
 *
 * @package AutoBlogCraft\Translation
 * @since 2.0.0
 */

namespace AutoBlogCraft\Modules\Translation;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hreflang Manager class
 *
 * Responsibilities:
 * - Generate hreflang tags for multilingual pages
 * - Support both Polylang and WPML
 * - Add to HTML head
 * - Support sitemap hreflang
 *
 * @since 2.0.0
 */
class Hreflang_Manager {

    /**
     * Translation manager
     *
     * @var Translation_Manager|null
     */
    private $translation_manager = null;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        add_action('wp_head', [$this, 'output_hreflang_tags'], 1);
    }

    /**
     * Set translation manager
     *
     * @since 2.0.0
     * @param Translation_Manager $manager Translation manager instance.
     */
    public function set_translation_manager($manager) {
        $this->translation_manager = $manager;
    }

    /**
     * Get hreflang tags for current page
     *
     * @since 2.0.0
     * @return array Array of hreflang tags.
     */
    public function get_hreflang_tags() {
        if (!is_singular()) {
            return [];
        }

        // Check for translation plugin
        if (!$this->has_translation_plugin()) {
            return [];
        }

        global $post;
        
        if (!$post) {
            return [];
        }

        $tags = [];
        $translations = $this->get_translations($post->ID);

        if (empty($translations)) {
            return [];
        }

        // Add current page
        $current_language = $this->get_current_language();
        $current_url = get_permalink($post->ID);

        if ($current_language && $current_url) {
            $tags[] = [
                'hreflang' => $this->format_hreflang($current_language),
                'href' => $current_url,
            ];
        }

        // Add translations
        foreach ($translations as $language => $translation_id) {
            // Skip current language (already added)
            if ($language === $current_language) {
                continue;
            }

            $url = get_permalink($translation_id);
            
            if ($url) {
                $tags[] = [
                    'hreflang' => $this->format_hreflang($language),
                    'href' => $url,
                ];
            }
        }

        // Add x-default (usually the default language)
        $default_language = $this->get_default_language();
        
        if ($default_language && isset($translations[$default_language])) {
            $tags[] = [
                'hreflang' => 'x-default',
                'href' => get_permalink($translations[$default_language]),
            ];
        }

        return $tags;
    }

    /**
     * Output hreflang tags in HTML head
     *
     * @since 2.0.0
     */
    public function output_hreflang_tags() {
        $tags = $this->get_hreflang_tags();

        if (empty($tags)) {
            return;
        }

        foreach ($tags as $tag) {
            printf(
                '<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
                esc_attr($tag['hreflang']),
                esc_url($tag['href'])
            );
        }
    }

    /**
     * Format language code for hreflang
     *
     * @since 2.0.0
     * @param string $language Language code.
     * @return string Formatted hreflang code.
     */
    private function format_hreflang($language) {
        // Convert to lowercase and replace underscore with hyphen
        $formatted = strtolower(str_replace('_', '-', $language));

        // Handle special cases
        $mappings = [
            'en' => 'en',
            'en-us' => 'en-US',
            'en-gb' => 'en-GB',
            'pt' => 'pt',
            'pt-br' => 'pt-BR',
            'pt-pt' => 'pt-PT',
            'zh' => 'zh',
            'zh-cn' => 'zh-CN',
            'zh-tw' => 'zh-TW',
        ];

        return isset($mappings[$formatted]) ? $mappings[$formatted] : $formatted;
    }

    /**
     * Check if translation plugin is active
     *
     * @since 2.0.0
     * @return bool True if plugin active.
     */
    private function has_translation_plugin() {
        return function_exists('pll_get_post_translations') || 
               function_exists('wpml_get_language_information');
    }

    /**
     * Get post translations
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @return array Translations array.
     */
    private function get_translations($post_id) {
        // Try Polylang
        if (function_exists('pll_get_post_translations')) {
            return pll_get_post_translations($post_id);
        }

        // Try WPML
        if (function_exists('wpml_get_language_information')) {
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

        return [];
    }

    /**
     * Get current language
     *
     * @since 2.0.0
     * @return string|null Language code.
     */
    private function get_current_language() {
        // Try Polylang
        if (function_exists('pll_current_language')) {
            return pll_current_language();
        }

        // Try WPML
        if (defined('ICL_LANGUAGE_CODE')) {
            return ICL_LANGUAGE_CODE;
        }

        return null;
    }

    /**
     * Get default language
     *
     * @since 2.0.0
     * @return string|null Language code.
     */
    private function get_default_language() {
        // Try Polylang
        if (function_exists('pll_default_language')) {
            return pll_default_language();
        }

        // Try WPML
        global $sitepress;
        if ($sitepress) {
            return $sitepress->get_default_language();
        }

        return null;
    }

    /**
     * Get hreflang sitemap
     *
     * @since 2.0.0
     * @param array $post_ids Array of post IDs.
     * @return array Sitemap data with hreflang.
     */
    public function get_sitemap_data($post_ids) {
        if (empty($post_ids)) {
            return [];
        }

        $sitemap_data = [];

        foreach ($post_ids as $post_id) {
            $translations = $this->get_translations($post_id);
            
            if (empty($translations)) {
                continue;
            }

            foreach ($translations as $language => $translation_id) {
                $url = get_permalink($translation_id);
                
                if (!$url) {
                    continue;
                }

                $alternates = [];
                
                // Add all other language versions as alternates
                foreach ($translations as $alt_language => $alt_id) {
                    $alt_url = get_permalink($alt_id);
                    
                    if ($alt_url) {
                        $alternates[] = [
                            'hreflang' => $this->format_hreflang($alt_language),
                            'href' => $alt_url,
                        ];
                    }
                }

                $sitemap_data[] = [
                    'loc' => $url,
                    'alternates' => $alternates,
                ];
            }
        }

        return $sitemap_data;
    }
}
