<?php
/**
 * PSR-4 Autoloader
 *
 * @package AutoBlogCraft\Core
 * @since 2.0.0
 */

namespace AutoBlogCraft\Core;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Autoloader
 *
 * PSR-4 compliant autoloader for AutoBlogCraft plugin.
 * Maps: AutoBlogCraft\ -> includes/
 * File naming: class-foo-bar.php <-> Foo_Bar class
 */
class Autoloader
{

    /**
     * Namespace prefix
     *
     * @var string
     */
    private static $prefix = 'AutoBlogCraft\\';

    /**
     * Base directory for the namespace prefix
     *
     * @var string
     */
    private static $base_dir;

    /**
     * Register the autoloader
     */
    public static function register()
    {
        self::$base_dir = ABC_PLUGIN_DIR . 'includes/';

        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * Autoload classes
     *
     * @param string $class The fully-qualified class name.
     * @return void
     */
    public static function autoload($class)
    {
        // Check if the class uses the namespace prefix
        $len = strlen(self::$prefix);
        if (strncmp(self::$prefix, $class, $len) !== 0) {
            return;
        }

        // Get the relative class name
        $relative_class = substr($class, $len);

        // Replace namespace separators with directory separators
        // Convert to lowercase and replace underscores with hyphens for file naming
        $file = self::$base_dir . strtolower(str_replace('\\', '/', $relative_class));

        // Convert class name to file name (Class_Name -> class-class-name.php)
        $parts = explode('/', $file);
        $class_name = array_pop($parts);

        // For directory parts, just ensure they are lowercase (already done above)
        // But we need to handle the filename specifically with convert_class_to_filename method logic for the class part
        // The previous line lowercased everything, which is correct for directories in this project structure
        // But let's be precise.

        // Re-implementing logic to be safer:
        $path_parts = explode('\\', $relative_class);
        $class_name = array_pop($path_parts);

        // Lowercase directories
        $path_parts = array_map('strtolower', $path_parts);

        // Build path
        $path = implode('/', $path_parts);

        $class_file = 'class-' . self::convert_class_to_filename($class_name) . '.php';

        $file = self::$base_dir . ($path ? $path . '/' : '') . $class_file;

        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
        }
    }

    /**
     * Convert class name to file name
     *
     * Class_Name -> class-name
     * ClassName -> class-name
     *
     * @param string $class_name
     * @return string
     */
    private static function convert_class_to_filename($class_name)
    {
        // Convert underscores to hyphens
        $filename = str_replace('_', '-', $class_name);

        // Convert PascalCase to kebab-case
        $filename = preg_replace('/([a-z])([A-Z])/', '$1-$2', $filename);

        // Convert to lowercase
        return strtolower($filename);
    }
}
