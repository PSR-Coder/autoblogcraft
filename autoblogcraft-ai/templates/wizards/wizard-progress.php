<?php
/**
 * Wizard Progress Bar Template
 *
 * Shared template for displaying wizard progress across all campaign types.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards
 * @since 2.0.0
 *
 * @var array $steps Array of wizard steps
 * @var int $current_step Current step number
 */

defined('ABSPATH') || exit;

$steps = $steps ?? [];
$current_step = $current_step ?? 1;
?>

<div class="abc-wizard-progress">
	<div class="abc-wizard-progress-bar">
		<?php foreach ($steps as $index => $step): 
			$step_num = $index + 1;
			$is_current = ($step_num === $current_step);
			$is_completed = ($step_num < $current_step);
			$is_accessible = ($step_num <= $current_step);
			?>
			<div class="abc-wizard-step-indicator <?php echo $is_current ? 'active' : ''; ?> <?php echo $is_completed ? 'completed' : ''; ?>">
				<div class="abc-wizard-step-number">
					<?php if ($is_completed): ?>
						<span class="dashicons dashicons-yes"></span>
					<?php else: ?>
						<?php echo esc_html($step_num); ?>
					<?php endif; ?>
				</div>
				<div class="abc-wizard-step-info">
					<div class="abc-wizard-step-title"><?php echo esc_html($step['title']); ?></div>
					<div class="abc-wizard-step-description"><?php echo esc_html($step['description']); ?></div>
				</div>
			</div>
			<?php if ($step_num < count($steps)): ?>
				<div class="abc-wizard-step-connector <?php echo $is_completed ? 'completed' : ''; ?>"></div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
</div>
