<?php
/**
 * News Campaign Wizard - Step 2: Basic Settings
 *
 * Configure basic settings for news campaign.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards\News
 * @since 2.0.0
 *
 * @var array $wizard_data Wizard form data
 */

defined('ABSPATH') || exit;

$wizard_data = $wizard_data ?? [];
?>

<div class="abc-wizard-step abc-wizard-step-news-basic">
	<div class="abc-wizard-step-header">
		<h2><?php esc_html_e('News Campaign Settings', 'autoblogcraft-ai'); ?></h2>
		<p class="description">
			<?php esc_html_e('Configure your news content campaign.', 'autoblogcraft-ai'); ?>
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
					<label for="news_keywords">
						<?php esc_html_e('News Keywords', 'autoblogcraft-ai'); ?>
						<span class="required">*</span>
					</label>
				</th>
				<td>
					<div class="abc-keyword-list" id="news-keywords-list">
						<?php 
						$keywords = $wizard_data['news_keywords'] ?? [''];
						foreach ($keywords as $index => $keyword) :
						?>
							<div class="abc-keyword-item">
								<input type="text" name="news_keywords[]" 
								       value="<?php echo esc_attr($keyword); ?>" 
								       placeholder="e.g., technology, AI, sports" 
								       class="regular-text" required>
								<button type="button" class="button abc-remove-keyword">
									<span class="dashicons dashicons-no"></span>
								</button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button abc-add-keyword">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e('Add Keyword', 'autoblogcraft-ai'); ?>
					</button>
					<p class="description"><?php esc_html_e('Keywords to search for in news articles.', 'autoblogcraft-ai'); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="freshness_filter"><?php esc_html_e('Freshness Filter', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<select name="freshness_filter" id="freshness_filter" class="regular-text">
						<option value="1h" <?php selected($wizard_data['freshness_filter'] ?? '24h', '1h'); ?>><?php esc_html_e('Last Hour', 'autoblogcraft-ai'); ?></option>
						<option value="6h" <?php selected($wizard_data['freshness_filter'] ?? '24h', '6h'); ?>><?php esc_html_e('Last 6 Hours', 'autoblogcraft-ai'); ?></option>
						<option value="24h" <?php selected($wizard_data['freshness_filter'] ?? '24h', '24h'); ?>><?php esc_html_e('Last 24 Hours', 'autoblogcraft-ai'); ?></option>
						<option value="7d" <?php selected($wizard_data['freshness_filter'] ?? '24h', '7d'); ?>><?php esc_html_e('Last 7 Days', 'autoblogcraft-ai'); ?></option>
					</select>
					<p class="description"><?php esc_html_e('Only discover news from this time period.', 'autoblogcraft-ai'); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="country_filter"><?php esc_html_e('Country/Region', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<select name="country_filter" id="country_filter" class="regular-text">
						<option value=""><?php esc_html_e('All Countries', 'autoblogcraft-ai'); ?></option>
						<option value="us" <?php selected($wizard_data['country_filter'] ?? '', 'us'); ?>><?php esc_html_e('United States', 'autoblogcraft-ai'); ?></option>
						<option value="gb" <?php selected($wizard_data['country_filter'] ?? '', 'gb'); ?>><?php esc_html_e('United Kingdom', 'autoblogcraft-ai'); ?></option>
						<option value="ca" <?php selected($wizard_data['country_filter'] ?? '', 'ca'); ?>><?php esc_html_e('Canada', 'autoblogcraft-ai'); ?></option>
						<option value="au" <?php selected($wizard_data['country_filter'] ?? '', 'au'); ?>><?php esc_html_e('Australia', 'autoblogcraft-ai'); ?></option>
						<option value="de" <?php selected($wizard_data['country_filter'] ?? '', 'de'); ?>><?php esc_html_e('Germany', 'autoblogcraft-ai'); ?></option>
						<option value="fr" <?php selected($wizard_data['country_filter'] ?? '', 'fr'); ?>><?php esc_html_e('France', 'autoblogcraft-ai'); ?></option>
					</select>
					<p class="description"><?php esc_html_e('Filter news by country/region.', 'autoblogcraft-ai'); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="language_filter"><?php esc_html_e('Language', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<select name="language_filter" id="language_filter" class="regular-text">
						<option value="en" <?php selected($wizard_data['language_filter'] ?? 'en', 'en'); ?>><?php esc_html_e('English', 'autoblogcraft-ai'); ?></option>
						<option value="es" <?php selected($wizard_data['language_filter'] ?? 'en', 'es'); ?>><?php esc_html_e('Spanish', 'autoblogcraft-ai'); ?></option>
						<option value="fr" <?php selected($wizard_data['language_filter'] ?? 'en', 'fr'); ?>><?php esc_html_e('French', 'autoblogcraft-ai'); ?></option>
						<option value="de" <?php selected($wizard_data['language_filter'] ?? 'en', 'de'); ?>><?php esc_html_e('German', 'autoblogcraft-ai'); ?></option>
						<option value="it" <?php selected($wizard_data['language_filter'] ?? 'en', 'it'); ?>><?php esc_html_e('Italian', 'autoblogcraft-ai'); ?></option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="source_allowlist"><?php esc_html_e('Source Allow List (Optional)', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<textarea name="source_allowlist" id="source_allowlist" rows="3" class="large-text"><?php echo esc_textarea($wizard_data['source_allowlist'] ?? ''); ?></textarea>
					<p class="description"><?php esc_html_e('Only include news from these domains (one per line, e.g., bbc.com).', 'autoblogcraft-ai'); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="source_blocklist"><?php esc_html_e('Source Block List (Optional)', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<textarea name="source_blocklist" id="source_blocklist" rows="3" class="large-text"><?php echo esc_textarea($wizard_data['source_blocklist'] ?? ''); ?></textarea>
					<p class="description"><?php esc_html_e('Exclude news from these domains (one per line).', 'autoblogcraft-ai'); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="articles_per_discovery"><?php esc_html_e('Articles Per Discovery', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<input type="number" name="articles_per_discovery" id="articles_per_discovery" 
					       value="<?php echo esc_attr($wizard_data['articles_per_discovery'] ?? 10); ?>" 
					       min="1" max="50" class="small-text">
					<p class="description"><?php esc_html_e('Maximum articles to discover per run.', 'autoblogcraft-ai'); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="discovery_interval"><?php esc_html_e('Discovery Frequency', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<select name="discovery_interval" id="discovery_interval" class="regular-text">
						<option value="5min" <?php selected($wizard_data['discovery_interval'] ?? 'hourly', '5min'); ?>><?php esc_html_e('Every 5 Minutes', 'autoblogcraft-ai'); ?></option>
						<option value="15min" <?php selected($wizard_data['discovery_interval'] ?? 'hourly', '15min'); ?>><?php esc_html_e('Every 15 Minutes', 'autoblogcraft-ai'); ?></option>
						<option value="30min" <?php selected($wizard_data['discovery_interval'] ?? 'hourly', '30min'); ?>><?php esc_html_e('Every 30 Minutes', 'autoblogcraft-ai'); ?></option>
						<option value="hourly" <?php selected($wizard_data['discovery_interval'] ?? 'hourly', 'hourly'); ?>><?php esc_html_e('Hourly', 'autoblogcraft-ai'); ?></option>
					</select>
					<p class="description"><?php esc_html_e('For fresh news, use more frequent intervals.', 'autoblogcraft-ai'); ?></p>
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
		// Add keyword field
		$('.abc-add-keyword').on('click', function() {
			var newItem = $('<div class="abc-keyword-item">' +
				'<input type="text" name="news_keywords[]" value="" placeholder="e.g., technology, AI, sports" class="regular-text" required>' +
				'<button type="button" class="button abc-remove-keyword"><span class="dashicons dashicons-no"></span></button>' +
				'</div>');
			$('#news-keywords-list').append(newItem);
		});

		// Remove keyword field
		$(document).on('click', '.abc-remove-keyword', function() {
			if ($('.abc-keyword-item').length > 1) {
				$(this).closest('.abc-keyword-item').remove();
			}
		});
	});
</script>
