<?php
/**
 * All in One SEO Integration
 *
 * Integrates with All in One SEO plugin.
 *
 * @package AutoBlogCraft\SEO
 * @since 2.0.0
 */

namespace AutoBlogCraft\Modules\SEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AIOSEO Integration class
 *
 * @since 2.0.0
 */
class AIOSEO_Integration {

    /**
     * Set post SEO data
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param array $seo_data SEO data.
     * @return bool True on success.
     */
    public function set_post_seo($post_id, $seo_data) {
        // All in One SEO uses a different approach with aioseo_posts table
        // We'll use post meta as fallback for older versions
        
        // Meta description
        if (isset($seo_data['meta_description'])) {
            update_post_meta($post_id, '_aioseo_description', sanitize_text_field($seo_data['meta_description']));
        }

        // Focus keyword (AIOSEO calls it keyphrases)
        if (isset($seo_data['focus_keyword'])) {
            $keyphrases = [
                'focus' => [
                    'keyphrase' => sanitize_text_field($seo_data['focus_keyword']),
                    'score' => 0,
                ],
            ];
            update_post_meta($post_id, '_aioseo_keyphrases', wp_json_encode($keyphrases));
        }

        // SEO title
        if (isset($seo_data['seo_title'])) {
            update_post_meta($post_id, '_aioseo_title', sanitize_text_field($seo_data['seo_title']));
        }

        // Canonical URL
        if (isset($seo_data['canonical_url'])) {
            update_post_meta($post_id, '_aioseo_canonical_url', esc_url_raw($seo_data['canonical_url']));
        }

        // OpenGraph data
        if (isset($seo_data['og_data'])) {
            if (isset($seo_data['og_data']['og_title'])) {
                update_post_meta($post_id, '_aioseo_og_title', sanitize_text_field($seo_data['og_data']['og_title']));
            }
            if (isset($seo_data['og_data']['og_description'])) {
                update_post_meta($post_id, '_aioseo_og_description', sanitize_text_field($seo_data['og_data']['og_description']));
            }
            if (isset($seo_data['og_data']['og_type'])) {
                update_post_meta($post_id, '_aioseo_og_article_section', sanitize_text_field($seo_data['og_data']['og_type']));
            }
        }

        // Twitter Card data
        if (isset($seo_data['twitter_data'])) {
            if (isset($seo_data['twitter_data']['twitter_title'])) {
                update_post_meta($post_id, '_aioseo_twitter_title', sanitize_text_field($seo_data['twitter_data']['twitter_title']));
            }
            if (isset($seo_data['twitter_data']['twitter_description'])) {
                update_post_meta($post_id, '_aioseo_twitter_description', sanitize_text_field($seo_data['twitter_data']['twitter_description']));
            }
            if (isset($seo_data['twitter_data']['twitter_card'])) {
                update_post_meta($post_id, '_aioseo_twitter_card', sanitize_text_field($seo_data['twitter_data']['twitter_card']));
            }
        }

        // Schema markup
        if (isset($seo_data['schema_type'])) {
            update_post_meta($post_id, '_aioseo_schema_type', sanitize_text_field($seo_data['schema_type']));
        }

        return true;
    }
}
