<?php
namespace mt2Tech\MarkupByAttribute;

class Autoloader {
	public static function register($prepend = false) {
		spl_autoload_register([new self, 'autoload'], true, $prepend);
	}

	public static function autoload($class) {
		// Base directory for your plugin classes
		$base_dir = dirname(__FILE__) . '/src/';

		// Check if the class uses the namespace prefix
		$len = strlen(__NAMESPACE__);
		if (strncmp(__NAMESPACE__, $class, $len) !== 0) {
			return; // Not our class, let other autoloaders handle it
		}

		// Get the relative class name
		$relative_class = substr($class, $len);

		// Replace namespace separators with directory separators in the relative class name
		// and convert to lowercase for file system compatibility
		$file = $base_dir . str_replace('\\', '/', strtolower($relative_class)) . '.php';

		// If the file exists, require it
		if (file_exists($file)) {
			require $file;
		}
	}
}