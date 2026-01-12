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
class Admin_Menu {

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
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'handle_wizard_submission']);
        
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
    public function register_menu() {
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
            __('Campaign Wizard', 'autoblogcraft'),
            __('Campaign Wizard', 'autoblogcraft'),
            'manage_options',
            'abc-campaign-wizard',
            [$this, 'render_campaign_wizard']
        );

        add_submenu_page(
            null, // No parent = hidden
            __('Campaign Detail', 'autoblogcraft'),
            __('Campaign Detail', 'autoblogcraft'),
            'manage_options',
            'abc-campaign-detail',
            [$this, 'render_campaign_detail']
        );
    }

    /**
     * Render dashboard page
     *
     * @since 2.0.0
     */
    public function render_dashboard() {
        $page = $this->get_page('dashboard');
        $page->render();
    }

    /**
     * Render campaigns page
     *
     * @since 2.0.0
     */
    public function render_campaigns() {
        $page = $this->get_page('campaigns');
        $page->render();
    }

    /**
     * Render queue page
     *
     * @since 2.0.0
     */
    public function render_queue() {
        $page = $this->get_page('queue');
        $page->render();
    }

    /**
     * Render API keys page
     *
     * @since 2.0.0
     */
    public function render_api_keys() {
        $page = $this->get_page('api-keys');
        $page->render();
    }

    /**
     * Render settings page
     *
     * @since 2.0.0
     */
    public function render_settings() {
        $page = $this->get_page('settings');
        $page->render();
    }

    /**
     * Render logs page
     *
     * @since 2.0.0
     */
    public function render_logs() {
        $page = $this->get_page('logs');
        $page->render();
    }

    /**
     * Render campaign wizard page
     *
     * @since 2.0.0
     */
    public function render_campaign_wizard() {
        $page = $this->get_page('campaign-wizard');
        $page->render();
    }

    /**
     * Render campaign detail page
     *
     * @since 2.0.0
     */
    public function render_campaign_detail() {
        $page = $this->get_page('campaign-detail');
        $page->render();
    }

    /**
     * Get page instance
     *
     * @since 2.0.0
     * @param string $page_id Page identifier.
     * @return object Page instance.
     */
    private function get_page($page_id) {
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
                break;

            case 'campaign-wizard':
                if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_Base')) {
                    require_once plugin_dir_path(__FILE__) . 'pages/class-page-base.php';
                }
                if (!class_exists('AutoBlogCraft\\Admin\\Wizards\\Wizard_Base')) {
                    require_once plugin_dir_path(__FILE__) . 'wizards/class-base-wizard.php';
                }
                if (!class_exists('AutoBlogCraft\\Admin\\Wizards\\Admin_Page_Campaign_Wizard')) {
                    require_once plugin_dir_path(__FILE__) . 'wizards/class-wizard-base.php';
                }
                $this->pages[$page_id] = new Wizards\Admin_Page_Campaign_Wizard();
                break;

            case 'campaign-detail':
                if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_Base')) {
                    require_once plugin_dir_path(__FILE__) . 'pages/class-page-base.php';
                }
                if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_Campaign_Detail')) {
                    require_once plugin_dir_path(__FILE__) . 'pages/class-campaign-detail-page.php';
                }
                $this->pages[$page_id] = new Pages\Admin_Page_Campaign_Detail();
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
    public function enqueue_assets($hook) {
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

        // Enqueue wizard CSS & JS for campaign wizard page
        if (strpos($hook, 'abc-campaign-wizard') !== false || (isset($_GET['page']) && $_GET['page'] === 'abc-campaign-wizard')) {
            // Enqueue wizard CSS
            wp_enqueue_style(
                'abc-wizard',
                plugins_url('assets/css/wizard.css', dirname(dirname(__FILE__))),
                ['abc-admin'],
                '2.0.0'
            );

            // Enqueue wizard JS
            wp_enqueue_script(
                'abc-wizard',
                plugins_url('assets/js/wizard.js', dirname(dirname(__FILE__))),
                ['jquery'],
                '2.0.0',
                true
            );

            // Localize wizard script
            wp_localize_script('abc-wizard', 'abcWizard', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('abc_admin'),
                'strings' => [
                    'validating' => __('Validating...', 'autoblogcraft'),
                    'valid' => __('Valid!', 'autoblogcraft'),
                    'invalid' => __('Invalid', 'autoblogcraft'),
                    'required' => __('This field is required.', 'autoblogcraft'),
                ],
            ]);
        }

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
     * Handle wizard form submission early (before any output)
     *
     * @since 2.0.0
     */
    public function handle_wizard_submission() {
        // Only process on wizard page
        if (!isset($_GET['page']) || $_GET['page'] !== 'abc-campaign-wizard') {
            return;
        }

        // Check if form was submitted
        if (!isset($_POST['abc_wizard_submit'])) {
            return;
        }

        error_log('ABC_WIZARD_DEBUG: handle_wizard_submission() in Admin_Menu - Early processing');

        // Verify nonce
        if (!isset($_POST['abc_wizard_nonce']) || !wp_verify_nonce($_POST['abc_wizard_nonce'], 'abc_wizard')) {
            error_log('ABC_WIZARD_DEBUG: Nonce verification failed');
            wp_die(__('Security check failed.', 'autoblogcraft'));
        }

        // Verify permissions
        if (!current_user_can('manage_options')) {
            error_log('ABC_WIZARD_DEBUG: Permission denied');
            wp_die(__('Permission denied.', 'autoblogcraft'));
        }

        $step = isset($_GET['step']) ? absint($_GET['step']) : 1;
        $campaign_id_post = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;
        $campaign_id_get = isset($_GET['campaign_id']) ? absint($_GET['campaign_id']) : 0;
        
        // Use POST campaign_id if available, otherwise fall back to GET
        $campaign_id = $campaign_id_post ? $campaign_id_post : $campaign_id_get;

        error_log('ABC_WIZARD_DEBUG: Processing step ' . $step . ' - POST campaign_id: ' . $campaign_id_post . ', GET campaign_id: ' . $campaign_id_get . ', Using: ' . $campaign_id);
        error_log('ABC_WIZARD_DEBUG: POST data: ' . print_r($_POST, true));
        error_log('ABC_WIZARD_DEBUG: GET data: ' . print_r($_GET, true));

        // Load required classes in correct order
        if (!class_exists('AutoBlogCraft\\Admin\\Pages\\Admin_Page_Base')) {
            require_once plugin_dir_path(__FILE__) . 'pages/class-page-base.php';
        }
        if (!class_exists('AutoBlogCraft\\Admin\\Wizards\\Wizard_Base')) {
            require_once plugin_dir_path(__FILE__) . 'wizards/class-base-wizard.php';
        }
        if (!class_exists('AutoBlogCraft\\Admin\\Wizards\\Admin_Page_Campaign_Wizard')) {
            require_once plugin_dir_path(__FILE__) . 'wizards/class-wizard-base.php';
        }

        // Create wizard instance (not needed for save, but validates class loads)
        $wizard = new Wizards\Admin_Page_Campaign_Wizard();
        
        switch ($step) {
            case 1:
                error_log('ABC_WIZARD_DEBUG: Calling wizard->save_step_1()');
                $campaign_id = $this->wizard_save_step_1($campaign_id);
                error_log('ABC_WIZARD_DEBUG: Step 1 saved, campaign_id: ' . $campaign_id);
                wp_redirect(admin_url('admin.php?page=abc-campaign-wizard&step=2&campaign_id=' . $campaign_id));
                exit;

            case 2:
                error_log('ABC_WIZARD_DEBUG: Calling wizard->save_step_2()');
                $this->wizard_save_step_2($campaign_id);
                wp_redirect(admin_url('admin.php?page=abc-campaign-wizard&step=3&campaign_id=' . $campaign_id));
                exit;

            case 3:
                error_log('ABC_WIZARD_DEBUG: Calling wizard->save_step_3()');
                $this->wizard_save_step_3($campaign_id);
                wp_redirect(admin_url('admin.php?page=abc-campaign-wizard&step=4&campaign_id=' . $campaign_id));
                exit;

            case 4:
                error_log('ABC_WIZARD_DEBUG: Calling wizard->save_step_4()');
                $this->wizard_save_step_4($campaign_id);
                wp_redirect(admin_url('admin.php?page=autoblogcraft-campaigns&created=1'));
                exit;
        }
    }

    /**
     * Save wizard step 1 data
     */
    private function wizard_save_step_1($campaign_id) {
        $campaign_name = isset($_POST['campaign_name']) ? sanitize_text_field($_POST['campaign_name']) : '';
        $campaign_type = isset($_POST['campaign_type']) ? sanitize_text_field($_POST['campaign_type']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';

        error_log('ABC_WIZARD_DEBUG: wizard_save_step_1() - Name: ' . $campaign_name . ', Type: ' . $campaign_type);

        if (!$campaign_id) {
            $campaign_id = wp_insert_post([
                'post_title' => $campaign_name,
                'post_content' => $description,
                'post_type' => 'abc_campaign',
                'post_status' => 'draft',
            ]);

            if (!$campaign_id) {
                error_log('ABC_WIZARD_DEBUG: Failed to create campaign');
                wp_die(__('Failed to create campaign.', 'autoblogcraft'));
            }

            error_log('ABC_WIZARD_DEBUG: Created campaign with ID: ' . $campaign_id);
            update_post_meta($campaign_id, '_campaign_type', $campaign_type);
            update_post_meta($campaign_id, '_campaign_status', 'draft');
        } else {
            wp_update_post([
                'ID' => $campaign_id,
                'post_title' => $campaign_name,
                'post_content' => $description,
            ]);
        }

        return $campaign_id;
    }

    /**
     * Save wizard step 2 data
     */
    private function wizard_save_step_2($campaign_id) {
        $source_config = isset($_POST['source_config']) ? $_POST['source_config'] : [];
        
        $sanitized_config = [];
        foreach ($source_config as $key => $value) {
            $key = sanitize_key($key);
            if (is_array($value)) {
                $sanitized_config[$key] = array_map('sanitize_text_field', $value);
            } else {
                if (in_array($key, ['feed_url', 'start_url'])) {
                    $sanitized_config[$key] = esc_url_raw($value);
                } else {
                    $sanitized_config[$key] = sanitize_text_field($value);
                }
            }
        }

        update_post_meta($campaign_id, '_source_config', $sanitized_config);
    }

    /**
     * Save wizard step 3 data
     */
    private function wizard_save_step_3($campaign_id) {
        $ai_config = isset($_POST['ai_config']) ? $_POST['ai_config'] : [];
        
        $sanitized_config = [];
        foreach ($ai_config as $key => $value) {
            $key = sanitize_key($key);
            if (is_numeric($value)) {
                $sanitized_config[$key] = absint($value);
            } else {
                $sanitized_config[$key] = sanitize_text_field($value);
            }
        }

        update_post_meta($campaign_id, '_ai_config', $sanitized_config);
    }

    /**
     * Save wizard step 4 data
     */
    private function wizard_save_step_4($campaign_id) {
        $discovery_interval = isset($_POST['discovery_interval']) ? sanitize_text_field($_POST['discovery_interval']) : 'hourly';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active';

        update_post_meta($campaign_id, '_discovery_interval', $discovery_interval);
        update_post_meta($campaign_id, '_campaign_status', $status);

        wp_update_post([
            'ID' => $campaign_id,
            'post_status' => 'publish',
        ]);
    }
}
