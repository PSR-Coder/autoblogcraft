<?php
/**
 * REST Controller
 *
 * Main controller for WordPress REST API integration.
 * Registers all API routes and initializes endpoints.
 *
 * @package AutoBlogCraft\API
 * @since 2.0.0
 */

namespace AutoBlogCraft\API;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\API\Endpoints\Campaigns_Endpoint;
use AutoBlogCraft\API\Endpoints\Queue_Endpoint;
use AutoBlogCraft\API\Endpoints\Stats_Endpoint;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST Controller class
 *
 * Manages REST API registration and endpoint initialization.
 *
 * @since 2.0.0
 */
class REST_Controller {

    /**
     * Instance of this class
     *
     * @var REST_Controller
     */
    private static $instance = null;

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
     * API namespace
     *
     * @var string
     */
    private $namespace = 'abc/v1';

    /**
     * Registered endpoints
     *
     * @var array
     */
    private $endpoints = [];

    /**
     * Get instance
     *
     * @since 2.0.0
     * @return REST_Controller
     */
    public static function get_instance() {
        if (null === self::$instance) {
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
        $this->logger = Logger::instance();
        
        // Ensure Auth_Handler class is loaded
        if (!class_exists('AutoBlogCraft\API\Auth_Handler')) {
            require_once ABC_PLUGIN_DIR . 'includes/api/class-auth-handler.php';
        }
        
        $this->auth = new Auth_Handler();
    }

    /**
     * Initialize REST API
     *
     * @since 2.0.0
     */
    public function init() {
        add_action('rest_api_init', [$this, 'register_routes']);
        
        $this->logger->info('REST API controller initialized');
    }

    /**
     * Register all routes
     *
     * @since 2.0.0
     */
    public function register_routes() {
        // Ensure endpoint classes are loaded
        if (!class_exists('AutoBlogCraft\API\Endpoints\Campaigns_Endpoint')) {
            require_once ABC_PLUGIN_DIR . 'includes/api/endpoints/class-campaigns-endpoint.php';
        }
        if (!class_exists('AutoBlogCraft\API\Endpoints\Queue_Endpoint')) {
            require_once ABC_PLUGIN_DIR . 'includes/api/endpoints/class-queue-endpoint.php';
        }
        if (!class_exists('AutoBlogCraft\API\Endpoints\Stats_Endpoint')) {
            require_once ABC_PLUGIN_DIR . 'includes/api/endpoints/class-stats-endpoint.php';
        }
        
        // Initialize endpoints
        $this->endpoints = [
            'campaigns' => new Campaigns_Endpoint($this->namespace, $this->auth),
            'queue' => new Queue_Endpoint($this->namespace, $this->auth),
            'stats' => new Stats_Endpoint($this->namespace, $this->auth),
        ];

        // Register each endpoint
        foreach ($this->endpoints as $name => $endpoint) {
            $endpoint->register_routes();
            $this->logger->debug("Registered {$name} endpoint");
        }

        // Register root endpoint
        $this->register_root_route();

        $this->logger->info('All REST API routes registered', [
            'namespace' => $this->namespace,
            'endpoints' => array_keys($this->endpoints),
        ]);
    }

    /**
     * Register root endpoint
     *
     * Provides API information and available endpoints.
     *
     * @since 2.0.0
     */
    private function register_root_route() {
        register_rest_route($this->namespace, '/', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_api_info'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Get API information
     *
     * @since 2.0.0
     * @return array API info.
     */
    public function get_api_info() {
        return [
            'name' => 'AutoBlogCraft API',
            'version' => '1.0.0',
            'namespace' => $this->namespace,
            'authentication' => 'API Key',
            'endpoints' => [
                'campaigns' => [
                    'GET /campaigns' => 'List all campaigns',
                    'GET /campaigns/{id}' => 'Get campaign details',
                    'POST /campaigns' => 'Create new campaign',
                    'PUT /campaigns/{id}' => 'Update campaign',
                    'DELETE /campaigns/{id}' => 'Delete campaign',
                ],
                'queue' => [
                    'GET /queue' => 'List queue items',
                    'GET /queue/{id}' => 'Get queue item details',
                    'POST /queue/{id}/process' => 'Process queue item',
                    'DELETE /queue/{id}' => 'Delete queue item',
                ],
                'stats' => [
                    'GET /stats' => 'Get global statistics',
                    'GET /stats/campaigns/{id}' => 'Get campaign statistics',
                ],
            ],
            'documentation' => home_url('/wp-json/' . $this->namespace),
        ];
    }

    /**
     * Get namespace
     *
     * @since 2.0.0
     * @return string Namespace.
     */
    public function get_namespace() {
        return $this->namespace;
    }

    /**
     * Get endpoint
     *
     * @since 2.0.0
     * @param string $name Endpoint name.
     * @return object|null Endpoint object or null.
     */
    public function get_endpoint($name) {
        return $this->endpoints[$name] ?? null;
    }

    /**
     * Check if REST API is enabled
     *
     * @since 2.0.0
     * @return bool True if enabled.
     */
    public function is_enabled() {
        return (bool) get_option('abc_rest_api_enabled', true);
    }

    /**
     * Enable REST API
     *
     * @since 2.0.0
     * @return bool Success.
     */
    public function enable() {
        $this->logger->info('REST API enabled');
        return update_option('abc_rest_api_enabled', true);
    }

    /**
     * Disable REST API
     *
     * @since 2.0.0
     * @return bool Success.
     */
    public function disable() {
        $this->logger->warning('REST API disabled');
        return update_option('abc_rest_api_enabled', false);
    }

    /**
     * Get API usage statistics
     *
     * @since 2.0.0
     * @return array Statistics.
     */
    public function get_usage_stats() {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_api_requests';
        
        // Get request counts by endpoint
        $results = $wpdb->get_results("
            SELECT 
                endpoint,
                COUNT(*) as total_requests,
                SUM(CASE WHEN status_code < 400 THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as failed
            FROM {$table}
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY endpoint
        ", ARRAY_A);

        return [
            'enabled' => $this->is_enabled(),
            'namespace' => $this->namespace,
            'endpoints' => count($this->endpoints),
            'usage' => $results,
        ];
    }

    /**
     * Log API request
     *
     * @since 2.0.0
     * @param string $endpoint Endpoint.
     * @param string $method HTTP method.
     * @param int $status_code Response status code.
     * @param string $api_key API key used.
     */
    public function log_request($endpoint, $method, $status_code, $api_key = '') {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_api_requests';
        
        $wpdb->insert($table, [
            'endpoint' => $endpoint,
            'method' => $method,
            'status_code' => $status_code,
            'api_key' => substr($api_key, 0, 8) . '***', // Masked
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * Create API requests table
     *
     * @since 2.0.0
     */
    public static function create_api_requests_table() {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_api_requests';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            endpoint varchar(255) NOT NULL,
            method varchar(10) NOT NULL,
            status_code int(11) NOT NULL,
            api_key varchar(255) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY endpoint (endpoint),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Cleanup old API request logs
     *
     * @since 2.0.0
     * @param int $days Days to keep.
     * @return int Rows deleted.
     */
    public function cleanup_logs($days = 30) {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_api_requests';
        
        $deleted = $wpdb->query($wpdb->prepare("
            DELETE FROM {$table}
            WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $days));

        if ($deleted > 0) {
            $this->logger->info("Cleaned up {$deleted} old API request logs");
        }

        return $deleted;
    }
}
