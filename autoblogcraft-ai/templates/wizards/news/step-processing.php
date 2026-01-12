<?php
/**
 * News Campaign Wizard - Step 4: Processing Settings
 *
 * Template for configuring AI processing and publishing settings for news articles.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards\News
 * @since 2.0.0
 *
 * @var int $campaign_id Campaign ID
 * @var array $processing_settings Processing settings
 */

defined('ABSPATH') || exit;

$processing_settings = $processing_settings ?? [];
$rewrite_mode = $processing_settings['rewrite_mode'] ?? 'moderate';
$tone = $processing_settings['tone'] ?? 'informative';
$post_status = $processing_settings['post_status'] ?? 'draft';
?>

<div class="abc-wizard-step abc-wizard-step-processing">
	<div class="abc-wizard-step-header">
		<h2><?php esc_html_e('Processing & Publishing Settings', 'autoblogcraft-ai'); ?></h2>
		<p class="description">
			<?php esc_html_e('Configure how news articles will be rewritten and published.', 'autoblogcraft-ai'); ?>
		</p>
	</div>

	<div class="abc-wizard-step-content">
		<h3><?php esc_html_e('AI Settings', 'autoblogcraft-ai'); ?></h3>

		<div class="abc-form-field">
			<label for="rewrite_mode"><?php esc_html_e('Rewrite Mode', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<select id="rewrite_mode" name="processing_settings[rewrite_mode]" required>
				<option value="light" <?php selected($rewrite_mode, 'light'); ?>><?php esc_html_e('Light - Minor changes & paraphrasing', 'autoblogcraft-ai'); ?></option>
				<option value="moderate" <?php selected($rewrite_mode, 'moderate'); ?>><?php esc_html_e('Moderate - Balanced rewrite', 'autoblogcraft-ai'); ?></option>
				<option value="heavy" <?php selected($rewrite_mode, 'heavy'); ?>><?php esc_html_e('Heavy - Complete rewrite with new angle', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('How much should AI transform the original news content?', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="tone"><?php esc_html_e('Writing Tone', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<select id="tone" name="processing_settings[tone]" required>
				<option value="informative" <?php selected($tone, 'informative'); ?>><?php esc_html_e('Informative', 'autoblogcraft-ai'); ?></option>
				<option value="professional" <?php selected($tone, 'professional'); ?>><?php esc_html_e('Professional', 'autoblogcraft-ai'); ?></option>
				<option value="neutral" <?php selected($tone, 'neutral'); ?>><?php esc_html_e('Neutral/Objective', 'autoblogcraft-ai'); ?></option>
				<option value="analytical" <?php selected($tone, 'analytical'); ?>><?php esc_html_e('Analytical', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('The tone of voice for AI-generated content', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="target_length"><?php esc_html_e('Target Word Count', 'autoblogcraft-ai'); ?></label>
			<input type="number" id="target_length" name="processing_settings[target_length]" 
				   value="800" min="300" max="3000" class="small-text" />
			<span><?php esc_html_e('words', 'autoblogcraft-ai'); ?></span>
			<p class="description"><?php esc_html_e('Target length for AI-rewritten articles', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[preserve_facts]" value="1" checked />
				<?php esc_html_e('Preserve factual accuracy', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Ensure key facts, dates, and quotes remain accurate', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[add_context]" value="1" />
				<?php esc_html_e('Add background context', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('AI adds context and background information to news stories', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[add_seo]" value="1" checked />
				<?php esc_html_e('Add SEO optimization', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Generate meta descriptions and optimize for search engines', 'autoblogcraft-ai'); ?></p>
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
				<input type="checkbox" name="processing_settings[download_images]" value="1" checked />
				<?php esc_html_e('Download featured images', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Download and import images from original news articles', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[add_source_link]" value="1" checked />
				<?php esc_html_e('Add source attribution', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Include link back to original news source', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field abc-dependent-field" data-depends="processing_settings[add_source_link]">
			<label for="attribution_text"><?php esc_html_e('Attribution Format', 'autoblogcraft-ai'); ?></label>
			<input type="text" id="attribution_text" name="processing_settings[attribution_text]" 
				   value="<?php echo esc_attr__('Source: {source_name}', 'autoblogcraft-ai'); ?>" class="regular-text" />
			<p class="description"><?php esc_html_e('Use {source_name} and {source_url} placeholders', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[add_timestamp]" value="1" />
				<?php esc_html_e('Add publish timestamp', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Show original news publication date in the article', 'autoblogcraft-ai'); ?></p>
		</div>
	</div>
</div>
