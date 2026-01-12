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

defined('ABSPATH') || exit;

$campaign = $campaign ?? null;
$logs = $logs ?? [];
$pagination = $pagination ?? (object) ['total' => 0, 'per_page' => 50, 'current_page' => 1];
$filters = $filters ?? [];

if (!$campaign) {
	return;
}

// Log level icons and colors
$log_icons = [
	'info' => 'info',
	'success' => 'yes-alt',
	'warning' => 'warning',
	'error' => 'dismiss',
	'debug' => 'admin-tools',
];
?>

<div class="abc-campaign-logs">
	<!-- Logs Filters -->
	<div class="abc-logs-filters">
		<form method="get" class="abc-filters-form">
			<input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? ''); ?>">
			<input type="hidden" name="action" value="detail">
			<input type="hidden" name="id" value="<?php echo esc_attr($campaign->ID); ?>">
			<input type="hidden" name="tab" value="logs">
			
			<div class="abc-filter-group">
				<label for="filter-level"><?php esc_html_e('Level:', 'autoblogcraft-ai'); ?></label>
				<select name="log_level" id="filter-level">
					<option value=""><?php esc_html_e('All Levels', 'autoblogcraft-ai'); ?></option>
					<option value="info" <?php selected($filters['level'] ?? '', 'info'); ?>><?php esc_html_e('Info', 'autoblogcraft-ai'); ?></option>
					<option value="success" <?php selected($filters['level'] ?? '', 'success'); ?>><?php esc_html_e('Success', 'autoblogcraft-ai'); ?></option>
					<option value="warning" <?php selected($filters['level'] ?? '', 'warning'); ?>><?php esc_html_e('Warning', 'autoblogcraft-ai'); ?></option>
					<option value="error" <?php selected($filters['level'] ?? '', 'error'); ?>><?php esc_html_e('Error', 'autoblogcraft-ai'); ?></option>
					<option value="debug" <?php selected($filters['level'] ?? '', 'debug'); ?>><?php esc_html_e('Debug', 'autoblogcraft-ai'); ?></option>
				</select>
			</div>

			<div class="abc-filter-group">
				<label for="filter-category"><?php esc_html_e('Category:', 'autoblogcraft-ai'); ?></label>
				<select name="log_category" id="filter-category">
					<option value=""><?php esc_html_e('All Categories', 'autoblogcraft-ai'); ?></option>
					<option value="discovery" <?php selected($filters['category'] ?? '', 'discovery'); ?>><?php esc_html_e('Discovery', 'autoblogcraft-ai'); ?></option>
					<option value="processing" <?php selected($filters['category'] ?? '', 'processing'); ?>><?php esc_html_e('Processing', 'autoblogcraft-ai'); ?></option>
					<option value="publishing" <?php selected($filters['category'] ?? '', 'publishing'); ?>><?php esc_html_e('Publishing', 'autoblogcraft-ai'); ?></option>
					<option value="ai" <?php selected($filters['category'] ?? '', 'ai'); ?>><?php esc_html_e('AI', 'autoblogcraft-ai'); ?></option>
					<option value="system" <?php selected($filters['category'] ?? '', 'system'); ?>><?php esc_html_e('System', 'autoblogcraft-ai'); ?></option>
				</select>
			</div>

			<button type="submit" class="button"><?php esc_html_e('Filter', 'autoblogcraft-ai'); ?></button>
			
			<?php if (!empty($filters['level']) || !empty($filters['category'])) : ?>
				<a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaigns&action=detail&id=' . $campaign->ID . '&tab=logs')); ?>" class="button">
					<?php esc_html_e('Clear', 'autoblogcraft-ai'); ?>
				</a>
			<?php endif; ?>
		</form>

		<div class="abc-logs-actions">
			<button type="button" class="button" id="export-logs-btn">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e('Export Logs', 'autoblogcraft-ai'); ?>
			</button>
			<button type="button" class="button" id="clear-logs-btn">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e('Clear Logs', 'autoblogcraft-ai'); ?>
			</button>
		</div>
	</div>

	<!-- Logs List -->
	<?php if (!empty($logs)) : ?>
		<div class="abc-logs-list">
			<?php foreach ($logs as $log) : ?>
				<div class="abc-log-entry abc-log-<?php echo esc_attr($log->level); ?>">
					<div class="abc-log-header">
						<span class="abc-log-icon dashicons dashicons-<?php echo esc_attr($log_icons[$log->level] ?? 'info'); ?>"></span>
						<span class="abc-log-level"><?php echo esc_html(strtoupper($log->level)); ?></span>
						<span class="abc-log-category">[<?php echo esc_html($log->category ?? 'general'); ?>]</span>
						<span class="abc-log-time">
							<?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?>
						</span>
						<span class="abc-log-ago">
							(<?php echo esc_html(human_time_diff(strtotime($log->created_at), current_time('timestamp'))); ?> <?php esc_html_e('ago', 'autoblogcraft-ai'); ?>)
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
			<span class="dashicons dashicons-media-text"></span>
			<h3><?php esc_html_e('No logs yet', 'autoblogcraft-ai'); ?></h3>
			<p><?php esc_html_e('Activity logs will appear here.', 'autoblogcraft-ai'); ?></p>
		</div>
	<?php endif; ?>
</div>

<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Toggle context details
		$('.abc-toggle-context').on('click', function() {
			var $btn = $(this);
			var $context = $btn.siblings('.abc-context-data');
			
			$context.slideToggle(200, function() {
				if ($context.is(':visible')) {
					$btn.find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
					$btn.html('<span class="dashicons dashicons-arrow-up-alt2"></span> <?php esc_html_e('Hide Details', 'autoblogcraft-ai'); ?>');
				} else {
					$btn.find('.dashicons').removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
					$btn.html('<span class="dashicons dashicons-arrow-down-alt2"></span> <?php esc_html_e('Show Details', 'autoblogcraft-ai'); ?>');
				}
			});
		});

		// Export logs
		$('#export-logs-btn').on('click', function() {
			var campaignId = <?php echo intval($campaign->ID); ?>;
			var level = '<?php echo esc_js($filters['level'] ?? ''); ?>';
			var category = '<?php echo esc_js($filters['category'] ?? ''); ?>';
			
			var params = new URLSearchParams({
				action: 'abc_export_logs',
				campaign_id: campaignId,
				level: level,
				category: category,
				nonce: '<?php echo esc_js(wp_create_nonce('abc_export_logs')); ?>'
			});
			
			window.location.href = ajaxurl + '?' + params.toString();
		});

		// Clear logs
		$('#clear-logs-btn').on('click', function() {
			if (!confirm('<?php esc_html_e('Are you sure you want to clear all logs for this campaign?', 'autoblogcraft-ai'); ?>')) {
				return;
			}
			
			var campaignId = <?php echo intval($campaign->ID); ?>;
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'abc_clear_campaign_logs',
					campaign_id: campaignId,
					nonce: '<?php echo esc_js(wp_create_nonce('abc_clear_campaign_logs')); ?>'
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || '<?php esc_html_e('Error clearing logs.', 'autoblogcraft-ai'); ?>');
					}
				}
			});
		});
	});
</script>
