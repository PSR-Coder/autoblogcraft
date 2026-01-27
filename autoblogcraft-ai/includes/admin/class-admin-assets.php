<?php
/**
 * Admin Assets Manager
 *
 * Handles enqueuing of CSS and JavaScript files for admin pages.
 * Ensures proper asset loading with dependencies and versioning.
 *
 * @package AutoBlogCraft\Admin
 * @since 2.0.0
 */

namespace AutoBlogCraft\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Assets class
 *
 * Responsibilities:
 * - Enqueue admin CSS files
 * - Enqueue admin JavaScript files
 * - Localize scripts with data
 * - Handle asset dependencies
 * - Conditional loading per page
 *
 * @since 2.0.0
 */
class Admin_Assets
{

    /**
     * Assets version
     *
     * @var string
     */
    private $version;

    /**
     * Assets base URL
     *
     * @var string
     */
    private $assets_url;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->version = ABC_VERSION;
        $this->assets_url = plugins_url('assets/', ABC_PLUGIN_FILE);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Enqueue admin assets
     *
     * @since 2.0.0
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_assets($hook)
    {
        // Only load on AutoBlogCraft pages
        if (!$this->is_abc_page($hook)) {
            return;
        }

        // Enqueue styles
        $this->enqueue_styles($hook);

        // Enqueue scripts
        $this->enqueue_scripts($hook);
    }

    /**
     * Enqueue admin styles
     *
     * @since 2.0.0
     * @param string $hook Current admin page hook.
     * @return void
     */
    private function enqueue_styles($hook)
    {
        // Global admin styles (all ABC pages) - Consolidated v2.6.0
        wp_enqueue_style(
            'abc-admin',
            $this->assets_url . 'css/abc-admin.css',
            [],
            $this->version
        );

        // WordPress color picker (for settings)
        if ($this->is_settings_page($hook)) {
            wp_enqueue_style('wp-color-picker');
        }
    }

    /**
     * Enqueue admin scripts
     *
     * @since 2.0.0
     * @param string $hook Current admin page hook.
     * @return void
     */
    private function enqueue_scripts($hook)
    {
        // Global admin scripts (all ABC pages)
        wp_enqueue_script(
            'abc-admin',
            $this->assets_url . 'js/admin.js',
            ['jquery'],
            $this->version,
            true
        );

        // Localize global script
        wp_localize_script('abc-admin', 'abcAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('abc_admin'),
            'strings' => $this->get_translatable_strings(),
            'settings' => $this->get_script_settings(),
        ]);

        // Dashboard-specific scripts (Chart.js for graphs)
        if ($this->is_dashboard_page($hook)) {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
                [],
                '4.4.1',
                true
            );
        }



        // API Keys page scripts
        if ($this->is_api_keys_page($hook)) {
            wp_enqueue_script(
                'abc-api-keys',
                $this->assets_url . 'js/api-keys.js',
                ['jquery', 'abc-admin'],
                $this->version,
                true
            );
        }

        // Campaign editor page scripts (tabbed interface)
        if ($this->is_campaign_editor_page($hook)) {
            wp_enqueue_script(
                'abc-campaign-detail',
                $this->assets_url . 'js/campaign-detail.js',
                ['jquery', 'abc-admin'],
                $this->version,
                true
            );

            wp_localize_script('abc-campaign-detail', 'abcCampaignDetail', [
                'campaignId' => isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0,
                'campaign_id' => isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0, // Keep both for compatibility
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('abc_campaign_detail'),
                'refreshInterval' => 30000, // 30 seconds
                'refresh_interval' => 30000, // Keep both for compatibility
                // Individual nonces for inline template handlers
                'exportLogsNonce' => wp_create_nonce('abc_export_logs'),
                'clearLogsNonce' => wp_create_nonce('abc_clear_campaign_logs'),
                'processQueueNonce' => wp_create_nonce('abc_process_queue'),
                'processQueueItemNonce' => wp_create_nonce('abc_process_queue_item'),
                'deleteQueueItemNonce' => wp_create_nonce('abc_delete_queue_item'),
                'clearFailedQueueNonce' => wp_create_nonce('abc_clear_failed_queue'),
                // i18n strings
                'i18n' => [
                    'showDetails' => __('Show Details', 'autoblogcraft-ai'),
                    'hideDetails' => __('Hide Details', 'autoblogcraft-ai'),
                    'confirmClearLogs' => __('Are you sure you want to clear all logs for this campaign? This action cannot be undone.', 'autoblogcraft-ai'),
                    'errorClearingLogs' => __('Error clearing logs. Please try again.', 'autoblogcraft-ai'),
                    'processingStarted' => __('Processing started successfully.', 'autoblogcraft-ai'),
                    'errorProcessingQueue' => __('Error processing queue. Please try again.', 'autoblogcraft-ai'),
                    'errorProcessingItem' => __('Error processing item. Please try again.', 'autoblogcraft-ai'),
                    'confirmDeleteItem' => __('Are you sure you want to delete this item?', 'autoblogcraft-ai'),
                    'confirmClearFailed' => __('Are you sure you want to delete all failed items?', 'autoblogcraft-ai'),
                    'errorClearingFailed' => __('Error clearing failed items. Please try again.', 'autoblogcraft-ai'),
                ],
            ]);
        }

        // Color picker (for settings)
        if ($this->is_settings_page($hook)) {
            wp_enqueue_script('wp-color-picker');
        }

        // Media uploader (if needed)
        if ($this->needs_media_uploader($hook)) {
            wp_enqueue_media();
        }
    }

    /**
     * Get translatable strings for JavaScript
     *
     * @since 2.0.0
     * @return array
     */
    private function get_translatable_strings()
    {
        return [
            'confirm_delete' => __('Are you sure you want to delete this? This action cannot be undone.', 'autoblogcraft'),
            'confirm_pause' => __('Pause this campaign?', 'autoblogcraft'),
            'confirm_resume' => __('Resume this campaign?', 'autoblogcraft'),
            'processing' => __('Processing...', 'autoblogcraft'),
            'saving' => __('Saving...', 'autoblogcraft'),
            'saved' => __('Saved!', 'autoblogcraft'),
            'error' => __('An error occurred. Please try again.', 'autoblogcraft'),
            'success' => __('Success!', 'autoblogcraft'),
            'loading' => __('Loading...', 'autoblogcraft'),
            'no_results' => __('No results found.', 'autoblogcraft'),
            'copy_success' => __('Copied to clipboard!', 'autoblogcraft'),
            'copy_error' => __('Failed to copy.', 'autoblogcraft'),
            'validation_error' => __('Please fix validation errors before continuing.', 'autoblogcraft'),
            'unsaved_changes' => __('You have unsaved changes. Do you want to leave this page?', 'autoblogcraft'),
        ];
    }

    /**
     * Get settings for JavaScript
     *
     * @since 2.0.0
     * @return array
     */
    private function get_script_settings()
    {
        return [
            'debug_mode' => get_option('abc_enable_debug_logging', false),
            'auto_refresh' => true,
            'refresh_interval' => 60000, // 1 minute
            'max_file_size' => wp_max_upload_size(),
        ];
    }



    /**
     * Check if current page is an AutoBlogCraft page
     *
     * @since 2.0.0
     * @param string $hook Page hook.
     * @return bool
     */
    private function is_abc_page($hook)
    {
        return strpos($hook, 'autoblogcraft') !== false || strpos($hook, 'abc-') !== false;
    }

    /**
     * Check if current page is the dashboard
     *
     * @since 2.0.0
     * @param string $hook Page hook.
     * @return bool
     */
    private function is_dashboard_page($hook)
    {
        return $hook === 'toplevel_page_autoblogcraft';
    }

    /**
     * Check if current page is settings
     *
     * @since 2.0.0
     * @param string $hook Page hook.
     * @return bool
     */
    private function is_settings_page($hook)
    {
        return strpos($hook, 'autoblogcraft-settings') !== false;
    }

    /**
     * Check if current page is API keys
     *
     * @since 2.0.0
     * @param string $hook Page hook.
     * @return bool
     */
    private function is_api_keys_page($hook)
    {
        return strpos($hook, 'autoblogcraft-api-keys') !== false;
    }

    /**
     * Check if current page is campaign editor (unified tabbed interface)
     *
     * @since 2.0.0
     * @param string $hook Page hook.
     * @return bool
     */
    private function is_campaign_editor_page($hook)
    {
        return isset($_GET['page']) && $_GET['page'] === 'abc-campaign-editor' && isset($_GET['campaign_id']);
    }

    /**
     * Check if media uploader is needed
     *
     * @since 2.0.0
     * @param string $hook Page hook.
     * @return bool
     */
    private function needs_media_uploader($hook)
    {
        // Add media uploader to settings pages
        return $this->is_settings_page($hook);
    }

    /**
     * Register inline styles
     *
     * Useful for dynamic CSS based on settings.
     *
     * @since 2.0.0
     * @return void
     */
    public function add_inline_styles()
    {
        $custom_css = get_option('abc_custom_admin_css', '');

        if (!empty($custom_css)) {
            wp_add_inline_style('abc-admin', $custom_css);
        }
    }
}
