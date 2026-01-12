<?php
/**
 * Queue Processor
 *
 * Dedicated batch processing system for queue items with configurable batch sizes,
 * error handling, retry logic, and processing statistics.
 *
 * @package AutoBlogCraft\Processing
 * @since 2.0.0
 */

namespace AutoBlogCraft\Processing;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\Core\Rate_Limiter;
use AutoBlogCraft\Campaigns\Campaign_Factory;
use AutoBlogCraft\Discovery\Queue_Manager;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Queue Processor class
 *
 * Handles batch processing of queue items with sophisticated error handling,
 * retry logic, and performance optimization.
 *
 * @since 2.0.0
 */
class Queue_Processor {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Queue manager instance
     *
     * @var Queue_Manager
     */
    private $queue_manager;

    /**
     * Rate limiter instance
     *
     * @var Rate_Limiter
     */
    private $rate_limiter;

    /**
     * Processing statistics
     *
     * @var array
     */
    private $stats = [
        'processed' => 0,
        'succeeded' => 0,
        'failed' => 0,
        'skipped' => 0,
        'start_time' => 0,
        'end_time' => 0,
    ];

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->logger = Logger::instance();
        $this->queue_manager = Queue_Manager::get_instance();
        $this->rate_limiter = new Rate_Limiter();
    }

    /**
     * Process batch of queue items
     *
     * Main entry point for batch processing with complete workflow.
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @param int $batch_size Number of items to process.
     * @param array $options Processing options.
     * @return array|WP_Error Processing results or error.
     */
    public function process_batch($campaign_id, $batch_size = 10, $options = []) {
        $this->stats['start_time'] = microtime(true);
        
        $this->logger->info("Starting batch processing for campaign {$campaign_id}", [
            'batch_size' => $batch_size,
            'options' => $options,
        ]);

        // Get campaign
        $campaign = Campaign_Factory::create($campaign_id);
        if (is_wp_error($campaign)) {
            return $campaign;
        }

        // Check if campaign is active
        $status = get_post_meta($campaign_id, '_campaign_status', true);
        if ($status !== 'active') {
            return new WP_Error(
                'campaign_not_active',
                "Campaign {$campaign_id} is not active (status: {$status})"
            );
        }

        // Get pending items
        $items = $this->get_pending_items($campaign_id, $batch_size, $options);
        if (empty($items)) {
            $this->logger->info("No pending items for campaign {$campaign_id}");
            return [
                'processed' => 0,
                'message' => 'No pending items to process',
            ];
        }

        $this->logger->info("Found " . count($items) . " pending items to process");

        // Process each item
        foreach ($items as $item) {
            $result = $this->process_item($item, $campaign, $options);
            
            $this->stats['processed']++;
            
            if (is_wp_error($result)) {
                $this->stats['failed']++;
                $this->logger->error("Failed to process item {$item->id}: " . $result->get_error_message());
            } else {
                $this->stats['succeeded']++;
            }

            // Check rate limits
            if ($this->should_throttle($campaign_id)) {
                $this->logger->info("Rate limit reached, pausing batch processing");
                break;
            }

            // Optional delay between items
            $delay = !empty($options['delay']) ? (int) $options['delay'] : 0;
            if ($delay > 0) {
                sleep($delay);
            }
        }

        $this->stats['end_time'] = microtime(true);

        // Update campaign last processing run
        update_post_meta($campaign_id, '_last_processing_run', current_time('mysql'));

        return $this->get_processing_summary();
    }

    /**
     * Process single queue item
     *
     * @since 2.0.0
     * @param object $item Queue item from database.
     * @param object $campaign Campaign instance.
     * @param array $options Processing options.
     * @return int|WP_Error Post ID or error.
     */
    private function process_item($item, $campaign, $options = []) {
        $item_id = $item->id;

        $this->logger->info("Processing queue item {$item_id}", [
            'campaign_id' => $campaign->get_id(),
            'source_url' => $item->source_url,
            'item_type' => $item->item_type,
        ]);

        // Mark as processing
        $this->update_item_status($item_id, 'processing');

        try {
            // Get appropriate processor
            $processor = $this->get_processor($campaign, $item);
            if (is_wp_error($processor)) {
                $this->handle_processing_error($item_id, $processor);
                return $processor;
            }

            // Process the item
            $result = $processor->process($item);

            if (is_wp_error($result)) {
                $this->handle_processing_error($item_id, $result);
                return $result;
            }

            // Mark as completed
            $this->update_item_status($item_id, 'completed', [
                'post_id' => $result,
                'processed_at' => current_time('mysql'),
            ]);

            $this->logger->info("Successfully processed item {$item_id} → Post {$result}");

            return $result;

        } catch (\Exception $e) {
            $error = new WP_Error(
                'processing_exception',
                'Processing failed: ' . $e->getMessage(),
                ['exception' => $e]
            );
            
            $this->handle_processing_error($item_id, $error);
            return $error;
        }
    }

    /**
     * Get pending items from queue
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @param int $limit Number of items to fetch.
     * @param array $options Query options.
     * @return array Array of queue items.
     */
    private function get_pending_items($campaign_id, $limit = 10, $options = []) {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_discovery_queue';

        $where_clauses = ["campaign_id = %d"];
        $where_values = [$campaign_id];

        // Status filter
        $status = !empty($options['status']) ? $options['status'] : 'pending';
        $where_clauses[] = "status = %s";
        $where_values[] = $status;

        // Priority filter (optional)
        if (isset($options['min_priority'])) {
            $where_clauses[] = "priority >= %d";
            $where_values[] = (int) $options['min_priority'];
        }

        // Order by priority (highest first), then oldest first
        $order_by = !empty($options['order_by']) 
            ? $options['order_by'] 
            : 'priority DESC, discovered_at ASC';

        $where = implode(' AND ', $where_clauses);
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE {$where} 
            ORDER BY {$order_by} 
            LIMIT %d",
            array_merge($where_values, [$limit])
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get processor for item
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @param object $item Queue item.
     * @return Base_Processor|WP_Error Processor instance or error.
     */
    private function get_processor($campaign, $item) {
        $processor_class = $campaign->get_processor_class();

        if (!class_exists($processor_class)) {
            return new WP_Error(
                'processor_not_found',
                "Processor class not found: {$processor_class}"
            );
        }

        return new $processor_class();
    }

    /**
     * Update item status
     *
     * @since 2.0.0
     * @param int $item_id Queue item ID.
     * @param string $status New status.
     * @param array $data Additional data to update.
     * @return bool True on success, false on failure.
     */
    private function update_item_status($item_id, $status, $data = []) {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_discovery_queue';

        $update_data = ['status' => $status];

        // Add optional fields
        if (isset($data['post_id'])) {
            $update_data['post_id'] = (int) $data['post_id'];
        }

        if (isset($data['processed_at'])) {
            $update_data['processed_at'] = $data['processed_at'];
        }

        if (isset($data['error_message'])) {
            $update_data['error_message'] = $data['error_message'];
        }

        if (isset($data['retry_count'])) {
            $update_data['retry_count'] = (int) $data['retry_count'];
        }

        return $wpdb->update(
            $table,
            $update_data,
            ['id' => $item_id],
            null,
            ['%d']
        ) !== false;
    }

    /**
     * Handle processing error
     *
     * @since 2.0.0
     * @param int $item_id Queue item ID.
     * @param WP_Error $error Error object.
     * @return void
     */
    private function handle_processing_error($item_id, $error) {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_discovery_queue';

        // Get current retry count
        $retry_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT retry_count FROM {$table} WHERE id = %d",
            $item_id
        ));

        $max_retries = apply_filters('abc_queue_max_retries', 3);

        if ($retry_count < $max_retries) {
            // Increment retry and mark as pending
            $this->update_item_status($item_id, 'pending', [
                'retry_count' => $retry_count + 1,
                'error_message' => $error->get_error_message(),
            ]);

            $this->logger->warning("Item {$item_id} failed (retry {$retry_count}/{$max_retries}): " . $error->get_error_message());
        } else {
            // Max retries exceeded, mark as failed
            $this->update_item_status($item_id, 'failed', [
                'error_message' => $error->get_error_message(),
            ]);

            $this->logger->error("Item {$item_id} permanently failed after {$max_retries} retries: " . $error->get_error_message());
        }
    }

    /**
     * Check if processing should be throttled
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return bool True if should throttle, false otherwise.
     */
    private function should_throttle($campaign_id) {
        // Check daily post limit
        $max_posts_per_day = (int) get_post_meta($campaign_id, '_max_posts_per_day', true);
        
        if ($max_posts_per_day > 0) {
            $posts_today = $this->get_posts_published_today($campaign_id);
            
            if ($posts_today >= $max_posts_per_day) {
                $this->logger->info("Daily post limit reached ({$posts_today}/{$max_posts_per_day})");
                return true;
            }
        }

        // Check queue size limit
        $max_queue_size = (int) get_post_meta($campaign_id, '_max_queue_size', true);
        
        if ($max_queue_size > 0) {
            $current_queue_size = $this->get_pending_count($campaign_id);
            
            if ($current_queue_size >= $max_queue_size) {
                $this->logger->info("Queue size limit reached ({$current_queue_size}/{$max_queue_size})");
                return true;
            }
        }

        return false;
    }

    /**
     * Get posts published today
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return int Number of posts published today.
     */
    private function get_posts_published_today($campaign_id) {
        global $wpdb;

        $today_start = gmdate('Y-m-d 00:00:00');
        $today_end = gmdate('Y-m-d 23:59:59');

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE pm.meta_key = '_abc_campaign_id'
            AND pm.meta_value = %d
            AND p.post_date >= %s
            AND p.post_date <= %s
            AND p.post_status = 'publish'",
            $campaign_id,
            $today_start,
            $today_end
        ));
    }

    /**
     * Get pending queue count
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return int Number of pending items.
     */
    private function get_pending_count($campaign_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_discovery_queue';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
            WHERE campaign_id = %d AND status = 'pending'",
            $campaign_id
        ));
    }

    /**
     * Get processing summary
     *
     * @since 2.0.0
     * @return array Processing statistics.
     */
    private function get_processing_summary() {
        $duration = $this->stats['end_time'] - $this->stats['start_time'];
        $items_per_second = $duration > 0 
            ? round($this->stats['processed'] / $duration, 2) 
            : 0;

        return [
            'processed' => $this->stats['processed'],
            'succeeded' => $this->stats['succeeded'],
            'failed' => $this->stats['failed'],
            'skipped' => $this->stats['skipped'],
            'duration' => round($duration, 2),
            'items_per_second' => $items_per_second,
            'success_rate' => $this->stats['processed'] > 0 
                ? round(($this->stats['succeeded'] / $this->stats['processed']) * 100, 2) 
                : 0,
        ];
    }

    /**
     * Reprocess failed items
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @param int $limit Number of items to reprocess.
     * @return array|WP_Error Processing results or error.
     */
    public function reprocess_failed($campaign_id, $limit = 10) {
        $this->logger->info("Reprocessing failed items for campaign {$campaign_id}");

        // Reset retry count for failed items
        global $wpdb;
        $table = $wpdb->prefix . 'abc_discovery_queue';

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} 
            SET status = 'pending', retry_count = 0, error_message = NULL 
            WHERE campaign_id = %d AND status = 'failed' 
            LIMIT %d",
            $campaign_id,
            $limit
        ));

        // Process the batch
        return $this->process_batch($campaign_id, $limit);
    }

    /**
     * Clean up old completed items
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @param int $days_old Number of days to keep completed items.
     * @return int Number of items deleted.
     */
    public function cleanup_completed($campaign_id, $days_old = 30) {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_discovery_queue';
        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$days_old} days"));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} 
            WHERE campaign_id = %d 
            AND status = 'completed' 
            AND processed_at < %s",
            $campaign_id,
            $cutoff_date
        ));

        $this->logger->info("Cleaned up {$deleted} completed items older than {$days_old} days");

        return (int) $deleted;
    }

    /**
     * Get queue statistics
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return array Queue statistics.
     */
    public function get_queue_stats($campaign_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_discovery_queue';

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                AVG(priority) as avg_priority
            FROM {$table}
            WHERE campaign_id = %d",
            $campaign_id
        ), ARRAY_A);

        return $stats;
    }
}
