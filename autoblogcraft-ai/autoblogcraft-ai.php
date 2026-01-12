<?php
/**
 * Plugin Name: AutoBlogCraft AI
 * Plugin URI: https://autoblogcraft.com
 * Description: Enterprise-grade automated content curation from websites, YouTube, Amazon, and news sources with AI-powered rewriting.
 * Version: 2.0.0
 * Author: AutoBlogCraft Team
 * Author URI: https://autoblogcraft.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: autoblogcraft-ai
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Plugin version
define('ABC_VERSION', '2.0.0');

// Plugin root file
define('ABC_PLUGIN_FILE', __FILE__);

// Plugin directory path
define('ABC_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Plugin directory URL
define('ABC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Plugin basename
define('ABC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Load Action Scheduler library
 * Must be loaded BEFORE autoloader to ensure availability
 */
require_once ABC_PLUGIN_DIR . 'includes/libraries/action-scheduler-loader.php';
$as_loader = new AutoBlogCraft\Libraries\Action_Scheduler_Loader();
$as_loader->init();

/**
 * PSR-4 Autoloader
 */
require_once ABC_PLUGIN_DIR . 'includes/core/class-autoloader.php';

// Initialize autoloader
AutoBlogCraft\Core\Autoloader::register();

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, function() {
    // Manually load required classes for activation
    // This ensures they're available even if autoloader hasn't processed them yet
    if (!class_exists('AutoBlogCraft\Database\Installer')) {
        require_once ABC_PLUGIN_DIR . 'includes/database/class-installer.php';
    }
    AutoBlogCraft\Database\Installer::activate();
});

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, function() {
    // Manually load required classes for deactivation
    if (!class_exists('AutoBlogCraft\Core\Plugin')) {
        require_once ABC_PLUGIN_DIR . 'includes/core/class-plugin.php';
    }
    AutoBlogCraft\Core\Plugin::deactivate();
});

/**
 * Initialize the plugin
 */
function autoblogcraft_ai_init() {
    // Ensure Plugin class is loaded
    if (!class_exists('AutoBlogCraft\Core\Plugin')) {
        require_once ABC_PLUGIN_DIR . 'includes/core/class-plugin.php';
    }
    
    $plugin = AutoBlogCraft\Core\Plugin::instance();
    $plugin->init();
}
add_action('plugins_loaded', 'autoblogcraft_ai_init');
