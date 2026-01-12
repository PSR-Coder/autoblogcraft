<?php
/**
 * Queue Endpoint
 *
 * REST API endpoint for queue management.
 *
 * @package AutoBlogCraft\API\Endpoints
 * @since 2.0.0
 */

namespace AutoBlogCraft\API\Endpoints;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\API\Auth_Handler;
use AutoBlogCraft\Discovery\Queue_Manager;
use AutoBlogCraft\Processing\Processing_Manager;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Queue Endpoint class
 *
 * Handles queue operations via REST API.
 *
 * @since 2.0.0
 */
class Queue_Endpoint {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Auth handler
     *
     * @var Auth_Handler
     */
    private $auth;

    /**
     * Namespace
     *
     * @var string
     */
    private $namespace;

    /**
     * Queue manager
     *
     * @var Queue_Manager
     */
    private $queue_manager;

    /**
     * Processing manager
     *
     * @var Processing_Manager
     */
    private $processing_manager;

    /**
     * Constructor
     *
     * @since 2.0.0
     * @param string $namespace API namespace.
     * @param Auth_Handler $auth Auth handler.
     */
    public function __construct($namespace, $auth) {
        $this->logger = Logger::instance();
        $this->auth = $auth;
        $this->namespace = $namespace;
        
        // Manual loading to ensure classes are available
        if (!class_exists('AutoBlogCraft\\Discovery\\Queue_Manager')) {
            require_once plugin_dir_path(__FILE__) . '../../discovery/class-queue-manager.php';
        }
        if (!class_exists('AutoBlogCraft\\Processing\\Processing_Manager')) {
            require_once plugin_dir_path(__FILE__) . '../../processing/class-processing-manager.php';
        }
        
        $this->queue_manager = new Queue_Manager();
        $this->processing_manager = Processing_Manager::instance();
    }

    /**
     * Register routes
     *
     * @since 2.0.0
     */
    public function register_routes() {
        // List queue items
        register_rest_route($this->namespace, '/queue', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_queue_items'],
            'permission_callback' => [$this->auth, 'check_read_permission'],
            'args' => $this->get_collection_params(),
        ]);

        // Get single queue item
        register_rest_route($this->namespace, '/queue/(?P<id>[\d]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_queue_item'],
            'permission_callback' => [$this->auth, 'check_read_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Queue item ID',
                ],
            ],
        ]);

        // Process queue item
        register_rest_route($this->namespace, '/queue/(?P<id>[\d]+)/process', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'process_queue_item'],
            'permission_callback' => [$this->auth, 'check_admin_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Queue item ID',
                ],
            ],
        ]);

        // Delete queue item
        register_rest_route($this->namespace, '/queue/(?P<id>[\d]+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [$this, 'delete_queue_item'],
            'permission_callback' => [$this->auth, 'check_admin_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Queue item ID',
                ],
            ],
        ]);

        // Bulk process
        register_rest_route($this->namespace, '/queue/batch-process', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'batch_process'],
            'permission_callback' => [$this->auth, 'check_admin_permission'],
            'args' => [
                'campaign_id' => [
                    'type' => 'integer',
                    'description' => 'Campaign ID to process',
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100,
                    'description' => 'Number of items to process',
                ],
            ],
        ]);

        // Clear queue
        register_rest_route($this->namespace, '/queue/clear', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'clear_queue'],
            'permission_callback' => [$this->auth, 'check_admin_permission'],
            'args' => [
                'campaign_id' => [
                    'type' => 'integer',
                    'description' => 'Campaign ID (optional, clears all if not provided)',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['pending', 'processing', 'processed', 'failed'],
                    'description' => 'Clear only items with this status',
                ],
            ],
        ]);
    }

    /**
     * Get queue items
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function get_queue_items($request) {
        global $wpdb;

        $page = $request->get_param('page') ?? 1;
        $per_page = $request->get_param('per_page') ?? 10;
        $campaign_id = $request->get_param('campaign_id');
        $status = $request->get_param('status');

        $table = $wpdb->prefix . 'abc_queue';
        $where = ['1=1'];
        $values = [];

        if ($campaign_id) {
            $where[] = 'campaign_id = %d';
            $values[] = $campaign_id;
        }

        if ($status) {
            $where[] = 'status = %s';
            $values[] = $status;
        }

        $where_sql = implode(' AND ', $where);
        
        // Get total count
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}",
            ...$values
        ));

        // Get items
        $offset = ($page - 1) * $per_page;
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE {$where_sql}
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            ...array_merge($values, [$per_page, $offset])
        ), ARRAY_A);

        // Prepare response
        foreach ($items as &$item) {
            $item['data'] = json_decode($item['data'], true);
        }

        $response = new WP_REST_Response($items);
        $response->header('X-Total-Count', $total);
        $response->header('X-Total-Pages', ceil($total / $per_page));

        return $response;
    }

    /**
     * Get queue item
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function get_queue_item($request) {
        global $wpdb;

        $id = $request->get_param('id');
        $table = $wpdb->prefix . 'abc_queue';

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A);

        if (!$item) {
            return new WP_Error(
                'item_not_found',
                'Queue item not found',
                ['status' => 404]
            );
        }

        $item['data'] = json_decode($item['data'], true);

        return new WP_REST_Response($item);
    }

    /**
     * Process queue item
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function process_queue_item($request) {
        global $wpdb;

        $id = $request->get_param('id');
        $table = $wpdb->prefix . 'abc_queue';

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A);

        if (!$item) {
            return new WP_Error(
                'item_not_found',
                'Queue item not found',
                ['status' => 404]
            );
        }

        if ($item['status'] === 'processed') {
            return new WP_Error(
                'already_processed',
                'This item has already been processed',
                ['status' => 400]
            );
        }

        // Process item
        $result = $this->processing_manager->process_item($id);

        if (is_wp_error($result)) {
            return $result;
        }

        $this->logger->info('Queue item processed via API', ['id' => $id]);

        return new WP_REST_Response([
            'success' => true,
            'item_id' => $id,
            'result' => $result,
        ]);
    }

    /**
     * Delete queue item
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function delete_queue_item($request) {
        global $wpdb;

        $id = $request->get_param('id');
        $table = $wpdb->prefix . 'abc_queue';

        $deleted = $wpdb->delete($table, ['id' => $id]);

        if (!$deleted) {
            return new WP_Error(
                'delete_failed',
                'Failed to delete queue item',
                ['status' => 500]
            );
        }

        $this->logger->info('Queue item deleted via API', ['id' => $id]);

        return new WP_REST_Response([
            'deleted' => true,
            'item_id' => $id,
        ]);
    }

    /**
     * Batch process
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function batch_process($request) {
        $campaign_id = $request->get_param('campaign_id');
        $limit = $request->get_param('limit') ?? 10;

        global $wpdb;
        $table = $wpdb->prefix . 'abc_queue';

        // Get pending items
        $query = "SELECT id FROM {$table} WHERE status = 'pending'";
        
        if ($campaign_id) {
            $query .= $wpdb->prepare(' AND campaign_id = %d', $campaign_id);
        }

        $query .= $wpdb->prepare(' ORDER BY priority DESC, created_at ASC LIMIT %d', $limit);

        $item_ids = $wpdb->get_col($query);

        $results = [
            'total' => count($item_ids),
            'processed' => 0,
            'failed' => 0,
            'items' => [],
        ];

        foreach ($item_ids as $item_id) {
            $result = $this->processing_manager->process_item($item_id);

            if (is_wp_error($result)) {
                $results['failed']++;
                $results['items'][] = [
                    'id' => $item_id,
                    'success' => false,
                    'error' => $result->get_error_message(),
                ];
            } else {
                $results['processed']++;
                $results['items'][] = [
                    'id' => $item_id,
                    'success' => true,
                    'post_id' => $result,
                ];
            }
        }

        $this->logger->info('Batch processing completed via API', $results);

        return new WP_REST_Response($results);
    }

    /**
     * Clear queue
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function clear_queue($request) {
        global $wpdb;

        $campaign_id = $request->get_param('campaign_id');
        $status = $request->get_param('status');

        $table = $wpdb->prefix . 'abc_queue';
        $where = [];
        $values = [];

        if ($campaign_id) {
            $where[] = 'campaign_id = %d';
            $values[] = $campaign_id;
        }

        if ($status) {
            $where[] = 'status = %s';
            $values[] = $status;
        }

        if (empty($where)) {
            // Delete all
            $deleted = $wpdb->query("DELETE FROM {$table}");
        } else {
            $where_sql = implode(' AND ', $where);
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table} WHERE {$where_sql}",
                ...$values
            ));
        }

        $this->logger->info('Queue cleared via API', [
            'deleted' => $deleted,
            'campaign_id' => $campaign_id,
            'status' => $status,
        ]);

        return new WP_REST_Response([
            'cleared' => true,
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * Get collection params
     *
     * @since 2.0.0
     * @return array Parameters.
     */
    private function get_collection_params() {
        return [
            'page' => [
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
                'description' => 'Page number',
            ],
            'per_page' => [
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100,
                'description' => 'Items per page',
            ],
            'campaign_id' => [
                'type' => 'integer',
                'description' => 'Filter by campaign ID',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['pending', 'processing', 'processed', 'failed'],
                'description' => 'Filter by status',
            ],
        ];
    }
}
