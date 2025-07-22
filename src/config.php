<?php
namespace mt2Tech\MarkupByAttribute;

/**
 * Configuration constants for Markup-by-Attribute plugin
 * 
 * Centralizes all plugin configuration values and constants to improve
 * maintainability and provide a single source of truth for plugin settings.
 *
 * @package   mt2Tech\MarkupByAttribute
 * @author    Mark Tomlinson
 * @license   GPL-2.0+
 * @since     4.4.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit();

final class Config {
	/**
	 * Plugin metadata constants
	 */
	public const VERSION = '4.3.8';
	public const DB_VERSION = 2.2;
	public const PLUGIN_PREFIX = 'MT2MBA';
	public const TEXT_DOMAIN = 'markup-by-attribute-for-woocommerce';
	
	/**
	 * WordPress integration constants
	 */
	public const MIN_WP_VERSION = '3.3';
	public const MIN_WC_VERSION = '3.0';
	public const MIN_PHP_VERSION = '7.4';
	public const ADMIN_POINTER_PRIORITY = 1000;
	
	/**
	 * Markup validation limits
	 */
	public const MIN_PERCENTAGE = -100;
	public const MAX_PERCENTAGE = 1000;
	public const MAX_FIXED_AMOUNT = 999999.99;
	public const DECIMAL_PLACES_FIXED = 4;
	public const DECIMAL_PLACES_PERCENTAGE = 2;
	
	/**
	 * Default settings
	 */
	public const DEFAULT_MAX_VARIATIONS = 50;
	
	/**
	 * Initialize all WordPress-defined constants
	 * 
	 * This method should be called during plugin initialization to define
	 * constants that depend on WordPress functions or need translation support.
	 */
	public static function initialize(): void {
		// Plugin paths and URLs
		if (!defined('MT2MBA_PLUGIN_DIR')) {
			define('MT2MBA_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__) . '/markup-by-attribute-for-woocommerce.php'));
		}
		if (!defined('MT2MBA_PLUGIN_URL')) {
			define('MT2MBA_PLUGIN_URL', plugin_dir_url(dirname(__FILE__) . '/markup-by-attribute-for-woocommerce.php'));
		}
		if (!defined('MT2MBA_PLUGIN_BASENAME')) {
			define('MT2MBA_PLUGIN_BASENAME', plugin_basename(dirname(__FILE__) . '/markup-by-attribute-for-woocommerce.php'));
		}
		
		// Site information
		if (!defined('MT2MBA_SITE_URL')) {
			define('MT2MBA_SITE_URL', get_bloginfo('wpurl'));
		}
		
		// Translatable constants
		if (!defined('MT2MBA_PLUGIN_NAME')) {
			define('MT2MBA_PLUGIN_NAME', __('Markup by Attribute', self::TEXT_DOMAIN));
		}
		if (!defined('MT2MBA_PRICE_META')) {
			define('MT2MBA_PRICE_META', __('Product price', self::TEXT_DOMAIN) . ' ');
		}
		
		// HTML markup constants
		if (!defined('PRODUCT_MARKUP_DESC_BEG')) {
			define('PRODUCT_MARKUP_DESC_BEG', '<span id="mbainfo">');
		}
		if (!defined('PRODUCT_MARKUP_DESC_END')) {
			define('PRODUCT_MARKUP_DESC_END', '</span>');
		}
		
		// Prefix constants for metadata keys
		if (!defined('REWRITE_TERM_NAME_PREFIX')) {
			define('REWRITE_TERM_NAME_PREFIX', 'mt2mba_rewrite_attrb_name_');
		}
		if (!defined('REWRITE_TERM_DESC_PREFIX')) {
			define('REWRITE_TERM_DESC_PREFIX', 'mt2mba_rewrite_attrb_desc_');
		}
		if (!defined('DONT_OVERWRITE_THEME_PREFIX')) {
			define('DONT_OVERWRITE_THEME_PREFIX', 'mt2mba_dont_overwrite_theme_');
		}
		
		// Markup text formatting patterns
		if (!defined('MT2MBA_MARKUP_NAME_PATTERN_ADD')) {
			define('MT2MBA_MARKUP_NAME_PATTERN_ADD', '(' . __('Add', self::TEXT_DOMAIN) . ' %s)');
		}
		if (!defined('MT2MBA_MARKUP_NAME_PATTERN_SUBTRACT')) {
			define('MT2MBA_MARKUP_NAME_PATTERN_SUBTRACT', '(' . __('Subtract', self::TEXT_DOMAIN) . ' %s)');
		}
		
		// Price type constants
		if (!defined('REGULAR_PRICE')) {
			define('REGULAR_PRICE', 'regular_price');
		}
		if (!defined('SALE_PRICE')) {
			define('SALE_PRICE', 'sale_price');
		}
		
		// Legacy constant definitions for backward compatibility
		define('MT2MBA_PLUGIN_PREFIX', self::PLUGIN_PREFIX);
		define('MT2MBA_VERSION', self::VERSION);
		define('MT2MBA_DB_VERSION', self::DB_VERSION);
		define('MT2MBA_MIN_WP_VERSION', self::MIN_WP_VERSION);
		define('MT2MBA_DEFAULT_MAX_VARIATIONS', self::DEFAULT_MAX_VARIATIONS);
		define('MT2MBA_ADMIN_POINTER_PRIORITY', self::ADMIN_POINTER_PRIORITY);
		define('MT2MBA_MIN_PERCENTAGE', self::MIN_PERCENTAGE);
		define('MT2MBA_MAX_PERCENTAGE', self::MAX_PERCENTAGE);
		define('MT2MBA_MAX_FIXED_AMOUNT', self::MAX_FIXED_AMOUNT);
		define('MT2MBA_DECIMAL_PLACES_FIXED', self::DECIMAL_PLACES_FIXED);
		define('MT2MBA_DECIMAL_PLACES_PERCENTAGE', self::DECIMAL_PLACES_PERCENTAGE);
	}
	
	/**
	 * Get plugin information for headers
	 * 
	 * @return array Plugin header information
	 */
	public static function getPluginHeaders(): array {
		return [
			'Name' => 'Markup by Attribute for WooCommerce',
			'Description' => 'Adds product variation markup by attribute to WooCommerce.',
			'URI' => 'https://wordpress.org/plugins/markup-by-attribute-for-woocommerce/',
			'Author' => 'MarkTomlinson',
			'Version' => self::VERSION,
			'License' => 'GPLv3',
			'License URI' => 'https://www.gnu.org/licenses/gpl-3.0.html',
			'Text Domain' => self::TEXT_DOMAIN,
			'Domain Path' => '/languages',
			'Requires at least' => '4.6',
			'Tested up to' => '6.8.2',
			'Requires PHP' => self::MIN_PHP_VERSION,
			'WC tested up to' => '10.0.2',
			'WC requires at least' => self::MIN_WC_VERSION
		];
	}
}
?>