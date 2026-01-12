<?php
/**
 * Admin Page Header Template
 *
 * Displays the header section for admin pages with title, breadcrumbs, and action buttons.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Partials
 * @since 2.0.0
 *
 * @var string $page_title The main page title
 * @var string $page_description Optional page description
 * @var array $breadcrumbs Optional breadcrumb items ['label' => 'url']
 * @var array $actions Optional action buttons ['label' => 'url', 'class' => '']
 */

defined('ABSPATH') || exit;

$page_title = $page_title ?? '';
$page_description = $page_description ?? '';
$breadcrumbs = $breadcrumbs ?? [];
$actions = $actions ?? [];
?>

<div class="abc-admin-header">
	<div class="abc-header-container">
		<?php if (!empty($breadcrumbs)) : ?>
			<nav class="abc-breadcrumbs" aria-label="<?php esc_attr_e('Breadcrumb', 'autoblogcraft-ai'); ?>">
				<ol class="abc-breadcrumb-list">
					<?php foreach ($breadcrumbs as $label => $url) : ?>
						<li class="abc-breadcrumb-item">
							<?php if (!empty($url)) : ?>
								<a href="<?php echo esc_url($url); ?>">
									<?php echo esc_html($label); ?>
								</a>
							<?php else : ?>
								<span aria-current="page"><?php echo esc_html($label); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>
			</nav>
		<?php endif; ?>

		<div class="abc-header-title-section">
			<div class="abc-header-title-wrapper">
				<h1 class="abc-page-title">
					<?php echo esc_html($page_title); ?>
				</h1>
				
				<?php if (!empty($actions)) : ?>
					<div class="abc-header-actions">
						<?php foreach ($actions as $action) : ?>
							<a href="<?php echo esc_url($action['url'] ?? '#'); ?>" 
							   class="<?php echo esc_attr($action['class'] ?? 'button button-primary'); ?>"
							   <?php echo !empty($action['target']) ? 'target="' . esc_attr($action['target']) . '"' : ''; ?>
							   <?php echo !empty($action['data']) ? 'data-action="' . esc_attr($action['data']) . '"' : ''; ?>>
								<?php if (!empty($action['icon'])) : ?>
									<span class="dashicons dashicons-<?php echo esc_attr($action['icon']); ?>"></span>
								<?php endif; ?>
								<?php echo esc_html($action['label'] ?? ''); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if (!empty($page_description)) : ?>
				<p class="abc-page-description">
					<?php echo wp_kses_post($page_description); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>
</div>
