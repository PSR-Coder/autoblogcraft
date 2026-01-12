<?php
/**
 * Website Campaign Wizard - Step 2: Basic Settings
 *
 * Configure basic settings for website campaign.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards\Website
 * @since 2.0.0
 *
 * @var array $wizard_data Wizard form data
 */

defined('ABSPATH') || exit;

$wizard_data = $wizard_data ?? [];
?>

<div class="abc-wizard-step abc-wizard-step-website-basic">
	<div class="abc-wizard-step-header">
		<h2><?php esc_html_e('Website Campaign Settings', 'autoblogcraft-ai'); ?></h2>
		<p class="description">
			<?php esc_html_e('Configure your website content campaign.', 'autoblogcraft-ai'); ?>
		</p>
	</div>

	<div class="abc-wizard-form">
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="campaign_name">
						<?php esc_html_e('Campaign Name', 'autoblogcraft-ai'); ?>
						<span class="required">*</span>
					</label>
				</th>
				<td>
					<input type="text" name="campaign_name" id="campaign_name" 
					       value="<?php echo esc_attr($wizard_data['campaign_name'] ?? ''); ?>" 
					       class="regular-text" required>
					<p class="description"><?php esc_html_e('A descriptive name for this campaign.', 'autoblogcraft-ai'); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="website_urls">
						<?php esc_html_e('Website URLs', 'autoblogcraft-ai'); ?>
						<span class="required">*</span>
					</label>
				</th>
				<td>
					<div class="abc-url-list" id="website-urls-list">
						<?php 
						$urls = $wizard_data['website_urls'] ?? [''];
						foreach ($urls as $index => $url) :
						?>
							<div class="abc-url-item">
								<input type="url" name="website_urls[]" 
								       value="<?php echo esc_url($url); ?>" 
								       placeholder="https://example.com" 
								       class="regular-text" required>
								<button type="button" class="button abc-remove-url">
									<span class="dashicons dashicons-no"></span>
								</button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button abc-add-url">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e('Add Another URL', 'autoblogcraft-ai'); ?>
					</button>
					<p class="description"><?php esc_html_e('Enter the websites you want to curate content from.', 'autoblogcraft-ai'); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label><?php esc_html_e('Discovery Methods', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="discovery_methods[]" value="rss" 
							       <?php checked(in_array('rss', $wizard_data['discovery_methods'] ?? ['rss'])); ?>>
							<?php esc_html_e('RSS Feed Discovery', 'autoblogcraft-ai'); ?>
						</label>
						<br>
						<label>
							<input type="checkbox" name="discovery_methods[]" value="sitemap" 
							       <?php checked(in_array('sitemap', $wizard_data['discovery_methods'] ?? ['sitemap'])); ?>>
							<?php esc_html_e('Sitemap Discovery', 'autoblogcraft-ai'); ?>
						</label>
						<br>
						<label>
							<input type="checkbox" name="discovery_methods[]" value="web" 
							       <?php checked(in_array('web', $wizard_data['discovery_methods'] ?? [])); ?>>
							<?php esc_html_e('Web Scraping', 'autoblogcraft-ai'); ?>
						</label>
					</fieldset>
					<p class="description"><?php esc_html_e('Select how content should be discovered.', 'autoblogcraft-ai'); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="discovery_interval"><?php esc_html_e('Discovery Frequency', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<select name="discovery_interval" id="discovery_interval" class="regular-text">
						<option value="hourly" <?php selected($wizard_data['discovery_interval'] ?? 'hourly', 'hourly'); ?>><?php esc_html_e('Hourly', 'autoblogcraft-ai'); ?></option>
						<option value="twicedaily" <?php selected($wizard_data['discovery_interval'] ?? 'hourly', 'twicedaily'); ?>><?php esc_html_e('Twice Daily', 'autoblogcraft-ai'); ?></option>
						<option value="daily" <?php selected($wizard_data['discovery_interval'] ?? 'hourly', 'daily'); ?>><?php esc_html_e('Daily', 'autoblogcraft-ai'); ?></option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="auto_publish"><?php esc_html_e('Publishing', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" name="auto_publish" id="auto_publish" value="1" 
						       <?php checked(!empty($wizard_data['auto_publish'])); ?>>
						<?php esc_html_e('Automatically publish posts', 'autoblogcraft-ai'); ?>
					</label>
					<p class="description"><?php esc_html_e('If unchecked, posts will be saved as drafts.', 'autoblogcraft-ai'); ?></p>
				</td>
			</tr>
		</table>
	</div>
</div>

<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Add URL field
		$('.abc-add-url').on('click', function() {
			var newItem = $('<div class="abc-url-item">' +
				'<input type="url" name="website_urls[]" value="" placeholder="https://example.com" class="regular-text" required>' +
				'<button type="button" class="button abc-remove-url"><span class="dashicons dashicons-no"></span></button>' +
				'</div>');
			$('#website-urls-list').append(newItem);
		});

		// Remove URL field
		$(document).on('click', '.abc-remove-url', function() {
			if ($('.abc-url-item').length > 1) {
				$(this).closest('.abc-url-item').remove();
			}
		});
	});
</script>
