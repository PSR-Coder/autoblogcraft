<?php
/**
 * Campaign Wizard - Step 1: Campaign Type Selection
 *
 * First step of campaign creation wizard for selecting campaign type.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards
 * @since 2.0.0
 *
 * @var string $current_step Current wizard step
 * @var array $wizard_data Wizard form data
 */

defined('ABSPATH') || exit;

$current_step = $current_step ?? 'type';
$wizard_data = $wizard_data ?? [];
?>

<div class="abc-wizard-step abc-wizard-step-type">
	<div class="abc-wizard-step-header">
		<h2><?php esc_html_e('Choose Campaign Type', 'autoblogcraft-ai'); ?></h2>
		<p class="description">
			<?php esc_html_e('Select the type of content you want to generate.', 'autoblogcraft-ai'); ?>
		</p>
	</div>

	<div class="abc-campaign-types-grid">
		<!-- Website Campaign -->
		<div class="abc-campaign-type-card" data-type="website">
			<div class="abc-campaign-type-icon">
				<span class="dashicons dashicons-admin-site"></span>
			</div>
			<div class="abc-campaign-type-content">
				<h3><?php esc_html_e('Website', 'autoblogcraft-ai'); ?></h3>
				<p><?php esc_html_e('Curate content from blogs, news sites, and other websites.', 'autoblogcraft-ai'); ?></p>
				<ul class="abc-campaign-features">
					<li><?php esc_html_e('RSS Feed Discovery', 'autoblogcraft-ai'); ?></li>
					<li><?php esc_html_e('Sitemap Parsing', 'autoblogcraft-ai'); ?></li>
					<li><?php esc_html_e('Web Scraping', 'autoblogcraft-ai'); ?></li>
					<li><?php esc_html_e('AI Rewriting', 'autoblogcraft-ai'); ?></li>
				</ul>
			</div>
			<div class="abc-campaign-type-action">
				<button type="button" class="button button-primary button-large abc-select-type" data-type="website">
					<?php esc_html_e('Select Website', 'autoblogcraft-ai'); ?>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</button>
			</div>
		</div>

		<!-- YouTube Campaign -->
		<div class="abc-campaign-type-card" data-type="youtube">
			<div class="abc-campaign-type-icon abc-icon-youtube">
				<span class="dashicons dashicons-video-alt3"></span>
			</div>
			<div class="abc-campaign-type-content">
				<h3><?php esc_html_e('YouTube', 'autoblogcraft-ai'); ?></h3>
				<p><?php esc_html_e('Create blog posts from YouTube videos and channels.', 'autoblogcraft-ai'); ?></p>
				<ul class="abc-campaign-features">
					<li><?php esc_html_e('Channel Monitoring', 'autoblogcraft-ai'); ?></li>
					<li><?php esc_html_e('Playlist Discovery', 'autoblogcraft-ai'); ?></li>
					<li><?php esc_html_e('Transcript Extraction', 'autoblogcraft-ai'); ?></li>
					<li><?php esc_html_e('Video Embedding', 'autoblogcraft-ai'); ?></li>
				</ul>
			</div>
			<div class="abc-campaign-type-action">
				<button type="button" class="button button-primary button-large abc-select-type" data-type="youtube">
					<?php esc_html_e('Select YouTube', 'autoblogcraft-ai'); ?>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</button>
			</div>
		</div>

		<!-- Amazon Campaign -->
		<div class="abc-campaign-type-card" data-type="amazon">
			<div class="abc-campaign-type-icon abc-icon-amazon">
				<span class="dashicons dashicons-cart"></span>
			</div>
			<div class="abc-campaign-type-content">
				<h3><?php esc_html_e('Amazon Products', 'autoblogcraft-ai'); ?></h3>
				<p><?php esc_html_e('Generate product review posts from Amazon listings.', 'autoblogcraft-ai'); ?></p>
				<ul class="abc-campaign-features">
					<li><?php esc_html_e('Product Search', 'autoblogcraft-ai'); ?></li>
					<li><?php esc_html_e('Category Discovery', 'autoblogcraft-ai'); ?></li>
					<li><?php esc_html_e('Bestseller Lists', 'autoblogcraft-ai'); ?></li>
					<li><?php esc_html_e('Affiliate Links', 'autoblogcraft-ai'); ?></li>
				</ul>
			</div>
			<div class="abc-campaign-type-action">
				<button type="button" class="button button-primary button-large abc-select-type" data-type="amazon">
					<?php esc_html_e('Select Amazon', 'autoblogcraft-ai'); ?>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</button>
			</div>
		</div>

		<!-- News Campaign -->
		<div class="abc-campaign-type-card" data-type="news">
			<div class="abc-campaign-type-icon abc-icon-news">
				<span class="dashicons dashicons-media-document"></span>
			</div>
			<div class="abc-campaign-type-content">
				<h3><?php esc_html_e('News Articles', 'autoblogcraft-ai'); ?></h3>
				<p><?php esc_html_e('Publish trending news articles in real-time.', 'autoblogcraft-ai'); ?></p>
				<ul class="abc-campaign-features">
					<li><?php esc_html_e('Google News SERP', 'autoblogcraft-ai'); ?></li>
					<li><?php esc_html_e('Freshness Filtering', 'autoblogcraft-ai'); ?></li>
					<li><?php esc_html_e('Geo-targeting', 'autoblogcraft-ai'); ?></li>
					<li><?php esc_html_e('Real-time Updates', 'autoblogcraft-ai'); ?></li>
				</ul>
			</div>
			<div class="abc-campaign-type-action">
				<button type="button" class="button button-primary button-large abc-select-type" data-type="news">
					<?php esc_html_e('Select News', 'autoblogcraft-ai'); ?>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Campaign type selection
		$('.abc-select-type').on('click', function() {
			var type = $(this).data('type');
			
			// Add selected state
			$('.abc-campaign-type-card').removeClass('abc-type-selected');
			$(this).closest('.abc-campaign-type-card').addClass('abc-type-selected');
			
			// Set hidden input value
			$('input[name="campaign_type"]').val(type);
			
			// Enable next button
			$('.abc-wizard-next').prop('disabled', false);
			
			// Auto-advance after short delay
			setTimeout(function() {
				$('.abc-wizard-next').trigger('click');
			}, 500);
		});

		// Keyboard navigation
		$('.abc-campaign-type-card').on('keypress', function(e) {
			if (e.which === 13 || e.which === 32) { // Enter or Space
				e.preventDefault();
				$(this).find('.abc-select-type').trigger('click');
			}
		});
	});
</script>
