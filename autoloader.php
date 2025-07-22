<?php
namespace mt2Tech\MarkupByAttribute;

/**
 * PSR-4 compliant autoloader for Markup-by-Attribute plugin
 * 
 * Provides automatic class loading for the plugin's namespace-organized classes.
 * Follows PSR-4 standards for mapping namespaces to file system paths.
 *
 * @package   mt2Tech\MarkupByAttribute
 * @author    Mark Tomlinson
 * @license   GPL-2.0+
 * @since     3.0.0
 */
class Autoloader {
	/**
	 * Register the autoloader with PHP's SPL autoloader stack
	 * 
	 * @since 3.0.0
	 * @param bool $prepend Whether to prepend the autoloader or append it
	 */
	public static function register(bool $prepend = false): void {
		spl_autoload_register([new self, 'autoload'], true, $prepend);
	}

	/**
	 * Autoload classes within the plugin namespace
	 * 
	 * Converts namespaced class names to file paths and includes the appropriate
	 * PHP file if it exists within the plugin's src directory structure.
	 * Handles both subdirectory classes and root-level classes like Config.
	 * 
	 * @since 2.0.0
	 * @param string $class Fully qualified class name to load
	 */
	public static function autoload(string $class): void {
		// Base directory for plugin classes (src/ subdirectory)
		$base_dir = dirname(__FILE__) . '/src/';

		// Only handle classes within our namespace to avoid conflicts
		$len = strlen(__NAMESPACE__);
		if (strncmp(__NAMESPACE__, $class, $len) !== 0) {
			return; // Not our class, let other autoloaders handle it
		}

		// Extract the part of the class name after our base namespace
		// e.g., 'mt2Tech\MarkupByAttribute\Backend\Term' becomes '\Backend\Term'
		// e.g., 'mt2Tech\MarkupByAttribute\Config' becomes '\Config'
		$relative_class = substr($class, $len);

		// Handle root-level classes (no subdirectory)
		if (strpos($relative_class, '\\') === false) {
			// Root-level class like Config
			$file = $base_dir . strtolower(ltrim($relative_class, '\\')) . '.php';
		} else {
			// Subdirectory class like Backend\Term
			$file = $base_dir . str_replace('\\', '/', strtolower($relative_class)) . '.php';
		}

		// If the file exists, require it
		if (file_exists($file)) {
			require $file;
		}
	}
}