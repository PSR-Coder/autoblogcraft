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
                        'url' => admin_url('admin.php?page=abc-campaign-editor'),
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

                <!-- System Health with Cron Status -->
                <?php $this->render_health_check(); ?>

                <!-- Main Content - 3 Column Grid -->
                <div class="abc-dashboard-content" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-top: 20px;">
                    <?php $this->render_recent_activity(); ?>
                    <?php $this->render_system_status(); ?>
                    <?php $this->render_quick_actions(); ?>
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

        // Get cron status
        // Manual loading to ensure Cron_Detector is available
        if (!class_exists('AutoBlogCraft\\Cron\\Cron_Detector')) {
            require_once plugin_dir_path(__FILE__) . '../../cron/class-cron-detector.php';
        }
        
        $detector = Cron_Detector::get_instance();
        $cron_status = $detector->get_status();

        // Determine overall health status
        $health_class = 'abc-health-good';
        $health_label = __('Good', 'autoblogcraft');

        if ($api_status['rate_limited'] > 0 || $error_rate > 20 || $queue_depth > 1000 || $cron_status['health'] === 'warning') {
            $health_class = 'abc-health-warning';
            $health_label = __('Warning', 'autoblogcraft');
        }

        if ($api_status['active'] === 0 || $error_rate > 50 || $cron_status['health'] === 'critical') {
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
            'cron_status' => $cron_status,
            'cron_detector' => $detector,
        ]);
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
                <a href="<?php echo admin_url('admin.php?page=abc-campaign-editor'); ?>" class="abc-quick-action">
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
