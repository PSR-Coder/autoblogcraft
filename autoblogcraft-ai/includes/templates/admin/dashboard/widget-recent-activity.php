<?php
/**
 * Dashboard - Recent Activity Widget Template
 *
 * @package AutoBlogCraft\Templates
 * @since 2.0.0
 * 
 * Available variables:
 * @var array $logs Array of log objects
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<?php if (empty($logs)): ?>
    <p class="abc-text-muted"><?php esc_html_e('No activity yet.', 'autoblogcraft'); ?></p>
<?php else: ?>
    <div class="abc-activity-list">
        <?php foreach ($logs as $log): ?>
            <?php $level_class = 'abc-activity-' . strtolower($log->level); ?>
            <div class="abc-activity-item <?php echo esc_attr($level_class); ?>">
                <div class="abc-activity-icon">
                    <span class="dashicons dashicons-info"></span>
                </div>
                <div class="abc-activity-content">
                    <div class="abc-activity-message"><?php echo esc_html($log->message); ?></div>
                    <div class="abc-activity-time"><?php echo esc_html(human_time_diff(strtotime($log->created_at)) . ' ago'); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
