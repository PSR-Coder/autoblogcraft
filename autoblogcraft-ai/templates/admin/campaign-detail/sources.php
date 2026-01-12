<?php
/**
 * Campaign Detail - Sources Tab Template
 *
 * Displays and manages campaign content sources.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Admin\Campaign_Detail
 * @since 2.0.0
 *
 * @var object $campaign Campaign object
 * @var array $sources Campaign sources configuration
 * @var string $campaign_type Campaign type (website, youtube, amazon, news)
 */

defined('ABSPATH') || exit;

$campaign = $campaign ?? null;
$sources = $sources ?? [];
$campaign_type = $campaign_type ?? 'website';

if (!$campaign) {
	return;
}
?>

<div class="abc-campaign-sources">
	<div class="abc-sources-header">
		<h3><?php esc_html_e('Content Sources', 'autoblogcraft-ai'); ?></h3>
		<p class="description">
			<?php esc_html_e('Configure where content should be discovered from.', 'autoblogcraft-ai'); ?>
		</p>
	</div>

	<form method="post" class="abc-sources-form" id="abc-sources-form">
		<?php wp_nonce_field('abc_update_sources', 'abc_sources_nonce'); ?>
		<input type="hidden" name="action" value="update_sources">
		<input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign->ID); ?>">

		<?php if ($campaign_type === 'website') : ?>
			<!-- Website Sources -->
			<div class="abc-source-section">
				<h4><?php esc_html_e('Website URLs', 'autoblogcraft-ai'); ?></h4>
				
				<div class="abc-source-list" id="website-sources-list">
					<?php 
					$website_sources = $sources['websites'] ?? [''];
					foreach ($website_sources as $index => $url) :
					?>
						<div class="abc-source-item">
							<input type="url" 
							       name="sources[websites][]" 
							       value="<?php echo esc_url($url); ?>" 
							       placeholder="https://example.com"
							       class="regular-text">
							<button type="button" class="button abc-remove-source">
								<span class="dashicons dashicons-minus"></span>
							</button>
						</div>
					<?php endforeach; ?>
				</div>
				
				<button type="button" class="button abc-add-source" data-type="websites">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e('Add Website URL', 'autoblogcraft-ai'); ?>
				</button>
			</div>

			<!-- Discovery Methods -->
			<div class="abc-source-section">
				<h4><?php esc_html_e('Discovery Methods', 'autoblogcraft-ai'); ?></h4>
				<label>
					<input type="checkbox" 
					       name="sources[methods][]" 
					       value="rss" 
					       <?php checked(in_array('rss', $sources['methods'] ?? ['rss'])); ?>>
					<?php esc_html_e('RSS Feed Discovery', 'autoblogcraft-ai'); ?>
				</label>
				<br>
				<label>
					<input type="checkbox" 
					       name="sources[methods][]" 
					       value="sitemap" 
					       <?php checked(in_array('sitemap', $sources['methods'] ?? ['sitemap'])); ?>>
					<?php esc_html_e('Sitemap Discovery', 'autoblogcraft-ai'); ?>
				</label>
				<br>
				<label>
					<input type="checkbox" 
					       name="sources[methods][]" 
					       value="web" 
					       <?php checked(in_array('web', $sources['methods'] ?? [])); ?>>
					<?php esc_html_e('Web Scraping', 'autoblogcraft-ai'); ?>
				</label>
			</div>

		<?php elseif ($campaign_type === 'youtube') : ?>
			<!-- YouTube Sources -->
			<div class="abc-source-section">
				<h4><?php esc_html_e('YouTube Channels', 'autoblogcraft-ai'); ?></h4>
				
				<div class="abc-source-list" id="channel-sources-list">
					<?php 
					$channel_sources = $sources['channels'] ?? [''];
					foreach ($channel_sources as $index => $channel) :
					?>
						<div class="abc-source-item">
							<input type="text" 
							       name="sources[channels][]" 
							       value="<?php echo esc_attr($channel); ?>" 
							       placeholder="@channelname or Channel ID"
							       class="regular-text">
							<button type="button" class="button abc-remove-source">
								<span class="dashicons dashicons-minus"></span>
							</button>
						</div>
					<?php endforeach; ?>
				</div>
				
				<button type="button" class="button abc-add-source" data-type="channels">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e('Add Channel', 'autoblogcraft-ai'); ?>
				</button>
			</div>

			<div class="abc-source-section">
				<h4><?php esc_html_e('YouTube Playlists', 'autoblogcraft-ai'); ?></h4>
				
				<div class="abc-source-list" id="playlist-sources-list">
					<?php 
					$playlist_sources = $sources['playlists'] ?? [''];
					foreach ($playlist_sources as $index => $playlist) :
					?>
						<div class="abc-source-item">
							<input type="text" 
							       name="sources[playlists][]" 
							       value="<?php echo esc_attr($playlist); ?>" 
							       placeholder="Playlist ID or URL"
							       class="regular-text">
							<button type="button" class="button abc-remove-source">
								<span class="dashicons dashicons-minus"></span>
							</button>
						</div>
					<?php endforeach; ?>
				</div>
				
				<button type="button" class="button abc-add-source" data-type="playlists">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e('Add Playlist', 'autoblogcraft-ai'); ?>
				</button>
			</div>

		<?php elseif ($campaign_type === 'amazon') : ?>
			<!-- Amazon Sources -->
			<div class="abc-source-section">
				<h4><?php esc_html_e('Search Keywords', 'autoblogcraft-ai'); ?></h4>
				
				<div class="abc-source-list" id="keyword-sources-list">
					<?php 
					$keyword_sources = $sources['keywords'] ?? [''];
					foreach ($keyword_sources as $index => $keyword) :
					?>
						<div class="abc-source-item">
							<input type="text" 
							       name="sources[keywords][]" 
							       value="<?php echo esc_attr($keyword); ?>" 
							       placeholder="Product search keyword"
							       class="regular-text">
							<button type="button" class="button abc-remove-source">
								<span class="dashicons dashicons-minus"></span>
							</button>
						</div>
					<?php endforeach; ?>
				</div>
				
				<button type="button" class="button abc-add-source" data-type="keywords">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e('Add Keyword', 'autoblogcraft-ai'); ?>
				</button>
			</div>

			<div class="abc-source-section">
				<h4><?php esc_html_e('Category URLs', 'autoblogcraft-ai'); ?></h4>
				
				<div class="abc-source-list" id="category-sources-list">
					<?php 
					$category_sources = $sources['categories'] ?? [''];
					foreach ($category_sources as $index => $category) :
					?>
						<div class="abc-source-item">
							<input type="url" 
							       name="sources[categories][]" 
							       value="<?php echo esc_url($category); ?>" 
							       placeholder="https://amazon.com/category-url"
							       class="regular-text">
							<button type="button" class="button abc-remove-source">
								<span class="dashicons dashicons-minus"></span>
							</button>
						</div>
					<?php endforeach; ?>
				</div>
				
				<button type="button" class="button abc-add-source" data-type="categories">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e('Add Category', 'autoblogcraft-ai'); ?>
				</button>
			</div>

		<?php elseif ($campaign_type === 'news') : ?>
			<!-- News Sources -->
			<div class="abc-source-section">
				<h4><?php esc_html_e('Search Keywords', 'autoblogcraft-ai'); ?></h4>
				
				<div class="abc-source-list" id="news-keyword-sources-list">
					<?php 
					$news_keywords = $sources['keywords'] ?? [''];
					foreach ($news_keywords as $index => $keyword) :
					?>
						<div class="abc-source-item">
							<input type="text" 
							       name="sources[keywords][]" 
							       value="<?php echo esc_attr($keyword); ?>" 
							       placeholder="News search keyword"
							       class="regular-text">
							<button type="button" class="button abc-remove-source">
								<span class="dashicons dashicons-minus"></span>
							</button>
						</div>
					<?php endforeach; ?>
				</div>
				
				<button type="button" class="button abc-add-source" data-type="keywords">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e('Add Keyword', 'autoblogcraft-ai'); ?>
				</button>
			</div>

			<div class="abc-source-section">
				<h4><?php esc_html_e('Freshness Filter', 'autoblogcraft-ai'); ?></h4>
				<select name="sources[freshness]" class="regular-text">
					<option value="1h" <?php selected($sources['freshness'] ?? '24h', '1h'); ?>><?php esc_html_e('Last Hour', 'autoblogcraft-ai'); ?></option>
					<option value="6h" <?php selected($sources['freshness'] ?? '24h', '6h'); ?>><?php esc_html_e('Last 6 Hours', 'autoblogcraft-ai'); ?></option>
					<option value="24h" <?php selected($sources['freshness'] ?? '24h', '24h'); ?>><?php esc_html_e('Last 24 Hours', 'autoblogcraft-ai'); ?></option>
					<option value="7d" <?php selected($sources['freshness'] ?? '24h', '7d'); ?>><?php esc_html_e('Last 7 Days', 'autoblogcraft-ai'); ?></option>
				</select>
			</div>

			<div class="abc-source-section">
				<h4><?php esc_html_e('Country/Region', 'autoblogcraft-ai'); ?></h4>
				<select name="sources[country]" class="regular-text">
					<option value=""><?php esc_html_e('All Countries', 'autoblogcraft-ai'); ?></option>
					<option value="us" <?php selected($sources['country'] ?? '', 'us'); ?>><?php esc_html_e('United States', 'autoblogcraft-ai'); ?></option>
					<option value="gb" <?php selected($sources['country'] ?? '', 'gb'); ?>><?php esc_html_e('United Kingdom', 'autoblogcraft-ai'); ?></option>
					<option value="ca" <?php selected($sources['country'] ?? '', 'ca'); ?>><?php esc_html_e('Canada', 'autoblogcraft-ai'); ?></option>
					<option value="au" <?php selected($sources['country'] ?? '', 'au'); ?>><?php esc_html_e('Australia', 'autoblogcraft-ai'); ?></option>
				</select>
			</div>
		<?php endif; ?>

		<!-- Save Button -->
		<div class="abc-form-actions">
			<button type="submit" class="button button-primary">
				<span class="dashicons dashicons-saved"></span>
				<?php esc_html_e('Save Sources', 'autoblogcraft-ai'); ?>
			</button>
		</div>
	</form>
</div>

<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Add new source field
		$('.abc-add-source').on('click', function() {
			var type = $(this).data('type');
			var list = $('#' + type + '-sources-list, #news-keyword-sources-list, #keyword-sources-list, #website-sources-list, #channel-sources-list, #playlist-sources-list, #category-sources-list').filter(':visible');
			
			var inputType = (type === 'websites' || type === 'categories') ? 'url' : 'text';
			var placeholder = $(this).prev('.abc-source-list').find('input').first().attr('placeholder');
			
			var newItem = $('<div class="abc-source-item">' +
				'<input type="' + inputType + '" name="sources[' + type + '][]" value="" placeholder="' + placeholder + '" class="regular-text">' +
				'<button type="button" class="button abc-remove-source"><span class="dashicons dashicons-minus"></span></button>' +
				'</div>');
			
			list.append(newItem);
		});

		// Remove source field
		$(document).on('click', '.abc-remove-source', function() {
			var list = $(this).closest('.abc-source-list');
			if (list.find('.abc-source-item').length > 1) {
				$(this).closest('.abc-source-item').remove();
			} else {
				$(this).prev('input').val('');
			}
		});

		// Form validation
		$('#abc-sources-form').on('submit', function(e) {
			var hasSource = false;
			$(this).find('input[type="text"], input[type="url"]').each(function() {
				if ($(this).val().trim() !== '') {
					hasSource = true;
					return false;
				}
			});
			
			if (!hasSource) {
				e.preventDefault();
				alert('<?php esc_html_e('Please add at least one source.', 'autoblogcraft-ai'); ?>');
				return false;
			}
		});
	});
</script>
