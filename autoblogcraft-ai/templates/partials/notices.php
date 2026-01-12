<?php
/**
 * Admin Notices Template
 *
 * Displays admin notices for the plugin (success, error, warning, info).
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Partials
 * @since 2.0.0
 *
 * @var array $notices Array of notices ['type' => 'success|error|warning|info', 'message' => '', 'dismissible' => true]
 */

defined('ABSPATH') || exit;

$notices = $notices ?? [];

if (empty($notices)) {
	return;
}
?>

<div class="abc-notices-container">
	<?php foreach ($notices as $notice) : ?>
		<?php
		$type = $notice['type'] ?? 'info';
		$message = $notice['message'] ?? '';
		$dismissible = $notice['dismissible'] ?? true;
		$notice_id = $notice['id'] ?? '';
		
		// Map notice types to WordPress classes
		$type_class_map = [
			'success' => 'notice-success',
			'error' => 'notice-error',
			'warning' => 'notice-warning',
			'info' => 'notice-info',
		];
		
		$type_class = $type_class_map[$type] ?? 'notice-info';
		$dismissible_class = $dismissible ? 'is-dismissible' : '';
		
		// Icon mapping
		$icon_map = [
			'success' => 'yes-alt',
			'error' => 'dismiss',
			'warning' => 'warning',
			'info' => 'info',
		];
		
		$icon = $icon_map[$type] ?? 'info';
		?>
		
		<div class="notice abc-notice <?php echo esc_attr($type_class . ' ' . $dismissible_class); ?>" 
		     <?php echo !empty($notice_id) ? 'data-notice-id="' . esc_attr($notice_id) . '"' : ''; ?>>
			<div class="abc-notice-content">
				<span class="abc-notice-icon dashicons dashicons-<?php echo esc_attr($icon); ?>"></span>
				<div class="abc-notice-message">
					<?php echo wp_kses_post($message); ?>
				</div>
			</div>
			
			<?php if ($dismissible) : ?>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text">
						<?php esc_html_e('Dismiss this notice.', 'autoblogcraft-ai'); ?>
					</span>
				</button>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>

<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Handle notice dismissal
		$('.abc-notice.is-dismissible').on('click', '.notice-dismiss', function(e) {
			e.preventDefault();
			var $notice = $(this).closest('.abc-notice');
			var noticeId = $notice.data('notice-id');
			
			// Fade out the notice
			$notice.fadeOut(200, function() {
				$(this).remove();
			});
			
			// If notice has ID, save dismissal state
			if (noticeId) {
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'abc_dismiss_notice',
						notice_id: noticeId,
						nonce: '<?php echo esc_js(wp_create_nonce('abc_dismiss_notice')); ?>'
					}
				});
			}
		});
	});
</script>
