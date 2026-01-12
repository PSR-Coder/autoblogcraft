<?php
/**
 * Admin Page Footer Template
 *
 * Displays the footer section for admin pages with plugin info and links.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Partials
 * @since 2.0.0
 *
 * @var bool $show_version Show plugin version (default: true)
 * @var bool $show_links Show support/documentation links (default: true)
 * @var array $custom_links Optional custom footer links
 */

defined('ABSPATH') || exit;

$show_version = $show_version ?? true;
$show_links = $show_links ?? true;
$custom_links = $custom_links ?? [];

// Get plugin data
$plugin_file = dirname(dirname(dirname(__FILE__))) . '/autoblogcraft-ai.php';
$plugin_data = get_file_data($plugin_file, [
	'Version' => 'Version',
	'Author' => 'Author',
	'AuthorURI' => 'Author URI',
]);
?>

<div class="abc-admin-footer">
	<div class="abc-footer-container">
		<div class="abc-footer-info">
			<?php if ($show_version) : ?>
				<div class="abc-footer-version">
					<strong><?php esc_html_e('AutoBlogCraft AI', 'autoblogcraft-ai'); ?></strong>
					<span class="abc-version">
						<?php 
						/* translators: %s: Plugin version number */
						printf(esc_html__('v%s', 'autoblogcraft-ai'), esc_html($plugin_data['Version'] ?? '2.0.0')); 
						?>
					</span>
				</div>
			<?php endif; ?>

			<?php if (!empty($plugin_data['Author'])) : ?>
				<div class="abc-footer-author">
					<?php 
					/* translators: %s: Plugin author name */
					printf(
						esc_html__('By %s', 'autoblogcraft-ai'),
						!empty($plugin_data['AuthorURI']) 
							? '<a href="' . esc_url($plugin_data['AuthorURI']) . '" target="_blank" rel="noopener">' . esc_html($plugin_data['Author']) . '</a>'
							: esc_html($plugin_data['Author'])
					);
					?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ($show_links || !empty($custom_links)) : ?>
			<div class="abc-footer-links">
				<ul class="abc-footer-links-list">
					<?php if ($show_links) : ?>
						<li>
							<a href="<?php echo esc_url(admin_url('admin.php?page=abc-settings')); ?>">
								<span class="dashicons dashicons-admin-settings"></span>
								<?php esc_html_e('Settings', 'autoblogcraft-ai'); ?>
							</a>
						</li>
						<li>
							<a href="https://docs.autoblogcraft.ai" target="_blank" rel="noopener">
								<span class="dashicons dashicons-book"></span>
								<?php esc_html_e('Documentation', 'autoblogcraft-ai'); ?>
							</a>
						</li>
						<li>
							<a href="https://support.autoblogcraft.ai" target="_blank" rel="noopener">
								<span class="dashicons dashicons-sos"></span>
								<?php esc_html_e('Support', 'autoblogcraft-ai'); ?>
							</a>
						</li>
					<?php endif; ?>

					<?php foreach ($custom_links as $link) : ?>
						<li>
							<a href="<?php echo esc_url($link['url'] ?? '#'); ?>" 
							   <?php echo !empty($link['target']) ? 'target="' . esc_attr($link['target']) . '"' : ''; ?>
							   <?php echo !empty($link['rel']) ? 'rel="' . esc_attr($link['rel']) . '"' : ''; ?>>
								<?php if (!empty($link['icon'])) : ?>
									<span class="dashicons dashicons-<?php echo esc_attr($link['icon']); ?>"></span>
								<?php endif; ?>
								<?php echo esc_html($link['label'] ?? ''); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
</div>
