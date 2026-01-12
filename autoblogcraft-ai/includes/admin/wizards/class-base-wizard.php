<?php
/**
 * Abstract Wizard Base Class
 *
 * Base class for all campaign wizard types providing shared functionality
 * and template loading capabilities.
 *
 * @package AutoBlogCraft\Admin\Wizards
 * @since 2.0.0
 */

namespace AutoBlogCraft\Admin\Wizards;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Wizard_Base abstract class
 *
 * Provides common functionality for all campaign wizards including:
 * - Template loading system
 * - Step management
 * - Data validation
 * - Campaign meta management
 *
 * @since 2.0.0
 */
abstract class Wizard_Base {

    /**
     * Current step number
     *
     * @var int
     */
    protected $current_step = 1;

    /**
     * Campaign ID (0 for new campaigns)
     *
     * @var int
     */
    protected $campaign_id = 0;

    /**
     * Template directory path
     *
     * @var string
     */
    protected $template_dir;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->template_dir = plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'templates/wizards/';
    }

    /**
     * Get campaign type
     *
     * @since 2.0.0
     * @return string Campaign type (website, youtube, amazon, news)
     */
    abstract protected function get_campaign_type();

    /**
     * Get wizard steps configuration
     *
     * @since 2.0.0
     * @return array Array of step configurations
     */
    abstract public function get_steps();

    /**
     * Load a wizard template
     *
     * Loads template files from templates/wizards/ directory.
     * Passes variables to template via extract().
     *
     * @since 2.0.0
     * @param string $template Template name (e.g., 'step-sources', 'wizard-header').
     * @param array  $args     Variables to pass to template.
     * @return void
     */
    protected function load_template($template, $args = []) {
        $campaign_type = $this->get_campaign_type();
        
        // Try campaign-specific template first
        $template_path = $this->template_dir . $campaign_type . '/' . $template . '.php';
        
        // Fall back to shared template
        if (!file_exists($template_path)) {
            $template_path = $this->template_dir . $template . '.php';
        }

        // Load template if exists
        if (file_exists($template_path)) {
            // Extract variables for use in template
            extract($args);
            
            // Include template
            include $template_path;
        } else {
            // Log error if template not found
            error_log(sprintf(
                'AutoBlogCraft: Template not found: %s (tried %s and shared)',
                $template,
                $campaign_type . '/' . $template
            ));
        }
    }

    /**
     * Render wizard header
     *
     * @since 2.0.0
     * @param string $title Wizard title.
     * @return void
     */
    protected function render_header($title) {
        $this->load_template('wizard-header', [
            'title' => $title,
            'campaign_type' => $this->get_campaign_type(),
        ]);
    }

    /**
     * Render wizard progress bar
     *
     * @since 2.0.0
     * @return void
     */
    protected function render_progress() {
        $steps = $this->get_steps();
        
        $this->load_template('wizard-progress', [
            'steps' => $steps,
            'current_step' => $this->current_step,
        ]);
    }

    /**
     * Render wizard navigation
     *
     * @since 2.0.0
     * @param string $page_slug Admin page slug for URL generation.
     * @return void
     */
    protected function render_navigation($page_slug = 'abc-campaign-wizard') {
        $steps = $this->get_steps();
        
        $this->load_template('wizard-navigation', [
            'current_step' => $this->current_step,
            'total_steps' => count($steps),
            'campaign_id' => $this->campaign_id,
            'page_slug' => $page_slug,
        ]);
    }

    /**
     * Get campaign meta value
     *
     * @since 2.0.0
     * @param string $key Meta key.
     * @param mixed  $default Default value if meta doesn't exist.
     * @return mixed Meta value or default.
     */
    protected function get_campaign_meta($key, $default = '') {
        if (!$this->campaign_id) {
            return $default;
        }

        $value = get_post_meta($this->campaign_id, $key, true);
        return $value !== '' ? $value : $default;
    }

    /**
     * Save campaign meta value
     *
     * @since 2.0.0
     * @param string $key Meta key.
     * @param mixed  $value Meta value.
     * @return bool True on success, false on failure.
     */
    protected function save_campaign_meta($key, $value) {
        if (!$this->campaign_id) {
            return false;
        }

        return update_post_meta($this->campaign_id, $key, $value);
    }

    /**
     * Save multiple campaign meta values
     *
     * @since 2.0.0
     * @param array $data Associative array of meta key => value pairs.
     * @return void
     */
    protected function save_campaign_meta_batch($data) {
        if (!$this->campaign_id || !is_array($data)) {
            return;
        }

        foreach ($data as $key => $value) {
            update_post_meta($this->campaign_id, $key, $value);
        }
    }

    /**
     * Set current step
     *
     * @since 2.0.0
     * @param int $step Step number.
     * @return void
     */
    public function set_current_step($step) {
        $this->current_step = absint($step);
    }

    /**
     * Set campaign ID
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return void
     */
    public function set_campaign_id($campaign_id) {
        $this->campaign_id = absint($campaign_id);
    }

    /**
     * Get current step
     *
     * @since 2.0.0
     * @return int Current step number.
     */
    public function get_current_step() {
        return $this->current_step;
    }

    /**
     * Get campaign ID
     *
     * @since 2.0.0
     * @return int Campaign ID.
     */
    public function get_campaign_id() {
        return $this->campaign_id;
    }

    /**
     * Sanitize text field
     *
     * @since 2.0.0
     * @param string $value Value to sanitize.
     * @return string Sanitized value.
     */
    protected function sanitize_text($value) {
        return sanitize_text_field($value);
    }

    /**
     * Sanitize URL
     *
     * @since 2.0.0
     * @param string $value URL to sanitize.
     * @return string Sanitized URL.
     */
    protected function sanitize_url($value) {
        return esc_url_raw($value);
    }

    /**
     * Sanitize array of text fields
     *
     * @since 2.0.0
     * @param array $array Array to sanitize.
     * @return array Sanitized array.
     */
    protected function sanitize_text_array($array) {
        if (!is_array($array)) {
            return [];
        }

        return array_map('sanitize_text_field', $array);
    }

    /**
     * Sanitize array of URLs
     *
     * @since 2.0.0
     * @param array $array Array of URLs to sanitize.
     * @return array Sanitized array.
     */
    protected function sanitize_url_array($array) {
        if (!is_array($array)) {
            return [];
        }

        return array_map('esc_url_raw', array_filter($array));
    }
}
