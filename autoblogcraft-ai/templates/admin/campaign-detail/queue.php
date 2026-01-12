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
	<div class="abc-queue-filters">
		<form method="get" class="abc-filters-form">
			<input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? ''); ?>">
			<input type="hidden" name="action" value="detail">
			<input type="hidden" name="id" value="<?php echo esc_attr($campaign->ID); ?>">
			<input type="hidden" name="tab" value="queue">
			
			<div class="abc-filter-group">
				<label for="filter-status"><?php esc_html_e('Status:', 'autoblogcraft-ai'); ?></label>
				<select name="queue_status" id="filter-status">
					<option value=""><?php esc_html_e('All Statuses', 'autoblogcraft-ai'); ?></option>
					<option value="pending" <?php selected($filters['status'] ?? '', 'pending'); ?>><?php esc_html_e('Pending', 'autoblogcraft-ai'); ?></option>
					<option value="processing" <?php selected($filters['status'] ?? '', 'processing'); ?>><?php esc_html_e('Processing', 'autoblogcraft-ai'); ?></option>
					<option value="processed" <?php selected($filters['status'] ?? '', 'processed'); ?>><?php esc_html_e('Processed', 'autoblogcraft-ai'); ?></option>
					<option value="failed" <?php selected($filters['status'] ?? '', 'failed'); ?>><?php esc_html_e('Failed', 'autoblogcraft-ai'); ?></option>
				</select>
			</div>

			<button type="submit" class="button"><?php esc_html_e('Filter', 'autoblogcraft-ai'); ?></button>
			
			<?php if (!empty($filters['status'])) : ?>
				<a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaigns&action=detail&id=' . $campaign->ID . '&tab=queue')); ?>" class="button">
					<?php esc_html_e('Clear', 'autoblogcraft-ai'); ?>
				</a>
			<?php endif; ?>
		</form>

		<div class="abc-queue-actions">
			<button type="button" class="button" id="process-queue-btn" data-campaign-id="<?php echo esc_attr($campaign->ID); ?>">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e('Process Now', 'autoblogcraft-ai'); ?>
			</button>
			<button type="button" class="button" id="clear-failed-btn">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e('Clear Failed', 'autoblogcraft-ai'); ?>
			</button>
		</div>
	</div>

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
							<span class="abc-status abc-status-<?php echo esc_attr($item->status); ?>">
								<?php echo esc_html(ucfirst($item->status)); ?>
							</span>
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
							<?php echo esc_html(human_time_diff(strtotime($item->created_at), current_time('timestamp'))); ?>
							<?php esc_html_e('ago', 'autoblogcraft-ai'); ?>
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
		<?php if ($pagination->total > $pagination->per_page) : ?>
			<div class="abc-pagination">
				<?php
				$total_pages = ceil($pagination->total / $pagination->per_page);
				$current_page = $pagination->current_page;
				
				echo paginate_links([
					'base' => add_query_arg('paged', '%#%'),
					'format' => '',
					'prev_text' => __('&laquo; Previous', 'autoblogcraft-ai'),
					'next_text' => __('Next &raquo;', 'autoblogcraft-ai'),
					'total' => $total_pages,
					'current' => $current_page,
					'type' => 'plain',
				]);
				?>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<div class="abc-empty-state">
			<span class="dashicons dashicons-list-view"></span>
			<h3><?php esc_html_e('No queue items', 'autoblogcraft-ai'); ?></h3>
			<p><?php esc_html_e('Queue items will appear here when discovery runs.', 'autoblogcraft-ai'); ?></p>
		</div>
	<?php endif; ?>
</div>

<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Process queue
		$('#process-queue-btn').on('click', function() {
			var campaignId = $(this).data('campaign-id');
			var $btn = $(this);
			
			$btn.prop('disabled', true).find('.dashicons').addClass('spin');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'abc_process_queue',
					campaign_id: campaignId,
					nonce: '<?php echo esc_js(wp_create_nonce('abc_process_queue')); ?>'
				},
				success: function(response) {
					alert(response.data.message || '<?php esc_html_e('Processing started!', 'autoblogcraft-ai'); ?>');
					location.reload();
				},
				error: function() {
					alert('<?php esc_html_e('Error processing queue.', 'autoblogcraft-ai'); ?>');
				},
				complete: function() {
					$btn.prop('disabled', false).find('.dashicons').removeClass('spin');
				}
			});
		});

		// Process single item
		$('.abc-process-item').on('click', function() {
			var itemId = $(this).data('item-id');
			var $btn = $(this);
			
			$btn.prop('disabled', true);
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'abc_process_queue_item',
					item_id: itemId,
					nonce: '<?php echo esc_js(wp_create_nonce('abc_process_queue_item')); ?>'
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || '<?php esc_html_e('Error processing item.', 'autoblogcraft-ai'); ?>');
						$btn.prop('disabled', false);
					}
				}
			});
		});

		// Delete item
		$('.abc-delete-item').on('click', function() {
			if (!confirm('<?php esc_html_e('Are you sure you want to delete this item?', 'autoblogcraft-ai'); ?>')) {
				return;
			}
			
			var itemId = $(this).data('item-id');
			var $row = $(this).closest('tr');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'abc_delete_queue_item',
					item_id: itemId,
					nonce: '<?php echo esc_js(wp_create_nonce('abc_delete_queue_item')); ?>'
				},
				success: function(response) {
					if (response.success) {
						$row.fadeOut(200, function() {
							$(this).remove();
						});
					}
				}
			});
		});

		// Clear failed items
		$('#clear-failed-btn').on('click', function() {
			if (!confirm('<?php esc_html_e('Are you sure you want to delete all failed items?', 'autoblogcraft-ai'); ?>')) {
				return;
			}
			
			var campaignId = $('#process-queue-btn').data('campaign-id');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'abc_clear_failed_queue',
					campaign_id: campaignId,
					nonce: '<?php echo esc_js(wp_create_nonce('abc_clear_failed_queue')); ?>'
				},
				success: function(response) {
					location.reload();
				}
			});
		});
	});
</script>
