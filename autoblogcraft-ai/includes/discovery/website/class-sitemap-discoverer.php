<?php
/**
 * Sitemap Discoverer
 *
 * Discovers content from XML sitemaps.
 * Supports standard sitemaps and sitemap indexes.
 *
 * @package AutoBlogCraft\Discovery
 * @since 2.0.0
 */

namespace AutoBlogCraft\Discovery\Website;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sitemap Discoverer class
 *
 * Parses XML sitemaps and extracts URLs with metadata.
 *
 * @since 2.0.0
 */
class Sitemap_Discoverer extends Base_Discoverer {

    /**
     * Maximum depth for sitemap index recursion
     *
     * @var int
     */
    private $max_depth = 3;

    /**
     * Current recursion depth
     *
     * @var int
     */
    private $current_depth = 0;

    /**
     * Perform sitemap discovery
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @param array $source Source configuration.
     * @return array|WP_Error Array with 'items' or error.
     */
    protected function do_discover($campaign, $source) {
        $sitemap_url = $source['url'];

        if (empty($sitemap_url)) {
            return new WP_Error('missing_sitemap_url', 'Sitemap URL is required');
        }

        // Reset depth counter
        $this->current_depth = 0;

        // Parse sitemap (handles indexes recursively)
        $items = $this->parse_sitemap($sitemap_url);

        if (is_wp_error($items)) {
            return $items;
        }

        if (empty($items)) {
            $this->logger->warning("No URLs found in sitemap: {$sitemap_url}");
        }

        return [
            'items' => $items,
            'sitemap_url' => $sitemap_url,
        ];
    }

    /**
     * Parse sitemap
     *
     * Handles both regular sitemaps and sitemap indexes.
     *
     * @since 2.0.0
     * @param string $sitemap_url Sitemap URL.
     * @return array|WP_Error Array of items or error.
     */
    private function parse_sitemap($sitemap_url) {
        // Prevent infinite recursion
        if ($this->current_depth > $this->max_depth) {
            $this->logger->warning("Max sitemap depth reached: {$this->max_depth}");
            return [];
        }

        // Fetch sitemap
        $response = $this->fetch_url($sitemap_url);
        if (is_wp_error($response)) {
            return $response;
        }

        $xml = wp_remote_retrieve_body($response);

        // Parse XML
        $sitemap = $this->parse_xml($xml);
        if (is_wp_error($sitemap)) {
            return $sitemap;
        }

        // Determine sitemap type
        if ($this->is_sitemap_index($sitemap)) {
            return $this->parse_sitemap_index($sitemap);
        } else {
            return $this->parse_url_set($sitemap);
        }
    }

    /**
     * Check if XML is a sitemap index
     *
     * @since 2.0.0
     * @param \SimpleXMLElement $sitemap Sitemap XML.
     * @return bool True if sitemap index.
     */
    private function is_sitemap_index($sitemap) {
        $namespaces = $sitemap->getNamespaces(true);
        
        // Check for sitemapindex tag
        if (isset($sitemap->sitemap)) {
            return true;
        }

        // Check with namespace
        if (isset($namespaces[''])) {
            $ns = $sitemap->children($namespaces['']);
            if (isset($ns->sitemap)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse sitemap index
     *
     * Recursively parses child sitemaps.
     *
     * @since 2.0.0
     * @param \SimpleXMLElement $sitemap Sitemap index XML.
     * @return array Array of items from all child sitemaps.
     */
    private function parse_sitemap_index($sitemap) {
        $this->current_depth++;

        $items = [];
        $namespaces = $sitemap->getNamespaces(true);
        $ns = isset($namespaces['']) ? $namespaces[''] : null;

        // Get sitemap elements
        $sitemaps = [];
        if (isset($sitemap->sitemap)) {
            $sitemaps = $sitemap->sitemap;
        } elseif ($ns && isset($sitemap->children($ns)->sitemap)) {
            $sitemaps = $sitemap->children($ns)->sitemap;
        }

        foreach ($sitemaps as $sitemap_entry) {
            $loc = $ns ? $sitemap_entry->children($ns)->loc : $sitemap_entry->loc;
            $url = (string) $loc;

            if (empty($url)) {
                continue;
            }

            $this->logger->debug("Parsing child sitemap: {$url}");

            // Recursive call
            $child_items = $this->parse_sitemap($url);

            if (is_wp_error($child_items)) {
                $this->logger->warning("Failed to parse child sitemap: {$child_items->get_error_message()}");
                continue;
            }

            $items = array_merge($items, $child_items);
        }

        $this->current_depth--;

        return $items;
    }

    /**
     * Parse URL set (regular sitemap)
     *
     * @since 2.0.0
     * @param \SimpleXMLElement $sitemap Sitemap XML.
     * @return array Array of items.
     */
    private function parse_url_set($sitemap) {
        $items = [];
        $namespaces = $sitemap->getNamespaces(true);
        $ns = isset($namespaces['']) ? $namespaces[''] : null;

        // Get url elements
        $urls = [];
        if (isset($sitemap->url)) {
            $urls = $sitemap->url;
        } elseif ($ns && isset($sitemap->children($ns)->url)) {
            $urls = $sitemap->children($ns)->url;
        }

        foreach ($urls as $url_entry) {
            $item = $this->parse_url_entry($url_entry, $ns, $namespaces);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Parse URL entry
     *
     * @since 2.0.0
     * @param \SimpleXMLElement $entry URL entry.
     * @param string|null $ns Sitemap namespace.
     * @param array $namespaces All namespaces.
     * @return array|null Item data or null if invalid.
     */
    private function parse_url_entry($entry, $ns, $namespaces) {
        // Get elements (with or without namespace)
        $loc = $ns ? $entry->children($ns)->loc : $entry->loc;
        $lastmod = $ns ? $entry->children($ns)->lastmod : $entry->lastmod;
        $priority = $ns ? $entry->children($ns)->priority : $entry->priority;

        // URL (required)
        $url = (string) $loc;
        if (empty($url)) {
            return null;
        }

        // Extract title from URL (fallback)
        $title = $this->extract_title_from_url($url);

        // Last modified date
        $date = '';
        if (isset($lastmod)) {
            $date = (string) $lastmod;
        }

        // Priority (convert to our 0-100 scale)
        $item_priority = 50;
        if (isset($priority)) {
            $item_priority = (float) $priority * 100;
        }

        // Parse news sitemap extension (if present)
        $news_data = $this->parse_news_extension($entry, $namespaces);

        return [
            'url' => esc_url_raw($url),
            'title' => !empty($news_data['title']) ? $news_data['title'] : $title,
            'excerpt' => '',
            'date' => $date,
            'author' => '',
            'image' => !empty($namespaces['image']) ? $this->parse_image_extension($entry, $namespaces) : '',
            'categories' => !empty($news_data['keywords']) ? $news_data['keywords'] : [],
            'raw_data' => [
                'priority' => $item_priority,
                'news' => $news_data,
            ],
        ];
    }

    /**
     * Extract title from URL
     *
     * Uses URL path as fallback title.
     *
     * @since 2.0.0
     * @param string $url URL.
     * @return string Extracted title.
     */
    private function extract_title_from_url($url) {
        $path = parse_url($url, PHP_URL_PATH);
        
        if (empty($path) || $path === '/') {
            return parse_url($url, PHP_URL_HOST);
        }

        // Remove leading/trailing slashes
        $path = trim($path, '/');

        // Get last segment
        $segments = explode('/', $path);
        $last = end($segments);

        // Remove file extension
        $last = preg_replace('/\.(html|htm|php|asp|aspx)$/i', '', $last);

        // Replace separators with spaces
        $title = str_replace(['-', '_'], ' ', $last);

        // Capitalize words
        $title = ucwords($title);

        return $title;
    }

    /**
     * Parse Google News sitemap extension
     *
     * @since 2.0.0
     * @param \SimpleXMLElement $entry URL entry.
     * @param array $namespaces Namespaces.
     * @return array News data.
     */
    private function parse_news_extension($entry, $namespaces) {
        $news_data = [
            'title' => '',
            'keywords' => [],
            'publication_name' => '',
            'publication_language' => '',
        ];

        if (!isset($namespaces['news'])) {
            return $news_data;
        }

        $news = $entry->children($namespaces['news']);

        if (!isset($news->news)) {
            return $news_data;
        }

        $news_node = $news->news;

        // Publication
        if (isset($news_node->publication)) {
            $pub = $news_node->publication;
            if (isset($pub->name)) {
                $news_data['publication_name'] = (string) $pub->name;
            }
            if (isset($pub->language)) {
                $news_data['publication_language'] = (string) $pub->language;
            }
        }

        // Title
        if (isset($news_node->title)) {
            $news_data['title'] = sanitize_text_field((string) $news_node->title);
        }

        // Keywords
        if (isset($news_node->keywords)) {
            $keywords = (string) $news_node->keywords;
            $news_data['keywords'] = array_map('trim', explode(',', $keywords));
        }

        return $news_data;
    }

    /**
     * Parse image extension
     *
     * @since 2.0.0
     * @param \SimpleXMLElement $entry URL entry.
     * @param array $namespaces Namespaces.
     * @return string Image URL.
     */
    private function parse_image_extension($entry, $namespaces) {
        if (!isset($namespaces['image'])) {
            return '';
        }

        $image = $entry->children($namespaces['image']);

        if (!isset($image->image)) {
            return '';
        }

        $image_node = $image->image;

        if (isset($image_node->loc)) {
            return esc_url_raw((string) $image_node->loc);
        }

        return '';
    }

    /**
     * Get source type identifier
     *
     * @since 2.0.0
     * @return string Source type.
     */
    protected function get_source_type() {
        return 'sitemap';
    }

    /**
     * Validate campaign configuration
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @return bool|WP_Error True if valid, error otherwise.
     */
    protected function validate_campaign($campaign) {
        $parent_valid = parent::validate_campaign($campaign);
        if (is_wp_error($parent_valid)) {
            return $parent_valid;
        }

        $sources = $this->get_sources($campaign);
        if (empty($sources)) {
            return new WP_Error(
                'no_sitemaps',
                'No sitemaps configured for campaign'
            );
        }

        return true;
    }
}
