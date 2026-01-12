<?php
/**
 * Rank Math SEO Integration
 *
 * Integrates with Rank Math SEO plugin.
 *
 * @package AutoBlogCraft\SEO
 * @since 2.0.0
 */

namespace AutoBlogCraft\Modules\SEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rank Math Integration class
 *
 * @since 2.0.0
 */
class Rank_Math_Integration {

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
            update_post_meta($post_id, 'rank_math_description', sanitize_text_field($seo_data['meta_description']));
        }

        // Focus keyword
        if (isset($seo_data['focus_keyword'])) {
            update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($seo_data['focus_keyword']));
        }

        // SEO title
        if (isset($seo_data['seo_title'])) {
            update_post_meta($post_id, 'rank_math_title', sanitize_text_field($seo_data['seo_title']));
        }

        // Canonical URL
        if (isset($seo_data['canonical_url'])) {
            update_post_meta($post_id, 'rank_math_canonical_url', esc_url_raw($seo_data['canonical_url']));
        }

        // OpenGraph data
        if (isset($seo_data['og_data'])) {
            if (isset($seo_data['og_data']['og_title'])) {
                update_post_meta($post_id, 'rank_math_facebook_title', sanitize_text_field($seo_data['og_data']['og_title']));
            }
            if (isset($seo_data['og_data']['og_description'])) {
                update_post_meta($post_id, 'rank_math_facebook_description', sanitize_text_field($seo_data['og_data']['og_description']));
            }
        }

        // Twitter Card data
        if (isset($seo_data['twitter_data'])) {
            if (isset($seo_data['twitter_data']['twitter_title'])) {
                update_post_meta($post_id, 'rank_math_twitter_title', sanitize_text_field($seo_data['twitter_data']['twitter_title']));
            }
            if (isset($seo_data['twitter_data']['twitter_description'])) {
                update_post_meta($post_id, 'rank_math_twitter_description', sanitize_text_field($seo_data['twitter_data']['twitter_description']));
            }
            if (isset($seo_data['twitter_data']['twitter_card'])) {
                update_post_meta($post_id, 'rank_math_twitter_card_type', sanitize_text_field($seo_data['twitter_data']['twitter_card']));
            }
        }

        // Schema type
        if (isset($seo_data['schema_type'])) {
            update_post_meta($post_id, 'rank_math_rich_snippet', sanitize_text_field(strtolower($seo_data['schema_type'])));
        }

        return true;
    }
}
