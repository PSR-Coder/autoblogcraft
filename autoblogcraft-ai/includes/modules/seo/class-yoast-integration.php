<?php
/**
 * Yoast SEO Integration
 *
 * Integrates with Yoast SEO plugin.
 *
 * @package AutoBlogCraft\SEO
 * @since 2.0.0
 */

namespace AutoBlogCraft\Modules\SEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Yoast SEO Integration class
 *
 * @since 2.0.0
 */
class Yoast_Integration {

    /**
     * Set post SEO data
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param array $seo_data SEO data.
     * @return bool True on success.
     */
    public function set_post_seo($post_id, $seo_data) {
        // Meta description
        if (isset($seo_data['meta_description'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_text_field($seo_data['meta_description']));
        }

        // Focus keyword
        if (isset($seo_data['focus_keyword'])) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($seo_data['focus_keyword']));
        }

        // SEO title
        if (isset($seo_data['seo_title'])) {
            update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($seo_data['seo_title']));
        }

        // Canonical URL
        if (isset($seo_data['canonical_url'])) {
            update_post_meta($post_id, '_yoast_wpseo_canonical', esc_url_raw($seo_data['canonical_url']));
        }

        // OpenGraph data
        if (isset($seo_data['og_data'])) {
            if (isset($seo_data['og_data']['og_title'])) {
                update_post_meta($post_id, '_yoast_wpseo_opengraph-title', sanitize_text_field($seo_data['og_data']['og_title']));
            }
            if (isset($seo_data['og_data']['og_description'])) {
                update_post_meta($post_id, '_yoast_wpseo_opengraph-description', sanitize_text_field($seo_data['og_data']['og_description']));
            }
        }

        // Twitter Card data
        if (isset($seo_data['twitter_data'])) {
            if (isset($seo_data['twitter_data']['twitter_title'])) {
                update_post_meta($post_id, '_yoast_wpseo_twitter-title', sanitize_text_field($seo_data['twitter_data']['twitter_title']));
            }
            if (isset($seo_data['twitter_data']['twitter_description'])) {
                update_post_meta($post_id, '_yoast_wpseo_twitter-description', sanitize_text_field($seo_data['twitter_data']['twitter_description']));
            }
        }

        return true;
    }
}
