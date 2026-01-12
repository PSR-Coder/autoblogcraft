<?php
/**
 * Campaign Detail Admin Page
 *
 * Detailed view of a single campaign with tabs.
 *
 * @package AutoBlogCraft\Admin
 * @since 2.0.0
 */

namespace AutoBlogCraft\Admin\Pages;

use AutoBlogCraft\Campaigns\Campaign_Factory;
use AutoBlogCraft\Discovery\Queue_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Campaign Detail Admin Page class
 *
 * @since 2.0.0
 */
class Admin_Page_Campaign_Detail extends Admin_Page_Base {

    /**
     * Render page
     *
     * @since 2.0.0
     */
    public function render() {
        $campaign_id = isset($_GET['campaign_id']) ? absint($_GET['campaign_id']) : 0;

        if (!$campaign_id) {
            $this->render_notice(__('Invalid campaign ID.', 'autoblogcraft'), 'error');
            return;
        }

        // Manual loading to ensure Campaign_Factory is available
        if (!class_exists('AutoBlogCraft\\Campaigns\\Campaign_Factory')) {
            require_once plugin_dir_path(__FILE__) . '../../campaigns/class-campaign-factory.php';
        }

        $factory = new Campaign_Factory();
        $campaign = $factory->get_campaign($campaign_id);

        if (!$campaign) {
            $this->render_notice(__('Campaign not found.', 'autoblogcraft'), 'error');
            return;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';

        $this->render_header(sprintf(__('Campaign: %s', 'autoblogcraft'), esc_html($campaign->get_title())));

        // Campaign status bar
        $this->render_campaign_status_bar($campaign);

        // Tabs
        $tabs = [
            'overview' => __('Overview', 'autoblogcraft'),
            'queue' => __('Queue', 'autoblogcraft'),
            'settings' => __('Settings', 'autoblogcraft'),
            'logs' => __('Logs', 'autoblogcraft'),
        ];

        $this->render_tabs($tabs, $active_tab, admin_url('admin.php?page=abc-campaign-detail&campaign_id=' . $campaign_id));

        // Tab content
        ?>
        <div class="abc-tab-content">
            <?php
            switch ($active_tab) {
                case 'overview':
                    $this->render_overview_tab($campaign);
                    break;
                case 'queue':
                    $this->render_queue_tab($campaign);
                    break;
                case 'settings':
                    $this->render_settings_tab($campaign);
                    break;
                case 'logs':
                    $this->render_logs_tab($campaign);
                    break;
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render campaign status bar
     *
     * @since 2.0.0
     * @param object $campaign Campaign object.
     */
    private function render_campaign_status_bar($campaign) {
        $status = $campaign->get_meta('status', 'active');
        $campaign_type = $campaign->get_type();
        $last_run = $campaign->get_meta('last_discovery_at');
        $next_run = $campaign->get_meta('next_discovery_at');

        ?>
        <div class="abc-campaign-status-bar">
            <div class="abc-campaign-info">
                <span class="abc-campaign-type"><?php echo esc_html(ucfirst($campaign_type)); ?></span>
                <?php echo $this->format_status_badge($status); ?>
            </div>
            <div class="abc-campaign-schedule">
                <?php if ($last_run): ?>
                    <span><strong><?php esc_html_e('Last Run:', 'autoblogcraft'); ?></strong> <?php echo esc_html($this->format_timestamp($last_run)); ?></span>
                <?php endif; ?>
                <?php if ($next_run): ?>
                    <span><strong><?php esc_html_e('Next Run:', 'autoblogcraft'); ?></strong> <?php echo esc_html($this->format_timestamp($next_run)); ?></span>
                <?php endif; ?>
            </div>
            <div class="abc-campaign-actions">
                <?php if ($status === 'active'): ?>
                    <button class="button abc-campaign-action" data-action="pause" data-campaign-id="<?php echo esc_attr($campaign->get_id()); ?>">
                        <span class="dashicons dashicons-controls-pause"></span>
                        <?php esc_html_e('Pause', 'autoblogcraft'); ?>
                    </button>
                <?php else: ?>
                    <button class="button abc-campaign-action" data-action="activate" data-campaign-id="<?php echo esc_attr($campaign->get_id()); ?>">
                        <span class="dashicons dashicons-controls-play"></span>
                        <?php esc_html_e('Activate', 'autoblogcraft'); ?>
                    </button>
                <?php endif; ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaign-wizard&step=1&campaign_id=' . $campaign->get_id())); ?>" class="button">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e('Edit', 'autoblogcraft'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render overview tab
     *
     * @since 2.0.0
     * @param object $campaign Campaign object.
     */
    private function render_overview_tab($campaign) {
        global $wpdb;

        $campaign_id = $campaign->get_id();
        $source_config = $campaign->get_meta('source_config', []);
        $ai_config = $campaign->get_meta('ai_config', []);
        $discovery_interval = $campaign->get_meta('discovery_interval', 'hourly');

        // Get statistics
        // Manual loading to ensure Queue_Manager is available
        if (!class_exists('AutoBlogCraft\\Discovery\\Queue_Manager')) {
            require_once plugin_dir_path(__FILE__) . '../../discovery/class-queue-manager.php';
        }
        
        $queue_manager = new Queue_Manager();
        $queue_stats = $this->get_queue_stats($campaign_id);
        $post_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_abc_campaign_id' AND meta_value = %d",
            $campaign_id
        ));

        // Load template
        $this->load_template('admin/campaign-detail/tab-overview', [
            'campaign' => $campaign,
            'campaign_id' => $campaign_id,
            'source_config' => $source_config,
            'ai_config' => $ai_config,
            'discovery_interval' => $discovery_interval,
            'queue_stats' => $queue_stats,
            'post_count' => $post_count,
            'render_recent_posts_callback' => [$this, 'render_recent_posts'],
        ]);
    }

    /**
     * Render queue tab
     *
     * @since 2.0.0
     * @param object $campaign Campaign object.
     */
    private function render_queue_tab($campaign) {
        global $wpdb;

        $campaign_id = $campaign->get_id();
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';
        $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $per_page = 50;
        $offset = ($page - 1) * $per_page;

        // Build query
        $where = $wpdb->prepare("campaign_id = %d", $campaign_id);
        if ($status_filter) {
            $where .= $wpdb->prepare(" AND status = %s", $status_filter);
        }

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}abc_discovery_queue WHERE {$where}");
        $items = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}abc_discovery_queue WHERE {$where} ORDER BY priority DESC, discovered_at DESC LIMIT {$per_page} OFFSET {$offset}"
        );

        // Load template
        $this->load_template('admin/campaign-detail/tab-queue', [
            'campaign' => $campaign,
            'campaign_id' => $campaign_id,
            'status_filter' => $status_filter,
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'items' => $items,
            'format_status_badge_callback' => [$this, 'format_status_badge'],
            'format_timestamp_callback' => [$this, 'format_timestamp'],
            'get_pagination_callback' => [$this, 'get_pagination'],
            'render_empty_state_callback' => [$this, 'render_empty_state'],
            'render_table_callback' => [$this, 'render_table'],
        ]);
    }

    /**
     * Render settings tab
     *
     * @since 2.0.0
     * @param object $campaign Campaign object.
     */
    private function render_settings_tab($campaign) {
        if (isset($_POST['abc_save_settings'])) {
            $this->save_campaign_settings($campaign);
            $this->render_notice(__('Settings saved successfully.', 'autoblogcraft'), 'success');
        }

        $campaign_id = $campaign->get_id();
        $discovery_interval = $campaign->get_meta('discovery_interval', 'hourly');
        $status = $campaign->get_meta('status', 'active');

        // Load template
        $this->load_template('admin/campaign-detail/tab-settings', [
            'campaign' => $campaign,
            'campaign_id' => $campaign_id,
            'discovery_interval' => $discovery_interval,
            'status' => $status,
        ]);
    }

    /**
     * Render logs tab
     *
     * @since 2.0.0
     * @param object $campaign Campaign object.
     */
    private function render_logs_tab($campaign) {
        global $wpdb;

        $campaign_id = $campaign->get_id();
        $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $per_page = 50;
        $offset = ($page - 1) * $per_page;

        $context = '%"campaign_id":' . $campaign_id . '%';
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}abc_logs WHERE context LIKE %s",
            $context
        ));

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}abc_logs WHERE context LIKE %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $context,
            $per_page,
            $offset
        ));

        // Load template
        $this->load_template('admin/campaign-detail/tab-logs', [
            'campaign' => $campaign,
            'campaign_id' => $campaign_id,
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'logs' => $logs,
            'format_status_badge_callback' => [$this, 'format_status_badge'],
            'format_timestamp_callback' => [$this, 'format_timestamp'],
            'get_pagination_callback' => [$this, 'get_pagination'],
            'render_empty_state_callback' => [$this, 'render_empty_state'],
            'render_table_callback' => [$this, 'render_table'],
        ]);
    }

    /**
     * Get queue statistics
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return array Statistics.
     */
    private function get_queue_stats($campaign_id) {
        global $wpdb;

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM {$wpdb->prefix}abc_discovery_queue 
            WHERE campaign_id = %d",
            $campaign_id
        ), ARRAY_A);

        return $stats ?: [
            'total' => 0,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
        ];
    }

    /**
     * Render recent posts
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     */
    private function render_recent_posts($campaign_id) {
        global $wpdb;

        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT p.* FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE pm.meta_key = '_abc_campaign_id' 
            AND pm.meta_value = %d
            AND p.post_type = 'post'
            ORDER BY p.post_date DESC
            LIMIT 10",
            $campaign_id
        ));

        if (empty($posts)) {
            $this->render_empty_state(__('No posts published yet.', 'autoblogcraft'));
            return;
        }

        ?>
        <table class="abc-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Title', 'autoblogcraft'); ?></th>
                    <th><?php esc_html_e('Status', 'autoblogcraft'); ?></th>
                    <th><?php esc_html_e('Published', 'autoblogcraft'); ?></th>
                    <th><?php esc_html_e('Actions', 'autoblogcraft'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                <tr>
                    <td><?php echo esc_html($post->post_title); ?></td>
                    <td><?php echo $this->format_status_badge($post->post_status); ?></td>
                    <td><?php echo esc_html($this->format_timestamp($post->post_date)); ?></td>
                    <td>
                        <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>"><?php esc_html_e('Edit', 'autoblogcraft'); ?></a> |
                        <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" target="_blank"><?php esc_html_e('View', 'autoblogcraft'); ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Save campaign settings
     *
     * @since 2.0.0
     * @param object $campaign Campaign object.
     */
    private function save_campaign_settings($campaign) {
        if (!isset($_POST['abc_campaign_settings_nonce']) || !wp_verify_nonce($_POST['abc_campaign_settings_nonce'], 'abc_campaign_settings')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $discovery_interval = isset($_POST['discovery_interval']) ? sanitize_text_field($_POST['discovery_interval']) : 'hourly';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active';

        $campaign->update_meta('discovery_interval', $discovery_interval);
        $campaign->update_meta('status', $status);
    }
}
