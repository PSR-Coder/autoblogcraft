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

defined('ABSPATH') || exit;

$campaign = $campaign ?? null;
$stats = $stats ?? [];

if (!$campaign) {
	return;
}
?>

<div class="abc-campaign-overview">
	<!-- Campaign Info Card -->
	<div class="abc-info-card">
		<div class="abc-info-header">
			<h3><?php esc_html_e('Campaign Information', 'autoblogcraft-ai'); ?></h3>
			<a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaigns&action=edit&id=' . $campaign->ID)); ?>" class="button">
				<span class="dashicons dashicons-edit"></span>
				<?php esc_html_e('Edit', 'autoblogcraft-ai'); ?>
			</a>
		</div>
		
		<div class="abc-info-body">
			<div class="abc-info-row">
				<div class="abc-info-label"><?php esc_html_e('Campaign Name:', 'autoblogcraft-ai'); ?></div>
				<div class="abc-info-value"><?php echo esc_html($campaign->post_title); ?></div>
			</div>
			
			<div class="abc-info-row">
				<div class="abc-info-label"><?php esc_html_e('Campaign Type:', 'autoblogcraft-ai'); ?></div>
				<div class="abc-info-value">
					<span class="abc-campaign-type-badge abc-type-<?php echo esc_attr(get_post_meta($campaign->ID, '_abc_campaign_type', true)); ?>">
						<?php echo esc_html(ucfirst(get_post_meta($campaign->ID, '_abc_campaign_type', true))); ?>
					</span>
				</div>
			</div>
			
			<div class="abc-info-row">
				<div class="abc-info-label"><?php esc_html_e('Status:', 'autoblogcraft-ai'); ?></div>
				<div class="abc-info-value">
					<span class="abc-status abc-status-<?php echo esc_attr($campaign->post_status); ?>">
						<?php echo esc_html(ucfirst($campaign->post_status)); ?>
					</span>
				</div>
			</div>
			
			<div class="abc-info-row">
				<div class="abc-info-label"><?php esc_html_e('Created:', 'autoblogcraft-ai'); ?></div>
				<div class="abc-info-value">
					<?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($campaign->post_date))); ?>
				</div>
			</div>
			
			<div class="abc-info-row">
				<div class="abc-info-label"><?php esc_html_e('Last Modified:', 'autoblogcraft-ai'); ?></div>
				<div class="abc-info-value">
					<?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($campaign->post_modified))); ?>
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
	<div class="abc-quick-actions-card">
		<h3><?php esc_html_e('Quick Actions', 'autoblogcraft-ai'); ?></h3>
		<div class="abc-quick-actions-grid">
			<?php if ($campaign->post_status === 'publish') : ?>
				<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=abc-campaigns&action=pause&id=' . $campaign->ID), 'abc_pause_campaign_' . $campaign->ID)); ?>" 
				   class="abc-quick-action abc-action-pause">
					<span class="dashicons dashicons-controls-pause"></span>
					<span class="abc-action-label"><?php esc_html_e('Pause Campaign', 'autoblogcraft-ai'); ?></span>
				</a>
			<?php else : ?>
				<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=abc-campaigns&action=resume&id=' . $campaign->ID), 'abc_resume_campaign_' . $campaign->ID)); ?>" 
				   class="abc-quick-action abc-action-resume">
					<span class="dashicons dashicons-controls-play"></span>
					<span class="abc-action-label"><?php esc_html_e('Resume Campaign', 'autoblogcraft-ai'); ?></span>
				</a>
			<?php endif; ?>

			<a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaigns&action=discover&id=' . $campaign->ID)); ?>" 
			   class="abc-quick-action abc-action-discover" data-action="discover">
				<span class="dashicons dashicons-update"></span>
				<span class="abc-action-label"><?php esc_html_e('Run Discovery Now', 'autoblogcraft-ai'); ?></span>
			</a>

			<a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaigns&action=process&id=' . $campaign->ID)); ?>" 
			   class="abc-quick-action abc-action-process" data-action="process">
				<span class="dashicons dashicons-admin-generic"></span>
				<span class="abc-action-label"><?php esc_html_e('Process Queue', 'autoblogcraft-ai'); ?></span>
			</a>

			<a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaigns&action=clone&id=' . $campaign->ID)); ?>" 
			   class="abc-quick-action abc-action-clone">
				<span class="dashicons dashicons-admin-page"></span>
				<span class="abc-action-label"><?php esc_html_e('Clone Campaign', 'autoblogcraft-ai'); ?></span>
			</a>

			<a href="<?php echo esc_url(admin_url('edit.php?abc_campaign_id=' . $campaign->ID)); ?>" 
			   class="abc-quick-action abc-action-posts">
				<span class="dashicons dashicons-edit"></span>
				<span class="abc-action-label"><?php esc_html_e('View All Posts', 'autoblogcraft-ai'); ?></span>
			</a>

			<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=abc-campaigns&action=delete&id=' . $campaign->ID), 'abc_delete_campaign_' . $campaign->ID)); ?>" 
			   class="abc-quick-action abc-action-delete" 
			   data-confirm="<?php esc_attr_e('Are you sure you want to delete this campaign?', 'autoblogcraft-ai'); ?>">
				<span class="dashicons dashicons-trash"></span>
				<span class="abc-action-label"><?php esc_html_e('Delete Campaign', 'autoblogcraft-ai'); ?></span>
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
								<?php echo esc_html(human_time_diff(strtotime($post['date']), current_time('timestamp'))); ?>
								<?php esc_html_e('ago', 'autoblogcraft-ai'); ?>
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

<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Confirm delete action
		$('.abc-action-delete').on('click', function(e) {
			var message = $(this).data('confirm');
			if (!confirm(message)) {
				e.preventDefault();
			}
		});

		// Handle async actions (discover, process)
		$('[data-action="discover"], [data-action="process"]').on('click', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var action = $btn.data('action');
			
			$btn.addClass('abc-action-loading').find('.dashicons').addClass('spin');
			
			// This would trigger an AJAX call in the real implementation
			setTimeout(function() {
				$btn.removeClass('abc-action-loading').find('.dashicons').removeClass('spin');
				alert(action === 'discover' ? '<?php esc_html_e('Discovery started!', 'autoblogcraft-ai'); ?>' : '<?php esc_html_e('Processing started!', 'autoblogcraft-ai'); ?>');
			}, 1000);
		});
	});
</script>
