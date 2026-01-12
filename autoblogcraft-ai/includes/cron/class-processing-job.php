<?php
/**
 * Processing Job
 *
 * Scheduled job for processing queued content items.
 * Runs periodically to convert discovered items into published posts.
 *
 * @package AutoBlogCraft\Cron
 * @since 2.0.0
 */

namespace AutoBlogCraft\Cron;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\Processing\Processing_Manager;
use AutoBlogCraft\Discovery\Queue_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Processing Job class
 *
 * Responsibilities:
 * - Process queued items
 * - Respect processing limits
 * - Handle processing errors
 * - Track processing statistics
 *
 * @since 2.0.0
 */
class Processing_Job {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Processing manager
     *
     * @var Processing_Manager
     */
    private $processor;

    /**
     * Queue manager
     *
     * @var Queue_Manager
     */
    private $queue;

    /**
     * Batch size
     *
     * @var int
     */
    private $batch_size = 10;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->logger = Logger::instance();
        $this->processor = new Processing_Manager();
        $this->queue = new Queue_Manager();

        // Allow filtering batch size
        $this->batch_size = apply_filters('abc_processing_batch_size', $this->batch_size);
    }

    /**
     * Execute processing job
     *
     * @since 2.0.0
     * @return array Execution results.
     */
    public function execute() {
        $this->logger->info("Processing job started");

        $start_time = microtime(true);

        // Get queue stats
        $queue_stats = $this->queue->get_stats();

        if ($queue_stats['pending'] === 0) {
            $this->logger->debug("No items in queue to process");
            return [
                'items_total' => 0,
                'items_processed' => 0,
                'items_succeeded' => 0,
                'items_failed' => 0,
            ];
        }

        $this->logger->debug("Queue has {$queue_stats['pending']} pending items");

        // Process batch
        $result = $this->processor->process_batch($this->batch_size);

        $duration = round(microtime(true) - $start_time, 2);

        $this->logger->info("Processing job completed", [
            'duration' => $duration,
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * Process specific campaign
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @param int|null $batch_size Optional batch size.
     * @return array Processing result.
     */
    public function process_campaign($campaign_id, $batch_size = null) {
        $this->logger->info("Manual processing triggered: Campaign ID={$campaign_id}");

        $batch_size = $batch_size ?? $this->batch_size;

        try {
            $result = $this->processor->process_campaign($campaign_id, $batch_size);

            $this->logger->info("Campaign processing completed", [
                'campaign_id' => $campaign_id,
                'result' => $result,
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error("Campaign processing failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get processing status
     *
     * @since 2.0.0
     * @return array Status information.
     */
    public function get_status() {
        $queue_stats = $this->queue->get_stats();

        $status = [
            'queue' => $queue_stats,
            'batch_size' => $this->batch_size,
        ];

        // Estimate time to complete
        if ($queue_stats['pending'] > 0) {
            $batches_needed = ceil($queue_stats['pending'] / $this->batch_size);
            $job_interval = 120; // 2 minutes
            $estimated_seconds = $batches_needed * $job_interval;

            $status['estimated_completion'] = [
                'batches' => $batches_needed,
                'seconds' => $estimated_seconds,
                'human' => $this->format_duration($estimated_seconds),
            ];
        }

        return $status;
    }

    /**
     * Get campaign processing status
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return array Campaign status.
     */
    public function get_campaign_status($campaign_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_discovery_queue';

        // Get counts by status
        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count 
            FROM {$table} 
            WHERE campaign_id = %d 
            GROUP BY status",
            $campaign_id
        ), ARRAY_A);

        $status = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($stats as $stat) {
            $status[$stat['status']] = (int) $stat['count'];
        }

        $status['total'] = array_sum($status);

        // Calculate completion percentage
        if ($status['total'] > 0) {
            $processed = $status['completed'] + $status['failed'] + $status['skipped'];
            $status['completion_percentage'] = round(($processed / $status['total']) * 100, 2);
        } else {
            $status['completion_percentage'] = 0;
        }

        return $status;
    }

    /**
     * Retry failed items
     *
     * @since 2.0.0
     * @param int|null $campaign_id Optional campaign ID.
     * @param int $max_items Maximum items to retry.
     * @return int Number of items reset.
     */
    public function retry_failed($campaign_id = null, $max_items = 100) {
        $this->logger->info("Retrying failed items", [
            'campaign_id' => $campaign_id,
            'max_items' => $max_items,
        ]);

        try {
            $count = $this->processor->retry_failed($campaign_id, $max_items);

            $this->logger->info("Reset {$count} failed items for retry");

            return $count;

        } catch (\Exception $e) {
            $this->logger->error("Failed to retry items: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Clear completed items
     *
     * @since 2.0.0
     * @param int|null $campaign_id Optional campaign ID.
     * @param int $days_old Only clear items older than X days.
     * @return int Number of items deleted.
     */
    public function clear_completed($campaign_id = null, $days_old = 7) {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_discovery_queue';

        $where = ["status = 'completed'"];
        $where[] = $wpdb->prepare("discovered_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $days_old);

        if ($campaign_id) {
            $where[] = $wpdb->prepare("campaign_id = %d", $campaign_id);
        }

        $where_sql = implode(' AND ', $where);

        $count = $wpdb->query("DELETE FROM {$table} WHERE {$where_sql}");

        $this->logger->info("Cleared {$count} completed items");

        return $count;
    }

    /**
     * Pause processing for campaign
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     */
    public function pause_campaign($campaign_id) {
        update_post_meta($campaign_id, '_abc_processing_paused', true);
        $this->logger->info("Paused processing for campaign: ID={$campaign_id}");
    }

    /**
     * Resume processing for campaign
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     */
    public function resume_campaign($campaign_id) {
        delete_post_meta($campaign_id, '_abc_processing_paused');
        $this->logger->info("Resumed processing for campaign: ID={$campaign_id}");
    }

    /**
     * Check if campaign processing is paused
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return bool True if paused.
     */
    public function is_paused($campaign_id) {
        return (bool) get_post_meta($campaign_id, '_abc_processing_paused', true);
    }

    /**
     * Format duration in human-readable format
     *
     * @since 2.0.0
     * @param int $seconds Duration in seconds.
     * @return string Formatted duration.
     */
    private function format_duration($seconds) {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        }

        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        }

        if ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            
            $parts = [$hours . ' hour' . ($hours > 1 ? 's' : '')];
            if ($minutes > 0) {
                $parts[] = $minutes . ' minute' . ($minutes > 1 ? 's' : '');
            }
            
            return implode(' ', $parts);
        }

        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        
        $parts = [$days . ' day' . ($days > 1 ? 's' : '')];
        if ($hours > 0) {
            $parts[] = $hours . ' hour' . ($hours > 1 ? 's' : '');
        }
        
        return implode(' ', $parts);
    }

    /**
     * Set batch size
     *
     * @since 2.0.0
     * @param int $size Batch size.
     */
    public function set_batch_size($size) {
        $this->batch_size = max(1, min(100, (int) $size));
    }
}
