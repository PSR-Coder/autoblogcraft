<?php
/**
 * Admin Page - Campaigns
 *
 * Campaign list and management page.
 *
 * @package AutoBlogCraft\Admin
 * @since 2.0.0
 */

namespace AutoBlogCraft\Admin\Pages;

use AutoBlogCraft\Campaigns\Campaign_Factory;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Campaigns Page class
 *
 * @since 2.0.0
 */
class Admin_Page_Campaigns extends Admin_Page_Base
{

    /**
     * Render page
     *
     * @since 2.0.0
     */
    public function render()
    {
        // Handle success message
        if (isset($_GET['created'])) {
            $this->render_notice(__('Campaign created successfully!', 'autoblogcraft'), 'success');
        }

        ?>
        <div class="wrap abc-wrap">
            <?php
            $this->render_header(
                __('Campaigns', 'autoblogcraft'),
                __('Manage your content automation campaigns', 'autoblogcraft'),
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

            <div class="abc-page-content">
                <?php $this->render_campaigns_list(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render campaigns list
     *
     * @since 2.0.0
     */
    private function render_campaigns_list()
    {
        // Get campaigns
        $query = new \WP_Query([
            'post_type' => 'abc_campaign',
            'post_status' => 'any',
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        if (!$query->have_posts()) {
            $this->render_empty_state(
                __('No campaigns yet', 'autoblogcraft'),
                __('Create your first campaign to start automating content creation.', 'autoblogcraft'),
                [
                    'label' => __('Create Campaign', 'autoblogcraft'),
                    'url' => admin_url('admin.php?page=abc-campaign-editor'),
                ]
            );
            return;
        }

        // Manual loading to ensure Campaign_Factory is available
        if (!class_exists('AutoBlogCraft\\Campaigns\\Campaign_Factory')) {
            require_once plugin_dir_path(__FILE__) . '../../campaigns/class-campaign-factory.php';
        }

        $factory = new Campaign_Factory();

        // Prepare table data
        $columns = [
            'cb' => ['label' => '<input type="checkbox" id="abc-select-all">', 'class' => 'abc-check-column'],
            'title' => ['label' => __('Campaign', 'autoblogcraft')],
            'type' => ['label' => __('Type', 'autoblogcraft')],
            'status' => ['label' => __('Status', 'autoblogcraft')],
            'queue' => ['label' => __('Queue', 'autoblogcraft'), 'class' => 'abc-text-center'],
            'posts' => ['label' => __('Posts', 'autoblogcraft'), 'class' => 'abc-text-center'],
            'last_run' => ['label' => __('Last Run', 'autoblogcraft')],
            'actions' => ['label' => __('Actions', 'autoblogcraft'), 'class' => 'abc-text-right'],
        ];

        $rows = [];

        while ($query->have_posts()) {
            $query->the_post();
            $campaign_id = get_the_ID();

            try {
                $campaign = Campaign_Factory::create($campaign_id);

                // Check if campaign creation failed
                if (is_wp_error($campaign)) {
                    continue;
                }

                $type = $campaign->get_type();
                $status = $campaign->get_status();
                $last_run = get_post_meta($campaign_id, '_last_discovery_run', true);

                // Get queue count
                global $wpdb;
                $queue_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}abc_discovery_queue 
                    WHERE campaign_id = %d AND status = 'pending'",
                    $campaign_id
                ));

                // Get posts count
                $posts_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                    WHERE meta_key = '_abc_campaign_id' AND meta_value = %d",
                    $campaign_id
                ));

                // Get campaign description
                $description = get_post_meta($campaign_id, '_campaign_description', true);

                $rows[] = [
                    'cb' => sprintf('<input type="checkbox" class="abc-campaign-checkbox" value="%d">', $campaign_id),
                    'title' => sprintf(
                        '<strong><a href="%s">%s</a></strong><br><span class="abc-text-muted">%s</span>',
                        esc_url(admin_url('admin.php?page=abc-campaign-editor&campaign_id=' . $campaign_id)),
                        esc_html(get_the_title()),
                        esc_html($description)
                    ),
                    'type' => $this->format_campaign_type($type),
                    'status' => $this->format_status_badge($status),
                    'queue' => sprintf('<strong>%d</strong>', $queue_count),
                    'posts' => sprintf('<strong>%d</strong>', $posts_count),
                    'last_run' => $this->format_timestamp($last_run ? date('Y-m-d H:i:s', $last_run) : null),
                    'actions' => $this->get_campaign_actions($campaign_id, $status),
                ];

            } catch (\Exception $e) {
                continue;
            }
        }

        wp_reset_postdata();
        ?>
        <div class="abc-card">
            <div class="abc-card-header">
                <div class="abc-bulk-actions">
                    <select id="abc-bulk-action-select">
                        <option value=""><?php esc_html_e('Bulk Actions', 'autoblogcraft'); ?></option>
                        <option value="pause"><?php esc_html_e('Pause', 'autoblogcraft'); ?></option>
                        <option value="resume"><?php esc_html_e('Resume', 'autoblogcraft'); ?></option>
                        <option value="delete"><?php esc_html_e('Delete', 'autoblogcraft'); ?></option>
                    </select>
                    <button type="button" id="abc-bulk-action-apply" class="button">
                        <?php esc_html_e('Apply', 'autoblogcraft'); ?>
                    </button>
                </div>
            </div>
            <div class="abc-card-body">
                <?php $this->render_table($columns, $rows); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get campaign action buttons
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @param string $status Campaign status.
     * @return string Actions HTML.
     */
    private function get_campaign_actions($campaign_id, $status)
    {
        $actions = [];

        // View/Edit button - goes to unified campaign editor
        $actions[] = sprintf(
            '<a href="%s" class="button button-small">%s</a>',
            esc_url(admin_url('admin.php?page=abc-campaign-editor&campaign_id=' . $campaign_id)),
            __('View', 'autoblogcraft')
        );

        if ($status === 'active') {
            $actions[] = sprintf(
                '<a href="#" class="button button-small abc-pause-campaign" data-campaign="%d">%s</a>',
                $campaign_id,
                __('Pause', 'autoblogcraft')
            );
        } else {
            $actions[] = sprintf(
                '<a href="#" class="button button-small abc-activate-campaign" data-campaign="%d">%s</a>',
                $campaign_id,
                __('Activate', 'autoblogcraft')
            );
        }

        return implode(' ', $actions);
    }

    /**
     * Format campaign type badge
     *
     * @since 2.0.0
     * @param string $type Campaign type.
     * @return string Badge HTML.
     */
    private function format_campaign_type($type)
    {
        $colors = [
            'website' => 'blue',
            'youtube' => 'red',
            'amazon' => 'orange',
            'news' => 'purple',
        ];

        $labels = [
            'website' => __('Website', 'autoblogcraft'),
            'youtube' => __('YouTube', 'autoblogcraft'),
            'amazon' => __('Amazon', 'autoblogcraft'),
            'news' => __('News', 'autoblogcraft'),
        ];

        $color = $colors[$type] ?? 'gray';
        $label = $labels[$type] ?? ucfirst($type);

        return sprintf(
            '<span class="abc-badge abc-badge-%s">%s</span>',
            esc_attr($color),
            esc_html($label)
        );
    }
}
