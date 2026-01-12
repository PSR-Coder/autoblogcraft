<?php
/**
 * YouTube Campaign Wizard - Step 4: Processing Settings
 *
 * Template for configuring AI processing and publishing settings for YouTube videos.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards\YouTube
 * @since 2.0.0
 *
 * @var int $campaign_id Campaign ID
 * @var array $processing_settings Processing settings
 */

defined('ABSPATH') || exit;

$processing_settings = $processing_settings ?? [];
$rewrite_mode = $processing_settings['rewrite_mode'] ?? 'moderate';
$tone = $processing_settings['tone'] ?? 'professional';
$post_status = $processing_settings['post_status'] ?? 'draft';
$embed_video = $processing_settings['embed_video'] ?? true;
?>

<div class="abc-wizard-step abc-wizard-step-processing">
	<div class="abc-wizard-step-header">
		<h2><?php esc_html_e('Processing & Publishing Settings', 'autoblogcraft-ai'); ?></h2>
		<p class="description">
			<?php esc_html_e('Configure how videos will be processed and published as posts.', 'autoblogcraft-ai'); ?>
		</p>
	</div>

	<div class="abc-wizard-step-content">
		<h3><?php esc_html_e('AI Settings', 'autoblogcraft-ai'); ?></h3>

		<div class="abc-form-field">
			<label for="rewrite_mode"><?php esc_html_e('Content Creation Mode', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<select id="rewrite_mode" name="processing_settings[rewrite_mode]" required>
				<option value="light" <?php selected($rewrite_mode, 'light'); ?>><?php esc_html_e('Light - Use video description', 'autoblogcraft-ai'); ?></option>
				<option value="moderate" <?php selected($rewrite_mode, 'moderate'); ?>><?php esc_html_e('Moderate - Enhance with AI', 'autoblogcraft-ai'); ?></option>
				<option value="heavy" <?php selected($rewrite_mode, 'heavy'); ?>><?php esc_html_e('Heavy - Full AI article from transcript', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('How to generate article content from videos', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="tone"><?php esc_html_e('Writing Tone', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<select id="tone" name="processing_settings[tone]" required>
				<option value="professional" <?php selected($tone, 'professional'); ?>><?php esc_html_e('Professional', 'autoblogcraft-ai'); ?></option>
				<option value="casual" <?php selected($tone, 'casual'); ?>><?php esc_html_e('Casual', 'autoblogcraft-ai'); ?></option>
				<option value="informative" <?php selected($tone, 'informative'); ?>><?php esc_html_e('Informative', 'autoblogcraft-ai'); ?></option>
				<option value="educational" <?php selected($tone, 'educational'); ?>><?php esc_html_e('Educational', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('The tone of voice for AI-generated content', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[use_transcript]" value="1" checked />
				<?php esc_html_e('Use video transcript', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Extract and use video transcript/captions for content generation', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[extract_timestamps]" value="1" />
				<?php esc_html_e('Extract key timestamps', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Add video chapter timestamps to the article', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[add_seo]" value="1" checked />
				<?php esc_html_e('Add SEO optimization', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Generate meta descriptions and optimize for search engines', 'autoblogcraft-ai'); ?></p>
		</div>

		<hr />

		<h3><?php esc_html_e('Video Embed Settings', 'autoblogcraft-ai'); ?></h3>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[embed_video]" value="1" <?php checked($embed_video); ?> />
				<?php esc_html_e('Embed YouTube video in post', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Add video player to the published post', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field abc-dependent-field" data-depends="processing_settings[embed_video]">
			<label for="embed_position"><?php esc_html_e('Video Position', 'autoblogcraft-ai'); ?></label>
			<select id="embed_position" name="processing_settings[embed_position]">
				<option value="top"><?php esc_html_e('Top of content', 'autoblogcraft-ai'); ?></option>
				<option value="bottom"><?php esc_html_e('Bottom of content', 'autoblogcraft-ai'); ?></option>
				<option value="featured"><?php esc_html_e('As featured media', 'autoblogcraft-ai'); ?></option>
			</select>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[use_thumbnail]" value="1" checked />
				<?php esc_html_e('Use video thumbnail as featured image', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Download and set video thumbnail as post featured image', 'autoblogcraft-ai'); ?></p>
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
				<input type="checkbox" name="processing_settings[add_source_link]" value="1" checked />
				<?php esc_html_e('Add YouTube source link', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Include link back to original YouTube video', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="processing_settings[add_channel_credit]" value="1" checked />
				<?php esc_html_e('Credit channel author', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Mention the YouTube channel name in the post', 'autoblogcraft-ai'); ?></p>
		</div>
	</div>
</div>
