<?php
/**
 * Sitemap Generator
 *
 * Generates XML sitemap for campaign-generated content.
 *
 * @package AutoBlogCraft\SEO
 * @since 2.0.0
 */

namespace AutoBlogCraft\Modules\SEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sitemap class
 *
 * Responsibilities:
 * - Generate XML sitemap for campaign posts
 * - Add sitemap to robots.txt
 * - Support sitemap index
 *
 * @since 2.0.0
 */
class Sitemap {

    /**
     * Sitemap slug
     *
     * @var string
     */
    private $slug = 'abc-sitemap.xml';

    /**
     * Items per sitemap
     *
     * @var int
     */
    private $items_per_sitemap = 1000;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        add_action('init', [$this, 'add_rewrite_rules']);
        add_action('template_redirect', [$this, 'handle_request']);
        add_filter('robots_txt', [$this, 'add_to_robots'], 10, 2);
    }

    /**
     * Add rewrite rules
     *
     * @since 2.0.0
     */
    public function add_rewrite_rules() {
        // Main sitemap
        add_rewrite_rule(
            '^abc-sitemap\.xml$',
            'index.php?abc_sitemap=index',
            'top'
        );

        // Paginated sitemaps
        add_rewrite_rule(
            '^abc-sitemap-([0-9]+)\.xml$',
            'index.php?abc_sitemap=$matches[1]',
            'top'
        );

        // Register query var
        add_filter('query_vars', function($vars) {
            $vars[] = 'abc_sitemap';
            return $vars;
        });
    }

    /**
     * Handle sitemap request
     *
     * @since 2.0.0
     */
    public function handle_request() {
        $sitemap = get_query_var('abc_sitemap');

        if (!$sitemap) {
            return;
        }

        if ($sitemap === 'index') {
            $this->output_index();
        } else {
            $page = absint($sitemap);
            $this->output_sitemap($page);
        }

        exit;
    }

    /**
     * Output sitemap index
     *
     * @since 2.0.0
     */
    private function output_index() {
        global $wpdb;

        // Count campaign posts
        $count = $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE pm.meta_key = '_abc_campaign_id'
            AND p.post_status = 'publish'
            AND p.post_type = 'post'"
        );

        $sitemap_count = ceil($count / $this->items_per_sitemap);

        header('Content-Type: application/xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        for ($i = 1; $i <= $sitemap_count; $i++) {
            echo '  <sitemap>' . "\n";
            echo '    <loc>' . esc_url(home_url('/abc-sitemap-' . $i . '.xml')) . '</loc>' . "\n";
            echo '    <lastmod>' . esc_html(current_time('c')) . '</lastmod>' . "\n";
            echo '  </sitemap>' . "\n";
        }

        echo '</sitemapindex>';
    }

    /**
     * Output sitemap page
     *
     * @since 2.0.0
     * @param int $page Sitemap page number.
     */
    private function output_sitemap($page = 1) {
        global $wpdb;

        $offset = ($page - 1) * $this->items_per_sitemap;

        // Get campaign posts
        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT p.ID, p.post_modified_gmt 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE pm.meta_key = '_abc_campaign_id'
            AND p.post_status = 'publish'
            AND p.post_type = 'post'
            ORDER BY p.post_modified_gmt DESC
            LIMIT %d OFFSET %d",
            $this->items_per_sitemap,
            $offset
        ));

        header('Content-Type: application/xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        foreach ($posts as $post) {
            echo '  <url>' . "\n";
            echo '    <loc>' . esc_url(get_permalink($post->ID)) . '</loc>' . "\n";
            echo '    <lastmod>' . esc_html(mysql2date('c', $post->post_modified_gmt, false)) . '</lastmod>' . "\n";
            echo '    <changefreq>monthly</changefreq>' . "\n";
            echo '    <priority>0.8</priority>' . "\n";

            // Add featured image if available
            $thumbnail_id = get_post_thumbnail_id($post->ID);
            if ($thumbnail_id) {
                $image = wp_get_attachment_image_src($thumbnail_id, 'full');
                if ($image) {
                    echo '    <image:image>' . "\n";
                    echo '      <image:loc>' . esc_url($image[0]) . '</image:loc>' . "\n";
                    echo '    </image:image>' . "\n";
                }
            }

            echo '  </url>' . "\n";
        }

        echo '</urlset>';
    }

    /**
     * Add sitemap to robots.txt
     *
     * @since 2.0.0
     * @param string $output Robots.txt output.
     * @param bool $public Whether the site is public.
     * @return string Modified output.
     */
    public function add_to_robots($output, $public) {
        if (!$public) {
            return $output;
        }

        $output .= "\nSitemap: " . home_url('/abc-sitemap.xml') . "\n";

        return $output;
    }

    /**
     * Flush rewrite rules
     *
     * @since 2.0.0
     */
    public function flush_rules() {
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }

    /**
     * Get sitemap URL
     *
     * @since 2.0.0
     * @return string Sitemap URL.
     */
    public function get_url() {
        return home_url('/abc-sitemap.xml');
    }

    /**
     * Ping search engines
     *
     * @since 2.0.0
     */
    public function ping_search_engines() {
        $sitemap_url = urlencode($this->get_url());

        // Ping Google
        wp_remote_get('https://www.google.com/ping?sitemap=' . $sitemap_url, [
            'timeout' => 5,
            'blocking' => false,
        ]);

        // Ping Bing
        wp_remote_get('https://www.bing.com/ping?sitemap=' . $sitemap_url, [
            'timeout' => 5,
            'blocking' => false,
        ]);
    }
}
