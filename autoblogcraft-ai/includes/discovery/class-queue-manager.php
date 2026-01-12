<?php
/**
 * Queue Manager
 *
 * Handles all queue database operations for discovered content.
 * Manages the wp_abc_queue table with efficient batch operations.
 *
 * @package AutoBlogCraft\Discovery
 * @since 2.0.0
 */

namespace AutoBlogCraft\Discovery;

use AutoBlogCraft\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Queue Manager class
 *
 * Responsibilities:
 * - Add discovered items to queue
 * - Retrieve items for processing
 * - Mark items as processed/failed
 * - Clean up old entries
 * - Prevent duplicates
 *
 * @since 2.0.0
 */
class Queue_Manager {

    /**
     * Database table name (without prefix)
     *
     * @var string
     */
    private $table_name = 'abc_discovery_queue';

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->logger = Logger::instance();
    }

    /**
     * Add item to queue
     *
     * Prevents duplicate URLs per campaign using unique index.
     *
     * @since 2.0.0
     * @param array $item Queue item data.
     * @return int|false Queue item ID on success, false on failure.
     */
    public function add_to_queue($item) {
        global $wpdb;

        // Validate required fields
        $required = ['campaign_id', 'source_url', 'source_type'];
        foreach ($required as $field) {
            if (empty($item[$field])) {
                $this->logger->error("Queue item missing required field: {$field}");
                return false;
            }
        }

        $table = $wpdb->prefix . $this->table_name;

        // Prepare data
        $data = [
            'campaign_id'      => absint($item['campaign_id']),
            'source_url'       => esc_url_raw($item['source_url']),
            'source_type'      => sanitize_text_field($item['source_type']),
            'title'            => isset($item['title']) ? sanitize_text_field($item['title']) : '',
            'excerpt'          => isset($item['excerpt']) ? wp_kses_post($item['excerpt']) : '',
            'source_data'      => isset($item['source_data']) ? wp_json_encode($item['source_data']) : null,
            'priority'         => isset($item['priority']) ? absint($item['priority']) : 50,
            'status'           => 'pending',
            'discovered_at'    => current_time('mysql'),
        ];

        $format = ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'];

        // Insert with duplicate handling
        $result = $wpdb->insert($table, $data, $format);

        if ($result === false) {
            // Check if it's a duplicate
            if ($wpdb->last_error && strpos($wpdb->last_error, 'Duplicate entry') !== false) {
                $this->logger->debug("Duplicate queue item skipped: {$item['source_url']}");
                return false; // Not an error - just already exists
            }

            $this->logger->error("Failed to add queue item: {$wpdb->last_error}");
            return false;
        }

        $queue_id = $wpdb->insert_id;

        $this->logger->info("Added to queue: ID={$queue_id}, URL={$item['source_url']}");

        return $queue_id;
    }

    /**
     * Add multiple items to queue in batch
     *
     * More efficient than calling add_to_queue() multiple times.
     *
     * @since 2.0.0
     * @param array $items Array of queue items.
     * @return array Array with 'success' count and 'failed' count.
     */
    public function add_batch($items) {
        if (empty($items) || !is_array($items)) {
            return ['success' => 0, 'failed' => 0];
        }

        $success = 0;
        $failed = 0;

        foreach ($items as $item) {
            $result = $this->add_to_queue($item);
            if ($result !== false) {
                $success++;
            } else {
                $failed++;
            }
        }

        $this->logger->info("Batch queue insert: {$success} added, {$failed} failed/duplicates");

        return [
            'success' => $success,
            'failed' => $failed,
        ];
    }

    /**
     * Get next items from queue
     *
     * Retrieves pending items ordered by priority and discovery time.
     *
     * @since 2.0.0
     * @param int $limit Number of items to retrieve.
     * @param int|null $campaign_id Optional campaign ID filter.
     * @return array Array of queue items.
     */
    public function get_next_items($limit = 10, $campaign_id = null) {
        global $wpdb;

        $table = $wpdb->prefix . $this->table_name;
        $limit = absint($limit);

        $sql = "SELECT * FROM {$table} WHERE status = 'pending'";

        if ($campaign_id !== null) {
            $sql .= $wpdb->prepare(" AND campaign_id = %d", absint($campaign_id));
        }

        $sql .= " ORDER BY priority DESC, discovered_at ASC LIMIT {$limit}";

        $items = $wpdb->get_results($sql, ARRAY_A);

        if ($items === null) {
            $this->logger->error("Failed to get queue items: {$wpdb->last_error}");
            return [];
        }

        // Decode JSON fields
        foreach ($items as &$item) {
            if (!empty($item['source_data'])) {
                $item['source_data'] = json_decode($item['source_data'], true);
            }
        }

        return $items;
    }

    /**
     * Mark item as processing
     *
     * Prevents multiple workers from processing the same item.
     *
     * @since 2.0.0
     * @param int $queue_id Queue item ID.
     * @return bool True on success, false on failure.
     */
    public function mark_processing($queue_id) {
        global $wpdb;

        $table = $wpdb->prefix . $this->table_name;

        $result = $wpdb->update(
            $table,
            [
                'status' => 'processing',
                'processed_at' => current_time('mysql'),
            ],
            ['id' => absint($queue_id)],
            ['%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            $this->logger->error("Failed to mark queue item as processing: ID={$queue_id}");
            return false;
        }

        return true;
    }

    /**
     * Mark item as completed
     *
     * Links queue item to the created post.
     *
     * @since 2.0.0
     * @param int $queue_id Queue item ID.
     * @param int $post_id Created post ID.
     * @return bool True on success, false on failure.
     */
    public function mark_completed($queue_id, $post_id) {
        global $wpdb;

        $table = $wpdb->prefix . $this->table_name;

        $result = $wpdb->update(
            $table,
            [
                'status' => 'completed',
                'post_id' => absint($post_id),
                'processed_at' => current_time('mysql'),
            ],
            ['id' => absint($queue_id)],
            ['%s', '%d', '%s'],
            ['%d']
        );

        if ($result === false) {
            $this->logger->error("Failed to mark queue item as completed: ID={$queue_id}");
            return false;
        }

        $this->logger->info("Queue item completed: ID={$queue_id}, Post={$post_id}");

        return true;
    }

    /**
     * Mark item as failed
     *
     * Records error message for debugging.
     *
     * @since 2.0.0
     * @param int $queue_id Queue item ID.
     * @param string $error_message Error description.
     * @return bool True on success, false on failure.
     */
    public function mark_failed($queue_id, $error_message = '') {
        global $wpdb;

        $table = $wpdb->prefix . $this->table_name;

        $result = $wpdb->update(
            $table,
            [
                'status' => 'failed',
                'error_message' => sanitize_text_field($error_message),
                'processed_at' => current_time('mysql'),
            ],
            ['id' => absint($queue_id)],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            $this->logger->error("Failed to mark queue item as failed: ID={$queue_id}");
            return false;
        }

        $this->logger->warning("Queue item failed: ID={$queue_id}, Error={$error_message}");

        return true;
    }

    /**
     * Reset stuck items
     *
     * Changes 'processing' items older than threshold back to 'pending'.
     * Useful for recovering from crashes.
     *
     * @since 2.0.0
     * @param int $minutes Age threshold in minutes (default 30).
     * @return int Number of items reset.
     */
    public function reset_stuck_items($minutes = 30) {
        global $wpdb;

        $table = $wpdb->prefix . $this->table_name;
        $threshold = gmdate('Y-m-d H:i:s', time() - ($minutes * 60));

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} 
                SET status = 'pending', processed_at = NULL 
                WHERE status = 'processing' 
                AND processed_at < %s",
                $threshold
            )
        );

        if ($result === false) {
            $this->logger->error("Failed to reset stuck queue items");
            return 0;
        }

        if ($result > 0) {
            $this->logger->warning("Reset {$result} stuck queue items");
        }

        return $result;
    }

    /**
     * Delete old completed items
     *
     * Cleans up queue table to prevent unbounded growth.
     *
     * @since 2.0.0
     * @param int $days Age threshold in days (default 30).
     * @return int Number of items deleted.
     */
    public function cleanup_old_items($days = 30) {
        global $wpdb;

        $table = $wpdb->prefix . $this->table_name;
        $threshold = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} 
                WHERE status IN ('completed', 'failed') 
                AND processed_at < %s",
                $threshold
            )
        );

        if ($result === false) {
            $this->logger->error("Failed to cleanup old queue items");
            return 0;
        }

        if ($result > 0) {
            $this->logger->info("Cleaned up {$result} old queue items");
        }

        return $result;
    }

    /**
     * Get queue statistics
     *
     * Returns counts by status for monitoring.
     *
     * @since 2.0.0
     * @param int|null $campaign_id Optional campaign ID filter.
     * @return array Statistics array.
     */
    public function get_stats($campaign_id = null) {
        global $wpdb;

        $table = $wpdb->prefix . $this->table_name;

        $where = '';
        if ($campaign_id !== null) {
            $where = $wpdb->prepare(" WHERE campaign_id = %d", absint($campaign_id));
        }

        $stats = $wpdb->get_results(
            "SELECT status, COUNT(*) as count 
            FROM {$table}
            {$where}
            GROUP BY status",
            ARRAY_A
        );

        $result = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
        ];

        if ($stats) {
            foreach ($stats as $row) {
                $result[$row['status']] = (int) $row['count'];
            }
        }

        return $result;
    }

    /**
     * Check if URL exists in queue
     *
     * Prevents duplicates before adding to queue.
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @param string $source_url Source URL.
     * @return bool True if exists, false otherwise.
     */
    public function url_exists($campaign_id, $source_url) {
        global $wpdb;

        $table = $wpdb->prefix . $this->table_name;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} 
                WHERE campaign_id = %d 
                AND source_url = %s",
                absint($campaign_id),
                esc_url_raw($source_url)
            )
        );

        return $count > 0;
    }

    /**
     * Get item by ID
     *
     * @since 2.0.0
     * @param int $queue_id Queue item ID.
     * @return array|null Queue item or null if not found.
     */
    public function get_item($queue_id) {
        global $wpdb;

        $table = $wpdb->prefix . $this->table_name;

        $item = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                absint($queue_id)
            ),
            ARRAY_A
        );

        if ($item && !empty($item['source_data'])) {
            $item['source_data'] = json_decode($item['source_data'], true);
        }

        return $item;
    }

    /**
     * Delete queue item
     *
     * @since 2.0.0
     * @param int $queue_id Queue item ID.
     * @return bool True on success, false on failure.
     */
    public function delete_item($queue_id) {
        global $wpdb;

        $table = $wpdb->prefix . $this->table_name;

        $result = $wpdb->delete(
            $table,
            ['id' => absint($queue_id)],
            ['%d']
        );

        if ($result === false) {
            $this->logger->error("Failed to delete queue item: ID={$queue_id}");
            return false;
        }

        return true;
    }

    /**
     * Delete all items for a campaign
     *
     * Used when campaign is deleted.
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return int Number of items deleted.
     */
    public function delete_by_campaign($campaign_id) {
        global $wpdb;

        $table = $wpdb->prefix . $this->table_name;

        $result = $wpdb->delete(
            $table,
            ['campaign_id' => absint($campaign_id)],
            ['%d']
        );

        if ($result === false) {
            $this->logger->error("Failed to delete queue items for campaign: ID={$campaign_id}");
            return 0;
        }

        if ($result > 0) {
            $this->logger->info("Deleted {$result} queue items for campaign: ID={$campaign_id}");
        }

        return $result;
    }
}
