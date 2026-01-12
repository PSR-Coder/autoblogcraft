<?php
/**
 * Wizard Navigation Template
 *
 * Shared template for wizard navigation buttons (Previous/Next/Complete).
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards
 * @since 2.0.0
 *
 * @var int $current_step Current step number
 * @var int $total_steps Total number of steps
 * @var int $campaign_id Campaign ID
 * @var string $page_slug Page slug for navigation URLs
 */

defined('ABSPATH') || exit;

$current_step = $current_step ?? 1;
$total_steps = $total_steps ?? 4;
$campaign_id = $campaign_id ?? 0;
$page_slug = $page_slug ?? 'abc-campaign-wizard';

$is_first_step = ($current_step === 1);
$is_last_step = ($current_step === $total_steps);

$prev_url = $is_first_step 
	? admin_url('admin.php?page=abc-campaigns')
	: admin_url('admin.php?page=' . $page_slug . '&step=' . ($current_step - 1) . '&campaign_id=' . $campaign_id);

$cancel_url = admin_url('admin.php?page=abc-campaigns');
?>

<div class="abc-wizard-navigation">
	<div class="abc-wizard-nav-left">
		<?php if ($is_first_step): ?>
			<a href="<?php echo esc_url($cancel_url); ?>" class="button button-secondary">
				<span class="dashicons dashicons-no-alt"></span>
				<?php esc_html_e('Cancel', 'autoblogcraft-ai'); ?>
			</a>
		<?php else: ?>
			<a href="<?php echo esc_url($prev_url); ?>" class="button button-secondary">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e('Previous', 'autoblogcraft-ai'); ?>
			</a>
		<?php endif; ?>
	</div>

	<div class="abc-wizard-nav-center">
		<span class="abc-wizard-step-counter">
			<?php
			printf(
				/* translators: 1: current step, 2: total steps */
				esc_html__('Step %1$d of %2$d', 'autoblogcraft-ai'),
				'<strong>' . esc_html($current_step) . '</strong>',
				esc_html($total_steps)
			);
			?>
		</span>
	</div>

	<div class="abc-wizard-nav-right">
		<?php if ($is_last_step): ?>
			<button type="submit" class="button button-primary button-large">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e('Create Campaign', 'autoblogcraft-ai'); ?>
			</button>
		<?php else: ?>
			<button type="submit" class="button button-primary">
				<?php esc_html_e('Continue', 'autoblogcraft-ai'); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		<?php endif; ?>
	</div>
</div>
