<?php
/**
 * Campaign Detail - Overview Tab Template
 *
 * @package AutoBlogCraft\Templates
 * @since 2.0.0
 * 
 * Available variables:
 * @var object $campaign Campaign object
 * @var array $source_config Source configuration array
 * @var array $ai_config AI configuration array
 * @var string $discovery_interval Discovery interval
 * @var array $queue_stats Queue statistics
 * @var int $post_count Number of published posts
 * @var int $campaign_id Campaign ID
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="abc-dashboard-content">
    <!-- Statistics -->
    <div class="abc-stats-grid">
        <div class="abc-stat-card abc-stat-card-blue">
            <div class="abc-stat-icon">
                <span class="dashicons dashicons-list-view"></span>
            </div>
            <div class="abc-stat-details">
                <div class="abc-stat-value"><?php echo esc_html($queue_stats['total']); ?></div>
                <div class="abc-stat-label"><?php esc_html_e('Total in Queue', 'autoblogcraft'); ?></div>
            </div>
        </div>

        <div class="abc-stat-card abc-stat-card-green">
            <div class="abc-stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="abc-stat-details">
                <div class="abc-stat-value"><?php echo esc_html($queue_stats['completed']); ?></div>
                <div class="abc-stat-label"><?php esc_html_e('Completed', 'autoblogcraft'); ?></div>
            </div>
        </div>

        <div class="abc-stat-card abc-stat-card-purple">
            <div class="abc-stat-icon">
                <span class="dashicons dashicons-admin-post"></span>
            </div>
            <div class="abc-stat-details">
                <div class="abc-stat-value"><?php echo esc_html($post_count); ?></div>
                <div class="abc-stat-label"><?php esc_html_e('Published Posts', 'autoblogcraft'); ?></div>
            </div>
        </div>

        <div class="abc-stat-card abc-stat-card-orange">
            <div class="abc-stat-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="abc-stat-details">
                <div class="abc-stat-value"><?php echo esc_html($queue_stats['pending']); ?></div>
                <div class="abc-stat-label"><?php esc_html_e('Pending', 'autoblogcraft'); ?></div>
            </div>
        </div>
    </div>

    <div class="abc-two-column">
        <!-- Source Configuration -->
        <div class="abc-card">
            <div class="abc-card-header">
                <h3><?php esc_html_e('Source Configuration', 'autoblogcraft'); ?></h3>
            </div>
            <div class="abc-card-body">
                <table class="widefat">
                    <tbody>
                        <tr>
                            <th><?php esc_html_e('Discovery Interval:', 'autoblogcraft'); ?></th>
                            <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $discovery_interval))); ?></td>
                        </tr>
                        <?php foreach ($source_config as $key => $value): ?>
                        <tr>
                            <th><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?>:</th>
                            <td>
                                <?php 
                                if (is_array($value)) {
                                    echo esc_html(implode(', ', $value));
                                } elseif (in_array($key, ['feed_url', 'start_url'])) {
                                    echo '<a href="' . esc_url($value) . '" target="_blank">' . esc_html($value) . '</a>';
                                } else {
                                    echo esc_html($value);
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- AI Configuration -->
        <div class="abc-card">
            <div class="abc-card-header">
                <h3><?php esc_html_e('AI Configuration', 'autoblogcraft'); ?></h3>
            </div>
            <div class="abc-card-body">
                <table class="widefat">
                    <tbody>
                        <tr>
                            <th><?php esc_html_e('Rewrite Mode:', 'autoblogcraft'); ?></th>
                            <td><?php echo esc_html(ucfirst($ai_config['rewrite_mode'] ?? 'moderate')); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Writing Tone:', 'autoblogcraft'); ?></th>
                            <td><?php echo esc_html(ucfirst($ai_config['tone'] ?? 'professional')); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Content Length:', 'autoblogcraft'); ?></th>
                            <td><?php echo esc_html(($ai_config['min_length'] ?? 500) . ' - ' . ($ai_config['max_length'] ?? 2000) . ' words'); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Generate Images:', 'autoblogcraft'); ?></th>
                            <td><?php echo isset($ai_config['generate_images']) && $ai_config['generate_images'] ? '✓' : '✗'; ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('SEO Optimization:', 'autoblogcraft'); ?></th>
                            <td><?php echo isset($ai_config['add_seo']) && $ai_config['add_seo'] ? '✓' : '✗'; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="abc-card">
        <div class="abc-card-header">
            <h3><?php esc_html_e('Recent Posts', 'autoblogcraft'); ?></h3>
        </div>
        <div class="abc-card-body">
            <?php
            // Render recent posts using callback
            if (isset($render_recent_posts_callback) && is_callable($render_recent_posts_callback)) {
                call_user_func($render_recent_posts_callback, $campaign_id);
            }
            ?>
        </div>
    </div>
</div>
