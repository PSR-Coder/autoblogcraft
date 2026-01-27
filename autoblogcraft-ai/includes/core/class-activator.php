<?php
/**
 * Plugin Activation Handler
 *
 * Handles all tasks that need to be performed when the plugin is activated.
 *
 * @package AutoBlogCraft\Core
 * @since 2.0.0
 */

namespace AutoBlogCraft\Core;

use AutoBlogCraft\Database\Installer;
use AutoBlogCraft\Cron\Cron_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Activator class
 *
 * Responsibilities:
 * - Create database tables
 * - Set default options
 * - Schedule cron jobs
 * - Check system requirements
 * - Create default data
 *
 * @since 2.0.0
 */
class Activator {

    /**
     * Minimum PHP version required
     *
     * @var string
     */
    const MIN_PHP_VERSION = '7.4';

    /**
     * Minimum WordPress version required
     *
     * @var string
     */
    const MIN_WP_VERSION = '5.8';

    /**
     * Run activation tasks
     *
     * Called when the plugin is activated.
     *
     * @since 2.0.0
     * @return void
     */
    public static function activate() {
        // Check system requirements
        if (!self::check_requirements()) {
            return;
        }

        // Create database tables
        self::create_tables();

        // Set default options
        self::set_default_options();

        // Schedule cron jobs
        self::schedule_cron_jobs();

        // Set activation timestamp
        update_option('abc_activated_at', time());

        // Set current version
        update_option('abc_version', ABC_VERSION);
        update_option('abc_db_version', ABC_DB_VERSION);

        // Clear rewrite rules
        flush_rewrite_rules();

        // Log activation
        if (class_exists('AutoBlogCraft\Core\Logger')) {
            Logger::log(0, 'info', 'system', 'Plugin activated successfully', [
                'version' => ABC_VERSION,
                'php_version' => PHP_VERSION,
                'wp_version' => get_bloginfo('version'),
            ]);
        }
    }

    /**
     * Check system requirements
     *
     * @since 2.0.0
     * @return bool True if requirements met, false otherwise.
     */
    private static function check_requirements() {
        $errors = [];

        // Check PHP version
        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            $errors[] = sprintf(
                __('AutoBlogCraft AI requires PHP version %s or higher. You are running version %s.', 'autoblogcraft'),
                self::MIN_PHP_VERSION,
                PHP_VERSION
            );
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, self::MIN_WP_VERSION, '<')) {
            $errors[] = sprintf(
                __('AutoBlogCraft AI requires WordPress version %s or higher. You are running version %s.', 'autoblogcraft'),
                self::MIN_WP_VERSION,
                $wp_version
            );
        }

        // Check required PHP extensions
        $required_extensions = ['curl', 'json', 'mbstring'];
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $errors[] = sprintf(
                    __('AutoBlogCraft AI requires the PHP %s extension.', 'autoblogcraft'),
                    $extension
                );
            }
        }

        // Check if OpenSSL is available for encryption
        if (!function_exists('openssl_encrypt')) {
            $errors[] = __('AutoBlogCraft AI requires OpenSSL for API key encryption.', 'autoblogcraft');
        }

        // If there are errors, deactivate plugin and show notice
        if (!empty($errors)) {
            deactivate_plugins(plugin_basename(ABC_PLUGIN_FILE));
            
            wp_die(
                '<h1>' . __('Plugin Activation Error', 'autoblogcraft') . '</h1>' .
                '<p>' . implode('</p><p>', $errors) . '</p>' .
                '<p><a href="' . admin_url('plugins.php') . '">' . __('Return to Plugins', 'autoblogcraft') . '</a></p>'
            );
            
            return false;
        }

        return true;
    }

    /**
     * Create database tables
     *
     * @since 2.0.0
     * @return void
     */
    private static function create_tables() {
        $installer = new Installer();
        $installer->create_tables();
    }

    /**
     * Set default plugin options
     *
     * @since 2.0.0
     * @return void
     */
    private static function set_default_options() {
        // Default global settings
        $defaults = [
            'abc_global_rate_limit_per_minute' => 60,
            'abc_global_rate_limit_per_day' => 10000,
            'abc_queue_retention_days' => 30,
            'abc_log_retention_days' => 30,
            'abc_cache_retention_days' => 90,
            'abc_enable_debug_logging' => false,
            'abc_auto_pause_on_errors' => true,
            'abc_error_threshold' => 5,
            'abc_max_queue_size_global' => 10000,
        ];

        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }

        // Create default capability for admin users
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_autoblogcraft');
            $role->add_cap('edit_abc_campaigns');
            $role->add_cap('delete_abc_campaigns');
        }
    }

    /**
     * Schedule cron jobs
     *
     * Uses Action Scheduler for reliable cron execution.
     *
     * @since 2.0.0
     * @return void
     */
    private static function schedule_cron_jobs() {
        // Initialize Action Scheduler if not already loaded
        if (!function_exists('as_schedule_recurring_action')) {
            require_once ABC_PLUGIN_DIR . 'includes/libraries/action-scheduler-loader.php';
        }

        // Ensure Action Scheduler data store is initialized before using
        if (!did_action('action_scheduler_init')) {
            // Schedule for next request when Action Scheduler is fully initialized
            add_action('action_scheduler_init', [__CLASS__, 'schedule_cron_jobs']);
            return;
        }

        // Clear any existing schedules first
        as_unschedule_all_actions('abc_discovery_cron');
        as_unschedule_all_actions('abc_processing_cron');
        as_unschedule_all_actions('abc_cleanup_cron');
        as_unschedule_all_actions('abc_rate_limit_reset_cron');

        // Schedule discovery job (every 5 minutes)
        if (!as_next_scheduled_action('abc_discovery_cron')) {
            as_schedule_recurring_action(
                time(),
                5 * MINUTE_IN_SECONDS,
                'abc_discovery_cron',
                [],
                'autoblogcraft'
            );
        }

        // Schedule processing job (every 2 minutes)
        if (!as_next_scheduled_action('abc_processing_cron')) {
            as_schedule_recurring_action(
                time() + 60, // Start 1 minute after discovery
                2 * MINUTE_IN_SECONDS,
                'abc_processing_cron',
                [],
                'autoblogcraft'
            );
        }

        // Schedule cleanup job (daily at 3 AM)
        if (!as_next_scheduled_action('abc_cleanup_cron')) {
            $next_3am = strtotime('tomorrow 3:00am');
            as_schedule_recurring_action(
                $next_3am,
                DAY_IN_SECONDS,
                'abc_cleanup_cron',
                [],
                'autoblogcraft'
            );
        }

        // Schedule rate limit reset job (every minute)
        if (!as_next_scheduled_action('abc_rate_limit_reset_cron')) {
            as_schedule_recurring_action(
                time(),
                MINUTE_IN_SECONDS,
                'abc_rate_limit_reset_cron',
                [],
                'autoblogcraft'
            );
        }
    }

    /**
     * Check if this is a first-time activation
     *
     * @since 2.0.0
     * @return bool
     */
    public static function is_first_activation() {
        return get_option('abc_activated_at') === false;
    }

    /**
     * Get activation timestamp
     *
     * @since 2.0.0
     * @return int|false Timestamp or false if never activated.
     */
    public static function get_activation_time() {
        return get_option('abc_activated_at');
    }
}
