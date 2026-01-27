<?php
/**
 * Web Processor
 *
 * Processes web pages and sitemap URLs.
 * Fetches full content and extracts main article.
 *
 * @package AutoBlogCraft\Processing
 * @since 2.0.0
 */

namespace AutoBlogCraft\Processing\Processors;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Web Processor class
 *
 * Processes web pages (from sitemap or direct scraping).
 *
 * @since 2.0.0
 */
class Web_Processor extends Base_Processor {

    /**
     * Fetch content from web page
     *
     * @since 2.0.0
     * @param array $queue_item Queue item data.
     * @return array|WP_Error Content data or error.
     */
    protected function fetch_content($queue_item) {
        $fetcher = new Content_Fetcher();

        // Fetch page
        $response = $fetcher->fetch($queue_item['source_url'], [
            'cache' => true,
            'cache_ttl' => HOUR_IN_SECONDS,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        if (empty($response['content'])) {
            return new WP_Error('empty_content', 'Page content is empty');
        }

        // Extract readable content using Readability algorithm
        $readable = $this->content_cleaner->extract_readable($response['content']);

        return [
            'title' => !empty($response['title']) ? $response['title'] : $queue_item['title'],
            'content' => $readable,
            'html' => $response['content'],
            'author' => isset($response['author']) ? $response['author'] : '',
            'date' => isset($response['date']) ? $response['date'] : '',
            'image' => isset($response['image']) ? $response['image'] : '',
            'description' => isset($response['description']) ? $response['description'] : '',
        ];
    }

    /**
     * Get processor type
     *
     * @since 2.0.0
     * @return string Processor type.
     */
    protected function get_processor_type() {
        return 'web';
    }
}
