<?php
/**
 * RSS Discoverer
 *
 * Discovers content from RSS and Atom feeds.
 * Supports RSS 2.0, RSS 1.0, and Atom 1.0 formats.
 *
 * @package AutoBlogCraft\Discovery\Website
 * @since 2.0.0
 */

namespace AutoBlogCraft\Discovery\Website;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * RSS Discoverer class
 *
 * Parses RSS/Atom feeds and extracts article information.
 *
 * @since 2.0.0
 */
class RSS_Discoverer extends Base_Discoverer {

    /**
     * Perform RSS feed discovery
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @param array $source Source configuration.
     * @return array|WP_Error Array with 'items' or error.
     */
    protected function do_discover($campaign, $source) {
        $feed_url = $source['url'];

        if (empty($feed_url)) {
            return new WP_Error('missing_feed_url', 'Feed URL is required');
        }

        // Fetch feed
        $response = $this->fetch_url($feed_url);
        if (is_wp_error($response)) {
            return $response;
        }

        $xml = wp_remote_retrieve_body($response);

        // Parse feed
        $feed = $this->parse_xml($xml);
        if (is_wp_error($feed)) {
            return $feed;
        }

        // Detect feed type and parse
        $items = $this->parse_feed($feed);

        if (empty($items)) {
            $this->logger->warning("No items found in feed: {$feed_url}");
        }

        return [
            'items' => $items,
            'feed_url' => $feed_url,
        ];
    }

    /**
     * Parse feed based on format
     *
     * Auto-detects RSS 2.0, RSS 1.0, or Atom.
     *
     * @since 2.0.0
     * @param \SimpleXMLElement $feed Feed XML.
     * @return array Array of items.
     */
    private function parse_feed($feed) {
        // Register namespaces
        $namespaces = $feed->getNamespaces(true);

        // Detect feed type
        if ($this->is_atom_feed($feed, $namespaces)) {
            return $this->parse_atom_feed($feed, $namespaces);
        } elseif ($this->is_rss_feed($feed)) {
            return $this->parse_rss_feed($feed, $namespaces);
        }

        $this->logger->error('Unknown feed format');
        return [];
    }

    /**
     * Check if feed is Atom
     *
     * @since 2.0.0
     * @param \SimpleXMLElement $feed Feed XML.
     * @param array $namespaces Namespaces.
     * @return bool True if Atom feed.
     */
    private function is_atom_feed($feed, $namespaces) {
        return isset($namespaces['']) && 
               strpos($namespaces[''], 'http://www.w3.org/2005/Atom') !== false;
    }

    /**
     * Check if feed is RSS
     *
     * @since 2.0.0
     * @param \SimpleXMLElement $feed Feed XML.
     * @return bool True if RSS feed.
     */
    private function is_rss_feed($feed) {
        return isset($feed->channel) || isset($feed->item);
    }

    /**
     * Parse RSS 2.0 / RSS 1.0 feed
     *
     * @since 2.0.0
     * @param \SimpleXMLElement $feed Feed XML.
     * @param array $namespaces Namespaces.
     * @return array Array of items.
     */
    private function parse_rss_feed($feed, $namespaces) {
        $items = [];

        // RSS 2.0
        if (isset($feed->channel->item)) {
            $entries = $feed->channel->item;
        }
        // RSS 1.0
        elseif (isset($feed->item)) {
            $entries = $feed->item;
        } else {
            return [];
        }

        foreach ($entries as $entry) {
            $item = $this->parse_rss_item($entry, $namespaces);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Parse RSS item
     *
     * @since 2.0.0
     * @param \SimpleXMLElement $entry RSS item.
     * @param array $namespaces Namespaces.
     * @return array|null Item data or null if invalid.
     */
    private function parse_rss_item($entry, $namespaces) {
        // Title (required)
        $title = (string) $entry->title;
        if (empty($title)) {
            return null;
        }

        // Link (required)
        $link = (string) $entry->link;
        if (empty($link)) {
            return null;
        }

        // Description / Content
        $description = '';
        if (isset($entry->description)) {
            $description = (string) $entry->description;
        }

        // Content:encoded (from content namespace)
        $content = '';
        if (isset($namespaces['content'])) {
            $content_ns = $entry->children($namespaces['content']);
            if (isset($content_ns->encoded)) {
                $content = (string) $content_ns->encoded;
            }
        }

        // Use content if available, otherwise description
        $excerpt = !empty($content) ? $content : $description;
        $excerpt = $this->extract_text($excerpt);
        $excerpt = $this->truncate($excerpt, 500);

        // Published date
        $date = '';
        if (isset($entry->pubDate)) {
            $date = (string) $entry->pubDate;
        } elseif (isset($namespaces['dc'])) {
            $dc = $entry->children($namespaces['dc']);
            if (isset($dc->date)) {
                $date = (string) $dc->date;
            }
        }

        // Author
        $author = '';
        if (isset($entry->author)) {
            $author = (string) $entry->author;
        } elseif (isset($namespaces['dc'])) {
            $dc = $entry->children($namespaces['dc']);
            if (isset($dc->creator)) {
                $author = (string) $dc->creator;
            }
        }

        // Categories
        $categories = [];
        if (isset($entry->category)) {
            foreach ($entry->category as $cat) {
                $categories[] = (string) $cat;
            }
        }

        // Image (media:content or enclosure)
        $image = '';
        if (isset($namespaces['media'])) {
            $media = $entry->children($namespaces['media']);
            if (isset($media->content)) {
                $image = (string) $media->content->attributes()->url;
            } elseif (isset($media->thumbnail)) {
                $image = (string) $media->thumbnail->attributes()->url;
            }
        }
        if (empty($image) && isset($entry->enclosure)) {
            $enclosure = $entry->enclosure->attributes();
            if (isset($enclosure->type) && strpos($enclosure->type, 'image/') === 0) {
                $image = (string) $enclosure->url;
            }
        }

        return [
            'url' => esc_url_raw($link),
            'title' => sanitize_text_field($title),
            'excerpt' => $excerpt,
            'date' => $date,
            'author' => sanitize_text_field($author),
            'image' => esc_url_raw($image),
            'categories' => $categories,
            'raw_data' => [
                'guid' => isset($entry->guid) ? (string) $entry->guid : '',
            ],
        ];
    }

    /**
     * Parse Atom 1.0 feed
     *
     * @since 2.0.0
     * @param \SimpleXMLElement $feed Feed XML.
     * @param array $namespaces Namespaces.
     * @return array Array of items.
     */
    private function parse_atom_feed($feed, $namespaces) {
        $items = [];

        if (!isset($feed->entry)) {
            return [];
        }

        $atom_ns = isset($namespaces['']) ? $namespaces[''] : 'http://www.w3.org/2005/Atom';

        foreach ($feed->entry as $entry) {
            $item = $this->parse_atom_entry($entry, $atom_ns, $namespaces);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Parse Atom entry
     *
     * @since 2.0.0
     * @param \SimpleXMLElement $entry Atom entry.
     * @param string $atom_ns Atom namespace.
     * @param array $namespaces All namespaces.
     * @return array|null Item data or null if invalid.
     */
    private function parse_atom_entry($entry, $atom_ns, $namespaces) {
        $atom = $entry->children($atom_ns);

        // Title (required)
        $title = (string) $atom->title;
        if (empty($title)) {
            return null;
        }

        // Link (required)
        $link = '';
        if (isset($atom->link)) {
            foreach ($atom->link as $link_el) {
                $attrs = $link_el->attributes();
                if (!isset($attrs->rel) || $attrs->rel == 'alternate') {
                    $link = (string) $attrs->href;
                    break;
                }
            }
        }
        if (empty($link)) {
            return null;
        }

        // Summary / Content
        $content = '';
        if (isset($atom->content)) {
            $content = (string) $atom->content;
        } elseif (isset($atom->summary)) {
            $content = (string) $atom->summary;
        }

        $excerpt = $this->extract_text($content);
        $excerpt = $this->truncate($excerpt, 500);

        // Published/Updated date
        $date = '';
        if (isset($atom->published)) {
            $date = (string) $atom->published;
        } elseif (isset($atom->updated)) {
            $date = (string) $atom->updated;
        }

        // Author
        $author = '';
        if (isset($atom->author->name)) {
            $author = (string) $atom->author->name;
        }

        // Categories
        $categories = [];
        if (isset($atom->category)) {
            foreach ($atom->category as $cat) {
                $attrs = $cat->attributes();
                if (isset($attrs->term)) {
                    $categories[] = (string) $attrs->term;
                }
            }
        }

        // Image (media:content)
        $image = '';
        if (isset($namespaces['media'])) {
            $media = $entry->children($namespaces['media']);
            if (isset($media->content)) {
                $image = (string) $media->content->attributes()->url;
            } elseif (isset($media->thumbnail)) {
                $image = (string) $media->thumbnail->attributes()->url;
            }
        }

        return [
            'url' => esc_url_raw($link),
            'title' => sanitize_text_field($title),
            'excerpt' => $excerpt,
            'date' => $date,
            'author' => sanitize_text_field($author),
            'image' => esc_url_raw($image),
            'categories' => $categories,
            'raw_data' => [
                'id' => isset($atom->id) ? (string) $atom->id : '',
            ],
        ];
    }

    /**
     * Get source type identifier
     *
     * @since 2.0.0
     * @return string Source type.
     */
    protected function get_source_type() {
        return 'rss';
    }

    /**
     * Validate campaign configuration
     *
     * Ensures campaign has required RSS settings.
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
                'no_rss_feeds',
                'No RSS feeds configured for campaign'
            );
        }

        return true;
    }
}
