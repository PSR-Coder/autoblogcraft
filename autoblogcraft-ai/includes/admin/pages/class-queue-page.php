<?php
/**
 * Admin Page - Queue
 *
 * Queue viewer and management page.
 *
 * @package AutoBlogCraft\Admin
 * @since 2.0.0
 */

namespace AutoBlogCraft\Admin\Pages;

use AutoBlogCraft\Discovery\Queue_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Queue Page class
 *
 * @since 2.0.0
 */
class Admin_Page_Queue extends Admin_Page_Base {

    /**
     * Render page
     *
     * @since 2.0.0
     */
    public function render() {
        // Manual loading to ensure Queue_Manager is available
        if (!class_exists('AutoBlogCraft\\Discovery\\Queue_Manager')) {
            require_once plugin_dir_path(__FILE__) . '../../discovery/class-queue-manager.php';
        }
        
        $queue_manager = new Queue_Manager();
        
        // Get filter parameters
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        $campaign_filter = isset($_GET['campaign_id']) ? absint($_GET['campaign_id']) : 0;
        
        ?>
        <div class="wrap abc-wrap">
            <?php
            $this->render_header(
                __('Discovery Queue', 'autoblogcraft'),
                __('Monitor and manage discovered content items', 'autoblogcraft')
            );
            ?>

            <div class="abc-page-content">
                <?php $this->render_filters($status_filter, $campaign_filter); ?>
                <?php $this->render_queue_table($queue_manager, $status_filter, $campaign_filter); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render filters
     *
     * @since 2.0.0
     * @param string $status_filter Status filter.
     * @param int $campaign_filter Campaign filter.
     */
    private function render_filters($status_filter, $campaign_filter) {
        ?>
        <div class="abc-filters">
            <select id="abc-status-filter" class="abc-filter-select">
                <option value="all" <?php selected($status_filter, 'all'); ?>><?php _e('All Statuses', 'autoblogcraft'); ?></option>
                <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('Pending', 'autoblogcraft'); ?></option>
                <option value="processing" <?php selected($status_filter, 'processing'); ?>><?php _e('Processing', 'autoblogcraft'); ?></option>
                <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php _e('Completed', 'autoblogcraft'); ?></option>
                <option value="failed" <?php selected($status_filter, 'failed'); ?>><?php _e('Failed', 'autoblogcraft'); ?></option>
            </select>

            <select id="abc-campaign-filter" class="abc-filter-select">
                <option value="0"><?php _e('All Campaigns', 'autoblogcraft'); ?></option>
                <?php
                $campaigns = get_posts([
                    'post_type' => 'abc_campaign',
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'orderby' => 'title',
                    'order' => 'ASC',
                ]);
                
                foreach ($campaigns as $campaign) {
                    printf(
                        '<option value="%d" %s>%s</option>',
                        $campaign->ID,
                        selected($campaign_filter, $campaign->ID, false),
                        esc_html($campaign->post_title)
                    );
                }
                ?>
            </select>

            <button type="button" class="button" id="abc-apply-filters"><?php _e('Apply Filters', 'autoblogcraft'); ?></button>
            <button type="button" class="button" id="abc-clear-filters"><?php _e('Clear', 'autoblogcraft'); ?></button>
        </div>
        <?php
    }

    /**
     * Render queue table
     *
     * @since 2.0.0
     * @param Queue_Manager $queue_manager Queue manager instance.
     * @param string $status_filter Status filter.
     * @param int $campaign_filter Campaign filter.
     */
    private function render_queue_table($queue_manager, $status_filter, $campaign_filter) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'abc_discovery_queue';
        $where = ['1=1'];
        
        if ($status_filter !== 'all') {
            $where[] = $wpdb->prepare('status = %s', $status_filter);
        }
        
        if ($campaign_filter > 0) {
            $where[] = $wpdb->prepare('campaign_id = %d', $campaign_filter);
        }
        
        $where_sql = implode(' AND ', $where);
        
        $items = $wpdb->get_results(
            "SELECT * FROM {$table} 
            WHERE {$where_sql} 
            ORDER BY priority DESC, discovered_at DESC 
            LIMIT 50"
        );

        if (empty($items)) {
            $this->render_empty_state(
                __('No items in queue', 'autoblogcraft'),
                __('Queue items will appear here as campaigns discover new content.', 'autoblogcraft')
            );
            return;
        }

        $columns = [
            'title' => ['label' => __('Title', 'autoblogcraft')],
            'campaign' => ['label' => __('Campaign', 'autoblogcraft')],
            'type' => ['label' => __('Type', 'autoblogcraft')],
            'status' => ['label' => __('Status', 'autoblogcraft')],
            'priority' => ['label' => __('Priority', 'autoblogcraft'), 'class' => 'abc-text-center'],
            'discovered' => ['label' => __('Discovered', 'autoblogcraft')],
        ];

        $rows = [];
        
        foreach ($items as $item) {
            $campaign_title = get_the_title($item->campaign_id);
            
            $rows[] = [
                'title' => sprintf(
                    '<strong>%s</strong><br><span class="abc-text-muted abc-text-small">%s</span>',
                    esc_html($item->title),
                    esc_url($item->url)
                ),
                'campaign' => esc_html($campaign_title),
                'type' => $this->format_status_badge($item->type, [
                    'rss' => 'blue',
                    'web' => 'green',
                    'youtube' => 'red',
                    'news' => 'purple',
                ]),
                'status' => $this->format_status_badge($item->status),
                'priority' => sprintf('<strong>%d</strong>', $item->priority),
                'discovered' => $this->format_timestamp($item->discovered_at),
            ];
        }

        $this->render_card('', function() use ($columns, $rows) {
            $this->render_table($columns, $rows);
        });
    }
}
