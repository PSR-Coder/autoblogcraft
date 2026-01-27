<?php
/**
 * Campaign Detail - Overview Tab Template
 *
 * Displays campaign overview with statistics and quick actions.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Admin\Campaign_Detail
 * @since 2.0.0
 *
 * @var object $campaign Campaign object
 * @var array $stats Campaign statistics
 */

use AutoBlogCraft\Helpers\Template_Helpers;

defined('ABSPATH') || exit;

$campaign = $campaign ?? null;
$stats = $stats ?? [];

if (!$campaign) {
	return;
}
?>

<div class="abc-campaign-overview">
	<!-- Campaign Info Card -->
	<div class="abc-card" style="margin-bottom: 24px;">
		<div class="abc-card-header">
			<h3 class="abc-card-title">
				<span class="dashicons dashicons-admin-settings"></span>
				<?php esc_html_e('Campaign Information', 'autoblogcraft-ai'); ?>
			</h3>
			<a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaign-editor&campaign_id=' . $campaign->ID . '&tab=basic')); ?>" class="button button-secondary">
				<span class="dashicons dashicons-edit" style="margin-top: 3px;"></span>
				<?php esc_html_e('Edit', 'autoblogcraft-ai'); ?>
			</a>
		</div>
		
		<div class="abc-card-body">
			<div class="abc-two-column">
				<div class="abc-info-item">
					<label style="display: block; font-size: 12px; font-weight: 600; color: var(--abc-text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;"><?php esc_html_e('Campaign Name', 'autoblogcraft-ai'); ?></label>
					<div style="color: var(--abc-text-main); font-size: 14px; font-weight: 500;"><?php echo esc_html($campaign->post_title); ?></div>
				</div>
				
				<div class="abc-info-item">
					<label style="display: block; font-size: 12px; font-weight: 600; color: var(--abc-text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;"><?php esc_html_e('Campaign Type', 'autoblogcraft-ai'); ?></label>
					<div>
						<?php echo Template_Helpers::render_campaign_type_badge(get_post_meta($campaign->ID, '_campaign_type', true)); ?>
					</div>
				</div>
				
				<div class="abc-info-item">
					<label style="display: block; font-size: 12px; font-weight: 600; color: var(--abc-text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;"><?php esc_html_e('Status', 'autoblogcraft-ai'); ?></label>
					<div>
						<?php echo Template_Helpers::render_status_badge(get_post_meta($campaign->ID, '_campaign_status', true) ?: 'active'); ?>
					</div>
				</div>
				
				<div class="abc-info-item">
					<label style="display: block; font-size: 12px; font-weight: 600; color: var(--abc-text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;"><?php esc_html_e('Created', 'autoblogcraft-ai'); ?></label>
					<div style="color: var(--abc-text-main); font-size: 14px;">
						<span class="dashicons dashicons-calendar-alt" style="color: var(--abc-primary); margin-right: 4px; font-size: 16px; vertical-align: middle;"></span>
						<?php echo esc_html(date_i18n(get_option('date_format'), strtotime($campaign->post_date))); ?>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Statistics Grid -->
	<div class="abc-stats-grid">
		<div class="abc-stat-card">
			<div class="abc-stat-icon abc-stat-icon-discovered">
				<span class="dashicons dashicons-search"></span>
			</div>
			<div class="abc-stat-content">
				<div class="abc-stat-value"><?php echo esc_html($stats['total_discovered'] ?? 0); ?></div>
				<div class="abc-stat-label"><?php esc_html_e('Total Discovered', 'autoblogcraft-ai'); ?></div>
			</div>
		</div>

		<div class="abc-stat-card">
			<div class="abc-stat-icon abc-stat-icon-queue">
				<span class="dashicons dashicons-list-view"></span>
			</div>
			<div class="abc-stat-content">
				<div class="abc-stat-value"><?php echo esc_html($stats['queue_pending'] ?? 0); ?></div>
				<div class="abc-stat-label"><?php esc_html_e('Queue Pending', 'autoblogcraft-ai'); ?></div>
			</div>
		</div>

		<div class="abc-stat-card">
			<div class="abc-stat-icon abc-stat-icon-processing">
				<span class="dashicons dashicons-update"></span>
			</div>
			<div class="abc-stat-content">
				<div class="abc-stat-value"><?php echo esc_html($stats['queue_processing'] ?? 0); ?></div>
				<div class="abc-stat-label"><?php esc_html_e('Processing', 'autoblogcraft-ai'); ?></div>
			</div>
		</div>

		<div class="abc-stat-card">
			<div class="abc-stat-icon abc-stat-icon-published">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>
			<div class="abc-stat-content">
				<div class="abc-stat-value"><?php echo esc_html($stats['total_published'] ?? 0); ?></div>
				<div class="abc-stat-label"><?php esc_html_e('Published Posts', 'autoblogcraft-ai'); ?></div>
			</div>
		</div>

		<div class="abc-stat-card">
			<div class="abc-stat-icon abc-stat-icon-failed">
				<span class="dashicons dashicons-dismiss"></span>
			</div>
			<div class="abc-stat-content">
				<div class="abc-stat-value"><?php echo esc_html($stats['queue_failed'] ?? 0); ?></div>
				<div class="abc-stat-label"><?php esc_html_e('Failed', 'autoblogcraft-ai'); ?></div>
			</div>
		</div>

		<div class="abc-stat-card">
			<div class="abc-stat-icon abc-stat-icon-rate">
				<span class="dashicons dashicons-chart-line"></span>
			</div>
			<div class="abc-stat-content">
				<div class="abc-stat-value"><?php echo esc_html(number_format($stats['success_rate'] ?? 0, 1)); ?>%</div>
				<div class="abc-stat-label"><?php esc_html_e('Success Rate', 'autoblogcraft-ai'); ?></div>
			</div>
		</div>
	</div>

	<!-- Quick Actions -->
	<div class="abc-editor-section" style="margin-bottom: 20px;">
		<h3 style="margin: 0 0 15px 0;"><?php esc_html_e('Quick Actions', 'autoblogcraft-ai'); ?></h3>
		<div style="display: flex; flex-wrap: wrap; gap: 10px;">
			<?php if (get_post_meta($campaign->ID, '_campaign_status', true) !== 'paused') : ?>
				<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=abc-campaigns&action=pause&id=' . $campaign->ID), 'abc_pause_campaign_' . $campaign->ID)); ?>" 
				   class="button" style="display: inline-flex; align-items: center; gap: 5px;">
					<span class="dashicons dashicons-controls-pause" style="margin-top: 3px;"></span>
					<?php esc_html_e('Pause', 'autoblogcraft-ai'); ?>
				</a>
			<?php else : ?>
				<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=abc-campaigns&action=resume&id=' . $campaign->ID), 'abc_resume_campaign_' . $campaign->ID)); ?>" 
				   class="button button-primary" style="display: inline-flex; align-items: center; gap: 5px;">
					<span class="dashicons dashicons-controls-play" style="margin-top: 3px;"></span>
					<?php esc_html_e('Resume', 'autoblogcraft-ai'); ?>
				</a>
			<?php endif; ?>

			<a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaigns&action=discover&id=' . $campaign->ID)); ?>" 
			   class="button" data-action="discover" style="display: inline-flex; align-items: center; gap: 5px;">
				<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
				<?php esc_html_e('Run Discovery', 'autoblogcraft-ai'); ?>
			</a>

			<a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaigns&action=process&id=' . $campaign->ID)); ?>" 
			   class="button" data-action="process" style="display: inline-flex; align-items: center; gap: 5px;">
				<span class="dashicons dashicons-admin-generic" style="margin-top: 3px;"></span>
				<?php esc_html_e('Process Queue', 'autoblogcraft-ai'); ?>
			</a>

			<a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaigns&action=clone&id=' . $campaign->ID)); ?>" 
			   class="button" style="display: inline-flex; align-items: center; gap: 5px;">
				<span class="dashicons dashicons-admin-page" style="margin-top: 3px;"></span>
				<?php esc_html_e('Clone', 'autoblogcraft-ai'); ?>
			</a>

			<a href="<?php echo esc_url(admin_url('edit.php?abc_campaign_id=' . $campaign->ID)); ?>" 
			   class="button" style="display: inline-flex; align-items: center; gap: 5px;">
				<span class="dashicons dashicons-edit" style="margin-top: 3px;"></span>
				<?php esc_html_e('View Posts', 'autoblogcraft-ai'); ?>
			</a>

			<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=abc-campaigns&action=delete&id=' . $campaign->ID), 'abc_delete_campaign_' . $campaign->ID)); ?>" 
			   class="button" data-confirm="<?php esc_attr_e('Are you sure you want to delete this campaign?', 'autoblogcraft-ai'); ?>"
			   style="display: inline-flex; align-items: center; gap: 5px; color: #b32d2e;">
				<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
				<?php esc_html_e('Delete', 'autoblogcraft-ai'); ?>
			</a>
		</div>
	</div>

	<!-- Recent Activity -->
	<?php if (!empty($stats['recent_posts'])) : ?>
		<div class="abc-recent-activity-card">
			<h3><?php esc_html_e('Recent Posts', 'autoblogcraft-ai'); ?></h3>
			<div class="abc-recent-posts-list">
				<?php foreach ($stats['recent_posts'] as $post) : ?>
					<div class="abc-recent-post-item">
						<div class="abc-post-title">
							<a href="<?php echo esc_url(get_edit_post_link($post['id'])); ?>">
								<?php echo esc_html($post['title']); ?>
							</a>
						</div>
						<div class="abc-post-meta">
							<span class="abc-post-date">
							<?php echo esc_html(Template_Helpers::format_relative_time($post['date'])); ?>
							</span>
							<span class="abc-post-status abc-status-<?php echo esc_attr($post['status']); ?>">
								<?php echo esc_html(ucfirst($post['status'])); ?>
							</span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
