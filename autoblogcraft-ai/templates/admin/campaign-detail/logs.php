<?php
/**
 * Campaign Detail - Logs Tab Template
 *
 * Displays campaign activity logs.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Admin\Campaign_Detail
 * @since 2.0.0
 *
 * @var object $campaign Campaign object
 * @var array $logs Log entries
 * @var object $pagination Pagination data
 * @var array $filters Active filters
 */

use AutoBlogCraft\Helpers\Template_Helpers;

defined('ABSPATH') || exit;

$campaign = $campaign ?? null;
$logs = $logs ?? [];
$pagination = $pagination ?? (object) ['total' => 0, 'per_page' => 50, 'current_page' => 1];
$filters = $filters ?? [];

if (!$campaign) {
	return;
}
?>

<div class="abc-campaign-logs">
	<!-- Logs Filters -->
	<?php
	Template_Helpers::render_campaign_filter_bar(
		$campaign->ID,
		'logs',
		[
			[
				'type' => 'select',
				'key' => 'level',
				'name' => 'log_level',
				'label' => __('Level:', 'autoblogcraft-ai'),
				'placeholder' => __('All Levels', 'autoblogcraft-ai'),
				'options' => [
					'info' => __('Info', 'autoblogcraft-ai'),
					'success' => __('Success', 'autoblogcraft-ai'),
					'warning' => __('Warning', 'autoblogcraft-ai'),
					'error' => __('Error', 'autoblogcraft-ai'),
					'debug' => __('Debug', 'autoblogcraft-ai'),
				],
			],
			[
				'type' => 'select',
				'key' => 'category',
				'name' => 'log_category',
				'label' => __('Category:', 'autoblogcraft-ai'),
				'placeholder' => __('All Categories', 'autoblogcraft-ai'),
				'options' => [
					'discovery' => __('Discovery', 'autoblogcraft-ai'),
					'processing' => __('Processing', 'autoblogcraft-ai'),
					'publishing' => __('Publishing', 'autoblogcraft-ai'),
					'ai' => __('AI', 'autoblogcraft-ai'),
					'system' => __('System', 'autoblogcraft-ai'),
				],
			],
		],
		$filters,
		[
			[
				'id' => 'export-logs-btn',
				'label' => __('Export Logs', 'autoblogcraft-ai'),
				'icon' => 'download',
				'data' => [
					'campaign-id' => $campaign->ID,
					'level' => $filters['level'] ?? '',
					'category' => $filters['category'] ?? '',
				],
			],
			[
				'id' => 'clear-logs-btn',
				'label' => __('Clear Logs', 'autoblogcraft-ai'),
				'icon' => 'trash',
				'data' => [
					'campaign-id' => $campaign->ID,
				],
			],
		]
	);
	?>

	<!-- Logs List -->
	<?php if (!empty($logs)) : ?>
		<div class="abc-logs-list">
			<?php foreach ($logs as $log) : ?>
				<div class="abc-log-entry abc-log-<?php echo esc_attr($log->level); ?>">
					<div class="abc-log-header">
					<?php echo Template_Helpers::render_log_level_badge($log->level); ?>
						<span class="abc-log-category">[<?php echo esc_html($log->category ?? 'general'); ?>]</span>
						<span class="abc-log-time">
							<?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?>
						</span>
						<span class="abc-log-ago">
						(<?php echo esc_html(Template_Helpers::format_relative_time($log->created_at)); ?>)
						</span>
					</div>
					
					<div class="abc-log-message">
						<?php echo esc_html($log->message); ?>
					</div>
					
					<?php if (!empty($log->context)) : ?>
						<div class="abc-log-context">
							<button type="button" class="abc-toggle-context button button-small">
								<span class="dashicons dashicons-arrow-down-alt2"></span>
								<?php esc_html_e('Show Details', 'autoblogcraft-ai'); ?>
							</button>
							<div class="abc-context-data" style="display: none;">
								<pre><?php echo esc_html(is_string($log->context) ? $log->context : json_encode(json_decode($log->context), JSON_PRETTY_PRINT)); ?></pre>
							</div>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Pagination -->
		<?php echo Template_Helpers::render_pagination($pagination); ?>
	<?php else : ?>
		<?php Template_Helpers::render_empty_state(
			__('No logs yet', 'autoblogcraft-ai'),
			__('Activity logs will appear here.', 'autoblogcraft-ai'),
			'media-text'
		); ?>
	<?php endif; ?>
</div>
