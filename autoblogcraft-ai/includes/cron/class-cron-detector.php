<?php
/**
 * Cron Detector - Detects server cron vs WP-Cron configuration
 *
 * Analyzes the WordPress cron setup and provides recommendations for optimal
 * configuration. Detects whether site is using WP-Cron or server cron and
 * identifies potential issues.
 *
 * @package AutoBlogCraft
 * @subpackage Cron
 * @since 2.0.0
 */

namespace AutoBlogCraft\Cron;

use AutoBlogCraft\Core\Logger;

/**
 * Cron Detector Class
 *
 * Detects and analyzes WordPress cron configuration.
 *
 * @since 2.0.0
 */
class Cron_Detector {

	/**
	 * Cron type constants
	 */
	const TYPE_WP_CRON = 'wp-cron';
	const TYPE_SERVER_CRON = 'server-cron';
	const TYPE_ACTION_SCHEDULER = 'action-scheduler';
	const TYPE_UNKNOWN = 'unknown';

	/**
	 * Health status constants
	 */
	const HEALTH_GOOD = 'good';
	const HEALTH_WARNING = 'warning';
	const HEALTH_CRITICAL = 'critical';

	/**
	 * Logger instance
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Singleton instance
	 *
	 * @var Cron_Detector
	 */
	private static $instance = null;

	/**
	 * Cached detection results
	 *
	 * @var array
	 */
	private $cache = array();

	/**
	 * Get singleton instance
	 *
	 * @return Cron_Detector
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->logger = Logger::instance();
	}

	/**
	 * Get complete cron status
	 *
	 * Returns comprehensive information about cron setup.
	 *
	 * @return array {
	 *     Cron status information
	 *
	 *     @type string $type              Cron type (wp-cron, server-cron, action-scheduler)
	 *     @type string $health            Health status (good, warning, critical)
	 *     @type bool   $wp_cron_disabled  Whether DISABLE_WP_CRON is true
	 *     @type bool   $action_scheduler  Whether Action Scheduler is available
	 *     @type array  $issues            Array of detected issues
	 *     @type array  $recommendations   Array of recommendations
	 *     @type array  $details           Additional details
	 * }
	 */
	public function get_status() {
		if ( ! empty( $this->cache['status'] ) ) {
			return $this->cache['status'];
		}

		$type              = $this->detect_cron_type();
		$wp_cron_disabled  = $this->is_wp_cron_disabled();
		$action_scheduler  = $this->is_action_scheduler_available();
		$issues            = $this->detect_issues();
		$health            = $this->calculate_health( $issues );
		$recommendations   = $this->get_recommendations( $type, $issues );
		$details           = $this->get_details();

		$status = array(
			'type'              => $type,
			'health'            => $health,
			'wp_cron_disabled'  => $wp_cron_disabled,
			'action_scheduler'  => $action_scheduler,
			'issues'            => $issues,
			'recommendations'   => $recommendations,
			'details'           => $details,
			'timestamp'         => time(),
		);

		$this->cache['status'] = $status;

		return $status;
	}

	/**
	 * Detect cron type
	 *
	 * @return string Cron type constant
	 */
	public function detect_cron_type() {
		// Check for Action Scheduler first (preferred)
		if ( $this->is_action_scheduler_available() && $this->is_action_scheduler_active() ) {
			return self::TYPE_ACTION_SCHEDULER;
		}

		// Check if WP-Cron is disabled
		if ( $this->is_wp_cron_disabled() ) {
			// WP-Cron disabled means server cron should be configured
			if ( $this->is_server_cron_working() ) {
				return self::TYPE_SERVER_CRON;
			} else {
				// Disabled but not working - critical issue
				return self::TYPE_UNKNOWN;
			}
		}

		// WP-Cron is enabled (default WordPress behavior)
		return self::TYPE_WP_CRON;
	}

	/**
	 * Check if WP-Cron is disabled
	 *
	 * @return bool
	 */
	public function is_wp_cron_disabled() {
		return defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON === true;
	}

	/**
	 * Check if Action Scheduler is available
	 *
	 * @return bool
	 */
	public function is_action_scheduler_available() {
		return function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Check if Action Scheduler is actively being used
	 *
	 * @return bool
	 */
	private function is_action_scheduler_active() {
		// Check if we have any AutoBlogCraft actions scheduled
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return false;
		}

		$actions = as_get_scheduled_actions(
			array(
				'hook'     => 'abc_discovery_cron',
				'status'   => 'pending',
				'per_page' => 1,
			)
		);

		return ! empty( $actions );
	}

	/**
	 * Check if server cron is working
	 *
	 * @return bool
	 */
	private function is_server_cron_working() {
		// Check when wp-cron.php was last accessed
		$last_run = get_option( 'abc_cron_last_run' );

		if ( ! $last_run ) {
			// No record yet - assume it might be working
			return true;
		}

		// If last run was more than 10 minutes ago, might be an issue
		$time_since_run = time() - $last_run;

		return $time_since_run < 600; // 10 minutes
	}

	/**
	 * Detect cron issues
	 *
	 * @return array Array of issue descriptions
	 */
	public function detect_issues() {
		$issues = array();

		// Check 1: WP-Cron disabled but no server cron
		if ( $this->is_wp_cron_disabled() && ! $this->is_server_cron_working() ) {
			$issues[] = array(
				'severity' => 'critical',
				'message'  => 'WP-Cron is disabled but server cron may not be configured properly. Jobs may not run.',
			);
		}

		// Check 2: Using WP-Cron on high-traffic site
		if ( ! $this->is_wp_cron_disabled() && $this->is_high_traffic_site() ) {
			$issues[] = array(
				'severity' => 'warning',
				'message'  => 'Using WP-Cron on a high-traffic site. Consider switching to server cron for better performance.',
			);
		}

		// Check 3: WP-Cron on low-traffic site
		if ( ! $this->is_wp_cron_disabled() && $this->is_low_traffic_site() ) {
			$issues[] = array(
				'severity' => 'warning',
				'message'  => 'Low traffic detected. WP-Cron may not run frequently enough. Consider server cron or Action Scheduler.',
			);
		}

		// Check 4: Missed schedules
		$missed = $this->get_missed_schedules();
		if ( $missed > 5 ) {
			$issues[] = array(
				'severity' => 'warning',
				'message'  => sprintf( '%d scheduled events have been missed. Cron may not be running properly.', $missed ),
			);
		}

		// Check 5: Action Scheduler not available
		if ( ! $this->is_action_scheduler_available() ) {
			$issues[] = array(
				'severity' => 'info',
				'message'  => 'Action Scheduler not detected. Consider installing it for more reliable job execution.',
			);
		}

		// Check 6: Spawning cron disabled
		if ( defined( 'DISABLE_WP_CRON_SPAWN' ) && DISABLE_WP_CRON_SPAWN === true ) {
			$issues[] = array(
				'severity' => 'warning',
				'message'  => 'Cron spawning is disabled. Ensure server cron is configured.',
			);
		}

		// Check 7: Alternative cron disabled
		if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON === true ) {
			$issues[] = array(
				'severity' => 'info',
				'message'  => 'Using alternate cron method. This may impact performance on high-traffic sites.',
			);
		}

		return $issues;
	}

	/**
	 * Calculate overall health status
	 *
	 * @param array $issues Array of issues.
	 * @return string Health status constant
	 */
	private function calculate_health( $issues ) {
		if ( empty( $issues ) ) {
			return self::HEALTH_GOOD;
		}

		$has_critical = false;
		$has_warning  = false;

		foreach ( $issues as $issue ) {
			if ( 'critical' === $issue['severity'] ) {
				$has_critical = true;
			} elseif ( 'warning' === $issue['severity'] ) {
				$has_warning = true;
			}
		}

		if ( $has_critical ) {
			return self::HEALTH_CRITICAL;
		}

		if ( $has_warning ) {
			return self::HEALTH_WARNING;
		}

		return self::HEALTH_GOOD;
	}

	/**
	 * Get recommendations based on setup
	 *
	 * @param string $type   Cron type.
	 * @param array  $issues Detected issues.
	 * @return array Array of recommendations
	 */
	public function get_recommendations( $type, $issues ) {
		$recommendations = array();

		// Recommendation based on cron type
		switch ( $type ) {
			case self::TYPE_WP_CRON:
				if ( $this->is_high_traffic_site() ) {
					$recommendations[] = array(
						'priority' => 'high',
						'message'  => 'Switch to server cron for better performance on high-traffic sites.',
						'action'   => 'setup_server_cron',
					);
				}
				if ( ! $this->is_action_scheduler_available() ) {
					$recommendations[] = array(
						'priority' => 'medium',
						'message'  => 'Install Action Scheduler for more reliable background processing.',
						'action'   => 'install_action_scheduler',
					);
				}
				break;

			case self::TYPE_SERVER_CRON:
				$recommendations[] = array(
					'priority' => 'low',
					'message'  => 'Server cron is configured. Ensure it runs at least every 5 minutes for optimal performance.',
					'action'   => 'verify_server_cron',
				);
				break;

			case self::TYPE_ACTION_SCHEDULER:
				$recommendations[] = array(
					'priority' => 'low',
					'message'  => 'Action Scheduler is active. This is the recommended setup for AutoBlogCraft AI.',
					'action'   => 'none',
				);
				break;

			case self::TYPE_UNKNOWN:
				$recommendations[] = array(
					'priority' => 'critical',
					'message'  => 'Cron configuration issue detected. Please configure server cron or enable WP-Cron.',
					'action'   => 'fix_cron_setup',
				);
				break;
		}

		// Additional recommendations based on issues
		foreach ( $issues as $issue ) {
			if ( 'critical' === $issue['severity'] ) {
				$recommendations[] = array(
					'priority' => 'critical',
					'message'  => 'Fix: ' . $issue['message'],
					'action'   => 'fix_critical_issue',
				);
			}
		}

		return $recommendations;
	}

	/**
	 * Get detailed cron information
	 *
	 * @return array Detailed information
	 */
	private function get_details() {
		global $wp_version;

		return array(
			'wp_version'           => $wp_version,
			'php_version'          => PHP_VERSION,
			'disable_wp_cron'      => $this->is_wp_cron_disabled(),
			'action_scheduler'     => $this->is_action_scheduler_available(),
			'scheduled_events'     => $this->get_scheduled_event_count(),
			'missed_schedules'     => $this->get_missed_schedules(),
			'last_cron_run'        => $this->get_last_cron_run(),
			'cron_interval'        => $this->get_cron_interval(),
			'doing_cron'           => defined( 'DOING_CRON' ) && DOING_CRON,
			'alternate_cron'       => defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON,
			'disable_cron_spawn'   => defined( 'DISABLE_WP_CRON_SPAWN' ) && DISABLE_WP_CRON_SPAWN,
		);
	}

	/**
	 * Check if site is high traffic
	 *
	 * @return bool
	 */
	private function is_high_traffic_site() {
		// Check transient cache for traffic indicator
		$traffic_level = get_transient( 'abc_traffic_level' );

		if ( false === $traffic_level ) {
			// Estimate based on published posts in last 24 hours
			global $wpdb;
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} 
					WHERE post_status = 'publish' 
					AND post_date > DATE_SUB(NOW(), INTERVAL %d HOUR)",
					24
				)
			);

			$traffic_level = $count > 100 ? 'high' : 'normal';
			set_transient( 'abc_traffic_level', $traffic_level, HOUR_IN_SECONDS );
		}

		return 'high' === $traffic_level;
	}

	/**
	 * Check if site is low traffic
	 *
	 * @return bool
	 */
	private function is_low_traffic_site() {
		// Check transient cache
		$traffic_level = get_transient( 'abc_traffic_level' );

		if ( false === $traffic_level ) {
			// Estimate based on published posts in last 24 hours
			global $wpdb;
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} 
					WHERE post_status = 'publish' 
					AND post_date > DATE_SUB(NOW(), INTERVAL %d HOUR)",
					24
				)
			);

			$traffic_level = $count < 10 ? 'low' : 'normal';
			set_transient( 'abc_traffic_level', $traffic_level, HOUR_IN_SECONDS );
		}

		return 'low' === $traffic_level;
	}

	/**
	 * Get count of scheduled events
	 *
	 * @return int
	 */
	private function get_scheduled_event_count() {
		$crons = _get_cron_array();
		return is_array( $crons ) ? count( $crons, COUNT_RECURSIVE ) : 0;
	}

	/**
	 * Get count of missed schedules
	 *
	 * @return int
	 */
	private function get_missed_schedules() {
		$crons  = _get_cron_array();
		$missed = 0;

		if ( ! is_array( $crons ) ) {
			return 0;
		}

		$current_time = time();

		foreach ( $crons as $timestamp => $cron_array ) {
			if ( $timestamp < $current_time ) {
				$missed += count( $cron_array );
			}
		}

		return $missed;
	}

	/**
	 * Get last cron run timestamp
	 *
	 * @return int|null
	 */
	private function get_last_cron_run() {
		return get_option( 'abc_cron_last_run', null );
	}

	/**
	 * Get estimated cron interval
	 *
	 * @return int Seconds between cron runs
	 */
	private function get_cron_interval() {
		// For AutoBlogCraft, discovery runs every 5 minutes
		return 5 * MINUTE_IN_SECONDS;
	}

	/**
	 * Get setup instructions for server cron
	 *
	 * @return array Array of setup steps
	 */
	public function get_server_cron_instructions() {
		$wp_cron_url = site_url( 'wp-cron.php' );

		return array(
			'title'        => 'Server Cron Setup Instructions',
			'description'  => 'Setting up server cron provides more reliable and efficient background job execution.',
			'steps'        => array(
				array(
					'number' => 1,
					'title'  => 'Disable WP-Cron',
					'detail' => 'Add this line to your wp-config.php file (before "That\'s all, stop editing!"):<br><code>define(\'DISABLE_WP_CRON\', true);</code>',
				),
				array(
					'number' => 2,
					'title'  => 'Configure Server Cron',
					'detail' => 'Add this cron job to run every 5 minutes. Access your hosting control panel (cPanel, Plesk, etc.) and add:<br><code>*/5 * * * * wget -q -O - ' . esc_url( $wp_cron_url ) . ' &>/dev/null</code><br>Or if using curl:<br><code>*/5 * * * * curl -s ' . esc_url( $wp_cron_url ) . ' &>/dev/null</code>',
				),
				array(
					'number' => 3,
					'title'  => 'Verify Setup',
					'detail' => 'Wait 5-10 minutes and refresh this dashboard. The cron type should change to "Server Cron".',
				),
			),
			'alternative'  => array(
				'title'  => 'Alternative: Use Action Scheduler',
				'detail' => 'Install the Action Scheduler plugin for a WordPress-native alternative to server cron. It provides reliable background processing without server configuration.',
			),
		);
	}

	/**
	 * Record cron run
	 *
	 * Call this from cron jobs to track execution.
	 */
	public function record_cron_run() {
		update_option( 'abc_cron_last_run', time(), false );
	}

	/**
	 * Clear cached status
	 */
	public function clear_cache() {
		$this->cache = array();
	}

	/**
	 * Get health badge HTML
	 *
	 * @param string $health Health status.
	 * @return string HTML badge
	 */
	public function get_health_badge( $health ) {
		$badges = array(
			self::HEALTH_GOOD     => '<span class="abc-badge abc-badge-success">✓ Healthy</span>',
			self::HEALTH_WARNING  => '<span class="abc-badge abc-badge-warning">⚠ Warning</span>',
			self::HEALTH_CRITICAL => '<span class="abc-badge abc-badge-danger">✗ Critical</span>',
		);

		return isset( $badges[ $health ] ) ? $badges[ $health ] : '<span class="abc-badge">Unknown</span>';
	}

	/**
	 * Get cron type label
	 *
	 * @param string $type Cron type.
	 * @return string Human-readable label
	 */
	public function get_type_label( $type ) {
		$labels = array(
			self::TYPE_WP_CRON          => 'WP-Cron (Default)',
			self::TYPE_SERVER_CRON      => 'Server Cron (Recommended)',
			self::TYPE_ACTION_SCHEDULER => 'Action Scheduler (Best)',
			self::TYPE_UNKNOWN          => 'Unknown / Misconfigured',
		);

		return isset( $labels[ $type ] ) ? $labels[ $type ] : 'Unknown';
	}
}
