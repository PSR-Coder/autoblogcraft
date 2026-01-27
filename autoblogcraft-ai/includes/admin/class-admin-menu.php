<?php
/**
 * Admin Menu
 *
 * Registers admin menu structure and handles navigation.
 *
 * @package AutoBlogCraft\Admin
 * @since 2.0.0
 */

namespace AutoBlogCraft\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Menu class
 *
 * Responsibilities:
 * - Register admin menu pages
 * - Handle menu structure
 * - Load admin page classes
 * - Enqueue admin assets
 *
 * @since 2.0.0
 */
class Admin_Menu
{

    /**
     * Page instances
     *
     * @var array
     */
    private $pages = [];

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_post_abc_save_campaign', [$this, 'handle_save_campaign']);
        add_action('admin_post_abc_clear_logs', [$this, 'handle_clear_logs']);

        // Ensure AJAX_Handlers class is loaded
        if (!class_exists('AutoBlogCraft\Admin\AJAX_Handlers')) {
            require_once ABC_PLUGIN_DIR . 'includes/admin/class-ajax-handlers.php';
        }

        // Initialize AJAX handlers
        new AJAX_Handlers();
    }

    /**
     * Register admin menu
     *
     * @since 2.0.0
     */
    public function register_menu()
    {
        // Main menu
        add_menu_page(
            __('AutoBlogCraft AI', 'autoblogcraft'),
            __('AutoBlogCraft', 'autoblogcraft'),
            'manage_options',
            'autoblogcraft',
            [$this, 'render_dashboard'],
            'dashicons-welcome-write-blog',
            30
        );

        // Dashboard submenu (same as parent)
        add_submenu_page(
            'autoblogcraft',
            __('Dashboard', 'autoblogcraft'),
            __('Dashboard', 'autoblogcraft'),
            'manage_options',
            'autoblogcraft',
            [$this, 'render_dashboard']
        );

        // Campaigns submenu
        add_submenu_page(
            'autoblogcraft',
            __('Campaigns', 'autoblogcraft'),
            __('Campaigns', 'autoblogcraft'),
            'manage_options',
            'autoblogcraft-campaigns',
            [$this, 'render_campaigns']
        );

        // Queue submenu
        add_submenu_page(
            'autoblogcraft',
            __('Queue', 'autoblogcraft'),
            __('Queue', 'autoblogcraft'),
            'manage_options',
            'autoblogcraft-queue',
            [$this, 'render_queue']
        );

        // API Keys submenu
        add_submenu_page(
            'autoblogcraft',
            __('API Keys', 'autoblogcraft'),
            __('API Keys', 'autoblogcraft'),
            'manage_options',
            'autoblogcraft-api-keys',
            [$this, 'render_api_keys']
        );

        // Settings submenu
        add_submenu_page(
            'autoblogcraft',
            __('Settings', 'autoblogcraft'),
            __('Settings', 'autoblogcraft'),
            'manage_options',
            'autoblogcraft-settings',
            [$this, 'render_settings']
        );

        // Logs submenu
        add_submenu_page(
            'autoblogcraft',
            __('Logs', 'autoblogcraft'),
            __('Logs', 'autoblogcraft'),
            'manage_options',
            'autoblogcraft-logs',
            [$this, 'render_logs']
        );

        // Hidden pages (not in menu)
        add_submenu_page(
            null, // No parent = hidden
            __('Campaign Editor', 'autoblogcraft'),
            __('Campaign Editor', 'autoblogcraft'),
            'manage_options',
            'abc-campaign-editor',
            [$this, 'render_campaign_editor']
        );
    }

    /**
     * Render dashboard page
     *
     * @since 2.0.0
     */
    public function render_dashboard()
    {
        $page = $this->get_page('dashboard');
        $page->render();
    }

    /**
     * Render campaigns page
     *
     * @since 2.0.0
     */
    public function render_campaigns()
    {
        $page = $this->get_page('campaigns');
        $page->render();
    }

    /**
     * Render queue page
     *
     * @since 2.0.0
     */
    public function render_queue()
    {
        $page = $this->get_page('queue');
        $page->render();
    }

    /**
     * Render API keys page
     *
     * @since 2.0.0
     */
    public function render_api_keys()
    {
        $page = $this->get_page('api-keys');
        $page->render();
    }

    /**
     * Render settings page
     *
     * @since 2.0.0
     */
    public function render_settings()
    {
        $page = $this->get_page('settings');
        $page->render();
    }

    /**
     * Render logs page
     *
     * @since 2.0.0
     */
    public function render_logs()
    {
        $page = $this->get_page('logs');
        $page->render();
    }

    /**
     * Render campaign editor page
     *
     * @since 2.0.0
     */
    public function render_campaign_editor()
    {
        $page = $this->get_page('campaign-editor');
        $page->render();
    }

    /**
     * Get page instance
     *
     * @since 2.0.0
     * @param string $page_id Page identifier.
     * @return object Page instance.
     */
    private function get_page($page_id)
    {
        if (isset($this->pages[$page_id])) {
            return $this->pages[$page_id];
        }

        // Instantiate page
        switch ($page_id) {
            case 'dashboard':
                if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_Base')) {
                    require_once plugin_dir_path(__FILE__) . 'pages/class-page-base.php';
                }
                if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_Dashboard')) {
                    require_once plugin_dir_path(__FILE__) . 'pages/class-dashboard-page.php';
                }
                $this->pages[$page_id] = new Pages\Admin_Page_Dashboard();
                $this->pages[$page_id] = new Pages\Admin_Page_Dashboard();
                break;

            case 'campaign-editor':
                if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_Base')) {
                    require_once plugin_dir_path(__FILE__) . 'pages/class-page-base.php';
                }
                if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_Campaign_Editor')) {
                    require_once plugin_dir_path(__FILE__) . 'pages/class-campaign-editor.php';
                }
                $this->pages[$page_id] = new Pages\Admin_Page_Campaign_Editor();
                break;


            case 'campaigns':
                if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_Base')) {
                    require_once plugin_dir_path(__FILE__) . 'pages/class-page-base.php';
                }
                if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_Campaigns')) {
                    require_once plugin_dir_path(__FILE__) . 'pages/class-campaigns-page.php';
                }
                $this->pages[$page_id] = new Pages\Admin_Page_Campaigns();
                break;

            case 'queue':
                if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_Base')) {
                    require_once plugin_dir_path(__FILE__) . 'pages/class-page-base.php';
                }
                if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_Queue')) {
                    require_once plugin_dir_path(__FILE__) . 'pages/class-queue-page.php';
                }
                $this->pages[$page_id] = new Pages\Admin_Page_Queue();
                break;

            case 'api-keys':
                if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_Base')) {
                    require_once plugin_dir_path(__FILE__) . 'pages/class-page-base.php';
                }
                if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_API_Keys')) {
                    require_once plugin_dir_path(__FILE__) . 'pages/class-api-keys-page.php';
                }
                $this->pages[$page_id] = new Pages\Admin_Page_API_Keys();
                break;

            case 'settings':
                if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_Base')) {
                    require_once plugin_dir_path(__FILE__) . 'pages/class-page-base.php';
                }
                if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_Settings')) {
                    require_once plugin_dir_path(__FILE__) . 'pages/class-settings-page.php';
                }
                $this->pages[$page_id] = new Pages\Admin_Page_Settings();
                break;

            case 'logs':
                if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_Base')) {
                    require_once plugin_dir_path(__FILE__) . 'pages/class-page-base.php';
                }
                if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_Logs')) {
                    require_once plugin_dir_path(__FILE__) . 'pages/class-logs-page.php';
                }
                $this->pages[$page_id] = new Pages\Admin_Page_Logs();
                break;

            default:
                if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_Base')) {
                    require_once plugin_dir_path(__FILE__) . 'pages/class-page-base.php';
                }
                if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_Dashboard')) {
                    require_once plugin_dir_path(__FILE__) . 'pages/class-dashboard-page.php';
                }
                $this->pages[$page_id] = new Pages\Admin_Page_Dashboard();
        }

        return $this->pages[$page_id];
    }

    /**
     * Enqueue admin assets
     *
     * @since 2.0.0
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets($hook)
    {
        // Only load on our pages
        if (strpos($hook, 'autoblogcraft') === false && strpos($hook, 'abc-') === false) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'abc-admin',
            plugins_url('assets/css/admin.css', dirname(dirname(__FILE__))),
            [],
            '2.0.0'
        );

        // Enqueue JS
        wp_enqueue_script(
            'abc-admin',
            plugins_url('assets/js/admin.js', dirname(dirname(__FILE__))),
            ['jquery'],
            '2.0.0',
            true
        );

        // Localize script
        wp_localize_script('abc-admin', 'abcAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('abc_admin'),
            'strings' => [
                'confirm_delete' => __('Are you sure you want to delete this?', 'autoblogcraft'),
                'processing' => __('Processing...', 'autoblogcraft'),
                'error' => __('An error occurred. Please try again.', 'autoblogcraft'),
            ],
        ]);

        // Enqueue WordPress color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        // Enqueue Chart.js for dashboard
        if ($hook === 'toplevel_page_autoblogcraft') {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
                [],
                '4.4.1',
                true
            );
        }
    }

    /**
     * Handle unified campaign save
     *
     * @since 2.1.0
     */
    public function handle_save_campaign()
    {
        if (!isset($_POST['abc_nonce']) || !wp_verify_nonce($_POST['abc_nonce'], 'abc_save_campaign')) {
            wp_die(__('Security check failed', 'autoblogcraft'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'autoblogcraft'));
        }

        $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;
        $wizard_step = isset($_POST['wizard_step']) ? sanitize_key($_POST['wizard_step']) : '';
        $is_wizard = !empty($wizard_step);
        $create_campaign = isset($_POST['create_campaign']) && $_POST['create_campaign'] === '1';
        
        // Get user ID for transient storage
        $user_id = get_current_user_id();
        $transient_key = 'abc_campaign_wizard_' . $user_id;
        
        // For wizard mode, store data in transient until final submission
        if ($is_wizard && !$create_campaign) {
            // Load existing wizard data
            $wizard_data = get_transient($transient_key) ?: [];
            
            // Merge current step data
            if ($wizard_step === 'basic') {
                $wizard_data['title'] = sanitize_text_field($_POST['post_title']);
                $wizard_data['type'] = sanitize_key($_POST['campaign_type']);
                $wizard_data['wp_config']['category_id'] = absint($_POST['wp_category_id'] ?? 0);
                $wizard_data['wp_config']['author_id'] = absint($_POST['wp_author_id'] ?? get_current_user_id());
                $wizard_data['wp_config']['post_status'] = sanitize_key($_POST['wp_post_status'] ?? 'publish');
                $wizard_data['wp_config']['seo_plugin'] = sanitize_key($_POST['wp_config']['seo_plugin'] ?? 'none');
                
                // Handle discovery interval (use custom if provided, otherwise use dropdown)
                $discovery_interval = sanitize_text_field($_POST['discovery_interval'] ?? 'every_1_hour');
                $discovery_interval_custom = sanitize_text_field($_POST['discovery_interval_custom'] ?? '');
                $wizard_data['discovery_interval'] = !empty($discovery_interval_custom) ? $discovery_interval_custom : $discovery_interval;
                
                $wizard_data['limits'] = array_map('absint', $_POST['limits'] ?? []);
            } elseif ($wizard_step === 'sources') {
                $wizard_data['source_types'] = $_POST['source_types'] ?? [];
                $wizard_data['rss_sources'] = sanitize_textarea_field($_POST['rss_sources'] ?? '');
                $wizard_data['sitemap_sources'] = sanitize_textarea_field($_POST['sitemap_sources'] ?? '');
                $wizard_data['url_sources'] = sanitize_textarea_field($_POST['url_sources'] ?? '');
                $wizard_data['source_config'] = $this->sanitize_recursive($_POST['source_config'] ?? []);
            } elseif ($wizard_step === 'content') {
                $wizard_data['ai_config'] = array_merge($wizard_data['ai_config'] ?? [], $this->sanitize_recursive($_POST['ai_config'] ?? []));
            }
            
            // Save to transient (expires in 1 hour)
            set_transient($transient_key, $wizard_data, HOUR_IN_SECONDS);
            
            // Determine next step
            $next_steps = [
                'basic' => 'sources',
                'sources' => 'content',
                'content' => 'ai'
            ];
            $next_tab = $next_steps[$wizard_step] ?? 'ai';
            
            // Redirect to next step
            $redirect_url = admin_url('admin.php?page=abc-campaign-editor&tab=' . $next_tab);
            wp_redirect($redirect_url);
            exit;
        }
        
        // Final campaign creation (from AI Settings tab with create_campaign=1) or editing existing campaign
        if ($create_campaign || $campaign_id > 0) {
            // Load wizard data for final creation
            $wizard_data = [];
            if ($create_campaign) {
                $wizard_data = get_transient($transient_key) ?: [];
                // Merge final AI settings
                $wizard_data['ai_config'] = array_merge($wizard_data['ai_config'] ?? [], $this->sanitize_recursive($_POST['ai_config'] ?? []));
            }

            $title = $create_campaign ? ($wizard_data['title'] ?? '') : sanitize_text_field($_POST['post_title']);
            $type = $create_campaign ? ($wizard_data['type'] ?? 'website') : sanitize_key($_POST['campaign_type']);

            // 1. Create or Update Post
            $post_data = [
                'post_title' => $title,
                'post_type' => 'abc_campaign',
                'post_status' => 'publish'
            ];

            if ($campaign_id > 0) {
                $post_data['ID'] = $campaign_id;
                wp_update_post($post_data);
            } else {
                $campaign_id = wp_insert_post($post_data);
                if (is_wp_error($campaign_id)) {
                    wp_die($campaign_id->get_error_message());
                }
                // Set type only on creation
                update_post_meta($campaign_id, '_campaign_type', $type);
                update_post_meta($campaign_id, '_campaign_status', 'active');
            }

            // 1.1 Save WP Config
            $wp_config = $create_campaign ? ($wizard_data['wp_config'] ?? []) : [];
            if (isset($_POST['wp_category_id']) || isset($wp_config['category_id']))
                update_post_meta($campaign_id, '_wp_category_id', absint($_POST['wp_category_id'] ?? $wp_config['category_id'] ?? 0));
            if (isset($_POST['wp_author_id']) || isset($wp_config['author_id']))
                update_post_meta($campaign_id, '_wp_author_id', absint($_POST['wp_author_id'] ?? $wp_config['author_id'] ?? get_current_user_id()));
            if (isset($_POST['wp_post_status']) || isset($wp_config['post_status']))
                update_post_meta($campaign_id, '_wp_post_status', sanitize_key($_POST['wp_post_status'] ?? $wp_config['post_status'] ?? 'publish'));

            // 2. Save Source Config
            $clean_source_config = [];
            
            if ($type === 'website') {
                // Handle checkbox-based website sources from sources tab
                $source_types = $_POST['source_types'] ?? ($create_campaign ? ($wizard_data['source_types'] ?? []) : []);
                $rss_sources = $_POST['rss_sources'] ?? ($create_campaign ? ($wizard_data['rss_sources'] ?? '') : '');
                $sitemap_sources = $_POST['sitemap_sources'] ?? ($create_campaign ? ($wizard_data['sitemap_sources'] ?? '') : '');
                $url_sources = $_POST['url_sources'] ?? ($create_campaign ? ($wizard_data['url_sources'] ?? '') : '');
                
                $sources_array = [];
                
                // Process RSS feeds
                if (isset($source_types['rss']) && !empty($rss_sources)) {
                    $rss_urls = array_filter(array_map('trim', explode("\n", $rss_sources)));
                    foreach ($rss_urls as $url) {
                        $sources_array[] = ['type' => 'rss', 'url' => esc_url_raw($url)];
                    }
                }
                
                // Process Sitemaps
                if (isset($source_types['sitemap']) && !empty($sitemap_sources)) {
                    $sitemap_urls = array_filter(array_map('trim', explode("\n", $sitemap_sources)));
                    foreach ($sitemap_urls as $url) {
                        $sources_array[] = ['type' => 'sitemap', 'url' => esc_url_raw($url)];
                    }
                }
                
                // Process Direct URLs/Blogs
                if (isset($source_types['blogs']) && !empty($url_sources)) {
                    $direct_urls = array_filter(array_map('trim', explode("\n", $url_sources)));
                    foreach ($direct_urls as $url) {
                        $sources_array[] = ['type' => 'url', 'url' => esc_url_raw($url)];
                    }
                }
                
                $clean_source_config['sources'] = $sources_array;
                
                // Use wizard data if available
                if (empty($sources_array) && $create_campaign && isset($wizard_data['source_config'])) {
                    $clean_source_config = $wizard_data['source_config'];
                }
            } else {
                // For non-website campaigns (YouTube, Amazon, News)
                if (isset($_POST['source_config'][$type])) {
                    $clean_source_config = $this->sanitize_recursive($_POST['source_config'][$type]);
                } elseif ($create_campaign && isset($wizard_data['source_config'])) {
                    $clean_source_config = $wizard_data['source_config'];
                }
            }

            update_post_meta($campaign_id, '_source_config', $clean_source_config);

            // 3. Save AI Config
            $ai_config = $_POST['ai_config'] ?? ($create_campaign ? ($wizard_data['ai_config'] ?? []) : []);
            $clean_ai_config = $this->sanitize_recursive($ai_config);

        // Sanitize API Key ID specifically
        if (isset($ai_config['api_key_id'])) {
            $clean_ai_config['api_key_id'] = absint($ai_config['api_key_id']);
        }

        // Numeric fields
        if (isset($ai_config['temperature'])) {
            $clean_ai_config['temperature'] = floatval($ai_config['temperature']);
        }
        if (isset($ai_config['min_words'])) {
            $clean_ai_config['min_words'] = absint($ai_config['min_words']);
        }
        if (isset($ai_config['max_words'])) {
            $clean_ai_config['max_words'] = absint($ai_config['max_words']);
        }
        if (isset($ai_config['max_headings'])) {
            $clean_ai_config['max_headings'] = absint($ai_config['max_headings']);
        }

        // Checkbox handling - all checkboxes need explicit handling
        $clean_ai_config['humanizer_enabled'] = isset($ai_config['humanizer_enabled']);
        $clean_ai_config['seo_enabled'] = isset($ai_config['seo_enabled']);
        $clean_ai_config['fetch_images'] = isset($ai_config['fetch_images']);
        $clean_ai_config['translation_enabled'] = isset($ai_config['translation_enabled']);
        $clean_ai_config['match_source_length'] = isset($ai_config['match_source_length']);
        $clean_ai_config['match_source_tone'] = isset($ai_config['match_source_tone']);
        $clean_ai_config['match_source_headings'] = isset($ai_config['match_source_headings']);
        $clean_ai_config['match_source_brand'] = isset($ai_config['match_source_brand']);

        update_post_meta($campaign_id, '_ai_config', $clean_ai_config);

        // 4. Save WP Config (SEO Plugin)
        if (isset($_POST['wp_config']['seo_plugin'])) {
            update_post_meta($campaign_id, '_wp_seo_plugin', sanitize_key($_POST['wp_config']['seo_plugin']));
        }

            // 5. Save Common Settings
            // Handle discovery interval (custom takes priority)
            $discovery_interval_custom = sanitize_text_field($_POST['discovery_interval_custom'] ?? '');
            $discovery_interval_dropdown = sanitize_text_field($_POST['discovery_interval'] ?? '');
            $discovery_interval = !empty($discovery_interval_custom) ? $discovery_interval_custom : $discovery_interval_dropdown;
            
            // If empty, use wizard data or default
            if (empty($discovery_interval)) {
                $discovery_interval = $create_campaign ? ($wizard_data['discovery_interval'] ?? 'every_1_hour') : '';
            }
            
            $limits = $_POST['limits'] ?? ($create_campaign ? ($wizard_data['limits'] ?? []) : []);
            
            if (!empty($discovery_interval)) {
                update_post_meta($campaign_id, '_discovery_interval', sanitize_text_field($discovery_interval));
            }

            if (!empty($limits)) {
                update_post_meta($campaign_id, '_limits', array_map('absint', $limits));
            }

            // Clear wizard transient after successful campaign creation
            if ($create_campaign) {
                delete_transient($transient_key);
            }

            // Redirect based on context
            if ($create_campaign) {
                // New campaign created via wizard - redirect to edit mode with success message
                $redirect_url = admin_url('admin.php?page=abc-campaign-editor&campaign_id=' . $campaign_id . '&tab=overview&message=created');
            } else {
                // Existing campaign edited - redirect to current tab
                $current_tab = isset($_POST['current_tab']) ? sanitize_key($_POST['current_tab']) : 'overview';
                $redirect_url = admin_url('admin.php?page=abc-campaign-editor&campaign_id=' . $campaign_id . '&tab=' . $current_tab . '&message=saved');
            }
            
            wp_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Handle clear logs
     */
    public function handle_clear_logs()
    {
        if (!isset($_POST['abc_nonce']) || !wp_verify_nonce($_POST['abc_nonce'], 'abc_clear_logs')) {
            wp_die(__('Security check failed', 'autoblogcraft'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'autoblogcraft'));
        }

        $logger = \AutoBlogCraft\Core\Logger::instance();

        // Delete all logs (0 days retention)
        $deleted = $logger->cleanup_old_logs(0);

        // Redirect back
        $redirect_url = admin_url('admin.php?page=autoblogcraft-logs&message=cleared&count=' . $deleted);
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Recursive sanitization helper
     */
    private function sanitize_recursive($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->sanitize_recursive($value);
            }
            return $data;
        }
        return sanitize_text_field($data);
    }
}
