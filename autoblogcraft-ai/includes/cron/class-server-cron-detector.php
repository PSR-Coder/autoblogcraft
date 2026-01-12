<?php
/**
 * Server Cron Detector
 *
 * Detects whether server-level cron (real cron) or WP-Cron is being used.
 * Provides recommendations for optimal cron configuration.
 *
 * @package AutoBlogCraft\Cron
 * @since 2.0.0
 */

namespace AutoBlogCraft\Cron;

use AutoBlogCraft\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Server Cron Detector class
 *
 * Responsibilities:
 * - Detect server cron vs WP-Cron
 * - Check cron execution reliability
 * - Provide setup recommendations
 * - Monitor cron job health
 * - Suggest optimizations
 *
 * @since 2.0.0
 */
class Server_Cron_Detector {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Option key for cron type detection
     *
     * @var string
     */
    private $option_key = 'abc_cron_type';

    /**
     * Option key for last cron execution
     *
     * @var string
     */
    private $last_run_key = 'abc_cron_last_run';

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->logger = Logger::instance();
    }

    /**
     * Check if server cron is active
     *
     * @since 2.0.0
     * @return bool True if server cron is detected, false if WP-Cron.
     */
    public function is_server_cron_active() {
        // Check if DISABLE_WP_CRON is defined
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $this->logger->debug('Server cron detected: DISABLE_WP_CRON is true');
            update_option($this->option_key, 'server');
            return true;
        }

        // Check execution pattern
        $is_server_cron = $this->detect_by_execution_pattern();

        // Cache result
        update_option($this->option_key, $is_server_cron ? 'server' : 'wp-cron');

        return $is_server_cron;
    }

    /**
     * Detect cron type by execution pattern
     *
     * @since 2.0.0
     * @return bool True if server cron detected.
     */
    private function detect_by_execution_pattern() {
        // Get last 10 cron executions
        $executions = get_option('abc_cron_executions', []);

        if (count($executions) < 5) {
            // Not enough data, assume WP-Cron
            return false;
        }

        // Check if executions happen at regular intervals
        $intervals = [];
        for ($i = 1; $i < count($executions); $i++) {
            $intervals[] = $executions[$i] - $executions[$i - 1];
        }

        // Calculate standard deviation
        $avg_interval = array_sum($intervals) / count($intervals);
        $variance = 0;
        foreach ($intervals as $interval) {
            $variance += pow($interval - $avg_interval, 2);
        }
        $std_dev = sqrt($variance / count($intervals));

        // Server cron has more consistent intervals (lower std deviation)
        // If std dev is less than 10% of average, likely server cron
        $consistency_threshold = $avg_interval * 0.1;
        
        if ($std_dev < $consistency_threshold) {
            $this->logger->debug('Server cron detected by execution pattern', [
                'avg_interval' => $avg_interval,
                'std_dev' => $std_dev,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Record cron execution
     *
     * @since 2.0.0
     * @return void
     */
    public function record_execution() {
        $executions = get_option('abc_cron_executions', []);
        $executions[] = time();

        // Keep only last 20 executions
        if (count($executions) > 20) {
            $executions = array_slice($executions, -20);
        }

        update_option('abc_cron_executions', $executions);
        update_option($this->last_run_key, time());
    }

    /**
     * Get cron type (server or wp-cron)
     *
     * @since 2.0.0
     * @return string 'server', 'wp-cron', or 'unknown'.
     */
    public function get_cron_type() {
        $type = get_option($this->option_key, 'unknown');

        if ($type === 'unknown') {
            // Run detection
            $this->is_server_cron_active();
            $type = get_option($this->option_key, 'wp-cron');
        }

        return $type;
    }

    /**
     * Get cron health status
     *
     * @since 2.0.0
     * @return array Health status information.
     */
    public function get_health_status() {
        $cron_type = $this->get_cron_type();
        $last_run = get_option($this->last_run_key, 0);
        $time_since_last_run = time() - $last_run;

        // Determine health
        $health = 'good';
        $message = 'Cron is working properly.';

        if ($last_run === 0) {
            $health = 'unknown';
            $message = 'No cron execution recorded yet.';
        } elseif ($time_since_last_run > 3600) { // 1 hour
            $health = 'critical';
            $message = 'Cron has not run in over an hour.';
        } elseif ($time_since_last_run > 900) { // 15 minutes
            $health = 'warning';
            $message = 'Cron execution may be delayed.';
        }

        return [
            'cron_type' => $cron_type,
            'health' => $health,
            'message' => $message,
            'last_run' => $last_run,
            'time_since_last_run' => $time_since_last_run,
            'last_run_human' => $last_run > 0 ? human_time_diff($last_run) . ' ago' : 'Never',
            'is_server_cron' => $cron_type === 'server',
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
        ];
    }

    /**
     * Recommend cron setup
     *
     * @since 2.0.0
     * @return array Setup recommendations.
     */
    public function recommend_setup() {
        $status = $this->get_health_status();
        $recommendations = [];

        if ($status['is_server_cron']) {
            $recommendations[] = [
                'type' => 'success',
                'title' => 'Server Cron Detected',
                'message' => 'You are using server-level cron, which is the recommended setup for reliable scheduling.',
            ];
        } else {
            $recommendations[] = [
                'type' => 'info',
                'title' => 'WP-Cron in Use',
                'message' => 'You are using WP-Cron. For better reliability, consider setting up server-level cron.',
                'action' => 'setup_server_cron',
            ];
        }

        // Check if WP-Cron is disabled but no executions
        if ($status['wp_cron_disabled'] && $status['last_run'] === 0) {
            $recommendations[] = [
                'type' => 'critical',
                'title' => 'Cron Not Running',
                'message' => 'WP-Cron is disabled but no cron executions detected. Please set up server cron.',
                'action' => 'fix_cron_setup',
            ];
        }

        // Check execution frequency
        if ($status['time_since_last_run'] > 3600) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Infrequent Cron Execution',
                'message' => 'Cron is not running frequently enough. Check your cron configuration.',
                'action' => 'check_cron_config',
            ];
        }

        return $recommendations;
    }

    /**
     * Get server cron setup instructions
     *
     * @since 2.0.0
     * @return string Setup instructions HTML.
     */
    public function get_setup_instructions() {
        $cron_url = site_url('wp-cron.php');
        
        ob_start();
        ?>
        <div class="abc-cron-setup-instructions">
            <h3>Setting Up Server Cron</h3>
            
            <div class="abc-instruction-step">
                <h4>Step 1: Disable WP-Cron</h4>
                <p>Add this line to your <code>wp-config.php</code> file:</p>
                <pre><code>define('DISABLE_WP_CRON', true);</code></pre>
            </div>

            <div class="abc-instruction-step">
                <h4>Step 2: Set Up Server Cron Job</h4>
                <p>Add this cron job to your server (via cPanel, SSH, or hosting control panel):</p>
                <pre><code>*/5 * * * * wget -q -O - <?php echo esc_url($cron_url); ?> &>/dev/null</code></pre>
                <p>or using curl:</p>
                <pre><code>*/5 * * * * curl -s <?php echo esc_url($cron_url); ?> &>/dev/null</code></pre>
                <p><em>This runs WordPress cron every 5 minutes.</em></p>
            </div>

            <div class="abc-instruction-step">
                <h4>Step 3: Verify</h4>
                <p>After setting up, wait a few minutes and refresh this page to verify the cron is working.</p>
            </div>

            <div class="abc-instruction-note">
                <strong>Note:</strong> If you're using managed WordPress hosting (WP Engine, Kinsta, etc.), 
                they may already have server cron configured. Check with your hosting provider.
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Test cron execution
     *
     * @since 2.0.0
     * @return array Test results.
     */
    public function test_execution() {
        $test_hook = 'abc_cron_test_' . time();
        
        // Schedule a test event
        wp_schedule_single_event(time() + 60, $test_hook);

        // Record test
        update_option('abc_cron_test_scheduled', time());

        return [
            'scheduled' => true,
            'hook' => $test_hook,
            'message' => 'Test cron event scheduled. Check back in 2 minutes to verify execution.',
        ];
    }

    /**
     * Get cron statistics
     *
     * @since 2.0.0
     * @return array Cron statistics.
     */
    public function get_statistics() {
        $executions = get_option('abc_cron_executions', []);
        $status = $this->get_health_status();

        $stats = [
            'total_executions' => count($executions),
            'cron_type' => $status['cron_type'],
            'last_run' => $status['last_run_human'],
            'health' => $status['health'],
        ];

        if (count($executions) >= 2) {
            // Calculate average interval
            $intervals = [];
            for ($i = 1; $i < count($executions); $i++) {
                $intervals[] = $executions[$i] - $executions[$i - 1];
            }
            
            $avg_interval = array_sum($intervals) / count($intervals);
            $stats['avg_interval'] = round($avg_interval / 60, 1) . ' minutes';
            
            // Reliability score
            $expected_interval = 300; // 5 minutes
            $deviation = abs($avg_interval - $expected_interval) / $expected_interval;
            $reliability = max(0, 100 - ($deviation * 100));
            $stats['reliability'] = round($reliability, 1) . '%';
        }

        return $stats;
    }
}
