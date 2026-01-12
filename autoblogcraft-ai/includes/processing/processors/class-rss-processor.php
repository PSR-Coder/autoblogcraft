<?php
/**
 * RSS Processor
 *
 * Processes RSS/Atom feed items.
 * Content is already available in queue from discovery phase.
 *
 * @package AutoBlogCraft\Processing\Processors
 * @since 2.0.0
 */

namespace AutoBlogCraft\Processing\Processors;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * RSS Processor class
 *
 * Processes content from RSS/Atom feeds.
 *
 * @since 2.0.0
 */
class RSS_Processor extends Base_Processor {

    /**
     * Fetch content from RSS item
     *
     * RSS items already have excerpt in queue, but we fetch full content from URL.
     *
     * @since 2.0.0
     * @param array $queue_item Queue item data.
     * @return array|WP_Error Content data or error.
     */
    protected function fetch_content($queue_item) {
        $fetcher = new Content_Fetcher();

        // Fetch full page
        $response = $fetcher->fetch($queue_item['source_url'], [
            'cache' => true,
            'cache_ttl' => HOUR_IN_SECONDS,
        ]);

        if (is_wp_error($response)) {
            // Fallback to excerpt if fetch fails
            if (!empty($queue_item['excerpt'])) {
                $this->logger->warning("Full content fetch failed, using excerpt: {$response->get_error_message()}");
                
                return [
                    'title' => $queue_item['title'],
                    'content' => $queue_item['excerpt'],
                    'html' => $queue_item['excerpt'],
                    'fallback' => true,
                ];
            }

            return $response;
        }

        // Use fetched content if available
        if (!empty($response['content'])) {
            return $response;
        }

        // Fallback to excerpt
        return [
            'title' => $queue_item['title'],
            'content' => $queue_item['excerpt'],
            'html' => $queue_item['excerpt'],
            'fallback' => true,
        ];
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

        // Add RSS-specific metadata
        $source_data = $queue_item['source_data'];

        if (!empty($source_data['published_date'])) {
            $metadata['source_date'] = $source_data['published_date'];
        }

        if (!empty($source_data['author'])) {
            $metadata['source_author'] = $source_data['author'];
        }

        if (!empty($source_data['categories'])) {
            $metadata['source_categories'] = $source_data['categories'];
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
        return 'rss';
    }
}
