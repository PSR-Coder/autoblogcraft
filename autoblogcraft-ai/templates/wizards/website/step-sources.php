<?php
/**
 * Website Campaign Wizard - Step 2: Website Sources
 *
 * Template for configuring website sources (RSS, Sitemap, URLs).
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards\Website
 * @since 2.0.0
 *
 * @var int $campaign_id Campaign ID
 * @var array $sources Existing sources
 */

defined('ABSPATH') || exit;

$sources = $sources ?? [];
?>

<div class="abc-wizard-step abc-wizard-step-sources">
	<div class="abc-wizard-step-header">
		<h2><?php esc_html_e('Website Sources', 'autoblogcraft-ai'); ?></h2>
		<p class="description">
			<?php esc_html_e('Configure where to discover content from websites.', 'autoblogcraft-ai'); ?>
		</p>
	</div>

	<div class="abc-wizard-step-content">
		<div class="abc-form-field">
			<label for="source_type"><?php esc_html_e('Source Type', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<select id="source_type" name="source_type" required>
				<option value="rss"><?php esc_html_e('RSS Feed', 'autoblogcraft-ai'); ?></option>
				<option value="sitemap"><?php esc_html_e('XML Sitemap', 'autoblogcraft-ai'); ?></option>
				<option value="url"><?php esc_html_e('Single URL', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('Choose how to discover content from websites', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-field-group">
			<div class="abc-field-group-header">
				<h4 class="abc-field-group-title"><?php esc_html_e('Website URLs', 'autoblogcraft-ai'); ?></h4>
				<p class="description"><?php esc_html_e('Add RSS feed URLs, sitemap URLs, or webpage URLs', 'autoblogcraft-ai'); ?></p>
			</div>
			
			<div class="abc-field-list">
				<?php
				if (!empty($sources) && is_array($sources)) {
					foreach ($sources as $source) {
						?>
						<div class="abc-field-item">
							<input type="url" name="sources[]" value="<?php echo esc_url($source); ?>" 
								   placeholder="https://example.com/feed" class="regular-text" required />
							<button type="button" class="button abc-validate-url-btn" title="<?php esc_attr_e('Validate URL', 'autoblogcraft-ai'); ?>">
								<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Validate', 'autoblogcraft-ai'); ?>
							</button>
							<button type="button" class="button abc-remove-field-btn" title="<?php esc_attr_e('Remove', 'autoblogcraft-ai'); ?>">
								<span class="dashicons dashicons-no"></span> <?php esc_html_e('Remove', 'autoblogcraft-ai'); ?>
							</button>
							<span class="abc-field-status"></span>
						</div>
						<?php
					}
				} else {
					?>
					<div class="abc-field-item">
						<input type="url" name="sources[]" placeholder="https://example.com/feed" class="regular-text" required />
						<button type="button" class="button abc-validate-url-btn" title="<?php esc_attr_e('Validate URL', 'autoblogcraft-ai'); ?>">
							<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Validate', 'autoblogcraft-ai'); ?>
						</button>
						<button type="button" class="button abc-remove-field-btn" title="<?php esc_attr_e('Remove', 'autoblogcraft-ai'); ?>">
							<span class="dashicons dashicons-no"></span> <?php esc_html_e('Remove', 'autoblogcraft-ai'); ?>
						</button>
						<span class="abc-field-status"></span>
					</div>
					<?php
				}
				?>
			</div>

			<button type="button" class="button abc-add-field-btn" 
					data-field-name="sources" 
					data-field-type="url" 
					data-placeholder="https://example.com/feed">
				<span class="dashicons dashicons-plus"></span> <?php esc_html_e('Add Source URL', 'autoblogcraft-ai'); ?>
			</button>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="enable_auto_discovery" value="1" />
				<?php esc_html_e('Enable auto-discovery of RSS feeds', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Automatically detect RSS feeds from web pages', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="max_items"><?php esc_html_e('Max Items Per Discovery', 'autoblogcraft-ai'); ?></label>
			<input type="number" id="max_items" name="max_items" value="10" min="1" max="100" class="small-text" />
			<p class="description"><?php esc_html_e('Maximum number of items to fetch per discovery run', 'autoblogcraft-ai'); ?></p>
		</div>
	</div>
</div>
