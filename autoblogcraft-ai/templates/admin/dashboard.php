<?php
/**
 * Dashboard Template
 *
 * Main dashboard page showing statistics and overview.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Admin
 * @since 2.0.0
 *
 * @var array $stats Dashboard statistics
 * @var array $recent_campaigns Recently active campaigns
 * @var array $recent_posts Recently published posts
 * @var array $queue_stats Queue statistics
 */

defined('ABSPATH') || exit;

// Set up header data
$page_title = __('Dashboard', 'autoblogcraft-ai');
$page_description = __('Welcome to AutoBlogCraft AI. Monitor your campaigns and content generation.', 'autoblogcraft-ai');
$breadcrumbs = [
	__('Dashboard', 'autoblogcraft-ai') => '',
];
$actions = [
	[
		'label' => __('New Campaign', 'autoblogcraft-ai'),
		'url' => admin_url('admin.php?page=abc-campaigns&action=new'),
		'class' => 'button button-primary',
		'icon' => 'plus-alt',
	],
];

// Include header
include dirname(__FILE__) . '/../partials/header.php';

// Get stats
$stats = $stats ?? [];
$recent_campaigns = $recent_campaigns ?? [];
$recent_posts = $recent_posts ?? [];
$queue_stats = $queue_stats ?? [];
?>

<div class="abc-dashboard-wrapper">
	<!-- Statistics Cards -->
	<div class="abc-stats-grid">
		<div class="abc-stat-card abc-stat-campaigns">
			<div class="abc-stat-icon">
				<span class="dashicons dashicons-megaphone"></span>
			</div>
			<div class="abc-stat-content">
				<div class="abc-stat-value">
					<?php echo esc_html($stats['total_campaigns'] ?? 0); ?>
				</div>
				<div class="abc-stat-label">
					<?php esc_html_e('Total Campaigns', 'autoblogcraft-ai'); ?>
				</div>
				<div class="abc-stat-meta">
					<span class="abc-stat-active">
						<?php 
						/* translators: %d: Number of active campaigns */
						printf(esc_html__('%d Active', 'autoblogcraft-ai'), intval($stats['active_campaigns'] ?? 0)); 
						?>
					</span>
				</div>
			</div>
		</div>

		<div class="abc-stat-card abc-stat-posts">
			<div class="abc-stat-icon">
				<span class="dashicons dashicons-edit"></span>
			</div>
			<div class="abc-stat-content">
				<div class="abc-stat-value">
					<?php echo esc_html($stats['total_posts'] ?? 0); ?>
				</div>
				<div class="abc-stat-label">
					<?php esc_html_e('Published Posts', 'autoblogcraft-ai'); ?>
				</div>
				<div class="abc-stat-meta">
					<span class="abc-stat-today">
						<?php 
						/* translators: %d: Number of posts published today */
						printf(esc_html__('%d Today', 'autoblogcraft-ai'), intval($stats['posts_today'] ?? 0)); 
						?>
					</span>
				</div>
			</div>
		</div>

		<div class="abc-stat-card abc-stat-queue">
			<div class="abc-stat-icon">
				<span class="dashicons dashicons-list-view"></span>
			</div>
			<div class="abc-stat-content">
				<div class="abc-stat-value">
					<?php echo esc_html($stats['queue_pending'] ?? 0); ?>
				</div>
				<div class="abc-stat-label">
					<?php esc_html_e('Queue Pending', 'autoblogcraft-ai'); ?>
				</div>
				<div class="abc-stat-meta">
					<span class="abc-stat-processing">
						<?php 
						/* translators: %d: Number of items being processed */
						printf(esc_html__('%d Processing', 'autoblogcraft-ai'), intval($stats['queue_processing'] ?? 0)); 
						?>
					</span>
				</div>
			</div>
		</div>

		<div class="abc-stat-card abc-stat-tokens">
			<div class="abc-stat-icon">
				<span class="dashicons dashicons-performance"></span>
			</div>
			<div class="abc-stat-content">
				<div class="abc-stat-value">
					<?php echo esc_html(number_format_i18n($stats['tokens_used'] ?? 0)); ?>
				</div>
				<div class="abc-stat-label">
					<?php esc_html_e('AI Tokens Used', 'autoblogcraft-ai'); ?>
				</div>
				<div class="abc-stat-meta">
					<span class="abc-stat-month">
						<?php esc_html_e('This Month', 'autoblogcraft-ai'); ?>
					</span>
				</div>
			</div>
		</div>
	</div>

	<!-- Main Content Grid -->
	<div class="abc-dashboard-grid">
		<!-- Recent Campaigns -->
		<div class="abc-dashboard-section abc-recent-campaigns">
			<div class="abc-section-header">
				<h2><?php esc_html_e('Recent Campaigns', 'autoblogcraft-ai'); ?></h2>
				<a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaigns')); ?>" class="abc-section-link">
					<?php esc_html_e('View All', 'autoblogcraft-ai'); ?>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</a>
			</div>

			<?php if (!empty($recent_campaigns)) : ?>
				<div class="abc-campaigns-list">
					<?php foreach ($recent_campaigns as $campaign) : ?>
						<div class="abc-campaign-item">
							<div class="abc-campaign-icon">
								<span class="dashicons dashicons-<?php echo esc_attr($campaign['icon'] ?? 'admin-site'); ?>"></span>
							</div>
							<div class="abc-campaign-info">
								<h3>
									<a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaigns&action=detail&id=' . $campaign['id'])); ?>">
										<?php echo esc_html($campaign['name']); ?>
									</a>
								</h3>
								<div class="abc-campaign-meta">
									<span class="abc-campaign-type"><?php echo esc_html($campaign['type']); ?></span>
									<span class="abc-campaign-status abc-status-<?php echo esc_attr($campaign['status']); ?>">
										<?php echo esc_html(ucfirst($campaign['status'])); ?>
									</span>
								</div>
							</div>
							<div class="abc-campaign-stats">
								<div class="abc-stat-item">
									<span class="abc-stat-number"><?php echo esc_html($campaign['posts_count'] ?? 0); ?></span>
									<span class="abc-stat-text"><?php esc_html_e('Posts', 'autoblogcraft-ai'); ?></span>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="abc-empty-state">
					<span class="dashicons dashicons-megaphone"></span>
					<p><?php esc_html_e('No campaigns yet. Create your first campaign to get started!', 'autoblogcraft-ai'); ?></p>
					<a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaigns&action=new')); ?>" class="button button-primary">
						<?php esc_html_e('Create Campaign', 'autoblogcraft-ai'); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>

		<!-- Recent Posts -->
		<div class="abc-dashboard-section abc-recent-posts">
			<div class="abc-section-header">
				<h2><?php esc_html_e('Recent Posts', 'autoblogcraft-ai'); ?></h2>
				<a href="<?php echo esc_url(admin_url('edit.php')); ?>" class="abc-section-link">
					<?php esc_html_e('View All', 'autoblogcraft-ai'); ?>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</a>
			</div>

			<?php if (!empty($recent_posts)) : ?>
				<div class="abc-posts-list">
					<?php foreach ($recent_posts as $post) : ?>
						<div class="abc-post-item">
							<div class="abc-post-info">
								<h3>
									<a href="<?php echo esc_url(get_edit_post_link($post['id'])); ?>">
										<?php echo esc_html($post['title']); ?>
									</a>
								</h3>
								<div class="abc-post-meta">
									<span class="abc-post-date">
										<?php echo esc_html(human_time_diff(strtotime($post['date']), current_time('timestamp'))); ?>
										<?php esc_html_e('ago', 'autoblogcraft-ai'); ?>
									</span>
									<span class="abc-post-campaign">
										<?php echo esc_html($post['campaign_name'] ?? __('Unknown', 'autoblogcraft-ai')); ?>
									</span>
								</div>
							</div>
							<div class="abc-post-actions">
								<a href="<?php echo esc_url(get_permalink($post['id'])); ?>" target="_blank" class="abc-post-view" title="<?php esc_attr_e('View Post', 'autoblogcraft-ai'); ?>">
									<span class="dashicons dashicons-external"></span>
								</a>
								<a href="<?php echo esc_url(get_edit_post_link($post['id'])); ?>" class="abc-post-edit" title="<?php esc_attr_e('Edit Post', 'autoblogcraft-ai'); ?>">
									<span class="dashicons dashicons-edit"></span>
								</a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="abc-empty-state">
					<span class="dashicons dashicons-edit"></span>
					<p><?php esc_html_e('No posts published yet.', 'autoblogcraft-ai'); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- Queue Status -->
	<?php if (!empty($queue_stats['by_status'])) : ?>
		<div class="abc-dashboard-section abc-queue-status">
			<div class="abc-section-header">
				<h2><?php esc_html_e('Queue Status', 'autoblogcraft-ai'); ?></h2>
				<a href="<?php echo esc_url(admin_url('admin.php?page=abc-queue')); ?>" class="abc-section-link">
					<?php esc_html_e('Manage Queue', 'autoblogcraft-ai'); ?>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</a>
			</div>

			<div class="abc-queue-stats-grid">
				<?php foreach ($queue_stats['by_status'] as $status => $count) : ?>
					<div class="abc-queue-stat-item abc-queue-<?php echo esc_attr($status); ?>">
						<div class="abc-queue-stat-count"><?php echo esc_html($count); ?></div>
						<div class="abc-queue-stat-label"><?php echo esc_html(ucfirst($status)); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
</div>

<?php
// Include footer
include dirname(__FILE__) . '/../partials/footer.php';
