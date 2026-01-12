<?php
/**
 * Amazon Campaign Wizard - Step 4: Processing Settings
 *
 * Template for configuring AI processing and publishing settings for Amazon products.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards\Amazon
 * @since 2.0.0
 *
 * @var int $campaign_id Campaign ID
 * @var array $processing_settings Processing settings
 */

defined('ABSPATH') || exit;

$processing_settings = $processing_settings ?? [];
$content_type = $processing_settings['content_type'] ?? 'review';
$tone = $processing_settings['tone'] ?? 'informative';
$post_status = $processing_settings['post_status'] ?? 'draft';
?>

<div class="abc-wizard-step abc-wizard-step-processing">
	<div class="abc-wizard-step-header">
		<h2><?php esc_html_e('Processing & Publishing Settings', 'autoblogcraft-ai'); ?></h2>
		<p class="description">
			<?php esc_html_e('Configure how product data will be transformed into articles.', 'autoblogcraft-ai'); ?>
		</p>
	</div>

	<div class="abc-wizard-step-content">
		<h3><?php esc_html_e('AI Settings', 'autoblogcraft-ai'); ?></h3>

		<div class="abc-form-field">
			<label for="content_type"><?php esc_html_e('Content Type', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<select id="content_type" name="processing_settings[content_type]" required>
				<option value="review" <?php selected($content_type, 'review'); ?>><?php esc_html_e('Product Review', 'autoblogcraft-ai'); ?></option>
				<option value="comparison" <?php selected($content_type, 'comparison'); ?>><?php esc_html_e('Product Comparison', 'autoblogcraft-ai'); ?></option>
				<option value="buying_guide" <?php selected($content_type, 'buying_guide'); ?>><?php esc_html_e('Buying Guide', 'autoblogcraft-ai'); ?></option>
				<option value="roundup" <?php selected($content_type, 'roundup'); ?>><?php esc_html_e('Product Roundup', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('Type of content to generate for products', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="tone"><?php esc_html_e('Writing Tone', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<select id="tone" name="processing_settings[tone]" required>
				<option value="informative" <?php selected($tone, 'informative'); ?>><?php esc_html_e('Informative', 'autoblogcraft-ai'); ?></option>
				<option value="persuasive" <?php selected($tone, 'persuasive'); ?>><?php esc_html_e('Persuasive', 'autoblogcraft-ai'); ?></option>
				<option value="professional" <?php selected($tone, 'professional'); ?>><?php esc_html_e('Professional', 'autoblogcraft-ai'); ?></option>
				<option value="friendly" <?php selected($tone, 'friendly'); ?>><?php esc_html_e('Friendly', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('The tone of voice for AI-generated content', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[include_pros_cons]" value="1" checked />
				<?php esc_html_e('Include Pros & Cons section', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Generate pros and cons analysis for products', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[include_specs]" value="1" checked />
				<?php esc_html_e('Include specifications table', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Display product specifications in a formatted table', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[include_price]" value="1" checked />
				<?php esc_html_e('Show product price', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Display current Amazon price (may change)', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[add_buy_button]" value="1" checked />
				<?php esc_html_e('Add "Buy on Amazon" button', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Include affiliate link button to Amazon product page', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[add_seo]" value="1" checked />
				<?php esc_html_e('Add SEO optimization', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Generate meta descriptions and optimize for search engines', 'autoblogcraft-ai'); ?></p>
		</div>

		<hr />

		<h3><?php esc_html_e('Product Images', 'autoblogcraft-ai'); ?></h3>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[import_images]" value="1" checked />
				<?php esc_html_e('Import product images', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Download and import Amazon product images', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field abc-dependent-field" data-depends="processing_settings[import_images]">
			<label for="max_images"><?php esc_html_e('Max Images Per Product', 'autoblogcraft-ai'); ?></label>
			<input type="number" id="max_images" name="processing_settings[max_images]" 
				   value="5" min="1" max="20" class="small-text" />
			<p class="description"><?php esc_html_e('Maximum number of product images to import', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[set_featured_image]" value="1" checked />
				<?php esc_html_e('Set featured image', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Use first product image as post featured image', 'autoblogcraft-ai'); ?></p>
		</div>

		<hr />

		<h3><?php esc_html_e('Publishing Settings', 'autoblogcraft-ai'); ?></h3>

		<div class="abc-form-field">
			<label for="post_status"><?php esc_html_e('Post Status', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<select id="post_status" name="processing_settings[post_status]" required>
				<option value="draft" <?php selected($post_status, 'draft'); ?>><?php esc_html_e('Draft', 'autoblogcraft-ai'); ?></option>
				<option value="pending" <?php selected($post_status, 'pending'); ?>><?php esc_html_e('Pending Review', 'autoblogcraft-ai'); ?></option>
				<option value="publish" <?php selected($post_status, 'publish'); ?>><?php esc_html_e('Published', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('Status for newly created posts', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="post_author"><?php esc_html_e('Post Author', 'autoblogcraft-ai'); ?></label>
			<?php
			wp_dropdown_users([
				'name' => 'processing_settings[post_author]',
				'id' => 'post_author',
				'selected' => get_current_user_id(),
				'show_option_none' => __('Current User', 'autoblogcraft-ai'),
			]);
			?>
			<p class="description"><?php esc_html_e('Author to assign to generated posts', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[add_disclosure]" value="1" checked />
				<?php esc_html_e('Add affiliate disclosure', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Include required affiliate link disclosure (FTC compliance)', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field abc-dependent-field" data-depends="processing_settings[add_disclosure]">
			<label for="disclosure_text"><?php esc_html_e('Disclosure Text', 'autoblogcraft-ai'); ?></label>
			<textarea id="disclosure_text" name="processing_settings[disclosure_text]" rows="3" class="large-text"><?php echo esc_textarea__('This post contains affiliate links. We may earn a commission if you make a purchase through these links, at no additional cost to you.', 'autoblogcraft-ai'); ?></textarea>
		</div>
	</div>
</div>
