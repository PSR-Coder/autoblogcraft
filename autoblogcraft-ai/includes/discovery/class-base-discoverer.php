<?php
/**
 * Base Discoverer
 *
 * Abstract base class for all content discoverers.
 * Provides common functionality and enforces consistent interface.
 *
 * @package AutoBlogCraft\Discovery
 * @since 2.0.0
 */

namespace AutoBlogCraft\Discovery;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\Helpers\Duplicate_Detector;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base Discoverer class
 *
 * All discoverers must extend this class.
 *
 * Template Method Pattern:
 * - discover() - orchestrates the discovery process
 * - do_discover() - implemented by child classes
 *
 * @since 2.0.0
 */
abstract class Base_Discoverer {

    /**
     * Queue manager instance
     *
     * @var Queue_Manager
     */
    protected $queue_manager;

    /**
     * Logger instance
     *
     * @var Logger
     */
    protected $logger;

    /**
     * Duplicate detector
     *
     * @var Duplicate_Detector
     */
    protected $duplicate_detector;

    /**
     * Constructor
     *
     * @since 2.0.0
     * @param Queue_Manager $queue_manager Queue manager instance.
     */
    public function __construct(Queue_Manager $queue_manager) {
        $this->queue_manager = $queue_manager;
        $this->logger = Logger::instance();
        $this->duplicate_detector = new Duplicate_Detector();
    }

    /**
     * Discover content for campaign
     *
     * Template method that orchestrates the discovery process.
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @return array|WP_Error Discovery result or error.
     */
    public function discover($campaign) {
        $campaign_id = $campaign->get_id();
        $type = $campaign->get_type();

        $this->logger->debug("Starting {$type} discovery: Campaign={$campaign_id}");

        // Validate campaign
        $validation = $this->validate_campaign($campaign);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Get sources
        $sources = $this->get_sources($campaign);
        if (empty($sources)) {
            return new WP_Error(
                'no_sources',
                'No sources configured for campaign'
            );
        }

        $total_found = 0;
        $total_added = 0;
        $errors = [];

        // Discover from each source
        foreach ($sources as $source) {
            try {
                $result = $this->do_discover($campaign, $source);

                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                    continue;
                }

                $items = $result['items'];
                $total_found += count($items);

                // Process items
                $processed = $this->process_items($campaign, $items, $source);
                $total_added += $processed['added'];

            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
                $this->logger->error("Discovery exception: {$e->getMessage()}");
            }
        }

        // Build result
        $result = [
            'items_found' => $total_found,
            'items_added' => $total_added,
            'sources_processed' => count($sources),
            'errors' => $errors,
        ];

        if ($total_found === 0 && !empty($errors)) {
            return new WP_Error(
                'discovery_failed',
                implode('; ', $errors),
                $result
            );
        }

        $this->logger->info(
            "Discovery complete: Campaign={$campaign_id}, Found={$total_found}, Added={$total_added}"
        );

        return $result;
    }

    /**
     * Perform actual discovery
     *
     * Must be implemented by child classes.
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @param array $source Source configuration.
     * @return array|WP_Error Array with 'items' key or error.
     */
    abstract protected function do_discover($campaign, $source);

    /**
     * Validate campaign configuration
     *
     * Can be overridden by child classes for specific validation.
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @return bool|WP_Error True if valid, WP_Error otherwise.
     */
    protected function validate_campaign($campaign) {
        if (empty($campaign->get_id())) {
            return new WP_Error('invalid_campaign', 'Invalid campaign ID');
        }

        return true;
    }

    /**
     * Get sources from campaign
     *
     * Extracts sources array from campaign meta.
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @return array Array of sources.
     */
    protected function get_sources($campaign) {
        $sources = $campaign->get_meta('sources', []);

        if (!is_array($sources)) {
            return [];
        }

        // Filter active sources only
        return array_filter($sources, function($source) {
            return !isset($source['status']) || $source['status'] === 'active';
        });
    }

    /**
     * Process discovered items
     *
     * Filters, validates, and adds items to queue.
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @param array $items Discovered items.
     * @param array $source Source configuration.
     * @return array Processing result with counts.
     */
    protected function process_items($campaign, $items, $source) {
        $campaign_id = $campaign->get_id();
        $added = 0;
        $filtered = 0;
        $duplicates = 0;

        foreach ($items as $item) {
            // Validate item
            if (!$this->validate_item($item)) {
                $filtered++;
                continue;
            }

            // Check duplicates
            if ($this->is_duplicate($campaign_id, $item)) {
                $duplicates++;
                continue;
            }

            // Add to queue
            $queue_item = $this->prepare_queue_item($campaign_id, $item, $source);
            $result = $this->queue_manager->add_to_queue($queue_item);

            if ($result !== false) {
                $added++;
            }
        }

        $this->logger->debug(
            "Processed items: Added={$added}, Filtered={$filtered}, Duplicates={$duplicates}"
        );

        return [
            'added' => $added,
            'filtered' => $filtered,
            'duplicates' => $duplicates,
        ];
    }

    /**
     * Validate discovered item
     *
     * Checks if item has required fields.
     *
     * @since 2.0.0
     * @param array $item Item data.
     * @return bool True if valid, false otherwise.
     */
    protected function validate_item($item) {
        // Must have URL
        if (empty($item['url']) || !filter_var($item['url'], FILTER_VALIDATE_URL)) {
            return false;
        }

        // Must have title
        if (empty($item['title'])) {
            return false;
        }

        return true;
    }

    /**
     * Check if item is duplicate
     *
     * Uses multiple detection methods.
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @param array $item Item data.
     * @return bool True if duplicate, false otherwise.
     */
    protected function is_duplicate($campaign_id, $item) {
        // Check queue
        if ($this->queue_manager->url_exists($campaign_id, $item['url'])) {
            return true;
        }

        // Check published posts
        if ($this->duplicate_detector->url_exists($item['url'])) {
            return true;
        }

        // Check title similarity (optional)
        if (!empty($item['title'])) {
            $similar = $this->duplicate_detector->find_similar_title($item['title'], 90);
            if (!empty($similar)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prepare item for queue
     *
     * Converts discovered item to queue format.
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @param array $item Discovered item.
     * @param array $source Source configuration.
     * @return array Queue item.
     */
    protected function prepare_queue_item($campaign_id, $item, $source) {
        return [
            'campaign_id' => $campaign_id,
            'source_url' => $item['url'],
            'source_type' => $this->get_source_type(),
            'title' => $item['title'],
            'excerpt' => isset($item['excerpt']) ? $item['excerpt'] : '',
            'source_data' => [
                'published_date' => isset($item['date']) ? $item['date'] : '',
                'author' => isset($item['author']) ? $item['author'] : '',
                'image_url' => isset($item['image']) ? $item['image'] : '',
                'categories' => isset($item['categories']) ? $item['categories'] : [],
                'source_name' => isset($source['name']) ? $source['name'] : '',
                'source_url' => isset($source['url']) ? $source['url'] : '',
                'raw_data' => isset($item['raw_data']) ? $item['raw_data'] : [],
            ],
            'priority' => $this->calculate_priority($item, $source),
        ];
    }

    /**
     * Calculate item priority
     *
     * Can be overridden for custom priority logic.
     *
     * @since 2.0.0
     * @param array $item Discovered item.
     * @param array $source Source configuration.
     * @return int Priority (0-100, higher = more important).
     */
    protected function calculate_priority($item, $source) {
        $priority = 50; // Default

        // Boost recent items
        if (!empty($item['date'])) {
            $age_hours = (time() - strtotime($item['date'])) / 3600;
            if ($age_hours < 24) {
                $priority += 20;
            } elseif ($age_hours < 72) {
                $priority += 10;
            }
        }

        // Source-specific priority
        if (isset($source['priority'])) {
            $priority = absint($source['priority']);
        }

        return min(100, max(0, $priority));
    }

    /**
     * Get source type
     *
     * Must be implemented by child classes.
     *
     * @since 2.0.0
     * @return string Source type identifier.
     */
    abstract protected function get_source_type();

    /**
     * Make HTTP request
     *
     * Wrapper around wp_remote_get with common settings.
     *
     * @since 2.0.0
     * @param string $url URL to fetch.
     * @param array $args Optional request arguments.
     * @return array|WP_Error Response or error.
     */
    protected function fetch_url($url, $args = []) {
        $defaults = [
            'timeout' => 30,
            'user-agent' => 'AutoBlogCraft/2.0 (+' . home_url() . ')',
            'sslverify' => true,
        ];

        $args = wp_parse_args($args, $defaults);

        $this->logger->debug("Fetching URL: {$url}");

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            $this->logger->error("HTTP request failed: {$response->get_error_message()}");
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new WP_Error(
                'http_error',
                sprintf('HTTP %d: %s', $code, wp_remote_retrieve_response_message($response))
            );
        }

        return $response;
    }

    /**
     * Parse XML document
     *
     * Safe XML parsing with error handling.
     *
     * @since 2.0.0
     * @param string $xml XML string.
     * @return \SimpleXMLElement|WP_Error Parsed XML or error.
     */
    protected function parse_xml($xml) {
        // Disable XML errors
        libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $doc = simplexml_load_string(
                $xml,
                'SimpleXMLElement',
                LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING
            );

            if ($doc === false) {
                $errors = libxml_get_errors();
                $error_msg = !empty($errors) ? $errors[0]->message : 'Unknown XML error';
                libxml_clear_errors();

                return new WP_Error('xml_parse_error', trim($error_msg));
            }

            return $doc;

        } catch (\Exception $e) {
            return new WP_Error('xml_exception', $e->getMessage());
        } finally {
            libxml_use_internal_errors(false);
        }
    }

    /**
     * Extract text from HTML
     *
     * Strips HTML tags and decodes entities.
     *
     * @since 2.0.0
     * @param string $html HTML string.
     * @return string Plain text.
     */
    protected function extract_text($html) {
        // Remove scripts and styles
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        // Strip tags
        $text = wp_strip_all_tags($html);

        // Decode entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        return $text;
    }

    /**
     * Truncate text
     *
     * @since 2.0.0
     * @param string $text Text to truncate.
     * @param int $length Maximum length.
     * @param string $suffix Suffix for truncated text.
     * @return string Truncated text.
     */
    protected function truncate($text, $length = 200, $suffix = '...') {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . $suffix;
    }
}
