<?php
/**
 * YouTube Campaign Wizard - Step 2: YouTube Channels
 *
 * Template for configuring YouTube channels and playlists.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards\YouTube
 * @since 2.0.0
 *
 * @var int $campaign_id Campaign ID
 * @var array $channels YouTube channels
 * @var array $playlists YouTube playlists
 */

defined('ABSPATH') || exit;

$channels = $channels ?? [];
$playlists = $playlists ?? [];
?>

<div class="abc-wizard-step abc-wizard-step-channels">
	<div class="abc-wizard-step-header">
		<h2><?php esc_html_e('YouTube Sources', 'autoblogcraft-ai'); ?></h2>
		<p class="description">
			<?php esc_html_e('Add YouTube channels and playlists to discover videos from.', 'autoblogcraft-ai'); ?>
		</p>
	</div>

	<div class="abc-wizard-step-content">
		<div class="abc-field-group">
			<div class="abc-field-group-header">
				<h4 class="abc-field-group-title"><?php esc_html_e('YouTube Channels', 'autoblogcraft-ai'); ?></h4>
				<p class="description"><?php esc_html_e('Add channel IDs or channel URLs', 'autoblogcraft-ai'); ?></p>
			</div>
			
			<div class="abc-field-list">
				<?php
				if (!empty($channels) && is_array($channels)) {
					foreach ($channels as $channel) {
						?>
						<div class="abc-field-item">
							<input type="text" name="channels[]" value="<?php echo esc_attr($channel); ?>" 
								   placeholder="UCxxxxxx or https://youtube.com/@channel" class="regular-text" />
							<button type="button" class="button abc-validate-youtube-btn" data-type="channel" title="<?php esc_attr_e('Validate Channel', 'autoblogcraft-ai'); ?>">
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
						<input type="text" name="channels[]" placeholder="UCxxxxxx or https://youtube.com/@channel" class="regular-text" />
						<button type="button" class="button abc-validate-youtube-btn" data-type="channel" title="<?php esc_attr_e('Validate Channel', 'autoblogcraft-ai'); ?>">
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
					data-field-name="channels" 
					data-field-type="text" 
					data-placeholder="UCxxxxxx or https://youtube.com/@channel">
				<span class="dashicons dashicons-plus"></span> <?php esc_html_e('Add Channel', 'autoblogcraft-ai'); ?>
			</button>
		</div>

		<div class="abc-field-group">
			<div class="abc-field-group-header">
				<h4 class="abc-field-group-title"><?php esc_html_e('YouTube Playlists (Optional)', 'autoblogcraft-ai'); ?></h4>
				<p class="description"><?php esc_html_e('Add playlist IDs or playlist URLs', 'autoblogcraft-ai'); ?></p>
			</div>
			
			<div class="abc-field-list">
				<?php
				if (!empty($playlists) && is_array($playlists)) {
					foreach ($playlists as $playlist) {
						?>
						<div class="abc-field-item">
							<input type="text" name="playlists[]" value="<?php echo esc_attr($playlist); ?>" 
								   placeholder="PLxxxxxx or https://youtube.com/playlist?list=..." class="regular-text" />
							<button type="button" class="button abc-validate-youtube-btn" data-type="playlist" title="<?php esc_attr_e('Validate Playlist', 'autoblogcraft-ai'); ?>">
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
						<input type="text" name="playlists[]" placeholder="PLxxxxxx or https://youtube.com/playlist?list=..." class="regular-text" />
						<button type="button" class="button abc-validate-youtube-btn" data-type="playlist" title="<?php esc_attr_e('Validate Playlist', 'autoblogcraft-ai'); ?>">
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
					data-field-name="playlists" 
					data-field-type="text" 
					data-placeholder="PLxxxxxx or https://youtube.com/playlist?list=...">
				<span class="dashicons dashicons-plus"></span> <?php esc_html_e('Add Playlist', 'autoblogcraft-ai'); ?>
			</button>
		</div>

		<div class="abc-form-field">
			<label for="youtube_api_key"><?php esc_html_e('YouTube API Key', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<input type="text" id="youtube_api_key" name="youtube_api_key" value="" required class="regular-text" />
			<p class="description">
				<?php
				printf(
					/* translators: %s: Google Cloud Console URL */
					esc_html__('Get your API key from %s', 'autoblogcraft-ai'),
					'<a href="https://console.cloud.google.com/apis/credentials" target="_blank">' . esc_html__('Google Cloud Console', 'autoblogcraft-ai') . '</a>'
				);
				?>
			</p>
		</div>
	</div>
</div>
