<?php
/**
 * Dashboard - Health Check Widget Template
 *
 * @package AutoBlogCraft\Templates
 * @since 2.0.0
 * 
 * Available variables:
 * @var array $api_status API key status (active, rate_limited, total)
 * @var int $queue_depth Number of pending items in queue
 * @var float $error_rate Error rate percentage
 * @var int $total_processed Total processed items
 * @var array $queue_stats Queue statistics array
 * @var string $health_class Health status class (abc-health-good|warning|critical)
 * @var string $health_label Health status label
 * @var array $cron_status Cron status information
 * @var object $cron_detector Cron detector instance
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="abc-health-check">
    <div class="abc-health-header">
        <h3><?php esc_html_e('System Health', 'autoblogcraft'); ?></h3>
        <div class="abc-health-status <?php echo esc_attr($health_class); ?>">
            <span class="abc-health-indicator"></span>
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

        <!-- Cron Configuration -->
        <div class="abc-health-metric">
            <div class="abc-health-metric-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="abc-health-metric-details">
                <div class="abc-health-metric-label"><?php esc_html_e('Cron Configuration', 'autoblogcraft'); ?></div>
                <div class="abc-health-metric-value">
                    <span class="abc-health-value-number">
                        <?php echo esc_html($cron_detector->get_type_label($cron_status['type'])); ?>
                    </span>
                    <?php echo $cron_detector->get_health_badge($cron_status['health']); ?>
                </div>
                <?php if (!empty($cron_status['issues'])): ?>
                    <div class="abc-health-metric-warning">
                        <?php 
                        $critical_issues = array_filter($cron_status['issues'], function($issue) {
                            return $issue['severity'] === 'critical';
                        });
                        if (!empty($critical_issues)) {
                            echo esc_html($critical_issues[0]['message']);
                        } else {
                            echo esc_html($cron_status['issues'][0]['message']);
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
