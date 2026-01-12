<?php
/**
 * News Processor
 *
 * Processes news articles from SERP APIs.
 * Similar to RSS processor - content excerpt is in queue.
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
 * News Processor class
 *
 * Processes news articles.
 *
 * @since 2.0.0
 */
class News_Processor extends Base_Processor {

    /**
     * Fetch content from news article
     *
     * @since 2.0.0
     * @param array $queue_item Queue item data.
     * @return array|WP_Error Content data or error.
     */
    protected function fetch_content($queue_item) {
        $fetcher = new Content_Fetcher();

        // Fetch full article
        $response = $fetcher->fetch($queue_item['source_url'], [
            'cache' => true,
            'cache_ttl' => HOUR_IN_SECONDS,
        ]);

        if (is_wp_error($response)) {
            // Fallback to excerpt
            if (!empty($queue_item['excerpt'])) {
                $this->logger->warning("News fetch failed, using excerpt: {$response->get_error_message()}");
                
                return [
                    'title' => $queue_item['title'],
                    'content' => $queue_item['excerpt'],
                    'html' => $queue_item['excerpt'],
                    'fallback' => true,
                ];
            }

            return $response;
        }

        return $response;
    }

    /**
     * Extract metadata
     *
     * @since 2.0.0
     * @param array $content_data Content data.
     * @param array $queue_item Queue item.
     * @return array Metadata.
     */
    protected function extract_metadata($content_data, $queue_item) {
        $metadata = parent::extract_metadata($content_data, $queue_item);

        // Add news-specific metadata
        $source_data = $queue_item['source_data'];

        if (!empty($source_data['source'])) {
            $metadata['news_source'] = $source_data['source'];
        }

        return $metadata;
    }

    /**
     * Get processor type
     *
     * @since 2.0.0
     * @return string Processor type.
     */
    protected function get_processor_type() {
        return 'news';
    }
}
