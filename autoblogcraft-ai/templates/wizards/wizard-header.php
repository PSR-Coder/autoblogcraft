<?php
/**
 * Wizard Header Template
 *
 * Shared template for wizard page header with title and help text.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards
 * @since 2.0.0
 *
 * @var string $title Wizard title
 * @var string $campaign_type Campaign type (website, youtube, amazon, news)
 */

defined('ABSPATH') || exit;

$title = $title ?? __('Campaign Wizard', 'autoblogcraft-ai');
$campaign_type = $campaign_type ?? '';

$type_icons = [
	'website' => 'dashicons-admin-site',
	'youtube' => 'dashicons-video-alt3',
	'amazon' => 'dashicons-cart',
	'news' => 'dashicons-rss',
];

$icon_class = isset($type_icons[$campaign_type]) ? $type_icons[$campaign_type] : 'dashicons-admin-generic';
?>

<div class="abc-wizard-header">
	<div class="abc-wizard-header-content">
		<div class="abc-wizard-icon">
			<span class="dashicons <?php echo esc_attr($icon_class); ?>"></span>
		</div>
		<div class="abc-wizard-title-section">
			<h1 class="abc-wizard-title"><?php echo esc_html($title); ?></h1>
			<?php if ($campaign_type): ?>
				<p class="abc-wizard-subtitle">
					<?php
					switch ($campaign_type) {
						case 'website':
							esc_html_e('Create a campaign to curate content from websites, blogs, and RSS feeds', 'autoblogcraft-ai');
							break;
						case 'youtube':
							esc_html_e('Create a campaign to transform YouTube videos into blog posts', 'autoblogcraft-ai');
							break;
						case 'amazon':
							esc_html_e('Create a campaign to generate product reviews from Amazon', 'autoblogcraft-ai');
							break;
						case 'news':
							esc_html_e('Create a campaign to curate and rewrite trending news articles', 'autoblogcraft-ai');
							break;
					}
					?>
				</p>
			<?php endif; ?>
		</div>
	</div>
</div>
