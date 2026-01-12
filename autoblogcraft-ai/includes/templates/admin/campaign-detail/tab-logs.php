<?php
/**
 * Campaign Detail - Logs Tab Template
 *
 * @package AutoBlogCraft\Templates
 * @since 2.0.0
 * 
 * Available variables:
 * @var object $campaign Campaign object
 * @var int $campaign_id Campaign ID
 * @var int $page Current page number
 * @var int $per_page Items per page
 * @var int $total Total number of logs
 * @var array $logs Log entries
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
        <h3><?php esc_html_e('Campaign Logs', 'autoblogcraft'); ?></h3>
        <p class="description">
            <?php esc_html_e('View detailed logs for this campaign including discovery, processing, and error events.', 'autoblogcraft'); ?>
        </p>
    </div>
    <div class="abc-card-body">
        <?php if (empty($logs)): ?>
            <?php 
            if (isset($render_empty_state_callback) && is_callable($render_empty_state_callback)) {
                call_user_func($render_empty_state_callback, __('No logs found.', 'autoblogcraft'));
            }
            ?>
        <?php else: ?>
            <?php
            if (isset($render_table_callback) && is_callable($render_table_callback)) {
                call_user_func(
                    $render_table_callback,
                    ['Level', 'Message', 'Time'],
                    array_map(function($log) use ($format_status_badge_callback, $format_timestamp_callback) {
                        return [
                            is_callable($format_status_badge_callback) ? call_user_func($format_status_badge_callback, $log->level) : esc_html($log->level),
                            esc_html($log->message),
                            is_callable($format_timestamp_callback) ? call_user_func($format_timestamp_callback, $log->created_at) : esc_html($log->created_at),
                        ];
                    }, $logs)
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
