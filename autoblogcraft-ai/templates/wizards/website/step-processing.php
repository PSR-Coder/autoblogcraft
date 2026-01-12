<?php
/**
 * Website Campaign Wizard - Step 4: Processing Settings
 *
 * Template for configuring AI processing and publishing settings.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards\Website
 * @since 2.0.0
 *
 * @var int $campaign_id Campaign ID
 * @var array $processing_settings Processing settings
 */

defined('ABSPATH') || exit;

$processing_settings = $processing_settings ?? [];
$rewrite_mode = $processing_settings['rewrite_mode'] ?? 'moderate';
$tone = $processing_settings['tone'] ?? 'professional';
$min_length = $processing_settings['min_length'] ?? 500;
$max_length = $processing_settings['max_length'] ?? 2000;
$post_status = $processing_settings['post_status'] ?? 'draft';
$post_category = $processing_settings['post_category'] ?? [];
?>

<div class="abc-wizard-step abc-wizard-step-processing">
	<div class="abc-wizard-step-header">
		<h2><?php esc_html_e('Processing & Publishing Settings', 'autoblogcraft-ai'); ?></h2>
		<p class="description">
			<?php esc_html_e('Configure how AI will rewrite content and how posts will be published.', 'autoblogcraft-ai'); ?>
		</p>
	</div>

	<div class="abc-wizard-step-content">
		<h3><?php esc_html_e('AI Settings', 'autoblogcraft-ai'); ?></h3>

		<div class="abc-form-field">
			<label for="rewrite_mode"><?php esc_html_e('Rewrite Mode', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<select id="rewrite_mode" name="processing_settings[rewrite_mode]" required>
				<option value="light" <?php selected($rewrite_mode, 'light'); ?>><?php esc_html_e('Light - Minor changes', 'autoblogcraft-ai'); ?></option>
				<option value="moderate" <?php selected($rewrite_mode, 'moderate'); ?>><?php esc_html_e('Moderate - Balanced rewrite', 'autoblogcraft-ai'); ?></option>
				<option value="heavy" <?php selected($rewrite_mode, 'heavy'); ?>><?php esc_html_e('Heavy - Complete rewrite', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('How much should AI transform the original content?', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="tone"><?php esc_html_e('Writing Tone', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<select id="tone" name="processing_settings[tone]" required>
				<option value="professional" <?php selected($tone, 'professional'); ?>><?php esc_html_e('Professional', 'autoblogcraft-ai'); ?></option>
				<option value="casual" <?php selected($tone, 'casual'); ?>><?php esc_html_e('Casual', 'autoblogcraft-ai'); ?></option>
				<option value="informative" <?php selected($tone, 'informative'); ?>><?php esc_html_e('Informative', 'autoblogcraft-ai'); ?></option>
				<option value="persuasive" <?php selected($tone, 'persuasive'); ?>><?php esc_html_e('Persuasive', 'autoblogcraft-ai'); ?></option>
				<option value="friendly" <?php selected($tone, 'friendly'); ?>><?php esc_html_e('Friendly', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('The tone of voice for AI-generated content', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label><?php esc_html_e('Target Content Length', 'autoblogcraft-ai'); ?></label>
			<input type="number" name="processing_settings[min_length]" 
				   value="<?php echo esc_attr($min_length); ?>" min="100" max="10000" class="small-text" />
			<span><?php esc_html_e('to', 'autoblogcraft-ai'); ?></span>
			<input type="number" name="processing_settings[max_length]" 
				   value="<?php echo esc_attr($max_length); ?>" min="100" max="10000" class="small-text" />
			<span><?php esc_html_e('words', 'autoblogcraft-ai'); ?></span>
			<p class="description"><?php esc_html_e('Target word count range for AI-generated articles', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[generate_images]" value="1" checked />
				<?php esc_html_e('Generate featured images with AI', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Use AI to generate unique featured images for posts', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[add_seo]" value="1" checked />
				<?php esc_html_e('Add SEO optimization', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Generate meta descriptions and optimize for search engines', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[add_internal_links]" value="1" />
				<?php esc_html_e('Add internal links automatically', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Automatically link to related posts on your site', 'autoblogcraft-ai'); ?></p>
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
			<label for="post_category"><?php esc_html_e('Default Categories', 'autoblogcraft-ai'); ?></label>
			<?php
			wp_dropdown_categories([
				'name' => 'processing_settings[post_category][]',
				'id' => 'post_category',
				'selected' => $post_category,
				'hide_empty' => false,
				'hierarchical' => true,
				'show_option_none' => __('— Select Category —', 'autoblogcraft-ai'),
			]);
			?>
			<p class="description"><?php esc_html_e('Category to assign to generated posts', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[preserve_source_link]" value="1" checked />
				<?php esc_html_e('Add source attribution link', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Include a link back to the original source article', 'autoblogcraft-ai'); ?></p>
		</div>
	</div>
</div>
