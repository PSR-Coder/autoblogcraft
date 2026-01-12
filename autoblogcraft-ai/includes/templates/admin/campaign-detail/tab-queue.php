<?php
/**
 * Campaign Detail - Queue Tab Template
 *
 * @package AutoBlogCraft\Templates
 * @since 2.0.0
 * 
 * Available variables:
 * @var object $campaign Campaign object
 * @var int $campaign_id Campaign ID
 * @var string $status_filter Current status filter
 * @var int $page Current page number
 * @var int $per_page Items per page
 * @var int $total Total number of items
 * @var array $items Queue items
 * @var callable $format_status_badge_callback Callback for formatting status badges
 * @var callable $format_timestamp_callback Callback for formatting timestamps
 * @var callable $get_pagination_callback Callback for generating pagination
 * @var callable $render_empty_state_callback Callback for empty state
 * @var callable $render_table_callback Callback for rendering table
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="abc-card">
    <div class="abc-card-header">
        <h3><?php esc_html_e('Queue Items', 'autoblogcraft'); ?></h3>
        <div class="abc-filters">
            <select class="abc-filter-select" data-filter="status_filter">
                <option value=""><?php esc_html_e('All Statuses', 'autoblogcraft'); ?></option>
                <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php esc_html_e('Pending', 'autoblogcraft'); ?></option>
                <option value="processing" <?php selected($status_filter, 'processing'); ?>><?php esc_html_e('Processing', 'autoblogcraft'); ?></option>
                <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php esc_html_e('Completed', 'autoblogcraft'); ?></option>
                <option value="failed" <?php selected($status_filter, 'failed'); ?>><?php esc_html_e('Failed', 'autoblogcraft'); ?></option>
            </select>
        </div>
    </div>
    <div class="abc-card-body">
        <?php if (empty($items)): ?>
            <?php 
            if (isset($render_empty_state_callback) && is_callable($render_empty_state_callback)) {
                call_user_func($render_empty_state_callback, __('No queue items found.', 'autoblogcraft'));
            }
            ?>
        <?php else: ?>
            <?php
            if (isset($render_table_callback) && is_callable($render_table_callback)) {
                call_user_func(
                    $render_table_callback,
                    ['Title', 'Type', 'Status', 'Priority', 'Discovered', 'Actions'],
                    array_map(function($item) use ($format_status_badge_callback, $format_timestamp_callback) {
                        return [
                            esc_html($item->title),
                            esc_html(ucfirst($item->content_type)),
                            is_callable($format_status_badge_callback) ? call_user_func($format_status_badge_callback, $item->status) : esc_html($item->status),
                            esc_html($item->priority),
                            is_callable($format_timestamp_callback) ? call_user_func($format_timestamp_callback, $item->discovered_at) : esc_html($item->discovered_at),
                            sprintf(
                                '<a href="%s" target="_blank">View Source</a>',
                                esc_url($item->source_url)
                            ),
                        ];
                    }, $items)
                );
            }
            ?>
            <?php 
            if (isset($get_pagination_callback) && is_callable($get_pagination_callback)) {
                echo wp_kses_post(call_user_func($get_pagination_callback, $total, $per_page, $page));
            }
            ?>
        <?php endif; ?>
    </div>
</div>
