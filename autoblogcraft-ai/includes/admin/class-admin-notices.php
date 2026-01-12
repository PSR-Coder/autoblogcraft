<?php
/**
 * Admin Notices Manager
 *
 * Manages admin notices, warnings, and alerts for the AutoBlogCraft plugin.
 * Provides centralized notification system for user feedback.
 *
 * @package AutoBlogCraft\Admin
 * @since 2.0.0
 */

namespace AutoBlogCraft\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Notices class
 *
 * Responsibilities:
 * - Display admin notices
 * - Check system health
 * - Alert about missing configuration
 * - Show rate limit warnings
 * - Display update notifications
 *
 * @since 2.0.0
 */
class Admin_Notices {

    /**
     * Notice types and their CSS classes
     *
     * @var array
     */
    private $notice_types = [
        'error' => 'notice-error',
        'warning' => 'notice-warning',
        'success' => 'notice-success',
        'info' => 'notice-info',
    ];

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        add_action('admin_notices', [$this, 'display_notices']);
        add_action('admin_init', [$this, 'check_system_health']);
        add_action('admin_init', [$this, 'dismiss_notice_handler']);
    }

    /**
     * Display all pending notices
     *
     * @since 2.0.0
     * @return void
     */
    public function display_notices() {
        // Only show on ABC pages
        if (!$this->is_abc_page()) {
            return;
        }

        // Get stored notices
        $notices = get_option('abc_admin_notices', []);

        foreach ($notices as $id => $notice) {
            // Check if notice is dismissed
            if ($this->is_notice_dismissed($id)) {
                continue;
            }

            // Check if notice has expired
            if (isset($notice['expires']) && time() > $notice['expires']) {
                unset($notices[$id]);
                continue;
            }

            $this->render_notice($id, $notice);
        }

        // Clean up expired notices
        update_option('abc_admin_notices', $notices);
    }

    /**
     * Render a single notice
     *
     * @since 2.0.0
     * @param string $id     Notice ID.
     * @param array  $notice Notice data.
     * @return void
     */
    private function render_notice($id, $notice) {
        $type = isset($notice['type']) ? $notice['type'] : 'info';
        $message = isset($notice['message']) ? $notice['message'] : '';
        $dismissible = isset($notice['dismissible']) ? $notice['dismissible'] : true;

        $class = $this->notice_types[$type] ?? 'notice-info';
        $dismiss_class = $dismissible ? 'is-dismissible' : '';

        ?>
        <div class="notice <?php echo esc_attr($class); ?> <?php echo esc_attr($dismiss_class); ?>" data-notice-id="<?php echo esc_attr($id); ?>">
            <p><?php echo wp_kses_post($message); ?></p>
            <?php if ($dismissible): ?>
                <a href="<?php echo esc_url(add_query_arg(['abc_dismiss_notice' => $id])); ?>" class="notice-dismiss">
                    <span class="screen-reader-text"><?php _e('Dismiss this notice.', 'autoblogcraft'); ?></span>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Add a notice
     *
     * @since 2.0.0
     * @param string $id          Unique notice ID.
     * @param string $message     Notice message.
     * @param string $type        Notice type (error, warning, success, info).
     * @param bool   $dismissible Whether notice can be dismissed.
     * @param int    $expires     Expiration timestamp (0 = never).
     * @return void
     */
    public static function add($id, $message, $type = 'info', $dismissible = true, $expires = 0) {
        $notices = get_option('abc_admin_notices', []);

        $notices[$id] = [
            'message' => $message,
            'type' => $type,
            'dismissible' => $dismissible,
            'expires' => $expires,
            'created' => time(),
        ];

        update_option('abc_admin_notices', $notices);
    }

    /**
     * Remove a notice
     *
     * @since 2.0.0
     * @param string $id Notice ID.
     * @return void
     */
    public static function remove($id) {
        $notices = get_option('abc_admin_notices', []);
        unset($notices[$id]);
        update_option('abc_admin_notices', $notices);
    }

    /**
     * Check if notice is dismissed
     *
     * @since 2.0.0
     * @param string $id Notice ID.
     * @return bool
     */
    private function is_notice_dismissed($id) {
        $dismissed = get_user_meta(get_current_user_id(), 'abc_dismissed_notices', true);
        return is_array($dismissed) && in_array($id, $dismissed);
    }

    /**
     * Handle notice dismissal
     *
     * @since 2.0.0
     * @return void
     */
    public function dismiss_notice_handler() {
        if (!isset($_GET['abc_dismiss_notice'])) {
            return;
        }

        $notice_id = sanitize_key($_GET['abc_dismiss_notice']);
        $dismissed = get_user_meta(get_current_user_id(), 'abc_dismissed_notices', true);
        
        if (!is_array($dismissed)) {
            $dismissed = [];
        }

        if (!in_array($notice_id, $dismissed)) {
            $dismissed[] = $notice_id;
            update_user_meta(get_current_user_id(), 'abc_dismissed_notices', $dismissed);
        }

        // Redirect to clean URL
        wp_safe_redirect(remove_query_arg('abc_dismiss_notice'));
        exit;
    }

    /**
     * Check system health and display warnings
     *
     * @since 2.0.0
     * @return void
     */
    public function check_system_health() {
        // Check for API keys
        $this->check_api_keys();

        // Check for active campaigns without API keys
        $this->check_campaigns_config();

        // Check for rate limit warnings
        $this->check_rate_limits();

        // Check for queue issues
        $this->check_queue_health();

        // Check for database issues
        $this->check_database_health();

        // Check for plugin updates
        $this->check_plugin_updates();
    }

    /**
     * Check if API keys are configured
     *
     * @since 2.0.0
     * @return void
     */
    private function check_api_keys() {
        global $wpdb;

        $key_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}abc_api_keys WHERE status = 'active'"
        );

        if ($key_count == 0) {
            self::add(
                'no_api_keys',
                sprintf(
                    __('No API keys configured. <a href="%s">Add API keys</a> to start generating content.', 'autoblogcraft'),
                    admin_url('admin.php?page=autoblogcraft-api-keys')
                ),
                'warning',
                true
            );
        } else {
            self::remove('no_api_keys');
        }
    }

    /**
     * Check campaigns configuration
     *
     * @since 2.0.0
     * @return void
     */
    private function check_campaigns_config() {
        global $wpdb;

        // Check for active campaigns without AI config
        $campaigns = get_posts([
            'post_type' => 'abc_campaign',
            'post_status' => 'publish',
            'meta_query' => [
                ['key' => '_campaign_status', 'value' => 'active'],
            ],
            'fields' => 'ids',
        ]);

        $missing_config = 0;
        foreach ($campaigns as $campaign_id) {
            $has_config = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}abc_campaign_ai_config WHERE campaign_id = %d",
                $campaign_id
            ));

            if (!$has_config) {
                $missing_config++;
            }
        }

        if ($missing_config > 0) {
            self::add(
                'campaigns_missing_config',
                sprintf(
                    _n(
                        '%d active campaign is missing AI configuration.',
                        '%d active campaigns are missing AI configuration.',
                        $missing_config,
                        'autoblogcraft'
                    ),
                    $missing_config
                ),
                'warning',
                true
            );
        } else {
            self::remove('campaigns_missing_config');
        }
    }

    /**
     * Check for rate limit warnings
     *
     * @since 2.0.0
     * @return void
     */
    private function check_rate_limits() {
        global $wpdb;

        $rate_limited = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}abc_api_keys WHERE status = 'rate_limited'"
        );

        if ($rate_limited > 0) {
            self::add(
                'rate_limits',
                sprintf(
                    _n(
                        '%d API key is rate-limited. Processing may be delayed.',
                        '%d API keys are rate-limited. Processing may be delayed.',
                        $rate_limited,
                        'autoblogcraft'
                    ),
                    $rate_limited
                ),
                'warning',
                true,
                time() + 3600 // Expire in 1 hour
            );
        } else {
            self::remove('rate_limits');
        }
    }

    /**
     * Check queue health
     *
     * @since 2.0.0
     * @return void
     */
    private function check_queue_health() {
        global $wpdb;

        $failed_items = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}abc_discovery_queue 
             WHERE status = 'failed' AND retry_count >= 3"
        );

        if ($failed_items > 10) {
            self::add(
                'queue_failures',
                sprintf(
                    __('%d queue items have failed repeatedly. <a href="%s">Check logs</a> for details.', 'autoblogcraft'),
                    $failed_items,
                    admin_url('admin.php?page=autoblogcraft-logs&level=error')
                ),
                'error',
                true
            );
        } else {
            self::remove('queue_failures');
        }
    }

    /**
     * Check database health
     *
     * @since 2.0.0
     * @return void
     */
    private function check_database_health() {
        // Ensure Schema_Validator is loaded
        if (!class_exists('AutoBlogCraft\Database\Schema_Validator')) {
            require_once ABC_PLUGIN_DIR . 'includes/database/class-schema-validator.php';
        }
        
        $validator = new \AutoBlogCraft\Database\Schema_Validator();
        $report = $validator->get_health_report();

        if ($report['status'] !== 'healthy') {
            self::add(
                'database_issues',
                sprintf(
                    __('Database schema issues detected (%d errors). <a href="%s">View details</a>', 'autoblogcraft'),
                    $report['total_errors'],
                    admin_url('admin.php?page=autoblogcraft-settings&tab=system')
                ),
                'error',
                false
            );
        } else {
            self::remove('database_issues');
        }
    }

    /**
     * Check for plugin updates
     *
     * @since 2.0.0
     * @return void
     */
    private function check_plugin_updates() {
        $current_version = ABC_VERSION;
        $latest_version = get_option('abc_latest_version');

        if ($latest_version && version_compare($current_version, $latest_version, '<')) {
            self::add(
                'plugin_update',
                sprintf(
                    __('AutoBlogCraft AI %s is available. <a href="%s">Update now</a>', 'autoblogcraft'),
                    $latest_version,
                    admin_url('plugins.php')
                ),
                'info',
                true
            );
        }
    }

    /**
     * Check if current page is an ABC page
     *
     * @since 2.0.0
     * @return bool
     */
    private function is_abc_page() {
        $screen = get_current_screen();
        
        if (!$screen) {
            return false;
        }

        return strpos($screen->id, 'autoblogcraft') !== false;
    }

    /**
     * Display flash message
     *
     * One-time notice that expires immediately after display.
     *
     * @since 2.0.0
     * @param string $message Notice message.
     * @param string $type    Notice type.
     * @return void
     */
    public static function flash($message, $type = 'success') {
        $id = 'flash_' . md5($message . time());
        self::add($id, $message, $type, true, time() + 60); // 1 minute expiry
    }
}
