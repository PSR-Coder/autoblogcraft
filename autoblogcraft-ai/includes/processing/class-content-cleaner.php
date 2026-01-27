<?php
/**
 * Content Cleaner
 *
 * Cleans and normalizes HTML content.
 * Removes unwanted elements and prepares content for AI processing.
 *
 * @package AutoBlogCraft\Processing
 * @since 2.0.0
 */

namespace AutoBlogCraft\Processing;

use DOMDocument;
use DOMXPath;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content Cleaner class
 *
 * Responsibilities:
 * - Remove ads, scripts, styles
 * - Extract main content
 * - Normalize HTML
 * - Convert to clean text or HTML
 *
 * @since 2.0.0
 */
class Content_Cleaner {

    /**
     * Tags to remove completely
     *
     * @var array
     */
    private $remove_tags = [
        'script',
        'style',
        'noscript',
        'iframe',
        'object',
        'embed',
        'form',
        'input',
        'button',
        'select',
        'textarea',
        'nav',
        declare(strict_types=1);
        'header',
        'footer',
        'aside',
        'advertisement',
    ];

    /**
     * CSS classes/IDs to remove (ads, social, etc.)
     *
     * @var array
     */
    private $remove_selectors = [
        'ad',
        'ads',
        'advertisement',
        'banner',
        'popup',
        'modal',
        'social',
        'share',
        'comments',
        'related',
        'sidebar',
        'widget',
        'menu',
        'navigation',
    ];

    /**
     * Allowed HTML tags for cleaned content
     *
     * @var array
     */
    private $allowed_tags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li',
        'blockquote', 'cite', 'code', 'pre',
        'a', 'img',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'div', 'span',
    ];

    /**
     * Clean HTML content
     *
     * @since 2.0.0
     * @param string $html HTML content.
     * @param array $options Cleaning options.
     * @return string Cleaned content.
     */
    public function clean($html, $options = []) {
        $defaults = [
            'output_format' => 'html', // 'html' or 'text'
            'preserve_links' => true,
            'preserve_images' => true,
            'preserve_formatting' => true,
            'max_image_width' => 800,
        ];

        $options = wp_parse_args($options, $defaults);

        // Parse HTML
        $dom = $this->parse_html($html);
        if (!$dom) {
            return $this->fallback_clean($html);
        }

        // Remove unwanted elements
        $this->remove_unwanted_elements($dom);

        // Extract main content
        $content = $this->extract_main_content($dom);

        // Clean attributes
        $this->clean_attributes($content);

        // Process images
        if ($options['preserve_images']) {
            $this->process_images($content, $options['max_image_width']);
        } else {
            $this->remove_images($content);
        }

        // Process links
        if (!$options['preserve_links']) {
            $this->remove_links($content);
        }

        // Get HTML
        $cleaned = $this->get_inner_html($content);

        // Output format
        if ($options['output_format'] === 'text') {
            $cleaned = $this->html_to_text($cleaned);
        } elseif (!$options['preserve_formatting']) {
            $cleaned = $this->strip_formatting($cleaned);
        }

        // Normalize whitespace
        $cleaned = $this->normalize_whitespace($cleaned);

        return $cleaned;
    }

    /**
     * Parse HTML into DOM
     *
     * @since 2.0.0
     * @param string $html HTML string.
     * @return DOMDocument|null DOM document or null on failure.
     */
    private function parse_html($html) {
        if (empty($html)) {
            return null;
        }

        libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->encoding = 'UTF-8';

        // Load HTML
        $success = @$dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors(false);

        return $success ? $dom : null;
    }

    /**
     * Remove unwanted elements
     *
     * @since 2.0.0
     * @param DOMDocument $dom DOM document.
     */
    private function remove_unwanted_elements($dom) {
        $xpath = new DOMXPath($dom);

        // Remove tags
        foreach ($this->remove_tags as $tag) {
            $nodes = $dom->getElementsByTagName($tag);
            $remove = [];
            foreach ($nodes as $node) {
                $remove[] = $node;
            }
            foreach ($remove as $node) {
                $node->parentNode->removeChild($node);
            }
        }

        // Remove by class/ID
        foreach ($this->remove_selectors as $selector) {
            // By class
            $nodes = $xpath->query("//*[contains(@class, '{$selector}')]");
            $this->remove_nodes($nodes);

            // By ID
            $nodes = $xpath->query("//*[contains(@id, '{$selector}')]");
            $this->remove_nodes($nodes);
        }
    }

    /**
     * Remove DOM nodes
     *
     * @since 2.0.0
     * @param \DOMNodeList $nodes Nodes to remove.
     */
    private function remove_nodes($nodes) {
        $remove = [];
        foreach ($nodes as $node) {
            $remove[] = $node;
        }
        foreach ($remove as $node) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    /**
     * Extract main content
     *
     * Tries to find the main content area.
     *
     * @since 2.0.0
     * @param DOMDocument $dom DOM document.
     * @return \DOMElement Main content element.
     */
    private function extract_main_content($dom) {
        $xpath = new DOMXPath($dom);

        // Try common content selectors
        $selectors = [
            "//main",
            "//article",
            "//*[contains(@class, 'content')]",
            "//*[contains(@class, 'post')]",
            "//*[contains(@class, 'entry')]",
            "//*[contains(@id, 'content')]",
            "//*[contains(@id, 'main')]",
            "//body",
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                return $nodes->item(0);
            }
        }

        // Fallback to body
        return $dom->getElementsByTagName('body')->item(0);
    }

    /**
     * Clean element attributes
     *
     * @since 2.0.0
     * @param \DOMElement $element Element to clean.
     */
    private function clean_attributes($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        $nodes = $xpath->query('.//*', $element);

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            // Keep only essential attributes
            $keep_attrs = [];

            switch ($node->tagName) {
                case 'a':
                    if ($node->hasAttribute('href')) {
                        $keep_attrs['href'] = $node->getAttribute('href');
                    }
                    if ($node->hasAttribute('title')) {
                        $keep_attrs['title'] = $node->getAttribute('title');
                    }
                    break;

                case 'img':
                    if ($node->hasAttribute('src')) {
                        $keep_attrs['src'] = $node->getAttribute('src');
                    }
                    if ($node->hasAttribute('alt')) {
                        $keep_attrs['alt'] = $node->getAttribute('alt');
                    }
                    break;
            }

            // Remove all attributes
            while ($node->attributes->length > 0) {
                $node->removeAttribute($node->attributes->item(0)->name);
            }

            // Re-add essential attributes
            foreach ($keep_attrs as $name => $value) {
                $node->setAttribute($name, $value);
            }
        }
    }

    /**
     * Process images
     *
     * @since 2.0.0
     * @param \DOMElement $element Element containing images.
     * @param int $max_width Maximum image width.
     */
    private function process_images($element, $max_width) {
        $images = $element->getElementsByTagName('img');

        foreach ($images as $img) {
            // Add width attribute if specified
            if ($max_width > 0) {
                $img->setAttribute('width', $max_width);
                $img->removeAttribute('height'); // Let it scale proportionally
            }

            // Ensure alt text
            if (!$img->hasAttribute('alt')) {
                $img->setAttribute('alt', '');
            }
        }
    }

    /**
     * Remove images
     *
     * @since 2.0.0
     * @param \DOMElement $element Element to clean.
     */
    private function remove_images($element) {
        $images = $element->getElementsByTagName('img');
        $this->remove_nodes($images);
    }

    /**
     * Remove links (keep text)
     *
     * @since 2.0.0
     * @param \DOMElement $element Element to clean.
     */
    private function remove_links($element) {
        $links = $element->getElementsByTagName('a');
        $remove = [];
        
        foreach ($links as $link) {
            $remove[] = $link;
        }

        foreach ($remove as $link) {
            // Replace link with its text content
            $text = $link->ownerDocument->createTextNode($link->textContent);
            $link->parentNode->replaceChild($text, $link);
        }
    }

    /**
     * Get inner HTML of element
     *
     * @since 2.0.0
     * @param \DOMElement $element Element.
     * @return string Inner HTML.
     */
    private function get_inner_html($element) {
        $html = '';
        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument->saveHTML($child);
        }
        return $html;
    }

    /**
     * Convert HTML to plain text
     *
     * @since 2.0.0
     * @param string $html HTML content.
     * @return string Plain text.
     */
    private function html_to_text($html) {
        // Add line breaks for block elements
        $html = preg_replace('/<\/?(p|div|h[1-6]|li|blockquote)>/i', "\n", $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);

        // Strip all HTML tags
        $text = wp_strip_all_tags($html);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $text;
    }

    /**
     * Strip formatting tags but keep structure
     *
     * @since 2.0.0
     * @param string $html HTML content.
     * @return string HTML without formatting.
     */
    private function strip_formatting($html) {
        // Remove formatting tags but keep paragraphs, headings, lists
        $strip_tags = ['strong', 'b', 'em', 'i', 'u', 'span'];
        
        foreach ($strip_tags as $tag) {
            $html = preg_replace("/<\/?{$tag}[^>]*>/i", '', $html);
        }

        return $html;
    }

    /**
     * Normalize whitespace
     *
     * @since 2.0.0
     * @param string $text Text content.
     * @return string Normalized text.
     */
    private function normalize_whitespace($text) {
        // Remove excessive line breaks
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        // Trim lines
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        $text = implode("\n", $lines);

        // Normalize spaces
        $text = preg_replace('/[ \t]+/', ' ', $text);

        return trim($text);
    }

    /**
     * Fallback clean (when DOM parsing fails)
     *
     * @since 2.0.0
     * @param string $html HTML content.
     * @return string Cleaned content.
     */
    private function fallback_clean($html) {
        // Remove scripts and styles
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        // Use WordPress function
        $html = wp_kses_post($html);

        return $this->normalize_whitespace($html);
    }

    /**
     * Extract readable content using Readability algorithm
     *
     * Simplified version of Mozilla's Readability.
     *
     * @since 2.0.0
     * @param string $html HTML content.
     * @return string Extracted content.
     */
    public function extract_readable($html) {
        $dom = $this->parse_html($html);
        if (!$dom) {
            return $this->fallback_clean($html);
        }

        // Score paragraphs
        $candidates = [];
        $paragraphs = $dom->getElementsByTagName('p');

        foreach ($paragraphs as $p) {
            $text = trim($p->textContent);
            $word_count = str_word_count($text);

            if ($word_count < 20) {
                continue; // Skip short paragraphs
            }

            // Find parent container
            $parent = $p->parentNode;
            
            if (!isset($candidates[$parent->nodeName])) {
                $candidates[$parent->nodeName] = [
                    'element' => $parent,
                    'score' => 0,
                ];
            }

            // Score based on word count and paragraph density
            $candidates[$parent->nodeName]['score'] += $word_count;
        }

        if (empty($candidates)) {
            return $this->clean($html);
        }

        // Get highest scoring element
        usort($candidates, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        $best = $candidates[0]['element'];

        return $this->clean($this->get_inner_html($best));
    }
}
