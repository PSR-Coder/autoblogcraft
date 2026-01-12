<?php
/**
 * Campaigns List Template
 *
 * Displays the campaigns listing page with filters and bulk actions.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Admin
 * @since 2.0.0
 *
 * @var array $campaigns List of campaigns
 * @var array $filters Active filters
 * @var object $pagination Pagination data
 */

defined('ABSPATH') || exit;

// Set up header data
$page_title = __('Campaigns', 'autoblogcraft-ai');
$page_description = __('Manage your content generation campaigns.', 'autoblogcraft-ai');
$breadcrumbs = [
	__('Dashboard', 'autoblogcraft-ai') => admin_url('admin.php?page=abc-dashboard'),
	__('Campaigns', 'autoblogcraft-ai') => '',
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

$campaigns = $campaigns ?? [];
$filters = $filters ?? [];
$pagination = $pagination ?? (object) ['total' => 0, 'per_page' => 20, 'current_page' => 1];

// Campaign type icons
$type_icons = [
	'website' => 'admin-site',
	'youtube' => 'video-alt3',
	'amazon' => 'cart',
	'news' => 'media-document',
];
?>

<div class="abc-campaigns-wrapper">
	<!-- Filters and Search -->
	<div class="abc-campaigns-filters">
		<form method="get" class="abc-filters-form">
			<input type="hidden" name="page" value="abc-campaigns">
			
			<div class="abc-filter-group">
				<label for="filter-type"><?php esc_html_e('Type:', 'autoblogcraft-ai'); ?></label>
				<select name="type" id="filter-type">
					<option value=""><?php esc_html_e('All Types', 'autoblogcraft-ai'); ?></option>
					<option value="website" <?php selected($filters['type'] ?? '', 'website'); ?>><?php esc_html_e('Website', 'autoblogcraft-ai'); ?></option>
					<option value="youtube" <?php selected($filters['type'] ?? '', 'youtube'); ?>><?php esc_html_e('YouTube', 'autoblogcraft-ai'); ?></option>
					<option value="amazon" <?php selected($filters['type'] ?? '', 'amazon'); ?>><?php esc_html_e('Amazon', 'autoblogcraft-ai'); ?></option>
					<option value="news" <?php selected($filters['type'] ?? '', 'news'); ?>><?php esc_html_e('News', 'autoblogcraft-ai'); ?></option>
				</select>
			</div>

			<div class="abc-filter-group">
				<label for="filter-status"><?php esc_html_e('Status:', 'autoblogcraft-ai'); ?></label>
				<select name="status" id="filter-status">
					<option value=""><?php esc_html_e('All Statuses', 'autoblogcraft-ai'); ?></option>
					<option value="active" <?php selected($filters['status'] ?? '', 'active'); ?>><?php esc_html_e('Active', 'autoblogcraft-ai'); ?></option>
					<option value="paused" <?php selected($filters['status'] ?? '', 'paused'); ?>><?php esc_html_e('Paused', 'autoblogcraft-ai'); ?></option>
					<option value="draft" <?php selected($filters['status'] ?? '', 'draft'); ?>><?php esc_html_e('Draft', 'autoblogcraft-ai'); ?></option>
				</select>
			</div>

			<div class="abc-filter-group abc-filter-search">
				<label for="filter-search" class="screen-reader-text"><?php esc_html_e('Search campaigns', 'autoblogcraft-ai'); ?></label>
				<input type="search" name="s" id="filter-search" 
				       value="<?php echo esc_attr($filters['search'] ?? ''); ?>" 
				       placeholder="<?php esc_attr_e('Search campaigns...', 'autoblogcraft-ai'); ?>">
			</div>

			<button type="submit" class="button">
				<?php esc_html_e('Filter', 'autoblogcraft-ai'); ?>
			</button>
			
			<?php if (!empty($filters['type']) || !empty($filters['status']) || !empty($filters['search'])) : ?>
				<a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaigns')); ?>" class="button">
					<?php esc_html_e('Clear Filters', 'autoblogcraft-ai'); ?>
				</a>
			<?php endif; ?>
		</form>
	</div>

	<!-- Campaigns Table -->
	<?php if (!empty($campaigns)) : ?>
		<form method="post" id="abc-campaigns-form">
			<?php wp_nonce_field('abc_bulk_action', 'abc_bulk_nonce'); ?>
			
			<!-- Bulk Actions -->
			<div class="abc-bulk-actions">
				<select name="bulk_action" id="bulk-action-selector">
					<option value=""><?php esc_html_e('Bulk Actions', 'autoblogcraft-ai'); ?></option>
					<option value="pause"><?php esc_html_e('Pause', 'autoblogcraft-ai'); ?></option>
					<option value="resume"><?php esc_html_e('Resume', 'autoblogcraft-ai'); ?></option>
					<option value="delete"><?php esc_html_e('Delete', 'autoblogcraft-ai'); ?></option>
				</select>
				<button type="submit" class="button" id="bulk-action-submit">
					<?php esc_html_e('Apply', 'autoblogcraft-ai'); ?>
				</button>
			</div>

			<table class="wp-list-table widefat fixed striped abc-campaigns-table">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column">
							<input type="checkbox" id="cb-select-all">
						</td>
						<th class="manage-column column-name column-primary">
							<?php esc_html_e('Campaign Name', 'autoblogcraft-ai'); ?>
						</th>
						<th class="manage-column column-type">
							<?php esc_html_e('Type', 'autoblogcraft-ai'); ?>
						</th>
						<th class="manage-column column-status">
							<?php esc_html_e('Status', 'autoblogcraft-ai'); ?>
						</th>
						<th class="manage-column column-posts">
							<?php esc_html_e('Posts', 'autoblogcraft-ai'); ?>
						</th>
						<th class="manage-column column-queue">
							<?php esc_html_e('Queue', 'autoblogcraft-ai'); ?>
						</th>
						<th class="manage-column column-date">
							<?php esc_html_e('Created', 'autoblogcraft-ai'); ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($campaigns as $campaign) : ?>
						<tr>
							<th class="check-column">
								<input type="checkbox" name="campaign_ids[]" value="<?php echo esc_attr($campaign['id']); ?>">
							</th>
							<td class="column-name column-primary" data-colname="<?php esc_attr_e('Campaign Name', 'autoblogcraft-ai'); ?>">
								<strong>
									<a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaigns&action=detail&id=' . $campaign['id'])); ?>">
										<?php echo esc_html($campaign['name']); ?>
									</a>
								</strong>
								<div class="row-actions">
									<span class="view">
										<a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaigns&action=detail&id=' . $campaign['id'])); ?>">
											<?php esc_html_e('View', 'autoblogcraft-ai'); ?>
										</a> |
									</span>
									<span class="edit">
										<a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaigns&action=edit&id=' . $campaign['id'])); ?>">
											<?php esc_html_e('Edit', 'autoblogcraft-ai'); ?>
										</a> |
									</span>
									<?php if ($campaign['status'] === 'active') : ?>
										<span class="pause">
											<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=abc-campaigns&action=pause&id=' . $campaign['id']), 'abc_pause_campaign_' . $campaign['id'])); ?>">
												<?php esc_html_e('Pause', 'autoblogcraft-ai'); ?>
											</a> |
										</span>
									<?php else : ?>
										<span class="resume">
											<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=abc-campaigns&action=resume&id=' . $campaign['id']), 'abc_resume_campaign_' . $campaign['id'])); ?>">
												<?php esc_html_e('Resume', 'autoblogcraft-ai'); ?>
											</a> |
										</span>
									<?php endif; ?>
									<span class="trash">
										<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=abc-campaigns&action=delete&id=' . $campaign['id']), 'abc_delete_campaign_' . $campaign['id'])); ?>" 
										   class="abc-delete-campaign" 
										   data-campaign-name="<?php echo esc_attr($campaign['name']); ?>">
											<?php esc_html_e('Delete', 'autoblogcraft-ai'); ?>
										</a>
									</span>
								</div>
							</td>
							<td class="column-type" data-colname="<?php esc_attr_e('Type', 'autoblogcraft-ai'); ?>">
								<span class="abc-campaign-type">
									<span class="dashicons dashicons-<?php echo esc_attr($type_icons[$campaign['type']] ?? 'admin-generic'); ?>"></span>
									<?php echo esc_html(ucfirst($campaign['type'])); ?>
								</span>
							</td>
							<td class="column-status" data-colname="<?php esc_attr_e('Status', 'autoblogcraft-ai'); ?>">
								<span class="abc-status abc-status-<?php echo esc_attr($campaign['status']); ?>">
									<?php echo esc_html(ucfirst($campaign['status'])); ?>
								</span>
							</td>
							<td class="column-posts" data-colname="<?php esc_attr_e('Posts', 'autoblogcraft-ai'); ?>">
								<a href="<?php echo esc_url(admin_url('edit.php?abc_campaign_id=' . $campaign['id'])); ?>">
									<?php echo esc_html($campaign['posts_count'] ?? 0); ?>
								</a>
							</td>
							<td class="column-queue" data-colname="<?php esc_attr_e('Queue', 'autoblogcraft-ai'); ?>">
								<span class="abc-queue-count">
									<?php echo esc_html($campaign['queue_pending'] ?? 0); ?>
									<span class="abc-queue-label"><?php esc_html_e('pending', 'autoblogcraft-ai'); ?></span>
								</span>
							</td>
							<td class="column-date" data-colname="<?php esc_attr_e('Created', 'autoblogcraft-ai'); ?>">
								<?php echo esc_html(date_i18n(get_option('date_format'), strtotime($campaign['created_at']))); ?>
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
		</form>
	<?php else : ?>
		<div class="abc-empty-state abc-empty-campaigns">
			<span class="dashicons dashicons-megaphone"></span>
			<h3><?php esc_html_e('No campaigns found', 'autoblogcraft-ai'); ?></h3>
			<p><?php esc_html_e('Get started by creating your first campaign.', 'autoblogcraft-ai'); ?></p>
			<a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaigns&action=new')); ?>" class="button button-primary button-large">
				<span class="dashicons dashicons-plus-alt"></span>
				<?php esc_html_e('Create Your First Campaign', 'autoblogcraft-ai'); ?>
			</a>
		</div>
	<?php endif; ?>
</div>

<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Select all checkbox
		$('#cb-select-all').on('change', function() {
			$('input[name="campaign_ids[]"]').prop('checked', $(this).prop('checked'));
		});

		// Delete confirmation
		$('.abc-delete-campaign').on('click', function(e) {
			var campaignName = $(this).data('campaign-name');
			if (!confirm('<?php esc_html_e('Are you sure you want to delete', 'autoblogcraft-ai'); ?> "' + campaignName + '"?')) {
				e.preventDefault();
			}
		});

		// Bulk actions confirmation
		$('#abc-campaigns-form').on('submit', function(e) {
			var action = $('#bulk-action-selector').val();
			var checked = $('input[name="campaign_ids[]"]:checked').length;
			
			if (!action) {
				e.preventDefault();
				alert('<?php esc_html_e('Please select a bulk action.', 'autoblogcraft-ai'); ?>');
				return false;
			}
			
			if (checked === 0) {
				e.preventDefault();
				alert('<?php esc_html_e('Please select at least one campaign.', 'autoblogcraft-ai'); ?>');
				return false;
			}
			
			if (action === 'delete') {
				if (!confirm('<?php esc_html_e('Are you sure you want to delete', 'autoblogcraft-ai'); ?> ' + checked + ' <?php esc_html_e('campaign(s)?', 'autoblogcraft-ai'); ?>')) {
					e.preventDefault();
					return false;
				}
			}
		});
	});
</script>

<?php
// Include footer
include dirname(__FILE__) . '/../partials/footer.php';
