<?php
/**
 * Module Base Class
 *
 * Abstract base class for all AutoBlogCraft modules.
 * Standardizes initialization patterns, dependency injection, and error handling.
 *
 * @package AutoBlogCraft\Core
 * @since 2.0.0
 */

namespace AutoBlogCraft\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Module_Base abstract class
 *
 * Provides:
 * - Standardized initialization pattern
 * - Dependency injection support
 * - Logger integration
 * - Error handling
 * - Module lifecycle hooks
 *
 * Usage:
 * ```php
 * class My_Module extends Module_Base {
 *     protected function get_module_name() {
 *         return 'my_module';
 *     }
 *     
 *     protected function init() {
 *         // Module-specific initialization
 *     }
 * }
 * ```
 *
 * @since 2.0.0
 */
abstract class Module_Base {

    /**
     * Logger instance
     *
     * @var Logger
     */
    protected $logger;

    /**
     * Module name (for logging and identification)
     *
     * @var string
     */
    protected $module_name;

    /**
     * Whether module is initialized
     *
     * @var bool
     */
    protected $initialized = false;

    /**
     * Module dependencies
     *
     * @var array
     */
    protected $dependencies = [];

    /**
     * Module configuration
     *
     * @var array
     */
    protected $config = [];

    /**
     * Constructor
     *
     * @since 2.0.0
     * @param Logger|null $logger Optional logger instance (dependency injection).
     * @param array $config Optional configuration array.
     */
    public function __construct($logger = null, $config = []) {
        $this->logger = $logger ?? Logger::instance();
        $this->config = $config;
        $this->module_name = $this->get_module_name();
        
        $this->log_debug('Module constructor called');
        
        // Initialize module
        $this->initialize();
    }

    /**
     * Get module name
     *
     * Must be implemented by child classes.
     *
     * @since 2.0.0
     * @return string Module name (e.g., 'seo_manager', 'translation_manager').
     */
    abstract protected function get_module_name();

    /**
     * Initialize module
     *
     * Can be overridden by child classes for custom initialization logic.
     *
     * @since 2.0.0
     * @return void
     */
    protected function init() {
        // Override in child classes
    }

    /**
     * Initialize the module
     *
     * Template method that coordinates initialization steps.
     *
     * @since 2.0.0
     * @return void
     */
    final private function initialize() {
        if ($this->initialized) {
            $this->log_warning('Module already initialized');
            return;
        }

        try {
            // Check dependencies
            if (!$this->check_dependencies()) {
                $this->log_error('Module dependencies not met');
                return;
            }

            // Run pre-init hook
            $this->before_init();

            // Call child class initialization
            $this->init();

            // Run post-init hook
            $this->after_init();

            $this->initialized = true;
            $this->log_info('Module initialized successfully');

        } catch (\Exception $e) {
            $this->log_error('Module initialization failed: ' . $e->getMessage());
            $this->handle_error($e);
        }
    }

    /**
     * Check module dependencies
     *
     * Override in child classes to check for required plugins, PHP extensions, etc.
     *
     * @since 2.0.0
     * @return bool True if all dependencies are met.
     */
    protected function check_dependencies() {
        foreach ($this->dependencies as $dependency => $checker) {
            if (is_callable($checker) && !call_user_func($checker)) {
                $this->log_error("Dependency not met: {$dependency}");
                return false;
            }
        }
        return true;
    }

    /**
     * Hook called before initialization
     *
     * Override in child classes for pre-initialization tasks.
     *
     * @since 2.0.0
     * @return void
     */
    protected function before_init() {
        // Override in child classes
    }

    /**
     * Hook called after initialization
     *
     * Override in child classes for post-initialization tasks.
     *
     * @since 2.0.0
     * @return void
     */
    protected function after_init() {
        // Override in child classes
    }

    /**
     * Check if module is initialized
     *
     * @since 2.0.0
     * @return bool True if initialized.
     */
    public function is_initialized() {
        return $this->initialized;
    }

    /**
     * Get module name
     *
     * @since 2.0.0
     * @return string Module name.
     */
    public function get_name() {
        return $this->module_name;
    }

    /**
     * Get configuration value
     *
     * @since 2.0.0
     * @param string $key Configuration key.
     * @param mixed $default Default value if key not found.
     * @return mixed Configuration value.
     */
    protected function get_config($key, $default = null) {
        return $this->config[$key] ?? $default;
    }

    /**
     * Set configuration value
     *
     * @since 2.0.0
     * @param string $key Configuration key.
     * @param mixed $value Configuration value.
     * @return void
     */
    protected function set_config($key, $value) {
        $this->config[$key] = $value;
    }

    // ========================================
    // Logging Methods
    // ========================================

    /**
     * Log debug message
     *
     * @since 2.0.0
     * @param string $message Log message.
     * @param array $context Additional context data.
     * @return void
     */
    protected function log_debug($message, $context = []) {
        $this->logger->debug(
            null,
            $this->module_name,
            $message,
            $context
        );
    }

    /**
     * Log info message
     *
     * @since 2.0.0
     * @param string $message Log message.
     * @param array $context Additional context data.
     * @return void
     */
    protected function log_info($message, $context = []) {
        $this->logger->info(
            null,
            $this->module_name,
            $message,
            $context
        );
    }

    /**
     * Log warning message
     *
     * @since 2.0.0
     * @param string $message Log message.
     * @param array $context Additional context data.
     * @return void
     */
    protected function log_warning($message, $context = []) {
        $this->logger->warning(
            null,
            $this->module_name,
            $message,
            $context
        );
    }

    /**
     * Log error message
     *
     * @since 2.0.0
     * @param string $message Log message.
     * @param array $context Additional context data.
     * @return void
     */
    protected function log_error($message, $context = []) {
        $this->logger->error(
            null,
            $this->module_name,
            $message,
            $context
        );
    }

    // ========================================
    // Error Handling
    // ========================================

    /**
     * Handle error
     *
     * Override in child classes for custom error handling.
     *
     * @since 2.0.0
     * @param \Exception $exception Exception to handle.
     * @return void
     */
    protected function handle_error(\Exception $exception) {
        $this->log_error(
            sprintf(
                'Exception in %s: %s (Line: %d, File: %s)',
                $this->module_name,
                $exception->getMessage(),
                $exception->getLine(),
                $exception->getFile()
            ),
            [
                'trace' => $exception->getTraceAsString(),
            ]
        );
    }

    /**
     * Safely execute a callback with error handling
     *
     * @since 2.0.0
     * @param callable $callback Callback to execute.
     * @param array $args Arguments to pass to callback.
     * @param mixed $default Default value to return on error.
     * @return mixed Callback result or default value.
     */
    protected function safe_execute($callback, $args = [], $default = null) {
        try {
            return call_user_func_array($callback, $args);
        } catch (\Exception $e) {
            $this->handle_error($e);
            return $default;
        }
    }

    // ========================================
    // Dependency Injection Helpers
    // ========================================

    /**
     * Set logger instance
     *
     * Allows injecting logger for testing.
     *
     * @since 2.0.0
     * @param Logger $logger Logger instance.
     * @return void
     */
    public function set_logger(Logger $logger) {
        $this->logger = $logger;
    }

    /**
     * Get logger instance
     *
     * @since 2.0.0
     * @return Logger Logger instance.
     */
    public function get_logger() {
        return $this->logger;
    }

    /**
     * Add dependency check
     *
     * @since 2.0.0
     * @param string $name Dependency name.
     * @param callable $checker Callable that returns true if dependency is met.
     * @return void
     */
    protected function add_dependency($name, $checker) {
        $this->dependencies[$name] = $checker;
    }

    // ========================================
    // Utility Methods
    // ========================================

    /**
     * Get option with module prefix
     *
     * @since 2.0.0
     * @param string $key Option key (will be prefixed with abc_{module_name}_).
     * @param mixed $default Default value.
     * @return mixed Option value.
     */
    protected function get_option($key, $default = null) {
        $option_key = "abc_{$this->module_name}_{$key}";
        return get_option($option_key, $default);
    }

    /**
     * Update option with module prefix
     *
     * @since 2.0.0
     * @param string $key Option key (will be prefixed with abc_{module_name}_).
     * @param mixed $value Option value.
     * @return bool True on success.
     */
    protected function update_option($key, $value) {
        $option_key = "abc_{$this->module_name}_{$key}";
        return update_option($option_key, $value);
    }

    /**
     * Delete option with module prefix
     *
     * @since 2.0.0
     * @param string $key Option key (will be prefixed with abc_{module_name}_).
     * @return bool True on success.
     */
    protected function delete_option($key) {
        $option_key = "abc_{$this->module_name}_{$key}";
        return delete_option($option_key);
    }

    /**
     * Get module version
     *
     * Override in child classes to provide module-specific version.
     *
     * @since 2.0.0
     * @return string Module version.
     */
    public function get_version() {
        return '2.0.0';
    }

    /**
     * Get module status
     *
     * @since 2.0.0
     * @return array Module status information.
     */
    public function get_status() {
        return [
            'name' => $this->module_name,
            'initialized' => $this->initialized,
            'version' => $this->get_version(),
            'dependencies_met' => $this->check_dependencies(),
        ];
    }

    /**
     * Reset module state
     *
     * Override in child classes to implement module-specific reset logic.
     *
     * @since 2.0.0
     * @return void
     */
    public function reset() {
        $this->initialized = false;
        $this->log_info('Module reset');
    }
}
