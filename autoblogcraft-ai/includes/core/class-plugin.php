<?php
/**
 * Main Plugin Class
 *
 * @package AutoBlogCraft\Core
 * @since 2.0.0
 */

namespace AutoBlogCraft\Core;

use AutoBlogCraft\Database\Installer;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Plugin
 *
 * Main plugin orchestrator - Singleton pattern
 * Bootstraps all components in correct order
 */
class Plugin {

    /**
     * Singleton instance
     *
     * @var Plugin
     */
    protected static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Plugin
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     * Private to enforce singleton
     */
    private function __construct() {
        // Constructor is intentionally empty
        // All initialization happens in init() method
    }

    /**
     * Initialize the plugin
     * Called on 'plugins_loaded' hook
     */
    public function init() {
        // Initialize security hardening
        if (class_exists('AutoBlogCraft\Security\Security_Hardening')) {
            \AutoBlogCraft\Security\Security_Hardening::init();
        }

        // Check database version and auto-migrate if needed
        $this->check_database();

        // Register custom post types
        add_action('init', [$this, 'register_post_types']);

        // Initialize core components
        $this->init_core();

        // Initialize admin components (admin only)
        if (is_admin()) {
            $this->init_admin();
        }

        // Initialize cron jobs
        $this->init_cron();

        // Initialize REST API
        $this->init_rest_api();

        // Load text domain
        $this->load_textdomain();

        // Custom action for extensions
        do_action('autoblogcraft_ai_loaded');
    }

    /**
     * Check database version and migrate if needed
     */
    private function check_database() {
        // Ensure Installer class is loaded
        if (!class_exists('AutoBlogCraft\Database\Installer')) {
            require_once ABC_PLUGIN_DIR . 'includes/database/class-installer.php';
        }
        Installer::check_version();
    }

    /**
     * Register custom post types
     */
    public function register_post_types() {
        // Register Campaign CPT
        $labels = [
            'name' => __('Campaigns', 'autoblogcraft-ai'),
            'singular_name' => __('Campaign', 'autoblogcraft-ai'),
            'add_new' => __('Add New', 'autoblogcraft-ai'),
            'add_new_item' => __('Add New Campaign', 'autoblogcraft-ai'),
            'edit_item' => __('Edit Campaign', 'autoblogcraft-ai'),
            'new_item' => __('New Campaign', 'autoblogcraft-ai'),
            'view_item' => __('View Campaign', 'autoblogcraft-ai'),
            'search_items' => __('Search Campaigns', 'autoblogcraft-ai'),
            'not_found' => __('No campaigns found', 'autoblogcraft-ai'),
            'not_found_in_trash' => __('No campaigns found in trash', 'autoblogcraft-ai'),
            'all_items' => __('All Campaigns', 'autoblogcraft-ai'),
            'menu_name' => __('Campaigns', 'autoblogcraft-ai'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => false, // We'll create custom admin menu
            'show_in_nav_menus' => false,
            'show_in_admin_bar' => false,
            'show_in_rest' => false, // No Gutenberg editor
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => ['title'], // Only title, meta handled separately
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false,
            'can_export' => true,
            'delete_with_user' => false,
        ];

        register_post_type('abc_campaign', $args);
    }

    /**
     * Initialize core components
     */
    private function init_core() {
        // Ensure Logger class is loaded
        if (!class_exists('AutoBlogCraft\Core\Logger')) {
            require_once ABC_PLUGIN_DIR . 'includes/core/class-logger.php';
        }
        
        // Initialize logger
        Logger::instance()->init();

        // Initialize AI manager (singleton, auto-loads Key_Manager)
        if (!class_exists('AutoBlogCraft\AI\AI_Manager')) {
            require_once ABC_PLUGIN_DIR . 'includes/ai/class-ai-manager.php';
        }
        \AutoBlogCraft\AI\AI_Manager::instance();

        // Campaign Factory is static, no initialization needed
        // Used via Campaign_Factory::create($campaign_id)
    }

    /**
     * Initialize admin components
     */
    private function init_admin() {
        // Ensure Admin_Menu class is loaded
        if (!class_exists('AutoBlogCraft\Admin\Admin_Menu')) {
            require_once ABC_PLUGIN_DIR . 'includes/admin/class-admin-menu.php';
        }
        
        // Register admin menu
        new \AutoBlogCraft\Admin\Admin_Menu();

        // Ensure Admin_Assets class is loaded
        if (!class_exists('AutoBlogCraft\Admin\Admin_Assets')) {
            require_once ABC_PLUGIN_DIR . 'includes/admin/class-admin-assets.php';
        }
        // Enqueue admin assets
        new \AutoBlogCraft\Admin\Admin_Assets();

        // Ensure Admin_Notices class is loaded
        if (!class_exists('AutoBlogCraft\Admin\Admin_Notices')) {
            require_once ABC_PLUGIN_DIR . 'includes/admin/class-admin-notices.php';
        }
        // Admin notices (server cron detection, etc.)
        new \AutoBlogCraft\Admin\Admin_Notices();
    }

    /**
     * Initialize cron jobs
     */
    private function init_cron() {
        // Ensure Cron_Manager class is loaded
        if (!class_exists('AutoBlogCraft\Cron\Cron_Manager')) {
            require_once ABC_PLUGIN_DIR . 'includes/cron/class-cron-manager.php';
        }
        
        // Initialize Cron Manager with Action Scheduler
        $cron_manager = new \AutoBlogCraft\Cron\Cron_Manager();
        $cron_manager->init();
    }

    /**
     * Initialize REST API
     */
    private function init_rest_api() {
        // Ensure REST_Controller class is loaded
        if (!class_exists('AutoBlogCraft\API\REST_Controller')) {
            require_once ABC_PLUGIN_DIR . 'includes/api/class-rest-controller.php';
        }
        
        // Initialize REST API
        \AutoBlogCraft\API\REST_Controller::get_instance()->init();
    }

    /**
     * Load plugin text domain
     */
    private function load_textdomain() {
        load_plugin_textdomain(
            'autoblogcraft-ai',
            false,
            dirname(ABC_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Unschedule cron jobs
        // Don't delete data - user might reactivate

        // Clear Action Scheduler jobs
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions('abc_discovery_job', [], 'autoblogcraft');
            as_unschedule_all_actions('abc_processing_job', [], 'autoblogcraft');
            as_unschedule_all_actions('abc_cleanup_job', [], 'autoblogcraft');
            as_unschedule_all_actions('abc_rate_limit_reset_job', [], 'autoblogcraft');
        }

        // Fallback to WP Cron unschedule
        wp_clear_scheduled_hook('abc_discovery_job');
        wp_clear_scheduled_hook('abc_processing_job');
        wp_clear_scheduled_hook('abc_cleanup_job');
        wp_clear_scheduled_hook('abc_rate_limit_reset_job');

        // Clear cache
        wp_cache_flush();
    }

    /**
     * Get plugin version
     *
     * @return string
     */
    public function get_version() {
        return ABC_VERSION;
    }

    /**
     * Get plugin directory path
     *
     * @return string
     */
    public function get_plugin_dir() {
        return ABC_PLUGIN_DIR;
    }

    /**
     * Get plugin directory URL
     *
     * @return string
     */
    public function get_plugin_url() {
        return ABC_PLUGIN_URL;
    }
}
