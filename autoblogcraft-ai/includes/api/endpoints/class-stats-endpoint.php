<?php
/**
 * Stats Endpoint
 *
 * REST API endpoint for statistics and analytics.
 *
 * @package AutoBlogCraft\API\Endpoints
 * @since 2.0.0
 */

namespace AutoBlogCraft\API\Endpoints;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\API\Auth_Handler;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Stats Endpoint class
 *
 * Provides statistics and analytics via REST API.
 *
 * @since 2.0.0
 */
class Stats_Endpoint {

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
    }

    /**
     * Register routes
     *
     * @since 2.0.0
     */
    public function register_routes() {
        // Global stats
        register_rest_route($this->namespace, '/stats', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_global_stats'],
            'permission_callback' => [$this->auth, 'check_read_permission'],
        ]);

        // Campaign stats
        register_rest_route($this->namespace, '/stats/campaigns/(?P<id>[\d]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_campaign_stats'],
            'permission_callback' => [$this->auth, 'check_read_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Campaign ID',
                ],
            ],
        ]);

        // Queue stats
        register_rest_route($this->namespace, '/stats/queue', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_queue_stats'],
            'permission_callback' => [$this->auth, 'check_read_permission'],
            'args' => [
                'campaign_id' => [
                    'type' => 'integer',
                    'description' => 'Filter by campaign ID',
                ],
            ],
        ]);

        // Publishing stats
        register_rest_route($this->namespace, '/stats/publishing', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_publishing_stats'],
            'permission_callback' => [$this->auth, 'check_read_permission'],
            'args' => [
                'period' => [
                    'type' => 'string',
                    'enum' => ['today', 'week', 'month', 'year'],
                    'default' => 'week',
                    'description' => 'Time period',
                ],
            ],
        ]);

        // AI usage stats
        register_rest_route($this->namespace, '/stats/ai-usage', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_ai_usage_stats'],
            'permission_callback' => [$this->auth, 'check_read_permission'],
            'args' => [
                'period' => [
                    'type' => 'string',
                    'enum' => ['today', 'week', 'month'],
                    'default' => 'week',
                    'description' => 'Time period',
                ],
            ],
        ]);

        // Performance stats
        register_rest_route($this->namespace, '/stats/performance', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_performance_stats'],
            'permission_callback' => [$this->auth, 'check_read_permission'],
        ]);
    }

    /**
     * Get global statistics
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function get_global_stats($request) {
        global $wpdb;

        $stats = [
            'campaigns' => [
                'total' => $this->get_campaign_count(),
                'active' => $this->get_campaign_count('active'),
                'paused' => $this->get_campaign_count('paused'),
            ],
            'queue' => [
                'total' => $this->get_queue_count(),
                'pending' => $this->get_queue_count('pending'),
                'processing' => $this->get_queue_count('processing'),
                'processed' => $this->get_queue_count('processed'),
                'failed' => $this->get_queue_count('failed'),
            ],
            'posts' => [
                'total' => $this->get_generated_posts_count(),
                'published' => $this->get_generated_posts_count('publish'),
                'draft' => $this->get_generated_posts_count('draft'),
            ],
            'system' => [
                'version' => defined('ABC_VERSION') ? ABC_VERSION : '2.0.0',
                'php_version' => PHP_VERSION,
                'wordpress_version' => get_bloginfo('version'),
                'uptime' => $this->get_system_uptime(),
            ],
        ];

        return new WP_REST_Response($stats);
    }

    /**
     * Get campaign statistics
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function get_campaign_stats($request) {
        $campaign_id = $request->get_param('id');

        if (!$this->campaign_exists($campaign_id)) {
            return new WP_Error(
                'campaign_not_found',
                'Campaign not found',
                ['status' => 404]
            );
        }

        global $wpdb;

        $stats = [
            'campaign_id' => $campaign_id,
            'name' => get_the_title($campaign_id),
            'type' => get_post_meta($campaign_id, '_campaign_type', true),
            'status' => get_post_meta($campaign_id, '_campaign_status', true),
            'queue' => [
                'total' => $this->get_queue_count(null, $campaign_id),
                'pending' => $this->get_queue_count('pending', $campaign_id),
                'processed' => $this->get_queue_count('processed', $campaign_id),
                'failed' => $this->get_queue_count('failed', $campaign_id),
            ],
            'posts' => [
                'total' => $this->get_campaign_posts_count($campaign_id),
                'published' => $this->get_campaign_posts_count($campaign_id, 'publish'),
                'draft' => $this->get_campaign_posts_count($campaign_id, 'draft'),
            ],
            'performance' => [
                'discovery_rate' => $this->calculate_discovery_rate($campaign_id),
                'processing_rate' => $this->calculate_processing_rate($campaign_id),
                'success_rate' => $this->calculate_success_rate($campaign_id),
            ],
            'timeline' => $this->get_campaign_timeline($campaign_id),
        ];

        return new WP_REST_Response($stats);
    }

    /**
     * Get queue statistics
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function get_queue_stats($request) {
        global $wpdb;

        $campaign_id = $request->get_param('campaign_id');
        $table = $wpdb->prefix . 'abc_queue';

        $where = $campaign_id ? $wpdb->prepare('WHERE campaign_id = %d', $campaign_id) : '';

        $stats = [
            'total' => $this->get_queue_count(null, $campaign_id),
            'by_status' => [],
            'by_campaign' => [],
            'priority_distribution' => [],
            'age_distribution' => [],
        ];

        // By status
        $by_status = $wpdb->get_results("
            SELECT status, COUNT(*) as count
            FROM {$table} {$where}
            GROUP BY status
        ", ARRAY_A);

        foreach ($by_status as $row) {
            $stats['by_status'][$row['status']] = (int) $row['count'];
        }

        // By campaign
        if (!$campaign_id) {
            $by_campaign = $wpdb->get_results("
                SELECT campaign_id, COUNT(*) as count
                FROM {$table}
                GROUP BY campaign_id
                ORDER BY count DESC
                LIMIT 10
            ", ARRAY_A);

            foreach ($by_campaign as $row) {
                $stats['by_campaign'][] = [
                    'campaign_id' => (int) $row['campaign_id'],
                    'campaign_name' => get_the_title($row['campaign_id']),
                    'count' => (int) $row['count'],
                ];
            }
        }

        // Priority distribution
        $by_priority = $wpdb->get_results("
            SELECT priority, COUNT(*) as count
            FROM {$table} {$where}
            GROUP BY priority
        ", ARRAY_A);

        foreach ($by_priority as $row) {
            $stats['priority_distribution'][$row['priority']] = (int) $row['count'];
        }

        return new WP_REST_Response($stats);
    }

    /**
     * Get publishing statistics
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function get_publishing_stats($request) {
        global $wpdb;

        $period = $request->get_param('period') ?? 'week';
        $interval = $this->get_date_interval($period);

        $stats = [
            'period' => $period,
            'total_published' => 0,
            'timeline' => [],
            'by_status' => [],
            'by_campaign' => [],
        ];

        // Timeline
        $timeline = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(post_date) as date,
                COUNT(*) as count
            FROM {$wpdb->posts}
            WHERE post_type = 'post'
            AND post_date > DATE_SUB(NOW(), INTERVAL %s)
            AND ID IN (
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_abc_generated'
            )
            GROUP BY DATE(post_date)
            ORDER BY date ASC
        ", $interval), ARRAY_A);

        foreach ($timeline as $row) {
            $stats['timeline'][] = [
                'date' => $row['date'],
                'count' => (int) $row['count'],
            ];
            $stats['total_published'] += (int) $row['count'];
        }

        // By status
        $by_status = $wpdb->get_results($wpdb->prepare("
            SELECT 
                post_status,
                COUNT(*) as count
            FROM {$wpdb->posts}
            WHERE post_type = 'post'
            AND post_date > DATE_SUB(NOW(), INTERVAL %s)
            AND ID IN (
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_abc_generated'
            )
            GROUP BY post_status
        ", $interval), ARRAY_A);

        foreach ($by_status as $row) {
            $stats['by_status'][$row['post_status']] = (int) $row['count'];
        }

        return new WP_REST_Response($stats);
    }

    /**
     * Get AI usage statistics
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function get_ai_usage_stats($request) {
        global $wpdb;

        $period = $request->get_param('period') ?? 'week';
        $interval = $this->get_date_interval($period);

        $table = $wpdb->prefix . 'abc_logs';

        $stats = [
            'period' => $period,
            'total_requests' => 0,
            'total_tokens' => 0,
            'by_provider' => [],
            'by_model' => [],
            'timeline' => [],
        ];

        // By provider
        $by_provider = $wpdb->get_results($wpdb->prepare("
            SELECT 
                JSON_EXTRACT(context, '$.provider') as provider,
                COUNT(*) as requests,
                SUM(CAST(JSON_EXTRACT(context, '$.tokens') AS UNSIGNED)) as tokens
            FROM {$table}
            WHERE type = 'ai_request'
            AND created_at > DATE_SUB(NOW(), INTERVAL %s)
            GROUP BY provider
        ", $interval), ARRAY_A);

        foreach ($by_provider as $row) {
            $provider = trim($row['provider'], '"');
            $stats['by_provider'][$provider] = [
                'requests' => (int) $row['requests'],
                'tokens' => (int) $row['tokens'],
            ];
            $stats['total_requests'] += (int) $row['requests'];
            $stats['total_tokens'] += (int) $row['tokens'];
        }

        return new WP_REST_Response($stats);
    }

    /**
     * Get performance statistics
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function get_performance_stats($request) {
        global $wpdb;

        $table_logs = $wpdb->prefix . 'abc_logs';

        $stats = [
            'average_processing_time' => 0,
            'average_discovery_time' => 0,
            'error_rate' => 0,
            'cache_hit_rate' => 0,
            'database_size' => [],
        ];

        // Average processing time
        $avg_processing = $wpdb->get_var("
            SELECT AVG(CAST(JSON_EXTRACT(context, '$.duration') AS DECIMAL(10,2)))
            FROM {$table_logs}
            WHERE type = 'processing'
            AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stats['average_processing_time'] = round((float) $avg_processing, 2);

        // Error rate
        $total_logs = $wpdb->get_var("
            SELECT COUNT(*) FROM {$table_logs}
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $error_logs = $wpdb->get_var("
            SELECT COUNT(*) FROM {$table_logs}
            WHERE level = 'error'
            AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stats['error_rate'] = $total_logs > 0 
            ? round(($error_logs / $total_logs) * 100, 2) 
            : 0;

        // Database sizes
        $tables = [
            'abc_queue',
            'abc_logs',
            'abc_api_requests',
            'abc_api_keys',
        ];

        foreach ($tables as $table) {
            $full_table = $wpdb->prefix . $table;
            $size = $wpdb->get_var("
                SELECT 
                    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = '{$wpdb->dbname}'
                AND TABLE_NAME = '{$full_table}'
            ");
            $stats['database_size'][$table] = (float) $size;
        }

        return new WP_REST_Response($stats);
    }

    /**
     * Get campaign count
     *
     * @since 2.0.0
     * @param string $status Status filter.
     * @return int Count.
     */
    private function get_campaign_count($status = null) {
        $args = [
            'post_type' => 'abc_campaign',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ];

        if ($status) {
            $args['meta_query'] = [
                [
                    'key' => '_campaign_status',
                    'value' => $status,
                ],
            ];
        }

        $query = new \WP_Query($args);
        return $query->found_posts;
    }

    /**
     * Get queue count
     *
     * @since 2.0.0
     * @param string $status Status filter.
     * @param int $campaign_id Campaign ID filter.
     * @return int Count.
     */
    private function get_queue_count($status = null, $campaign_id = null) {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_queue';
        $where = ['1=1'];
        $values = [];

        if ($status) {
            $where[] = 'status = %s';
            $values[] = $status;
        }

        if ($campaign_id) {
            $where[] = 'campaign_id = %d';
            $values[] = $campaign_id;
        }

        $where_sql = implode(' AND ', $where);

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}",
            ...$values
        ));
    }

    /**
     * Get generated posts count
     *
     * @since 2.0.0
     * @param string $status Post status.
     * @return int Count.
     */
    private function get_generated_posts_count($status = null) {
        global $wpdb;

        $where = "post_type = 'post'";
        
        if ($status) {
            $where .= $wpdb->prepare(' AND post_status = %s', $status);
        }

        return (int) $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts}
            WHERE {$where}
            AND ID IN (
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_abc_generated'
            )
        ");
    }

    /**
     * Get campaign posts count
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @param string $status Post status.
     * @return int Count.
     */
    private function get_campaign_posts_count($campaign_id, $status = null) {
        global $wpdb;

        $where = "post_type = 'post'";
        
        if ($status) {
            $where .= $wpdb->prepare(' AND post_status = %s', $status);
        }

        return (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->posts}
            WHERE {$where}
            AND ID IN (
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_abc_campaign_id' AND meta_value = %d
            )
        ", $campaign_id));
    }

    /**
     * Calculate discovery rate
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return float Rate per day.
     */
    private function calculate_discovery_rate($campaign_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_queue';
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$table}
            WHERE campaign_id = %d
            AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ", $campaign_id));

        return round($count / 7, 2);
    }

    /**
     * Calculate processing rate
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return float Rate per day.
     */
    private function calculate_processing_rate($campaign_id) {
        $count = $this->get_campaign_posts_count($campaign_id);
        $campaign = get_post($campaign_id);
        $days = max(1, (time() - strtotime($campaign->post_date)) / DAY_IN_SECONDS);

        return round($count / $days, 2);
    }

    /**
     * Calculate success rate
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return float Success rate percentage.
     */
    private function calculate_success_rate($campaign_id) {
        $total = $this->get_queue_count(null, $campaign_id);
        $processed = $this->get_queue_count('processed', $campaign_id);

        if ($total === 0) {
            return 0;
        }

        return round(($processed / $total) * 100, 2);
    }

    /**
     * Get campaign timeline
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return array Timeline data.
     */
    private function get_campaign_timeline($campaign_id) {
        global $wpdb;

        $timeline = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(post_date) as date,
                COUNT(*) as count
            FROM {$wpdb->posts}
            WHERE post_type = 'post'
            AND post_date > DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND ID IN (
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_abc_campaign_id' AND meta_value = %d
            )
            GROUP BY DATE(post_date)
            ORDER BY date ASC
        ", $campaign_id), ARRAY_A);

        return array_map(function($row) {
            return [
                'date' => $row['date'],
                'count' => (int) $row['count'],
            ];
        }, $timeline);
    }

    /**
     * Get date interval
     *
     * @since 2.0.0
     * @param string $period Period.
     * @return string SQL interval.
     */
    private function get_date_interval($period) {
        $intervals = [
            'today' => '1 DAY',
            'week' => '7 DAY',
            'month' => '30 DAY',
            'year' => '365 DAY',
        ];

        return $intervals[$period] ?? '7 DAY';
    }

    /**
     * Get system uptime
     *
     * @since 2.0.0
     * @return int Uptime in seconds.
     */
    private function get_system_uptime() {
        $install_date = get_option('abc_install_date');
        
        if (!$install_date) {
            return 0;
        }

        return time() - strtotime($install_date);
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
}
