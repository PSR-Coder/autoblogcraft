<?php
/**
 * Campaign Detail - Settings Tab Template
 *
 * Displays and manages campaign settings.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Admin\Campaign_Detail
 * @since 2.0.0
 *
 * @var object $campaign Campaign object
 * @var array $settings Campaign settings
 */

defined('ABSPATH') || exit;

$campaign = $campaign ?? null;
$settings = $settings ?? [];

if (!$campaign) {
	return;
}
?>

<div class="abc-campaign-settings">
	<form method="post" class="abc-settings-form" id="abc-settings-form">
		<?php wp_nonce_field('abc_update_settings', 'abc_settings_nonce'); ?>
		<input type="hidden" name="action" value="update_settings">
		<input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign->ID); ?>">

		<!-- Discovery Settings -->
		<div class="abc-settings-section">
			<h3><?php esc_html_e('Discovery Settings', 'autoblogcraft-ai'); ?></h3>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="discovery_interval"><?php esc_html_e('Discovery Interval', 'autoblogcraft-ai'); ?></label>
					</th>
					<td>
						<select name="settings[discovery_interval]" id="discovery_interval" class="regular-text">
							<option value="5min" <?php selected($settings['discovery_interval'] ?? 'hourly', '5min'); ?>><?php esc_html_e('Every 5 Minutes', 'autoblogcraft-ai'); ?></option>
							<option value="15min" <?php selected($settings['discovery_interval'] ?? 'hourly', '15min'); ?>><?php esc_html_e('Every 15 Minutes', 'autoblogcraft-ai'); ?></option>
							<option value="30min" <?php selected($settings['discovery_interval'] ?? 'hourly', '30min'); ?>><?php esc_html_e('Every 30 Minutes', 'autoblogcraft-ai'); ?></option>
							<option value="hourly" <?php selected($settings['discovery_interval'] ?? 'hourly', 'hourly'); ?>><?php esc_html_e('Hourly', 'autoblogcraft-ai'); ?></option>
							<option value="twicedaily" <?php selected($settings['discovery_interval'] ?? 'hourly', 'twicedaily'); ?>><?php esc_html_e('Twice Daily', 'autoblogcraft-ai'); ?></option>
							<option value="daily" <?php selected($settings['discovery_interval'] ?? 'hourly', 'daily'); ?>><?php esc_html_e('Daily', 'autoblogcraft-ai'); ?></option>
						</select>
						<p class="description"><?php esc_html_e('How often to check for new content.', 'autoblogcraft-ai'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="max_items_per_discovery"><?php esc_html_e('Max Items Per Discovery', 'autoblogcraft-ai'); ?></label>
					</th>
					<td>
						<input type="number" name="settings[max_items_per_discovery]" id="max_items_per_discovery" 
						       value="<?php echo esc_attr($settings['max_items_per_discovery'] ?? 10); ?>" 
						       min="1" max="100" class="small-text">
						<p class="description"><?php esc_html_e('Maximum items to add to queue per discovery run.', 'autoblogcraft-ai'); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Processing Settings -->
		<div class="abc-settings-section">
			<h3><?php esc_html_e('Processing Settings', 'autoblogcraft-ai'); ?></h3>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="processing_interval"><?php esc_html_e('Processing Interval', 'autoblogcraft-ai'); ?></label>
					</th>
					<td>
						<select name="settings[processing_interval]" id="processing_interval" class="regular-text">
							<option value="5min" <?php selected($settings['processing_interval'] ?? 'hourly', '5min'); ?>><?php esc_html_e('Every 5 Minutes', 'autoblogcraft-ai'); ?></option>
							<option value="15min" <?php selected($settings['processing_interval'] ?? 'hourly', '15min'); ?>><?php esc_html_e('Every 15 Minutes', 'autoblogcraft-ai'); ?></option>
							<option value="30min" <?php selected($settings['processing_interval'] ?? 'hourly', '30min'); ?>><?php esc_html_e('Every 30 Minutes', 'autoblogcraft-ai'); ?></option>
							<option value="hourly" <?php selected($settings['processing_interval'] ?? 'hourly', 'hourly'); ?>><?php esc_html_e('Hourly', 'autoblogcraft-ai'); ?></option>
							<option value="twicedaily" <?php selected($settings['processing_interval'] ?? 'hourly', 'twicedaily'); ?>><?php esc_html_e('Twice Daily', 'autoblogcraft-ai'); ?></option>
							<option value="daily" <?php selected($settings['processing_interval'] ?? 'hourly', 'daily'); ?>><?php esc_html_e('Daily', 'autoblogcraft-ai'); ?></option>
						</select>
						<p class="description"><?php esc_html_e('How often to process queue items.', 'autoblogcraft-ai'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="batch_size"><?php esc_html_e('Batch Size', 'autoblogcraft-ai'); ?></label>
					</th>
					<td>
						<input type="number" name="settings[batch_size]" id="batch_size" 
						       value="<?php echo esc_attr($settings['batch_size'] ?? 5); ?>" 
						       min="1" max="20" class="small-text">
						<p class="description"><?php esc_html_e('Number of items to process in each batch.', 'autoblogcraft-ai'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="auto_publish"><?php esc_html_e('Auto Publish', 'autoblogcraft-ai'); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="settings[auto_publish]" id="auto_publish" value="1" 
							       <?php checked(!empty($settings['auto_publish'])); ?>>
							<?php esc_html_e('Automatically publish generated posts', 'autoblogcraft-ai'); ?>
						</label>
						<p class="description"><?php esc_html_e('If unchecked, posts will be saved as drafts.', 'autoblogcraft-ai'); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- AI Settings -->
		<div class="abc-settings-section">
			<h3><?php esc_html_e('AI Content Settings', 'autoblogcraft-ai'); ?></h3>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="ai_provider"><?php esc_html_e('AI Provider', 'autoblogcraft-ai'); ?></label>
					</th>
					<td>
						<select name="settings[ai_provider]" id="ai_provider" class="regular-text">
							<option value="openai" <?php selected($settings['ai_provider'] ?? 'openai', 'openai'); ?>><?php esc_html_e('OpenAI (GPT-4)', 'autoblogcraft-ai'); ?></option>
							<option value="gemini" <?php selected($settings['ai_provider'] ?? 'openai', 'gemini'); ?>><?php esc_html_e('Google Gemini', 'autoblogcraft-ai'); ?></option>
							<option value="claude" <?php selected($settings['ai_provider'] ?? 'openai', 'claude'); ?>><?php esc_html_e('Anthropic Claude', 'autoblogcraft-ai'); ?></option>
							<option value="deepseek" <?php selected($settings['ai_provider'] ?? 'openai', 'deepseek'); ?>><?php esc_html_e('DeepSeek', 'autoblogcraft-ai'); ?></option>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="rewrite_content"><?php esc_html_e('Rewrite Content', 'autoblogcraft-ai'); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="settings[rewrite_content]" id="rewrite_content" value="1" 
							       <?php checked(!empty($settings['rewrite_content'])); ?>>
							<?php esc_html_e('Use AI to rewrite content', 'autoblogcraft-ai'); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="humanize_content"><?php esc_html_e('Humanize Content', 'autoblogcraft-ai'); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="settings[humanize_content]" id="humanize_content" value="1" 
							       <?php checked(!empty($settings['humanize_content'])); ?>>
							<?php esc_html_e('Make AI content more human-like', 'autoblogcraft-ai'); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="generate_featured_image"><?php esc_html_e('Generate Featured Images', 'autoblogcraft-ai'); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="settings[generate_featured_image]" id="generate_featured_image" value="1" 
							       <?php checked(!empty($settings['generate_featured_image'])); ?>>
							<?php esc_html_e('Automatically generate featured images', 'autoblogcraft-ai'); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<!-- SEO Settings -->
		<div class="abc-settings-section">
			<h3><?php esc_html_e('SEO Settings', 'autoblogcraft-ai'); ?></h3>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="enable_seo"><?php esc_html_e('Enable SEO', 'autoblogcraft-ai'); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="settings[enable_seo]" id="enable_seo" value="1" 
							       <?php checked(!empty($settings['enable_seo'])); ?>>
							<?php esc_html_e('Generate SEO meta tags', 'autoblogcraft-ai'); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="enable_schema"><?php esc_html_e('Enable Schema Markup', 'autoblogcraft-ai'); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="settings[enable_schema]" id="enable_schema" value="1" 
							       <?php checked(!empty($settings['enable_schema'])); ?>>
							<?php esc_html_e('Add structured data (Schema.org)', 'autoblogcraft-ai'); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="internal_linking"><?php esc_html_e('Internal Linking', 'autoblogcraft-ai'); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="settings[internal_linking]" id="internal_linking" value="1" 
							       <?php checked(!empty($settings['internal_linking'])); ?>>
							<?php esc_html_e('Add contextual internal links', 'autoblogcraft-ai'); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<!-- Post Settings -->
		<div class="abc-settings-section">
			<h3><?php esc_html_e('Post Settings', 'autoblogcraft-ai'); ?></h3>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="post_author"><?php esc_html_e('Post Author', 'autoblogcraft-ai'); ?></label>
					</th>
					<td>
						<?php
						wp_dropdown_users([
							'name' => 'settings[post_author]',
							'id' => 'post_author',
							'selected' => $settings['post_author'] ?? get_current_user_id(),
							'show_option_none' => __('Current User', 'autoblogcraft-ai'),
							'option_none_value' => 0,
						]);
						?>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="default_category"><?php esc_html_e('Default Category', 'autoblogcraft-ai'); ?></label>
					</th>
					<td>
						<?php
						wp_dropdown_categories([
							'name' => 'settings[default_category]',
							'id' => 'default_category',
							'selected' => $settings['default_category'] ?? 1,
							'hide_empty' => false,
							'show_option_none' => __('Uncategorized', 'autoblogcraft-ai'),
							'option_none_value' => 1,
						]);
						?>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="post_format"><?php esc_html_e('Post Format', 'autoblogcraft-ai'); ?></label>
					</th>
					<td>
						<select name="settings[post_format]" id="post_format" class="regular-text">
							<option value="standard" <?php selected($settings['post_format'] ?? 'standard', 'standard'); ?>><?php esc_html_e('Standard', 'autoblogcraft-ai'); ?></option>
							<option value="video" <?php selected($settings['post_format'] ?? 'standard', 'video'); ?>><?php esc_html_e('Video', 'autoblogcraft-ai'); ?></option>
							<option value="gallery" <?php selected($settings['post_format'] ?? 'standard', 'gallery'); ?>><?php esc_html_e('Gallery', 'autoblogcraft-ai'); ?></option>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<!-- Save Button -->
		<div class="abc-form-actions">
			<button type="submit" class="button button-primary button-large">
				<span class="dashicons dashicons-saved"></span>
				<?php esc_html_e('Save Settings', 'autoblogcraft-ai'); ?>
			</button>
		</div>
	</form>
</div>
