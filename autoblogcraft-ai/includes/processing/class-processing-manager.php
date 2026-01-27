<?php
/**
 * Processing Manager
 *
 * Orchestrates content processing from queue to published posts.
 * Main entry point for the processing system.
 *
 * @package AutoBlogCraft\Processing
 * @since 2.0.0
 */

namespace AutoBlogCraft\Processing;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\Core\Rate_Limiter;
use AutoBlogCraft\Discovery\Queue_Manager;
use AutoBlogCraft\Campaigns\Campaign_Factory;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Processing Manager class
 *
 * Responsibilities:
 * - Process items from queue
 * - Coordinate processors
 * - Track processing status
 * - Handle errors and retries
 *
 * @since 2.0.0
 */
class Processing_Manager {

    /**
     * Singleton instance
     *
     * @var Processing_Manager
     */
    private static $instance = null;

    /**
     * Queue manager instance
     *
     * @var Queue_Manager
     */
    private $queue_manager;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Rate limiter instance
     *
     * @var Rate_Limiter
     */
    private $rate_limiter;

    /**
     * Registered processors
     *
     * @var array
     */
    private $processors = [];

    /**
     * Get singleton instance
     *
     * @since 2.0.0
     * @return Processing_Manager
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    private function __construct() {
        $this->queue_manager = new Queue_Manager();
        $this->logger = Logger::instance();
        
        // Manual loading to ensure Rate_Limiter is available
        if (!class_exists('AutoBlogCraft\\Core\\Rate_Limiter')) {
            require_once plugin_dir_path(__FILE__) . '../core/class-rate-limiter.php';
        }
        
        $this->rate_limiter = Rate_Limiter::instance();
        $this->register_processors();
    }

    /**
     * Register all processors
     *
     * Maps source types to processor classes.
     *
     * @since 2.0.0
     */
    private function register_processors() {
        $this->processors = [
            'rss' => Web_Processor::class,
            'sitemap' => Web_Processor::class,
            'web' => Web_Processor::class,
            'youtube' => YouTube_Processor::class,
            'news' => News_Processor::class,
        ];

        /**
         * Filter registered processors
         *
         * Allows plugins to add custom processors.
         *
         * @since 2.0.0
         * @param array $processors Processor map.
         */
        $this->processors = apply_filters('abc_registered_processors', $this->processors);
    }

    /**
     * Process next batch from queue
     *
     * Main entry point for batch processing.
     *
     * @since 2.0.0
     * @param int $batch_size Number of items to process (default 10).
     * @param int|null $campaign_id Optional campaign ID filter.
     * @return array Processing results.
     */
    public function process_batch($batch_size = 10, $campaign_id = null) {
        $this->logger->info("Starting batch processing: Size={$batch_size}");

        $results = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'skipped' => 0,
            'posts_created' => [],
            'errors' => [],
        ];

        // Get items from queue
        $items = $this->queue_manager->get_next_items($batch_size, $campaign_id);

        if (empty($items)) {
            $this->logger->debug('No items in queue');
            return $results;
        }

        foreach ($items as $item) {
            $results['processed']++;

            // Mark as processing
            $this->queue_manager->mark_processing($item['id']);

            // Process item
            $result = $this->process_item($item);

            if (is_wp_error($result)) {
                // Processing failed
                $results['failed']++;
                $results['errors'][] = [
                    'queue_id' => $item['id'],
                    'error' => $result->get_error_message(),
                ];

                $this->queue_manager->mark_failed($item['id'], $result->get_error_message());
            } elseif ($result === false) {
                // Skipped (duplicate, etc.)
                $results['skipped']++;
            } else {
                // Success
                $results['succeeded']++;
                $results['posts_created'][] = $result;

                $this->queue_manager->mark_completed($item['id'], $result);
            }

            // Prevent timeout
            if ($results['processed'] % 5 === 0) {
                sleep(1);
            }
        }

        $this->logger->info(
            "Batch complete: Processed={$results['processed']}, " .
            "Succeeded={$results['succeeded']}, Failed={$results['failed']}, Skipped={$results['skipped']}"
        );

        return $results;
    }

    /**
     * Process single queue item
     *
     * @since 2.0.0
     * @param array $item Queue item.
     * @return int|false|WP_Error Post ID on success, false if skipped, error on failure.
     */
    public function process_item($item) {
        $queue_id = $item['id'];
        $source_type = $item['source_type'];
        $campaign_id = $item['campaign_id'];

        $this->logger->debug("Processing item: Queue={$queue_id}, Type={$source_type}");

        // Check rate limits before starting
        if (!$this->rate_limiter->can_start_campaign($campaign_id)) {
            $this->logger->info(
                "Campaign rate limit reached, skipping: ID={$campaign_id}",
                ['queue_id' => $queue_id]
            );
            // Return to pending status for next processing cycle
            return new WP_Error('rate_limit', 'Campaign rate limit reached');
        }

        // Load campaign
        $campaign = Campaign_Factory::create($campaign_id);
        if (is_wp_error($campaign)) {
            return $campaign;
        }

        // Check campaign status
        if ($campaign->get_status() !== 'active') {
            $this->logger->warning("Campaign inactive: ID={$campaign_id}");
            return new WP_Error('campaign_inactive', 'Campaign is not active');
        }

        // Acquire campaign slot
        $this->rate_limiter->start_campaign($campaign_id);
        
        // Track processing start time for stale lock cleanup
        update_post_meta($campaign_id, '_abc_processing_started', time());

        try {
            // Get processor
            $processor = $this->get_processor($source_type);
            if (is_wp_error($processor)) {
                return $processor;
            }

            // Process with processor
            $post_id = $processor->process($item, $campaign);

            if (is_wp_error($post_id)) {
                $this->update_campaign_stats($campaign_id, false);
                return $post_id;
            }

            // Track campaign stats
            $this->update_campaign_stats($campaign_id, true);

            return $post_id;

        } catch (\Exception $e) {
            $error = new WP_Error('processing_exception', $e->getMessage());
            $this->logger->error("Processing exception: {$e->getMessage()}");
            
            $this->update_campaign_stats($campaign_id, false);
            
            return $error;
            
        } finally {
            // Always release campaign slot
            $this->rate_limiter->finish_campaign($campaign_id);
            delete_post_meta($campaign_id, '_abc_processing_started');
        }
    }

    /**
     * Process all items for campaign
     *
     * Processes entire queue for a specific campaign.
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @param int $batch_size Batch size (default 10).
     * @return array Processing results.
     */
    public function process_campaign($campaign_id, $batch_size = 10) {
        $this->logger->info("Processing campaign: ID={$campaign_id}");

        $total_results = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'skipped' => 0,
            'posts_created' => [],
            'errors' => [],
        ];

        // Process in batches until queue is empty
        while (true) {
            $batch_results = $this->process_batch($batch_size, $campaign_id);

            // Merge results
            $total_results['processed'] += $batch_results['processed'];
            $total_results['succeeded'] += $batch_results['succeeded'];
            $total_results['failed'] += $batch_results['failed'];
            $total_results['skipped'] += $batch_results['skipped'];
            $total_results['posts_created'] = array_merge(
                $total_results['posts_created'],
                $batch_results['posts_created']
            );
            $total_results['errors'] = array_merge(
                $total_results['errors'],
                $batch_results['errors']
            );

            // Stop if no items processed
            if ($batch_results['processed'] === 0) {
                break;
            }

            // Prevent timeout on large queues
            sleep(2);
        }

        $this->logger->info(
            "Campaign processing complete: ID={$campaign_id}, Total={$total_results['processed']}"
        );

        return $total_results;
    }

    /**
     * Get processor for source type
     *
     * @since 2.0.0
     * @param string $source_type Source type.
     * @return object|WP_Error Processor instance or error.
     */
    private function get_processor($source_type) {
        if (!isset($this->processors[$source_type])) {
            return new WP_Error(
                'no_processor',
                sprintf('No processor registered for type: %s', $source_type)
            );
        }

        $class = $this->processors[$source_type];

        if (!class_exists($class)) {
            return new WP_Error(
                'processor_not_found',
                sprintf('Processor class not found: %s', $class)
            );
        }

        return new $class();
    }

    /**
     * Update campaign statistics
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @param bool $success Whether processing succeeded.
     */
    private function update_campaign_stats($campaign_id, $success) {
        // Increment total processed
        $total = (int) get_post_meta($campaign_id, '_abc_total_processed', true);
        update_post_meta($campaign_id, '_abc_total_processed', $total + 1);

        if ($success) {
            // Increment published count
            $published = (int) get_post_meta($campaign_id, '_abc_total_published', true);
            update_post_meta($campaign_id, '_abc_total_published', $published + 1);

            // Update last published
            update_post_meta($campaign_id, '_abc_last_published', current_time('mysql'));
        } else {
            // Increment failed count
            $failed = (int) get_post_meta($campaign_id, '_abc_total_failed', true);
            update_post_meta($campaign_id, '_abc_total_failed', $failed + 1);
        }
    }

    /**
     * Get processing statistics
     *
     * @since 2.0.0
     * @param int|null $campaign_id Optional campaign ID filter.
     * @return array Statistics.
     */
    public function get_stats($campaign_id = null) {
        $queue_stats = $this->queue_manager->get_stats($campaign_id);

        $stats = [
            'queue' => $queue_stats,
            'processing_rate' => 0,
            'success_rate' => 0,
        ];

        if ($campaign_id !== null) {
            $total_processed = (int) get_post_meta($campaign_id, '_abc_total_processed', true);
            $total_published = (int) get_post_meta($campaign_id, '_abc_total_published', true);

            $stats['total_processed'] = $total_processed;
            $stats['total_published'] = $total_published;
            $stats['success_rate'] = $total_processed > 0 ? 
                round(($total_published / $total_processed) * 100, 2) : 0;
        }

        return $stats;
    }

    /**
     * Retry failed items
     *
     * Resets failed items back to pending for retry.
     *
     * @since 2.0.0
     * @param int|null $campaign_id Optional campaign ID filter.
     * @param int $limit Maximum items to retry (default 50).
     * @return int Number of items reset.
     */
    public function retry_failed($campaign_id = null, $limit = 50) {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_queue';
        $limit = absint($limit);

        $sql = "UPDATE {$table} SET status = 'pending', error_message = NULL WHERE status = 'failed'";

        if ($campaign_id !== null) {
            $sql .= $wpdb->prepare(' AND campaign_id = %d', absint($campaign_id));
        }

        $sql .= " LIMIT {$limit}";

        $result = $wpdb->query($sql);

        if ($result > 0) {
            $this->logger->info("Reset {$result} failed items for retry");
        }

        return $result;
    }

    /**
     * Get queue manager
     *
     * @since 2.0.0
     * @return Queue_Manager
     */
    public function get_queue_manager() {
        return $this->queue_manager;
    }
}
