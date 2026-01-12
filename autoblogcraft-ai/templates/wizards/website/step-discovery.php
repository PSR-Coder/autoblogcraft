<?php
/**
 * Website Campaign Wizard - Step 3: Discovery Settings
 *
 * Template for configuring website content discovery settings.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards\Website
 * @since 2.0.0
 *
 * @var int $campaign_id Campaign ID
 * @var array $discovery_settings Discovery settings
 */

defined('ABSPATH') || exit;

$discovery_settings = $discovery_settings ?? [];
$interval = $discovery_settings['interval'] ?? 'hourly';
$max_depth = $discovery_settings['max_depth'] ?? 2;
$url_pattern = $discovery_settings['url_pattern'] ?? '';
$content_selector = $discovery_settings['content_selector'] ?? 'article';
?>

<div class="abc-wizard-step abc-wizard-step-discovery">
	<div class="abc-wizard-step-header">
		<h2><?php esc_html_e('Discovery Settings', 'autoblogcraft-ai'); ?></h2>
		<p class="description">
			<?php esc_html_e('Configure how often and how deeply to discover content.', 'autoblogcraft-ai'); ?>
		</p>
	</div>

	<div class="abc-wizard-step-content">
		<div class="abc-form-field">
			<label for="discovery_interval"><?php esc_html_e('Discovery Interval', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<select id="discovery_interval" name="discovery_settings[interval]" required>
				<option value="15min" <?php selected($interval, '15min'); ?>><?php esc_html_e('Every 15 minutes', 'autoblogcraft-ai'); ?></option>
				<option value="30min" <?php selected($interval, '30min'); ?>><?php esc_html_e('Every 30 minutes', 'autoblogcraft-ai'); ?></option>
				<option value="hourly" <?php selected($interval, 'hourly'); ?>><?php esc_html_e('Every hour', 'autoblogcraft-ai'); ?></option>
				<option value="twicedaily" <?php selected($interval, 'twicedaily'); ?>><?php esc_html_e('Twice daily', 'autoblogcraft-ai'); ?></option>
				<option value="daily" <?php selected($interval, 'daily'); ?>><?php esc_html_e('Daily', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('How often to check for new content', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="max_depth"><?php esc_html_e('Max Crawl Depth', 'autoblogcraft-ai'); ?></label>
			<input type="number" id="max_depth" name="discovery_settings[max_depth]" 
				   value="<?php echo esc_attr($max_depth); ?>" min="1" max="5" class="small-text" />
			<p class="description"><?php esc_html_e('Maximum link depth to crawl from start URL (for web scraping)', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="url_pattern"><?php esc_html_e('URL Pattern (Regex)', 'autoblogcraft-ai'); ?></label>
			<input type="text" id="url_pattern" name="discovery_settings[url_pattern]" 
				   value="<?php echo esc_attr($url_pattern); ?>" class="regular-text" 
				   placeholder="/\/blog\/.*/" />
			<p class="description"><?php esc_html_e('Only crawl URLs matching this regex pattern (leave empty to crawl all)', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="content_selector"><?php esc_html_e('Content Selector (CSS)', 'autoblogcraft-ai'); ?></label>
			<input type="text" id="content_selector" name="discovery_settings[content_selector]" 
				   value="<?php echo esc_attr($content_selector); ?>" class="regular-text" 
				   placeholder="article, .post-content, #main" />
			<p class="description"><?php esc_html_e('CSS selector to extract main content (for web scraping)', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="discovery_settings[skip_duplicates]" value="1" checked />
				<?php esc_html_e('Skip duplicate content', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Automatically detect and skip duplicate or similar content', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="discovery_settings[enable_pagination]" value="1" />
				<?php esc_html_e('Enable pagination parsing', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Follow pagination links to discover more content', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="min_content_length"><?php esc_html_e('Minimum Content Length', 'autoblogcraft-ai'); ?></label>
			<input type="number" id="min_content_length" name="discovery_settings[min_content_length]" 
				   value="300" min="100" max="5000" class="small-text" />
			<span><?php esc_html_e('words', 'autoblogcraft-ai'); ?></span>
			<p class="description"><?php esc_html_e('Minimum article length to queue for processing', 'autoblogcraft-ai'); ?></p>
		</div>
	</div>
</div>
