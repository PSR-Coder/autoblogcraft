<?php
/**
 * Admin Page - Dashboard
 *
 * Main dashboard page with statistics and overview.
 *
 * @package AutoBlogCraft\Admin
 * @since 2.0.0
 */

namespace AutoBlogCraft\Admin\Pages;

use AutoBlogCraft\Discovery\Queue_Manager;
use AutoBlogCraft\Core\Rate_Limiter;
use AutoBlogCraft\Cron\Discovery_Job;
use AutoBlogCraft\Cron\Processing_Job;
use AutoBlogCraft\Cron\Cron_Detector;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard Page class
 *
 * @since 2.0.0
 */
class Admin_Page_Dashboard extends Admin_Page_Base {

    /**
     * Render page
     *
     * @since 2.0.0
     */
    public function render() {
        ?>
        <div class="wrap abc-wrap">
            <?php
            $this->render_header(
                __('Dashboard', 'autoblogcraft'),
                __('Overview of your content automation campaigns', 'autoblogcraft'),
                [
                    [
                        'label' => __('New Campaign', 'autoblogcraft'),
                        'url' => admin_url('post-new.php?post_type=abc_campaign'),
                        'class' => 'button-primary',
                        'icon' => 'dashicons-plus-alt',
                    ],
                ]
            );
            ?>

            <div class="abc-dashboard">
                <!-- Stats Grid -->
                <div class="abc-stats-grid">
                    <?php $this->render_stats(); ?>
                </div>

                <!-- Health Check -->
                <?php $this->render_health_check(); ?>

                <!-- Cron Status -->
                <?php $this->render_cron_status(); ?>

                <!-- Main Content -->
                <div class="abc-dashboard-content">
                    <div class="abc-dashboard-left">
                        <?php $this->render_recent_activity(); ?>
                        <?php $this->render_queue_status(); ?>
                    </div>
                    <div class="abc-dashboard-right">
                        <?php $this->render_system_status(); ?>
                        <?php $this->render_quick_actions(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render health check widget
     *
     * @since 2.0.0
     */
    private function render_health_check() {
        global $wpdb;

        // Get API key status
        $api_keys = $wpdb->get_results(
            "SELECT provider, status, usage_count as quota_used, quota_limit 
            FROM {$wpdb->prefix}abc_api_keys"
        );

        $api_status = [
            'active' => 0,
            'rate_limited' => 0,
            'total' => count($api_keys),
        ];

        foreach ($api_keys as $key) {
            if ($key->status !== 'active') {
                continue;
            }

            $usage_percent = $key->quota_limit > 0 ? ($key->quota_used / $key->quota_limit) * 100 : 0;

            if ($usage_percent >= 90) {
                $api_status['rate_limited']++;
            } else {
                $api_status['active']++;
            }
        }

        // Get queue depth
        // Manual loading to ensure Queue_Manager is available
        if (!class_exists('AutoBlogCraft\\Discovery\\Queue_Manager')) {
            require_once plugin_dir_path(__FILE__) . '../../discovery/class-queue-manager.php';
        }
        
        $queue_manager = new Queue_Manager();
        $queue_stats = $queue_manager->get_stats();
        $queue_depth = $queue_stats['pending'];

        // Get error rate (failed items vs success)
        $total_processed = $queue_stats['completed'] + $queue_stats['failed'];
        $error_rate = $total_processed > 0 ? ($queue_stats['failed'] / $total_processed) * 100 : 0;

        // Determine overall health status
        $health_class = 'abc-health-good';
        $health_label = __('Good', 'autoblogcraft');

        if ($api_status['rate_limited'] > 0 || $error_rate > 20 || $queue_depth > 1000) {
            $health_class = 'abc-health-warning';
            $health_label = __('Warning', 'autoblogcraft');
        }

        if ($api_status['active'] === 0 || $error_rate > 50) {
            $health_class = 'abc-health-critical';
            $health_label = __('Critical', 'autoblogcraft');
        }

        // Load template
        $this->load_template('admin/dashboard/widget-health-check', [
            'api_status' => $api_status,
            'queue_depth' => $queue_depth,
            'error_rate' => $error_rate,
            'total_processed' => $total_processed,
            'queue_stats' => $queue_stats,
            'health_class' => $health_class,
            'health_label' => $health_label,
        ]);
    }

    /**
                    <?php echo esc_html($health_label); ?>
                </div>
            </div>
            <div class="abc-health-metrics">
                <!-- API Key Status -->
                <div class="abc-health-metric">
                    <div class="abc-health-metric-icon">
                        <span class="dashicons dashicons-admin-network"></span>
                    </div>
                    <div class="abc-health-metric-details">
                        <div class="abc-health-metric-label"><?php esc_html_e('API Key Status', 'autoblogcraft'); ?></div>
                        <div class="abc-health-metric-value">
                            <span class="abc-health-value-active"><?php echo esc_html($api_status['active']); ?></span>
                            <span class="abc-health-value-separator">/</span>
                            <span class="abc-health-value-total"><?php echo esc_html($api_status['total']); ?></span>
                            <span class="abc-health-value-label"><?php esc_html_e('Active', 'autoblogcraft'); ?></span>
                        </div>
                        <?php if ($api_status['rate_limited'] > 0): ?>
                            <div class="abc-health-metric-warning">
                                <?php echo sprintf(esc_html__('%d keys near rate limit', 'autoblogcraft'), $api_status['rate_limited']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Queue Depth -->
                <div class="abc-health-metric">
                    <div class="abc-health-metric-icon">
                        <span class="dashicons dashicons-list-view"></span>
                    </div>
                    <div class="abc-health-metric-details">
                        <div class="abc-health-metric-label"><?php esc_html_e('Queue Depth', 'autoblogcraft'); ?></div>
                        <div class="abc-health-metric-value">
                            <span class="abc-health-value-number <?php echo $queue_depth > 1000 ? 'abc-health-value-warning' : ''; ?>">
                                <?php echo esc_html(number_format($queue_depth)); ?>
                            </span>
                            <span class="abc-health-value-label"><?php esc_html_e('items waiting', 'autoblogcraft'); ?></span>
                        </div>
                        <?php if ($queue_depth > 1000): ?>
                            <div class="abc-health-metric-warning">
                                <?php esc_html_e('High queue backlog detected', 'autoblogcraft'); ?>
                            </div>
                        <?php elseif ($queue_depth === 0): ?>
                            <div class="abc-health-metric-info">
                                <?php esc_html_e('All items processed', 'autoblogcraft'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Error Rate -->
                <div class="abc-health-metric">
                    <div class="abc-health-metric-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="abc-health-metric-details">
                        <div class="abc-health-metric-label"><?php esc_html_e('Error Rate', 'autoblogcraft'); ?></div>
                        <div class="abc-health-metric-value">
                            <span class="abc-health-value-number <?php echo $error_rate > 20 ? 'abc-health-value-warning' : ''; ?>">
                                <?php echo esc_html(number_format($error_rate, 1)); ?>%
                            </span>
                            <span class="abc-health-value-label">
                                (<?php echo esc_html(number_format($queue_stats['failed'])); ?>/<?php echo esc_html(number_format($total_processed)); ?>)
                            </span>
                        </div>
                        <?php if ($error_rate > 20): ?>
                            <div class="abc-health-metric-warning">
                                <?php esc_html_e('High failure rate detected', 'autoblogcraft'); ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=abc-logs')); ?>">
                                    <?php esc_html_e('View logs', 'autoblogcraft'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render cron status widget
     *
     * @since 2.0.0
     */
    private function render_cron_status() {
        // Manual loading to ensure Cron_Detector is available
        if (!class_exists('AutoBlogCraft\\Cron\\Cron_Detector')) {
            require_once plugin_dir_path(__FILE__) . '../../cron/class-cron-detector.php';
        }
        
        $detector = Cron_Detector::get_instance();
        $status = $detector->get_status();

        $has_issues = !empty($status['issues']);
        $has_critical = false;

        foreach ($status['issues'] as $issue) {
            if ('critical' === $issue['severity']) {
                $has_critical = true;
                break;
            }
        }

        // Determine if we should expand this section
        $expanded_class = ($has_critical || $has_issues) ? 'abc-cron-expanded' : '';

        ?>
        <div class="abc-cron-status <?php echo esc_attr($expanded_class); ?>">
            <div class="abc-cron-header">
                <h3><?php esc_html_e('Cron Configuration', 'autoblogcraft'); ?></h3>
                <div class="abc-cron-type">
                    <strong><?php echo esc_html($detector->get_type_label($status['type'])); ?></strong>
                    <?php echo $detector->get_health_badge($status['health']); ?>
                </div>
            </div>

            <?php if ($has_issues): ?>
                <div class="abc-cron-issues">
                    <?php foreach ($status['issues'] as $issue): ?>
                        <div class="abc-cron-issue abc-cron-issue-<?php echo esc_attr($issue['severity']); ?>">
                            <span class="abc-issue-icon">
                                <?php if ('critical' === $issue['severity']): ?>
                                    <span class="dashicons dashicons-dismiss"></span>
                                <?php elseif ('warning' === $issue['severity']): ?>
                                    <span class="dashicons dashicons-warning"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-info"></span>
                                <?php endif; ?>
                            </span>
                            <span class="abc-issue-message"><?php echo esc_html($issue['message']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($status['recommendations'])): ?>
                <div class="abc-cron-recommendations">
                    <h4><?php esc_html_e('Recommendations', 'autoblogcraft'); ?></h4>
                    <?php foreach ($status['recommendations'] as $rec): ?>
                        <div class="abc-cron-recommendation abc-priority-<?php echo esc_attr($rec['priority']); ?>">
                            <?php echo esc_html($rec['message']); ?>
                            <?php if ('setup_server_cron' === $rec['action']): ?>
                                <button type="button" class="button button-small abc-show-cron-instructions">
                                    <?php esc_html_e('Show Instructions', 'autoblogcraft'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Server Cron Instructions (Initially Hidden) -->
            <?php if (Cron_Detector::TYPE_WP_CRON === $status['type']): ?>
                <div class="abc-cron-instructions" style="display: none;">
                    <?php $instructions = $detector->get_server_cron_instructions(); ?>
                    <h4><?php echo esc_html($instructions['title']); ?></h4>
                    <p><?php echo esc_html($instructions['description']); ?></p>
                    <?php foreach ($instructions['steps'] as $step): ?>
                        <div class="abc-cron-step">
                            <div class="abc-step-number"><?php echo esc_html($step['number']); ?></div>
                            <div class="abc-step-content">
                                <strong><?php echo esc_html($step['title']); ?></strong>
                                <p><?php echo wp_kses_post($step['detail']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="abc-alternative">
                        <strong><?php echo esc_html($instructions['alternative']['title']); ?></strong>
                        <p><?php echo esc_html($instructions['alternative']['detail']); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.abc-show-cron-instructions').on('click', function() {
                $('.abc-cron-instructions').slideToggle();
                $(this).text(
                    $('.abc-cron-instructions').is(':visible') ? 
                    '<?php echo esc_js(__('Hide Instructions', 'autoblogcraft')); ?>' : 
                    '<?php echo esc_js(__('Show Instructions', 'autoblogcraft')); ?>'
                );
            });
        });
        </script>
        <?php
    }

    /**
     * Render statistics
     *
     * @since 2.0.0
     */
    private function render_stats() {
        // Get campaign counts
        $campaigns = wp_count_posts('abc_campaign');
        $active_campaigns = get_posts([
            'post_type' => 'abc_campaign',
            'post_status' => 'publish',
            'meta_query' => [
                ['key' => '_abc_status', 'value' => 'active'],
            ],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        // Get queue stats
        // Manual loading to ensure Queue_Manager is available
        if (!class_exists('AutoBlogCraft\\Discovery\\Queue_Manager')) {
            require_once plugin_dir_path(__FILE__) . '../../discovery/class-queue-manager.php';
        }
        $queue_manager = new Queue_Manager();
        $queue_stats = $queue_manager->get_stats();

        // Get rate limiter stats
        // Manual loading to ensure Rate_Limiter is available
        if (!class_exists('AutoBlogCraft\\Core\\Rate_Limiter')) {
            require_once plugin_dir_path(__FILE__) . '../../core/class-rate-limiter.php';
        }
        $rate_limiter = Rate_Limiter::instance();
        $rate_stats = $rate_limiter->get_stats();

        // Get published posts count
        global $wpdb;
        $posts_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE meta_key = '_abc_campaign_id'"
        );

        // Load template
        $this->load_template('admin/dashboard/widget-quick-stats', [
            'active_campaigns_count' => count($active_campaigns),
            'queue_pending' => $queue_stats['pending'],
            'posts_count' => $posts_count,
            'rate_stats' => $rate_stats,
            'queue_processing' => $queue_stats['processing'],
            'render_stat_card_callback' => [$this, 'render_stat_card'],
        ]);
    }

    /**
     * Render recent activity
     *
     * @since 2.0.0
     */
    private function render_recent_activity() {
        global $wpdb;
        
        $logs = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}abc_logs 
            ORDER BY created_at DESC 
            LIMIT 10"
        );

        $this->render_card(__('Recent Activity', 'autoblogcraft'), function() use ($logs) {
            $this->load_template('admin/dashboard/widget-recent-activity', [
                'logs' => $logs,
            ]);
        }, [
            'footer' => '<a href="' . admin_url('admin.php?page=autoblogcraft-logs') . '">' . __('View all logs', 'autoblogcraft') . ' →</a>',
        ]);
    }

    /**
     * Render queue status
     *
     * @since 2.0.0
     */
    private function render_queue_status() {
        $this->render_card(__('Queue Status', 'autoblogcraft'), function() {
            // Manual loading to ensure Queue_Manager is available
            if (!class_exists('AutoBlogCraft\\Discovery\\Queue_Manager')) {
                require_once plugin_dir_path(__FILE__) . '../../discovery/class-queue-manager.php';
            }
            
            $queue_manager = new Queue_Manager();
            $stats = $queue_manager->get_stats();

            $total = array_sum($stats);
            ?>
            <div class="abc-queue-status">
                <?php if ($total === 0): ?>
                    <p class="abc-text-muted"><?php _e('Queue is empty.', 'autoblogcraft'); ?></p>
                <?php else: ?>
                    <div class="abc-queue-chart">
                        <canvas id="queueChart" width="400" height="200"></canvas>
                    </div>
                    <div class="abc-queue-stats">
                        <div class="abc-queue-stat">
                            <span class="abc-queue-stat-label"><?php _e('Pending', 'autoblogcraft'); ?></span>
                            <span class="abc-queue-stat-value"><?php echo number_format($stats['pending']); ?></span>
                        </div>
                        <div class="abc-queue-stat">
                            <span class="abc-queue-stat-label"><?php _e('Processing', 'autoblogcraft'); ?></span>
                            <span class="abc-queue-stat-value"><?php echo number_format($stats['processing']); ?></span>
                        </div>
                        <div class="abc-queue-stat">
                            <span class="abc-queue-stat-label"><?php _e('Completed', 'autoblogcraft'); ?></span>
                            <span class="abc-queue-stat-value"><?php echo number_format($stats['completed']); ?></span>
                        </div>
                        <div class="abc-queue-stat">
                            <span class="abc-queue-stat-label"><?php _e('Failed', 'autoblogcraft'); ?></span>
                            <span class="abc-queue-stat-value"><?php echo number_format($stats['failed']); ?></span>
                        </div>
                    </div>

                    <script>
                    jQuery(document).ready(function($) {
                        if (typeof Chart !== 'undefined') {
                            var ctx = document.getElementById('queueChart').getContext('2d');
                            new Chart(ctx, {
                                type: 'doughnut',
                                data: {
                                    labels: ['Pending', 'Processing', 'Completed', 'Failed'],
                                    datasets: [{
                                        data: [
                                            <?php echo $stats['pending']; ?>,
                                            <?php echo $stats['processing']; ?>,
                                            <?php echo $stats['completed']; ?>,
                                            <?php echo $stats['failed']; ?>
                                        ],
                                        backgroundColor: ['#3b82f6', '#f59e0b', '#10b981', '#ef4444']
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            display: false
                                        }
                                    }
                                }
                            });
                        }
                    });
                    </script>
                <?php endif; ?>
            </div>
            <?php
        });
    }

    /**
     * Render system status
     *
     * @since 2.0.0
     */
    private function render_system_status() {
        // Check Action Scheduler
        $as_available = class_exists('ActionScheduler');

        // Check API keys
        global $wpdb;
        $api_keys_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}abc_api_keys WHERE status = 'active'"
        );

        $this->render_card(__('System Status', 'autoblogcraft'), function() use ($as_available, $api_keys_count) {
            $this->load_template('admin/dashboard/widget-system-status', [
                'as_available' => $as_available,
                'api_keys_count' => $api_keys_count,
                'wp_version' => get_bloginfo('version'),
            ]);
        });
    }

    /**
     * Render quick actions
     *
     * @since 2.0.0
     */
    private function render_quick_actions() {
        $this->render_card(__('Quick Actions', 'autoblogcraft'), function() {
            ?>
            <div class="abc-quick-actions">
                <a href="<?php echo admin_url('post-new.php?post_type=abc_campaign'); ?>" class="abc-quick-action">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <span><?php _e('Create Campaign', 'autoblogcraft'); ?></span>
                </a>
                <a href="<?php echo admin_url('admin.php?page=autoblogcraft-api-keys'); ?>" class="abc-quick-action">
                    <span class="dashicons dashicons-admin-network"></span>
                    <span><?php _e('Manage API Keys', 'autoblogcraft'); ?></span>
                </a>
                <a href="<?php echo admin_url('admin.php?page=autoblogcraft-queue'); ?>" class="abc-quick-action">
                    <span class="dashicons dashicons-list-view"></span>
                    <span><?php _e('View Queue', 'autoblogcraft'); ?></span>
                </a>
                <a href="<?php echo admin_url('admin.php?page=autoblogcraft-settings'); ?>" class="abc-quick-action">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <span><?php _e('Settings', 'autoblogcraft'); ?></span>
                </a>
            </div>
            <?php
        });
    }
}
