<?php
/**
 * Dashboard - System Status Widget Template
 *
 * @package AutoBlogCraft\Templates
 * @since 2.0.0
 * 
 * Available variables:
 * @var bool $as_available Whether Action Scheduler is available
 * @var int $api_keys_count Number of active API keys
 * @var string $wp_version WordPress version
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="abc-system-status">
    <div class="abc-status-item <?php echo $as_available ? 'abc-status-ok' : 'abc-status-error'; ?>">
        <span class="abc-status-indicator"></span>
        <span class="abc-status-label"><?php esc_html_e('Action Scheduler', 'autoblogcraft'); ?></span>
        <span class="abc-status-value"><?php echo $as_available ? esc_html__('Active', 'autoblogcraft') : esc_html__('Inactive', 'autoblogcraft'); ?></span>
    </div>

    <div class="abc-status-item <?php echo $api_keys_count > 0 ? 'abc-status-ok' : 'abc-status-warning'; ?>">
        <span class="abc-status-indicator"></span>
        <span class="abc-status-label"><?php esc_html_e('API Keys', 'autoblogcraft'); ?></span>
        <span class="abc-status-value"><?php echo sprintf(esc_html__('%d configured', 'autoblogcraft'), $api_keys_count); ?></span>
    </div>

    <div class="abc-status-item abc-status-ok">
        <span class="abc-status-indicator"></span>
        <span class="abc-status-label"><?php esc_html_e('Database', 'autoblogcraft'); ?></span>
        <span class="abc-status-value"><?php esc_html_e('Connected', 'autoblogcraft'); ?></span>
    </div>

    <div class="abc-status-item abc-status-ok">
        <span class="abc-status-indicator"></span>
        <span class="abc-status-label"><?php esc_html_e('WordPress', 'autoblogcraft'); ?></span>
        <span class="abc-status-value"><?php echo esc_html($wp_version); ?></span>
    </div>
</div>
