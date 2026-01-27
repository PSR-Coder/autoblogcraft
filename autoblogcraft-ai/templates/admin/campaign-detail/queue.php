<?php
/**
 * Campaign Detail - Queue Tab Template
 *
 * Displays and manages campaign queue items.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Admin\Campaign_Detail
 * @since 2.0.0
 *
 * @var object $campaign Campaign object
 * @var array $queue_items Queue items
 * @var object $pagination Pagination data
 * @var array $filters Active filters
 */

use AutoBlogCraft\Helpers\Template_Helpers;

defined('ABSPATH') || exit;

$campaign = $campaign ?? null;
$queue_items = $queue_items ?? [];
$pagination = $pagination ?? (object) ['total' => 0, 'per_page' => 20, 'current_page' => 1];
$filters = $filters ?? [];

if (!$campaign) {
	return;
}
?>

<div class="abc-campaign-queue">
	<!-- Queue Filters -->
	<?php
	Template_Helpers::render_campaign_filter_bar(
		$campaign->ID,
		'queue',
		[
			[
				'type' => 'select',
				'key' => 'status',
				'name' => 'queue_status',
				'label' => __('Status:', 'autoblogcraft-ai'),
				'placeholder' => __('All Statuses', 'autoblogcraft-ai'),
				'options' => [
					'pending' => __('Pending', 'autoblogcraft-ai'),
					'processing' => __('Processing', 'autoblogcraft-ai'),
					'processed' => __('Processed', 'autoblogcraft-ai'),
					'failed' => __('Failed', 'autoblogcraft-ai'),
				],
			],
		],
		$filters,
		[
			[
				'id' => 'process-queue-btn',
				'label' => __('Process Now', 'autoblogcraft-ai'),
				'icon' => 'update',
				'data' => ['campaign-id' => $campaign->ID],
			],
			[
				'id' => 'clear-failed-btn',
				'label' => __('Clear Failed', 'autoblogcraft-ai'),
				'icon' => 'trash',
			],
		]
	);
	?>

	<!-- Queue Table -->
	<?php if (!empty($queue_items)) : ?>
		<table class="wp-list-table widefat fixed striped abc-queue-table">
			<thead>
				<tr>
					<th class="manage-column column-source"><?php esc_html_e('Source', 'autoblogcraft-ai'); ?></th>
					<th class="manage-column column-status"><?php esc_html_e('Status', 'autoblogcraft-ai'); ?></th>
					<th class="manage-column column-priority"><?php esc_html_e('Priority', 'autoblogcraft-ai'); ?></th>
					<th class="manage-column column-attempts"><?php esc_html_e('Attempts', 'autoblogcraft-ai'); ?></th>
					<th class="manage-column column-date"><?php esc_html_e('Added', 'autoblogcraft-ai'); ?></th>
					<th class="manage-column column-actions"><?php esc_html_e('Actions', 'autoblogcraft-ai'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($queue_items as $item) : ?>
					<tr class="abc-queue-item abc-queue-status-<?php echo esc_attr($item->status); ?>">
						<td class="column-source" data-colname="<?php esc_attr_e('Source', 'autoblogcraft-ai'); ?>">
							<strong><?php echo esc_html($item->source_url ?? __('Unknown', 'autoblogcraft-ai')); ?></strong>
							<?php if (!empty($item->data)) : ?>
								<?php $data = is_string($item->data) ? json_decode($item->data, true) : $item->data; ?>
								<?php if (!empty($data['title'])) : ?>
									<div class="abc-queue-title"><?php echo esc_html($data['title']); ?></div>
								<?php endif; ?>
							<?php endif; ?>
						</td>
						<td class="column-status" data-colname="<?php esc_attr_e('Status', 'autoblogcraft-ai'); ?>">
						<?php echo Template_Helpers::render_status_badge($item->status); ?>
							<?php if ($item->status === 'failed' && !empty($item->error_message)) : ?>
								<div class="abc-error-message" title="<?php echo esc_attr($item->error_message); ?>">
									<span class="dashicons dashicons-warning"></span>
									<?php echo esc_html(wp_trim_words($item->error_message, 10)); ?>
								</div>
							<?php endif; ?>
						</td>
						<td class="column-priority" data-colname="<?php esc_attr_e('Priority', 'autoblogcraft-ai'); ?>">
							<?php echo esc_html($item->priority ?? 5); ?>
						</td>
						<td class="column-attempts" data-colname="<?php esc_attr_e('Attempts', 'autoblogcraft-ai'); ?>">
							<?php echo esc_html($item->attempts ?? 0); ?> / 3
						</td>
						<td class="column-date" data-colname="<?php esc_attr_e('Added', 'autoblogcraft-ai'); ?>">
					<?php echo esc_html(Template_Helpers::format_relative_time($item->created_at)); ?>
						</td>
						<td class="column-actions" data-colname="<?php esc_attr_e('Actions', 'autoblogcraft-ai'); ?>">
							<?php if ($item->status === 'pending' || $item->status === 'failed') : ?>
								<button type="button" class="button button-small abc-process-item" data-item-id="<?php echo esc_attr($item->id); ?>">
									<?php esc_html_e('Process', 'autoblogcraft-ai'); ?>
								</button>
							<?php endif; ?>
							<?php if (!empty($item->post_id)) : ?>
								<a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>" class="button button-small">
									<?php esc_html_e('View Post', 'autoblogcraft-ai'); ?>
								</a>
							<?php endif; ?>
							<button type="button" class="button button-small abc-delete-item" data-item-id="<?php echo esc_attr($item->id); ?>">
								<?php esc_html_e('Delete', 'autoblogcraft-ai'); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<!-- Pagination -->
		<?php echo Template_Helpers::render_pagination($pagination); ?>
	<?php else : ?>
	<?php Template_Helpers::render_empty_state(
		__('No queue items', 'autoblogcraft-ai'),
		__('Queue items will appear here when discovery runs.', 'autoblogcraft-ai'),
		'list-view'
	); ?>
	<?php endif; ?>
</div>
