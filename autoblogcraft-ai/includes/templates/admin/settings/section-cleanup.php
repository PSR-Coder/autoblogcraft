<?php
/**
 * Settings Template - Cleanup Settings Section
 *
 * Template for rendering data retention and cleanup settings.
 *
 * @package AutoBlogCraft\Templates\Admin\Settings
 * @since 2.0.0
 *
 * @var int $log_retention_days    Days to retain log entries (1-365)
 * @var int $queue_retention_days  Days to retain completed queue items (1-365)
 * @var int $cache_retention_days  Days to retain cache entries (1-365)
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<table class="form-table">
    <tr>
        <th scope="row">
            <label for="log_retention_days"><?php _e('Log Retention', 'autoblogcraft'); ?></label>
        </th>
        <td>
            <input type="number" name="log_retention_days" id="log_retention_days" 
                   value="<?php echo esc_attr($log_retention_days); ?>" 
                   min="1" max="365" class="small-text">
            <?php _e('days', 'autoblogcraft'); ?>
            <p class="description">
                <?php _e('Delete logs older than this many days.', 'autoblogcraft'); ?>
            </p>
        </td>
    </tr>

    <tr>
        <th scope="row">
            <label for="queue_retention_days"><?php _e('Queue Retention', 'autoblogcraft'); ?></label>
        </th>
        <td>
            <input type="number" name="queue_retention_days" id="queue_retention_days" 
                   value="<?php echo esc_attr($queue_retention_days); ?>" 
                   min="1" max="365" class="small-text">
            <?php _e('days', 'autoblogcraft'); ?>
            <p class="description">
                <?php _e('Delete completed queue items older than this many days.', 'autoblogcraft'); ?>
            </p>
        </td>
    </tr>

    <tr>
        <th scope="row">
            <label for="cache_retention_days"><?php _e('Cache Retention', 'autoblogcraft'); ?></label>
        </th>
        <td>
            <input type="number" name="cache_retention_days" id="cache_retention_days" 
                   value="<?php echo esc_attr($cache_retention_days); ?>" 
                   min="1" max="365" class="small-text">
            <?php _e('days', 'autoblogcraft'); ?>
            <p class="description">
                <?php _e('Delete cache entries older than this many days.', 'autoblogcraft'); ?>
            </p>
        </td>
    </tr>
</table>
