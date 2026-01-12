<?php
/**
 * YouTube Campaign Wizard - Step 2: Basic Settings
 *
 * Configure basic settings for YouTube campaign.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards\YouTube
 * @since 2.0.0
 *
 * @var array $wizard_data Wizard form data
 */

defined('ABSPATH') || exit;

$wizard_data = $wizard_data ?? [];
?>

<div class="abc-wizard-step abc-wizard-step-youtube-basic">
	<div class="abc-wizard-step-header">
		<h2><?php esc_html_e('YouTube Campaign Settings', 'autoblogcraft-ai'); ?></h2>
		<p class="description">
			<?php esc_html_e('Configure your YouTube content campaign.', 'autoblogcraft-ai'); ?>
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
					<label for="youtube_api_key">
						<?php esc_html_e('YouTube API Key', 'autoblogcraft-ai'); ?>
						<span class="required">*</span>
					</label>
				</th>
				<td>
					<input type="text" name="youtube_api_key" id="youtube_api_key" 
					       value="<?php echo esc_attr($wizard_data['youtube_api_key'] ?? ''); ?>" 
					       class="regular-text" required>
					<p class="description">
						<?php 
						printf(
							/* translators: %s: Link to Google Cloud Console */
							esc_html__('Get your API key from %s', 'autoblogcraft-ai'),
							'<a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">' . esc_html__('Google Cloud Console', 'autoblogcraft-ai') . '</a>'
						);
						?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="youtube_channels"><?php esc_html_e('YouTube Channels', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<div class="abc-channel-list" id="youtube-channels-list">
						<?php 
						$channels = $wizard_data['youtube_channels'] ?? [''];
						foreach ($channels as $index => $channel) :
						?>
							<div class="abc-channel-item">
								<input type="text" name="youtube_channels[]" 
								       value="<?php echo esc_attr($channel); ?>" 
								       placeholder="@channelname or Channel ID" 
								       class="regular-text">
								<button type="button" class="button abc-remove-channel">
									<span class="dashicons dashicons-no"></span>
								</button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button abc-add-channel">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e('Add Channel', 'autoblogcraft-ai'); ?>
					</button>
					<p class="description"><?php esc_html_e('Enter YouTube channel handles or IDs.', 'autoblogcraft-ai'); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="youtube_playlists"><?php esc_html_e('YouTube Playlists', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<div class="abc-playlist-list" id="youtube-playlists-list">
						<?php 
						$playlists = $wizard_data['youtube_playlists'] ?? [''];
						foreach ($playlists as $index => $playlist) :
						?>
							<div class="abc-playlist-item">
								<input type="text" name="youtube_playlists[]" 
								       value="<?php echo esc_attr($playlist); ?>" 
								       placeholder="Playlist ID or URL" 
								       class="regular-text">
								<button type="button" class="button abc-remove-playlist">
									<span class="dashicons dashicons-no"></span>
								</button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button abc-add-playlist">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e('Add Playlist', 'autoblogcraft-ai'); ?>
					</button>
					<p class="description"><?php esc_html_e('Enter YouTube playlist IDs or URLs.', 'autoblogcraft-ai'); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="video_limit"><?php esc_html_e('Videos Per Discovery', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<input type="number" name="video_limit" id="video_limit" 
					       value="<?php echo esc_attr($wizard_data['video_limit'] ?? 10); ?>" 
					       min="1" max="50" class="small-text">
					<p class="description"><?php esc_html_e('Maximum videos to discover per run.', 'autoblogcraft-ai'); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="embed_video"><?php esc_html_e('Video Embedding', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" name="embed_video" id="embed_video" value="1" 
						       <?php checked(!empty($wizard_data['embed_video']) || !isset($wizard_data['embed_video'])); ?>>
						<?php esc_html_e('Embed YouTube videos in posts', 'autoblogcraft-ai'); ?>
					</label>
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
		// Add channel field
		$('.abc-add-channel').on('click', function() {
			var newItem = $('<div class="abc-channel-item">' +
				'<input type="text" name="youtube_channels[]" value="" placeholder="@channelname or Channel ID" class="regular-text">' +
				'<button type="button" class="button abc-remove-channel"><span class="dashicons dashicons-no"></span></button>' +
				'</div>');
			$('#youtube-channels-list').append(newItem);
		});

		// Remove channel field
		$(document).on('click', '.abc-remove-channel', function() {
			if ($('.abc-channel-item').length > 1) {
				$(this).closest('.abc-channel-item').remove();
			}
		});

		// Add playlist field
		$('.abc-add-playlist').on('click', function() {
			var newItem = $('<div class="abc-playlist-item">' +
				'<input type="text" name="youtube_playlists[]" value="" placeholder="Playlist ID or URL" class="regular-text">' +
				'<button type="button" class="button abc-remove-playlist"><span class="dashicons dashicons-no"></span></button>' +
				'</div>');
			$('#youtube-playlists-list').append(newItem);
		});

		// Remove playlist field
		$(document).on('click', '.abc-remove-playlist', function() {
			if ($('.abc-playlist-item').length > 1) {
				$(this).closest('.abc-playlist-item').remove();
			}
		});
	});
</script>
