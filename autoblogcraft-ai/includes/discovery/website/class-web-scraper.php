<?php
/**
 * Web Discoverer
 *
 * Scrapes web pages to extract content.
 * Uses CSS selectors for targeted content extraction.
 *
 * @package AutoBlogCraft\Discovery
 * @since 2.0.0
 */

namespace AutoBlogCraft\Discovery\Website;

use WP_Error;
use DOMDocument;
use DOMXPath;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Web Discoverer class
 *
 * Scrapes web pages using DOM parsing and CSS selectors.
 *
 * @since 2.0.0
 */
class Web_Discoverer extends Base_Discoverer {

    /**
     * Perform web scraping discovery
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @param array $source Source configuration.
     * @return array|WP_Error Array with 'items' or error.
     */
    protected function do_discover($campaign, $source) {
        $url = $source['url'];

        if (empty($url)) {
            return new WP_Error('missing_url', 'URL is required');
        }

        // Fetch page
        $response = $this->fetch_url($url);
        if (is_wp_error($response)) {
            return $response;
        }

        $html = wp_remote_retrieve_body($response);

        // Parse HTML
        $dom = $this->parse_html($html);
        if (is_wp_error($dom)) {
            return $dom;
        }

        // Extract items based on selectors
        $items = $this->extract_items($dom, $source);

        if (empty($items)) {
            $this->logger->warning("No items found on page: {$url}");
        }

        return [
            'items' => $items,
            'page_url' => $url,
        ];
    }

    /**
     * Parse HTML into DOM
     *
     * @since 2.0.0
     * @param string $html HTML string.
     * @return DOMDocument|WP_Error DOM document or error.
     */
    private function parse_html($html) {
        if (empty($html)) {
            return new WP_Error('empty_html', 'HTML content is empty');
        }

        // Suppress HTML parsing errors
        libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new DOMDocument();
        
        // Load HTML (UTF-8 encoding)
        $success = $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if (!$success) {
            return new WP_Error('html_parse_error', 'Failed to parse HTML');
        }

        return $dom;
    }

    /**
     * Extract items using CSS selectors
     *
     * @since 2.0.0
     * @param DOMDocument $dom DOM document.
     * @param array $source Source configuration with selectors.
     * @return array Array of items.
     */
    private function extract_items($dom, $source) {
        $xpath = new DOMXPath($dom);
        $items = [];

        // Get selectors from source config
        $container_selector = isset($source['container_selector']) ? $source['container_selector'] : '';
        $link_selector = isset($source['link_selector']) ? $source['link_selector'] : 'a';
        $title_selector = isset($source['title_selector']) ? $source['title_selector'] : '';
        $excerpt_selector = isset($source['excerpt_selector']) ? $source['excerpt_selector'] : '';
        $image_selector = isset($source['image_selector']) ? $source['image_selector'] : '';
        $date_selector = isset($source['date_selector']) ? $source['date_selector'] : '';

        // Find container elements
        if (!empty($container_selector)) {
            $xpath_query = $this->css_to_xpath($container_selector);
            $containers = $xpath->query($xpath_query);
        } else {
            // No container - use entire document
            $containers = [$dom->documentElement];
        }

        if ($containers->length === 0) {
            $this->logger->warning("Container not found: {$container_selector}");
            return [];
        }

        // Extract from each container
        foreach ($containers as $container) {
            $item = $this->extract_item_from_container($xpath, $container, [
                'link' => $link_selector,
                'title' => $title_selector,
                'excerpt' => $excerpt_selector,
                'image' => $image_selector,
                'date' => $date_selector,
            ], $source);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Extract item from container element
     *
     * @since 2.0.0
     * @param DOMXPath $xpath XPath instance.
     * @param \DOMElement $container Container element.
     * @param array $selectors CSS selectors.
     * @param array $source Source configuration.
     * @return array|null Item data or null if invalid.
     */
    private function extract_item_from_container($xpath, $container, $selectors, $source) {
        // Extract link (required)
        $link = $this->extract_element_text($xpath, $container, $selectors['link'], 'href');
        
        if (empty($link)) {
            return null;
        }

        // Convert relative URLs to absolute
        $link = $this->make_absolute_url($link, $source['url']);

        // Extract title
        $title = '';
        if (!empty($selectors['title'])) {
            $title = $this->extract_element_text($xpath, $container, $selectors['title']);
        }
        
        // Fallback: use link text
        if (empty($title)) {
            $link_xpath = $this->css_to_xpath($selectors['link']);
            $link_nodes = $xpath->query($link_xpath, $container);
            if ($link_nodes->length > 0) {
                $title = trim($link_nodes->item(0)->textContent);
            }
        }

        if (empty($title)) {
            return null;
        }

        // Extract excerpt
        $excerpt = '';
        if (!empty($selectors['excerpt'])) {
            $excerpt = $this->extract_element_text($xpath, $container, $selectors['excerpt']);
        }

        // Extract image
        $image = '';
        if (!empty($selectors['image'])) {
            $image = $this->extract_element_text($xpath, $container, $selectors['image'], 'src');
            if (!empty($image)) {
                $image = $this->make_absolute_url($image, $source['url']);
            }
        }

        // Extract date
        $date = '';
        if (!empty($selectors['date'])) {
            $date = $this->extract_element_text($xpath, $container, $selectors['date']);
        }

        return [
            'url' => esc_url_raw($link),
            'title' => sanitize_text_field($title),
            'excerpt' => wp_kses_post($excerpt),
            'date' => $date,
            'author' => '',
            'image' => esc_url_raw($image),
            'categories' => [],
            'raw_data' => [],
        ];
    }

    /**
     * Extract text from element using selector
     *
     * @since 2.0.0
     * @param DOMXPath $xpath XPath instance.
     * @param \DOMElement $context Context element.
     * @param string $selector CSS selector.
     * @param string|null $attribute Optional attribute to extract instead of text.
     * @return string Extracted text.
     */
    private function extract_element_text($xpath, $context, $selector, $attribute = null) {
        $xpath_query = $this->css_to_xpath($selector);
        $nodes = $xpath->query($xpath_query, $context);

        if ($nodes->length === 0) {
            return '';
        }

        $node = $nodes->item(0);

        if ($attribute !== null) {
            // Extract attribute value
            if ($node->hasAttribute($attribute)) {
                return trim($node->getAttribute($attribute));
            }
            return '';
        }

        // Extract text content
        return trim($node->textContent);
    }

    /**
     * Convert CSS selector to XPath
     *
     * Supports basic CSS selectors.
     *
     * @since 2.0.0
     * @param string $selector CSS selector.
     * @return string XPath expression.
     */
    private function css_to_xpath($selector) {
        // Remove leading/trailing whitespace
        $selector = trim($selector);

        // Simple conversions
        $xpath = $selector;

        // ID selector: #id -> //*[@id='id']
        $xpath = preg_replace('/#([a-zA-Z0-9_-]+)/', "*[@id='$1']", $xpath);

        // Class selector: .class -> //*[contains(@class,'class')]
        $xpath = preg_replace_callback(
            '/\.([a-zA-Z0-9_-]+)/',
            function($matches) {
                return "*[contains(concat(' ', normalize-space(@class), ' '), ' {$matches[1]} ')]";
            },
            $xpath
        );

        // Attribute selector: [attr=value] -> *[@attr='value']
        // (already XPath-like, just ensure proper format)

        // Descendant combinator: space -> /
        $xpath = str_replace(' > ', '/', $xpath);
        $xpath = preg_replace('/\s+/', '//', $xpath);

        // Prepend // if not already present
        if (substr($xpath, 0, 1) !== '/' && substr($xpath, 0, 2) !== '//') {
            $xpath = '//' . $xpath;
        }

        return $xpath;
    }

    /**
     * Convert relative URL to absolute
     *
     * @since 2.0.0
     * @param string $url URL (may be relative).
     * @param string $base Base URL.
     * @return string Absolute URL.
     */
    private function make_absolute_url($url, $base) {
        // Already absolute
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        $base_parts = parse_url($base);
        
        if (!$base_parts) {
            return $url;
        }

        $scheme = isset($base_parts['scheme']) ? $base_parts['scheme'] : 'http';
        $host = isset($base_parts['host']) ? $base_parts['host'] : '';

        // Protocol-relative URL
        if (substr($url, 0, 2) === '//') {
            return $scheme . ':' . $url;
        }

        // Absolute path
        if (substr($url, 0, 1) === '/') {
            return $scheme . '://' . $host . $url;
        }

        // Relative path
        $path = isset($base_parts['path']) ? $base_parts['path'] : '/';
        $path = dirname($path);
        
        // Remove ./ and ../
        $url = $path . '/' . $url;
        $url = preg_replace('/\/\.\//', '/', $url);
        while (preg_match('/\/[^\/]+\/\.\.\//', $url)) {
            $url = preg_replace('/\/[^\/]+\/\.\.\//', '/', $url);
        }

        return $scheme . '://' . $host . $url;
    }

    /**
     * Get source type identifier
     *
     * @since 2.0.0
     * @return string Source type.
     */
    protected function get_source_type() {
        return 'web';
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
                'no_web_sources',
                'No web sources configured for campaign'
            );
        }

        return true;
    }
}
