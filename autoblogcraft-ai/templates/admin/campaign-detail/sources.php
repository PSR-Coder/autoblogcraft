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

use AutoBlogCraft\Helpers\Template_Helpers;

defined('ABSPATH') || exit;

$campaign = $campaign ?? null;
$sources = $sources ?? [];
$campaign_type = $campaign_type ?? 'website';

if (!$campaign) {
	return;
}

// For website campaigns, separate sources by type
$rss_sources = [];
$sitemap_sources = [];
$url_sources = [];
$enabled_types = [];

if ($campaign_type === 'website' && isset($sources['sources'])) {
	foreach ($sources['sources'] as $source) {
		if (is_array($source) && isset($source['type'], $source['url'])) {
			$type = $source['type'];
			$url = $source['url'];
			
			if (empty($url)) continue;
			
			switch ($type) {
				case 'rss':
					$rss_sources[] = $url;
					$enabled_types['rss'] = true;
					break;
				case 'sitemap':
					$sitemap_sources[] = $url;
					$enabled_types['sitemap'] = true;
					break;
				case 'url':
					$url_sources[] = $url;
					$enabled_types['url'] = true;
					break;
			}
		}
	}
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
			<div class="abc-source-section" style="margin-bottom: 25px;">
				<div style="display: flex; align-items: center; margin-bottom: 15px;">
					<label style="display: flex; align-items: center; font-weight: 600; font-size: 14px;">
						<input type="checkbox" 
							   name="source_types[rss]" 
							   value="1" 
							   class="abc-source-type-checkbox" 
							   data-target="rss-sources-container"
							   <?php checked(!empty($enabled_types['rss'])); ?>
							   style="margin: 0 8px 0 0;" />
						<span class="dashicons dashicons-rss" style="margin-right: 5px;"></span>
						<?php esc_html_e('RSS Feeds', 'autoblogcraft-ai'); ?>
					</label>
				</div>
				<div id="rss-sources-container" class="abc-sources-container" style="margin-left: 25px;">
					<p class="description" style="margin: 0 0 10px 0;">
						<?php esc_html_e('Add RSS or Atom feed URLs (one per line)', 'autoblogcraft-ai'); ?>
					</p>
					<textarea name="rss_sources" 
							  class="large-text abc-source-textarea" 
							  rows="4" 
							  placeholder="https://example.com/feed&#10;https://example2.com/rss"
							  <?php disabled(empty($enabled_types['rss'])); ?>><?php echo esc_textarea(implode("\n", array_filter($rss_sources))); ?></textarea>
				</div>
			</div>

			<div class="abc-source-section" style="margin-bottom: 25px;">
				<div style="display: flex; align-items: center; margin-bottom: 15px;">
					<label style="display: flex; align-items: center; font-weight: 600; font-size: 14px;">
						<input type="checkbox" 
							   name="source_types[sitemap]" 
							   value="1" 
							   class="abc-source-type-checkbox" 
							   data-target="sitemap-sources-container"
							   <?php checked(!empty($enabled_types['sitemap'])); ?>
							   style="margin: 0 8px 0 0;" />
						<span class="dashicons dashicons-networking" style="margin-right: 5px;"></span>
						<?php esc_html_e('Sitemaps', 'autoblogcraft-ai'); ?>
					</label>
				</div>
				<div id="sitemap-sources-container" class="abc-sources-container" style="margin-left: 25px;">
					<p class="description" style="margin: 0 0 10px 0;">
						<?php esc_html_e('Add XML sitemap URLs (supports RankMath/Yoast indexes)', 'autoblogcraft-ai'); ?>
					</p>
					<textarea name="sitemap_sources" 
							  class="large-text abc-source-textarea" 
							  rows="4" 
							  placeholder="https://example.com/sitemap.xml&#10;https://example2.com/post-sitemap.xml"
							  <?php disabled(empty($enabled_types['sitemap'])); ?>><?php echo esc_textarea(implode("\n", array_filter($sitemap_sources))); ?></textarea>
				</div>
			</div>

			<div class="abc-source-section" style="margin-bottom: 25px;">
				<div style="display: flex; align-items: center; margin-bottom: 15px;">
					<label style="display: flex; align-items: center; font-weight: 600; font-size: 14px;">
						<input type="checkbox" 
							   name="source_types[blogs]" 
							   value="1" 
							   class="abc-source-type-checkbox" 
							   data-target="blogs-sources-container"
							   <?php checked(!empty($enabled_types['url'])); ?>
							   style="margin: 0 8px 0 0;" />
						<span class="dashicons dashicons-admin-links" style="margin-right: 5px;"></span>
						<?php esc_html_e('Blogs/Websites', 'autoblogcraft-ai'); ?>
					</label>
				</div>
				<div id="blogs-sources-container" class="abc-sources-container" style="margin-left: 25px;">
					<p class="description" style="margin: 0 0 10px 0;">
						<?php esc_html_e('Add article pages or blog/category pages for web scraping', 'autoblogcraft-ai'); ?>
					</p>
					<textarea name="url_sources" 
							  class="large-text abc-source-textarea" 
							  rows="4" 
							  placeholder="https://example.com/blog&#10;https://example2.com/article/page-title"
							  <?php disabled(empty($enabled_types['url'])); ?>><?php echo esc_textarea(implode("\n", array_filter($url_sources))); ?></textarea>
				</div>
			</div>

			<p class="description" style="margin-top: 20px; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1;">
				<strong><?php esc_html_e('Tip:', 'autoblogcraft-ai'); ?></strong> <?php esc_html_e('Enable at least one source type and add URLs (one per line). You can use multiple source types together.', 'autoblogcraft-ai'); ?>
			</p>

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
	</form>
</div>

<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Toggle textarea enabled/disabled based on checkbox
		$('.abc-source-type-checkbox').on('change', function() {
			const targetId = $(this).data('target');
			const textarea = $('#' + targetId).find('textarea');
			
			if ($(this).is(':checked')) {
				textarea.prop('disabled', false);
			} else {
				textarea.prop('disabled', true);
			}
		});
		
		// Add new source input field
		$('.abc-add-source').on('click', function() {
			const type = $(this).data('type');
			const listId = type === 'keywords' ? '#news-keyword-sources-list' : '#category-sources-list';
			const placeholder = type === 'keywords' ? 'News search keyword' : 'https://amazon.com/category-url';
			const inputType = type === 'keywords' ? 'text' : 'url';
			const inputName = 'sources[' + type + '][]';
			
			const newItem = $('<div class="abc-source-item">' +
				'<input type="' + inputType + '" name="' + inputName + '" value="" placeholder="' + placeholder + '" class="regular-text">' +
				'<button type="button" class="button abc-remove-source"><span class="dashicons dashicons-minus"></span></button>' +
				'</div>');
			
			$(listId).append(newItem);
			newItem.find('input').focus();
		});
		
		// Remove source input field
		$(document).on('click', '.abc-remove-source', function() {
			const $item = $(this).closest('.abc-source-item');
			const $list = $item.parent();
			
			// Only remove if there's more than one item
			if ($list.find('.abc-source-item').length > 1) {
				$item.remove();
			} else {
				// If it's the last item, just clear the input
				$item.find('input').val('');
			}
		});
		
		// Form validation
		$('#abc-sources-form').on('submit', function(e) {
			const campaignType = '<?php echo esc_js($campaign_type); ?>';
			let hasSource = false;
			
			if (campaignType === 'website') {
				$('.abc-source-type-checkbox:checked').each(function() {
					const targetId = $(this).data('target');
					const textareaValue = $('#' + targetId).find('textarea').val().trim();
					
					if (textareaValue !== '') {
						hasSource = true;
						return false; // break loop
					}
				});
				
				if (!hasSource) {
					e.preventDefault();
					alert('<?php esc_html_e('Please enable at least one source type and add URLs.', 'autoblogcraft-ai'); ?>');
					return false;
				}
			} else if (campaignType === 'youtube') {
				const channelId = $('input[name="source_config[youtube][channel_id]"]').val().trim();
				if (!channelId) {
					e.preventDefault();
					alert('<?php esc_html_e('Please enter a YouTube Channel ID.', 'autoblogcraft-ai'); ?>');
					return false;
				}
			} else if (campaignType === 'news') {
				const keywords = $('input[name="source_config[news][keywords]"]').val().trim();
				if (!keywords) {
					e.preventDefault();
					alert('<?php esc_html_e('Please enter keywords for news tracking.', 'autoblogcraft-ai'); ?>');
					return false;
				}
			} else if (campaignType === 'amazon') {
				const keywords = $('input[name="source_config[amazon][keywords]"]').val().trim();
				if (!keywords) {
					e.preventDefault();
					alert('<?php esc_html_e('Please enter search keywords for Amazon products.', 'autoblogcraft-ai'); ?>');
					return false;
				}
			}
		});
	});
</script>
