<?php
/**
 * Campaigns Endpoint
 *
 * REST API endpoint for campaign management (CRUD operations).
 *
 * @package AutoBlogCraft\API\Endpoints
 * @since 2.0.0
 */

namespace AutoBlogCraft\API\Endpoints;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\API\Auth_Handler;
use AutoBlogCraft\Campaigns\Campaign_Factory;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Campaigns Endpoint class
 *
 * Handles campaign CRUD operations via REST API.
 *
 * @since 2.0.0
 */
class Campaigns_Endpoint {

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
     * Campaign factory
     *
     * @var Campaign_Factory
     */
    private $factory;

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
        
        // Ensure Campaign_Factory is loaded
        if (!class_exists('AutoBlogCraft\Campaigns\Campaign_Factory')) {
            require_once ABC_PLUGIN_DIR . 'includes/campaigns/class-campaign-factory.php';
        }
        
        $this->factory = new Campaign_Factory();
    }

    /**
     * Register routes
     *
     * @since 2.0.0
     */
    public function register_routes() {
        // List campaigns
        register_rest_route($this->namespace, '/campaigns', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_campaigns'],
            'permission_callback' => [$this->auth, 'check_read_permission'],
            'args' => $this->get_collection_params(),
        ]);

        // Create campaign
        register_rest_route($this->namespace, '/campaigns', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'create_campaign'],
            'permission_callback' => [$this->auth, 'check_admin_permission'],
            'args' => $this->get_create_params(),
        ]);

        // Get single campaign
        register_rest_route($this->namespace, '/campaigns/(?P<id>[\d]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_campaign'],
            'permission_callback' => [$this->auth, 'check_read_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Campaign ID',
                ],
            ],
        ]);

        // Update campaign
        register_rest_route($this->namespace, '/campaigns/(?P<id>[\d]+)', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'update_campaign'],
            'permission_callback' => [$this->auth, 'check_admin_permission'],
            'args' => $this->get_update_params(),
        ]);

        // Delete campaign
        register_rest_route($this->namespace, '/campaigns/(?P<id>[\d]+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [$this, 'delete_campaign'],
            'permission_callback' => [$this->auth, 'check_admin_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Campaign ID',
                ],
                'force' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Force delete (skip trash)',
                ],
            ],
        ]);

        // Pause/Resume campaign
        register_rest_route($this->namespace, '/campaigns/(?P<id>[\d]+)/(?P<action>pause|resume)', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'toggle_campaign_status'],
            'permission_callback' => [$this->auth, 'check_admin_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
                'action' => [
                    'required' => true,
                    'enum' => ['pause', 'resume'],
                ],
            ],
        ]);
    }

    /**
     * Get campaigns
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function get_campaigns($request) {
        $page = $request->get_param('page') ?? 1;
        $per_page = $request->get_param('per_page') ?? 10;
        $type = $request->get_param('type');
        $status = $request->get_param('status');

        $args = [
            'post_type' => 'abc_campaign',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'meta_query' => [],
        ];

        if ($type) {
            $args['meta_query'][] = [
                'key' => '_campaign_type',
                'value' => $type,
            ];
        }

        if ($status) {
            $args['meta_query'][] = [
                'key' => '_campaign_status',
                'value' => $status,
            ];
        }

        $query = new \WP_Query($args);

        $campaigns = [];
        foreach ($query->posts as $post) {
            $campaigns[] = $this->prepare_campaign_response($post->ID);
        }

        $response = new WP_REST_Response($campaigns);
        $response->header('X-Total-Count', $query->found_posts);
        $response->header('X-Total-Pages', $query->max_num_pages);

        return $response;
    }

    /**
     * Get campaign
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function get_campaign($request) {
        $campaign_id = $request->get_param('id');

        if (!$this->campaign_exists($campaign_id)) {
            return new WP_Error(
                'campaign_not_found',
                'Campaign not found',
                ['status' => 404]
            );
        }

        return new WP_REST_Response(
            $this->prepare_campaign_response($campaign_id)
        );
    }

    /**
     * Create campaign
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function create_campaign($request) {
        $name = $request->get_param('name');
        $type = $request->get_param('type');
        $config = $request->get_param('config') ?? [];

        // Create campaign post
        $campaign_id = wp_insert_post([
            'post_title' => $name,
            'post_type' => 'abc_campaign',
            'post_status' => 'publish',
        ]);

        if (is_wp_error($campaign_id)) {
            return $campaign_id;
        }

        // Save campaign type
        update_post_meta($campaign_id, '_campaign_type', $type);
        update_post_meta($campaign_id, '_campaign_status', 'active');

        // Save configuration
        foreach ($config as $key => $value) {
            update_post_meta($campaign_id, '_' . $key, $value);
        }

        $this->logger->info('Campaign created via API', [
            'campaign_id' => $campaign_id,
            'type' => $type,
        ]);

        return new WP_REST_Response(
            $this->prepare_campaign_response($campaign_id),
            201
        );
    }

    /**
     * Update campaign
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function update_campaign($request) {
        $campaign_id = $request->get_param('id');

        if (!$this->campaign_exists($campaign_id)) {
            return new WP_Error(
                'campaign_not_found',
                'Campaign not found',
                ['status' => 404]
            );
        }

        $updates = [];

        // Update name
        if ($request->has_param('name')) {
            $updates['ID'] = $campaign_id;
            $updates['post_title'] = $request->get_param('name');
        }

        if (!empty($updates)) {
            wp_update_post($updates);
        }

        // Update configuration
        $config = $request->get_param('config');
        if (is_array($config)) {
            foreach ($config as $key => $value) {
                update_post_meta($campaign_id, '_' . $key, $value);
            }
        }

        $this->logger->info('Campaign updated via API', [
            'campaign_id' => $campaign_id,
        ]);

        return new WP_REST_Response(
            $this->prepare_campaign_response($campaign_id)
        );
    }

    /**
     * Delete campaign
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function delete_campaign($request) {
        $campaign_id = $request->get_param('id');
        $force = $request->get_param('force');

        if (!$this->campaign_exists($campaign_id)) {
            return new WP_Error(
                'campaign_not_found',
                'Campaign not found',
                ['status' => 404]
            );
        }

        $deleted = wp_delete_post($campaign_id, $force);

        if (!$deleted) {
            return new WP_Error(
                'delete_failed',
                'Failed to delete campaign',
                ['status' => 500]
            );
        }

        $this->logger->info('Campaign deleted via API', [
            'campaign_id' => $campaign_id,
            'force' => $force,
        ]);

        return new WP_REST_Response([
            'deleted' => true,
            'campaign_id' => $campaign_id,
        ]);
    }

    /**
     * Toggle campaign status
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function toggle_campaign_status($request) {
        $campaign_id = $request->get_param('id');
        $action = $request->get_param('action');

        if (!$this->campaign_exists($campaign_id)) {
            return new WP_Error(
                'campaign_not_found',
                'Campaign not found',
                ['status' => 404]
            );
        }

        $new_status = $action === 'pause' ? 'paused' : 'active';
        update_post_meta($campaign_id, '_campaign_status', $new_status);

        $this->logger->info("Campaign {$action}d via API", [
            'campaign_id' => $campaign_id,
            'status' => $new_status,
        ]);

        return new WP_REST_Response([
            'success' => true,
            'campaign_id' => $campaign_id,
            'status' => $new_status,
        ]);
    }

    /**
     * Prepare campaign response
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return array Campaign data.
     */
    private function prepare_campaign_response($campaign_id) {
        $post = get_post($campaign_id);
        
        $campaign = [
            'id' => $campaign_id,
            'name' => $post->post_title,
            'type' => get_post_meta($campaign_id, '_campaign_type', true),
            'status' => get_post_meta($campaign_id, '_campaign_status', true),
            'created_at' => $post->post_date,
            'modified_at' => $post->post_modified,
        ];

        // Add configuration
        $config_keys = [
            'discovery_interval', 'processing_interval', 'post_status',
            'sources', 'ai_model', 'writing_style', 'content_length',
        ];

        $campaign['config'] = [];
        foreach ($config_keys as $key) {
            $value = get_post_meta($campaign_id, '_' . $key, true);
            if (!empty($value)) {
                $campaign['config'][$key] = $value;
            }
        }

        // Add statistics
        $campaign['stats'] = [
            'total_discovered' => $this->get_discovered_count($campaign_id),
            'total_processed' => $this->get_processed_count($campaign_id),
            'total_published' => $this->get_published_count($campaign_id),
        ];

        return $campaign;
    }

    /**
     * Check if campaign exists
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return bool True if exists.
     */
    private function campaign_exists($campaign_id) {
        $post = get_post($campaign_id);
        return $post && $post->post_type === 'abc_campaign';
    }

    /**
     * Get discovered count
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return int Count.
     */
    private function get_discovered_count($campaign_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'abc_queue';
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE campaign_id = %d",
            $campaign_id
        ));
    }

    /**
     * Get processed count
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return int Count.
     */
    private function get_processed_count($campaign_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'abc_queue';
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE campaign_id = %d AND status = 'processed'",
            $campaign_id
        ));
    }

    /**
     * Get published count
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return int Count.
     */
    private function get_published_count($campaign_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'post' 
             AND post_status = 'publish'
             AND ID IN (
                 SELECT post_id FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_abc_campaign_id' AND meta_value = %d
             )",
            $campaign_id
        ));
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
            'type' => [
                'type' => 'string',
                'enum' => ['website', 'youtube', 'amazon', 'news'],
                'description' => 'Filter by campaign type',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['active', 'paused', 'completed'],
                'description' => 'Filter by campaign status',
            ],
        ];
    }

    /**
     * Get create params
     *
     * @since 2.0.0
     * @return array Parameters.
     */
    private function get_create_params() {
        return [
            'name' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Campaign name',
            ],
            'type' => [
                'required' => true,
                'type' => 'string',
                'enum' => ['website', 'youtube', 'amazon', 'news'],
                'description' => 'Campaign type',
            ],
            'config' => [
                'type' => 'object',
                'description' => 'Campaign configuration',
            ],
        ];
    }

    /**
     * Get update params
     *
     * @since 2.0.0
     * @return array Parameters.
     */
    private function get_update_params() {
        return [
            'id' => [
                'required' => true,
                'type' => 'integer',
            ],
            'name' => [
                'type' => 'string',
                'description' => 'Campaign name',
            ],
            'config' => [
                'type' => 'object',
                'description' => 'Campaign configuration',
            ],
        ];
    }
}
