<?php
/**
 * YouTube Campaign Wizard - Step 3: Discovery Settings
 *
 * Template for configuring YouTube video discovery settings.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards\YouTube
 * @since 2.0.0
 *
 * @var int $campaign_id Campaign ID
 * @var array $discovery_settings Discovery settings
 */

defined('ABSPATH') || exit;

$discovery_settings = $discovery_settings ?? [];
$interval = $discovery_settings['interval'] ?? 'hourly';
$max_videos = $discovery_settings['max_videos'] ?? 10;
$video_duration = $discovery_settings['video_duration'] ?? 'any';
$video_order = $discovery_settings['video_order'] ?? 'date';
?>

<div class="abc-wizard-step abc-wizard-step-discovery">
	<div class="abc-wizard-step-header">
		<h2><?php esc_html_e('Video Discovery Settings', 'autoblogcraft-ai'); ?></h2>
		<p class="description">
			<?php esc_html_e('Configure how to discover and filter YouTube videos.', 'autoblogcraft-ai'); ?>
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
			<p class="description"><?php esc_html_e('How often to check for new videos', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="max_videos"><?php esc_html_e('Max Videos Per Discovery', 'autoblogcraft-ai'); ?></label>
			<input type="number" id="max_videos" name="discovery_settings[max_videos]" 
				   value="<?php echo esc_attr($max_videos); ?>" min="1" max="50" class="small-text" />
			<p class="description"><?php esc_html_e('Maximum number of videos to fetch per discovery run', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="video_order"><?php esc_html_e('Video Order', 'autoblogcraft-ai'); ?></label>
			<select id="video_order" name="discovery_settings[video_order]">
				<option value="date" <?php selected($video_order, 'date'); ?>><?php esc_html_e('Latest first', 'autoblogcraft-ai'); ?></option>
				<option value="viewCount" <?php selected($video_order, 'viewCount'); ?>><?php esc_html_e('Most viewed', 'autoblogcraft-ai'); ?></option>
				<option value="rating" <?php selected($video_order, 'rating'); ?>><?php esc_html_e('Highest rated', 'autoblogcraft-ai'); ?></option>
				<option value="relevance" <?php selected($video_order, 'relevance'); ?>><?php esc_html_e('Most relevant', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('How to sort discovered videos', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="video_duration"><?php esc_html_e('Video Duration Filter', 'autoblogcraft-ai'); ?></label>
			<select id="video_duration" name="discovery_settings[video_duration]">
				<option value="any" <?php selected($video_duration, 'any'); ?>><?php esc_html_e('Any duration', 'autoblogcraft-ai'); ?></option>
				<option value="short" <?php selected($video_duration, 'short'); ?>><?php esc_html_e('Short (< 4 minutes)', 'autoblogcraft-ai'); ?></option>
				<option value="medium" <?php selected($video_duration, 'medium'); ?>><?php esc_html_e('Medium (4-20 minutes)', 'autoblogcraft-ai'); ?></option>
				<option value="long" <?php selected($video_duration, 'long'); ?>><?php esc_html_e('Long (> 20 minutes)', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('Filter videos by duration', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="min_views"><?php esc_html_e('Minimum Views', 'autoblogcraft-ai'); ?></label>
			<input type="number" id="min_views" name="discovery_settings[min_views]" 
				   value="0" min="0" class="regular-text" />
			<p class="description"><?php esc_html_e('Only discover videos with at least this many views', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="min_likes"><?php esc_html_e('Minimum Likes', 'autoblogcraft-ai'); ?></label>
			<input type="number" id="min_likes" name="discovery_settings[min_likes]" 
				   value="0" min="0" class="regular-text" />
			<p class="description"><?php esc_html_e('Only discover videos with at least this many likes', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="discovery_settings[skip_shorts]" value="1" />
				<?php esc_html_e('Skip YouTube Shorts', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Exclude short-form videos (< 60 seconds)', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="discovery_settings[skip_live]" value="1" />
				<?php esc_html_e('Skip live streams', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Exclude live stream videos', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="discovery_settings[require_captions]" value="1" />
				<?php esc_html_e('Require closed captions', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Only discover videos with available captions/transcripts', 'autoblogcraft-ai'); ?></p>
		</div>
	</div>
</div>
