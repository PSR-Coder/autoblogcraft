<?php
/**
 * Action Scheduler Loader
 *
 * Loads the bundled Action Scheduler library.
 * Action Scheduler is a scalable, traceable job queue for WordPress.
 *
 * @package AutoBlogCraft\Libraries
 * @since 2.0.0
 */

namespace AutoBlogCraft\Libraries;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Action Scheduler Loader class
 *
 * NOTE: Action Scheduler is an external library developed by Automattic.
 * In production, you should:
 * 1. Download Action Scheduler from: https://github.com/woocommerce/action-scheduler
 * 2. Place the entire library in: includes/libraries/action-scheduler/
 * 3. This loader will automatically initialize it
 *
 * Action Scheduler features:
 * - WP-Cron alternative with guaranteed execution
 * - Handles large volumes of jobs efficiently
 * - Built-in job retry and error handling
 * - Admin UI for monitoring scheduled actions
 * - Used by WooCommerce, Jetpack, and other major plugins
 *
 * @since 2.0.0
 */
class Action_Scheduler_Loader {

    /**
     * Action Scheduler path
     *
     * @var string
     */
    private $as_path;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->as_path = dirname(__FILE__) . '/action-scheduler/action-scheduler.php';
    }

    /**
     * Initialize Action Scheduler
     *
     * @since 2.0.0
     */
    public function init() {
        // Check if Action Scheduler is already loaded by another plugin (WooCommerce, Jetpack, etc.)
        if (class_exists('ActionScheduler')) {
            return; // Already available, no notice needed
        }

        // Check if WooCommerce or Jetpack are active (they will load Action Scheduler later)
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $has_woocommerce = is_plugin_active('woocommerce/woocommerce.php');
        $has_jetpack = is_plugin_active('jetpack/jetpack.php');
        
        if ($has_woocommerce || $has_jetpack) {
            // WooCommerce or Jetpack will load Action Scheduler, no notice needed
            return;
        }

        // Check if our bundled version exists
        if (!file_exists($this->as_path)) {
            // Action Scheduler not found AND not loaded by other plugins - show notice and enable WP-Cron fallback
            $this->create_placeholder_notice();
            $this->enable_wp_cron_fallback();
            return;
        }

        // Load our bundled Action Scheduler
        require_once $this->as_path;
    }

    /**
     * Create admin notice if Action Scheduler is missing
     *
     * @since 2.0.0
     */
    private function create_placeholder_notice() {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong>AutoBlogCraft AI:</strong> Action Scheduler library is not installed. 
                    Falling back to WP-Cron (less reliable).
                </p>
                <p>
                    <strong>Recommended: Install Action Scheduler for better reliability</strong>
                </p>
                <ol>
                    <li>Download Action Scheduler from: <a href="https://github.com/woocommerce/action-scheduler/releases" target="_blank">GitHub Releases</a></li>
                    <li>Extract the <code>action-scheduler</code> folder</li>
                    <li>Place it in: <code>wp-content/plugins/autoblogcraft-ai/includes/libraries/action-scheduler/</code></li>
                    <li>Reload this page</li>
                </ol>
                <p>
                    <em>Note: Alternatively, install WooCommerce or Jetpack - both include Action Scheduler.</em>
                </p>
            </div>
            <?php
        });
    }

    /**
     * Check if Action Scheduler is available
     *
     * @since 2.0.0
     * @return bool True if available.
     */
    public function is_available() {
        return class_exists('ActionScheduler');
    }

    /**
     * Get Action Scheduler version
     *
     * @since 2.0.0
     * @return string|null Version string or null.
     */
    public function get_version() {
        if (!class_exists('ActionScheduler_Versions')) {
            return null;
        }

        return ActionScheduler_Versions::instance()->latest_version();
    }

    /**
     * Enable WP-Cron fallback mode
     * 
     * This creates compatibility shims so the plugin can work with WP-Cron
     * when Action Scheduler is not available.
     *
     * @since 2.0.0
     */
    private function enable_wp_cron_fallback() {
        // Create Action Scheduler function shims for WP-Cron fallback
        if (!function_exists('as_schedule_recurring_action')) {
            function as_schedule_recurring_action($timestamp, $interval, $hook, $args = [], $group = '') {
                if (!wp_next_scheduled($hook, $args)) {
                    wp_schedule_event($timestamp, $interval . 'sec', $hook, $args);
                }
                return true;
            }
        }

        if (!function_exists('as_next_scheduled_action')) {
            function as_next_scheduled_action($hook, $args = [], $group = '') {
                return wp_next_scheduled($hook, $args);
            }
        }

        if (!function_exists('as_unschedule_all_actions')) {
            function as_unschedule_all_actions($hook, $args = [], $group = '') {
                wp_clear_scheduled_hook($hook, $args);
            }
        }

        if (!function_exists('as_schedule_single_action')) {
            function as_schedule_single_action($timestamp, $hook, $args = [], $group = '') {
                wp_schedule_single_event($timestamp, $hook, $args);
                return true;
            }
        }

        if (!function_exists('as_unschedule_action')) {
            function as_unschedule_action($hook, $args = [], $group = '') {
                wp_unschedule_event(wp_next_scheduled($hook, $args), $hook, $args);
            }
        }

        if (!function_exists('as_get_scheduled_actions')) {
            function as_get_scheduled_actions($args = []) {
                // WP-Cron doesn't have equivalent - return empty array
                return [];
            }
        }

        // Add custom WP-Cron schedules for our intervals
        add_filter('cron_schedules', function($schedules) {
            $schedules['120sec'] = [
                'interval' => 120,
                'display' => __('Every 2 Minutes', 'autoblogcraft-ai'),
            ];
            $schedules['300sec'] = [
                'interval' => 300,
                'display' => __('Every 5 Minutes', 'autoblogcraft-ai'),
            ];
            $schedules['86400sec'] = [
                'interval' => 86400,
                'display' => __('Daily', 'autoblogcraft-ai'),
            ];
            return $schedules;
        });
    }
}
