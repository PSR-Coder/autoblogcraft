<?php
/**
 * Security Auditor
 *
 * Performs security checks and vulnerability scanning.
 *
 * @package AutoBlogCraft\Security
 * @since 2.0.0
 */

namespace AutoBlogCraft\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Auditor class
 *
 * Responsibilities:
 * - SQL injection detection
 * - XSS vulnerability scanning
 * - CSRF token validation
 * - Capability checks
 * - Data sanitization verification
 * - Nonce validation
 *
 * @since 2.0.0
 */
class Security_Auditor {

    /**
     * Security issues found
     *
     * @var array
     */
    private $issues = [];

    /**
     * Run complete security audit
     *
     * @since 2.0.0
     * @return array Audit results.
     */
    public function run_audit() {
        $this->issues = [];

        // Check database queries
        $this->audit_database_queries();

        // Check AJAX handlers
        $this->audit_ajax_handlers();

        // Check admin pages
        $this->audit_admin_pages();

        // Check user capabilities
        $this->audit_capabilities();

        // Check data sanitization
        $this->audit_sanitization();

        // Check nonce validation
        $this->audit_nonces();

        // Check file permissions
        $this->audit_file_permissions();

        // Check API key encryption
        $this->audit_encryption();

        return [
            'status' => empty($this->issues) ? 'pass' : 'fail',
            'issues' => $this->issues,
            'timestamp' => current_time('mysql'),
        ];
    }

    /**
     * Audit database queries for SQL injection
     *
     * @since 2.0.0
     */
    private function audit_database_queries() {
        // Check for direct SQL without prepare()
        $patterns = [
            '/\$wpdb->query\s*\(\s*[\'"](?!SELECT|INSERT|UPDATE|DELETE)/',
            '/\$wpdb->get_results\s*\(\s*[\'"](?!SELECT)/',
            '/\$wpdb->get_var\s*\(\s*[\'"](?!SELECT)/',
        ];

        foreach ($patterns as $pattern) {
            if ($this->scan_codebase($pattern)) {
                $this->add_issue(
                    'sql_injection',
                    'High',
                    'Direct SQL query without prepare() detected'
                );
            }
        }

        // Check for missing prepare() usage
        if ($this->scan_codebase('/\$wpdb->query\s*\(\s*[\'"].*\$\w+/')) {
            $this->add_issue(
                'sql_injection',
                'High',
                'Variable interpolation in SQL query without prepare()'
            );
        }
    }

    /**
     * Audit AJAX handlers
     *
     * @since 2.0.0
     */
    private function audit_ajax_handlers() {
        // Check for missing nonce verification
        if ($this->scan_codebase('/wp_ajax_.*function.*\{(?!.*check_ajax_referer)/s')) {
            $this->add_issue(
                'csrf',
                'High',
                'AJAX handler missing nonce verification'
            );
        }

        // Check for missing capability checks
        if ($this->scan_codebase('/wp_ajax_.*function.*\{(?!.*current_user_can)/s')) {
            $this->add_issue(
                'authorization',
                'Medium',
                'AJAX handler missing capability check'
            );
        }
    }

    /**
     * Audit admin pages
     *
     * @since 2.0.0
     */
    private function audit_admin_pages() {
        // Check for missing capability checks on menu pages
        if ($this->scan_codebase('/add_menu_page\s*\([^)]*[\'"](?!manage_options|edit_posts)/')) {
            $this->add_issue(
                'authorization',
                'Medium',
                'Admin menu page with weak capability requirement'
            );
        }

        // Check for unescaped output
        $patterns = [
            '/echo\s+\$\w+(?!.*esc_)/i',
            '/print\s+\$\w+(?!.*esc_)/i',
        ];

        foreach ($patterns as $pattern) {
            if ($this->scan_codebase($pattern)) {
                $this->add_issue(
                    'xss',
                    'High',
                    'Unescaped output detected'
                );
            }
        }
    }

    /**
     * Audit user capability checks
     *
     * @since 2.0.0
     */
    private function audit_capabilities() {
        // Check for admin functions without capability checks
        $admin_functions = [
            'delete_campaign',
            'update_campaign',
            'create_campaign',
            'delete_api_key',
            'update_settings',
        ];

        foreach ($admin_functions as $function) {
            if ($this->function_exists_without_cap_check($function)) {
                $this->add_issue(
                    'authorization',
                    'High',
                    "Function {$function} missing capability check"
                );
            }
        }
    }

    /**
     * Audit data sanitization
     *
     * @since 2.0.0
     */
    private function audit_sanitization() {
        // Check $_POST usage without sanitization
        if ($this->scan_codebase('/\$_POST\[.*\](?!.*sanitize_)/')) {
            $this->add_issue(
                'xss',
                'High',
                '$_POST data used without sanitization'
            );
        }

        // Check $_GET usage without sanitization
        if ($this->scan_codebase('/\$_GET\[.*\](?!.*sanitize_)/')) {
            $this->add_issue(
                'xss',
                'Medium',
                '$_GET data used without sanitization'
            );
        }
    }

    /**
     * Audit nonce validation
     *
     * @since 2.0.0
     */
    private function audit_nonces() {
        // Check for form submissions without nonce
        if ($this->scan_codebase('/<form[^>]*method=[\'"]post[\'"][^>]*>(?!.*wp_nonce_field)/is')) {
            $this->add_issue(
                'csrf',
                'High',
                'Form submission without nonce field'
            );
        }
    }

    /**
     * Audit file permissions
     *
     * @since 2.0.0
     */
    private function audit_file_permissions() {
        $plugin_dir = ABC_PLUGIN_DIR;

        // Check writable directories
        $writable_dirs = [
            $plugin_dir . 'logs',
            $plugin_dir . 'cache',
        ];

        foreach ($writable_dirs as $dir) {
            if (is_dir($dir) && !is_writable($dir)) {
                $this->add_issue(
                    'filesystem',
                    'Low',
                    "Directory not writable: {$dir}"
                );
            }
        }

        // Check for overly permissive files
        $files = glob($plugin_dir . '**/*.php');
        foreach ($files as $file) {
            $perms = fileperms($file) & 0777;
            if ($perms > 0644) {
                $this->add_issue(
                    'filesystem',
                    'Medium',
                    "Overly permissive file: {$file} (" . decoct($perms) . ")"
                );
            }
        }
    }

    /**
     * Audit API key encryption
     *
     * @since 2.0.0
     */
    private function audit_encryption() {
        global $wpdb;

        // Check for unencrypted API keys
        $keys = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}abc_api_keys WHERE encrypted_key NOT LIKE '%:%'"
        );

        if (!empty($keys)) {
            $this->add_issue(
                'encryption',
                'Critical',
                'Unencrypted API keys found in database'
            );
        }

        // Check encryption method
        if (!defined('ABC_ENCRYPTION_METHOD') || ABC_ENCRYPTION_METHOD !== 'aes-256-cbc') {
            $this->add_issue(
                'encryption',
                'High',
                'Weak encryption method detected'
            );
        }
    }

    /**
     * Scan codebase for pattern
     *
     * @since 2.0.0
     * @param string $pattern Regex pattern.
     * @return bool True if pattern found.
     */
    private function scan_codebase($pattern) {
        $plugin_dir = ABC_PLUGIN_DIR . 'includes/';
        $files = glob($plugin_dir . '**/*.php');

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if function exists without capability check
     *
     * @since 2.0.0
     * @param string $function_name Function name.
     * @return bool True if exists without check.
     */
    private function function_exists_without_cap_check($function_name) {
        $plugin_dir = ABC_PLUGIN_DIR . 'includes/';
        $files = glob($plugin_dir . '**/*.php');

        foreach ($files as $file) {
            $content = file_get_contents($file);
            
            // Look for function definition
            if (preg_match("/function\s+{$function_name}\s*\([^)]*\)\s*\{([^}]+)\}/s", $content, $matches)) {
                $function_body = $matches[1];
                
                // Check if capability check exists in function body
                if (!preg_match('/current_user_can/', $function_body)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add security issue
     *
     * @since 2.0.0
     * @param string $type Issue type.
     * @param string $severity Severity level.
     * @param string $description Description.
     */
    private function add_issue($type, $severity, $description) {
        $this->issues[] = [
            'type' => $type,
            'severity' => $severity,
            'description' => $description,
            'timestamp' => current_time('mysql'),
        ];
    }

    /**
     * Get security score
     *
     * @since 2.0.0
     * @return int Score from 0-100.
     */
    public function get_security_score() {
        $critical = count(array_filter($this->issues, function($issue) {
            return $issue['severity'] === 'Critical';
        }));

        $high = count(array_filter($this->issues, function($issue) {
            return $issue['severity'] === 'High';
        }));

        $medium = count(array_filter($this->issues, function($issue) {
            return $issue['severity'] === 'Medium';
        }));

        $low = count(array_filter($this->issues, function($issue) {
            return $issue['severity'] === 'Low';
        }));

        // Calculate score (100 = perfect, 0 = critical issues)
        $score = 100;
        $score -= ($critical * 25);
        $score -= ($high * 10);
        $score -= ($medium * 5);
        $score -= ($low * 2);

        return max(0, $score);
    }

    /**
     * Generate security report
     *
     * @since 2.0.0
     * @return string HTML report.
     */
    public function generate_report() {
        $score = $this->get_security_score();
        $audit = $this->run_audit();

        $html = '<div class="abc-security-report">';
        $html .= '<h2>Security Audit Report</h2>';
        $html .= '<p><strong>Score:</strong> ' . $score . '/100</p>';
        $html .= '<p><strong>Status:</strong> ' . esc_html($audit['status']) . '</p>';
        $html .= '<p><strong>Timestamp:</strong> ' . esc_html($audit['timestamp']) . '</p>';

        if (!empty($audit['issues'])) {
            $html .= '<h3>Issues Found</h3>';
            $html .= '<table class="widefat">';
            $html .= '<thead><tr><th>Type</th><th>Severity</th><th>Description</th></tr></thead>';
            $html .= '<tbody>';

            foreach ($audit['issues'] as $issue) {
                $html .= '<tr>';
                $html .= '<td>' . esc_html($issue['type']) . '</td>';
                $html .= '<td>' . esc_html($issue['severity']) . '</td>';
                $html .= '<td>' . esc_html($issue['description']) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
        } else {
            $html .= '<p class="success">No security issues found.</p>';
        }

        $html .= '</div>';

        return $html;
    }
}
