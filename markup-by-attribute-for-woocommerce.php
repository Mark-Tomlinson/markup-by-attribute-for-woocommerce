<?php
namespace mt2Tech\MarkupByAttribute;

use mt2Tech\MarkupByAttribute\Backend as Backend;
use mt2Tech\MarkupByAttribute\Frontend as Frontend;
use mt2Tech\MarkupByAttribute\Utility as Utility;

/**
 * Markup by Attribute for WooCommerce
 *
 * This file is part of the Markup by Attribute for WooCommerce plugin by Mark Tomlinson
 *
 * @package   markup-by-attribute-for-woocommerce
 * @version   4.6.1
 * @author    Mark Tomlinson
 * @license   GPL-2.0+
 */

/**
 * Plugin Name:             Markup by Attribute for WooCommerce
 * Description:             Adds product variation markup by attribute to WooCommerce.
 * Plugin URI:              https://wordpress.org/plugins/markup-by-attribute-for-woocommerce/
 * Tags:                    WooCommerce, Attribute, Price, Variation, Markup
 * Author:                  MarkTomlinson
 * Contributors:            MarkTomlinson
 * Donate link:             https://www.paypal.me/MT2Dev/5
 * License:                 GPLv3
 * License URI:             https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:             markup-by-attribute-for-woocommerce
 * Domain Path:             /languages
 * Version:                 4.6.1
 * Stable tag:              4.6.1
 * Tested up to:            7.0
 * Requires at least:       5.7
 * PHP tested up to:        8.4.11
 * Requires PHP:            7.4.3
 * NOTE: Union types (e.g., string|float) require PHP 8.0+. Some method parameters
 *       accept multiple types at runtime but are typed as string for 7.4 compatibility.
 *       See affected method docblocks for details.
 * WC tested up to:         10.6.2
 * WC requires at least:    5.0.0
 * MySQL tested up to:      8.4.8
 */

// Sanity check. Exit if accessed directly.
if (!defined('ABSPATH')) exit;

// Register class autoloader
require_once __DIR__ . '/autoloader.php';
Autoloader::register();

/**
 * Add settings and instruction links to plugin action links
 *
 * Enhances the plugin row on the plugins page with convenient links
 * to settings and documentation.
 *
 * @since  1.0.0
 * @param  array $links Existing plugin action links
 * @return array        Modified links array with additional settings and instruction links
 */
function add_links(array $links): array {
	$mt2mba_links = [
		'settings' => '<a id="mt2mba_settings" href="admin.php?page=wc-settings&tab=products&section=mt2mba">' . __('Settings', 'markup-by-attribute-for-woocommerce') . '</a>',
		'instructions' => '<a id="mt2mba_instructions" href="https://wordpress.org/plugins/markup-by-attribute-for-woocommerce/#installation" target="_blank">' . __('Instructions', 'markup-by-attribute-for-woocommerce') . '</a>'
	];
	return array_merge($mt2mba_links, $links);
}
// Add settings and instruction links to plugin page
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), __NAMESPACE__ . '\add_links' );

/**
 * Enqueue admin styles for product edit pages
 *
 * Loads custom CSS to modify the appearance of WooCommerce product
 * edit interfaces, specifically hiding the 'Add price' button for variations.
 *
 * @since 2.0.0
 * @param string $hook Current admin page hook suffix
 */
function enqueue_custom_admin_styles(string $hook): void {
	global $post_type;

	if (($hook === 'post.php' || $hook === 'post-new.php') && $post_type === 'product') {
		$css_url = plugin_dir_url(__FILE__) . 'src/css/admin-style.css';
		wp_enqueue_style('custom-admin-style', $css_url);
	}
}
add_action('admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_custom_admin_styles');

/**
 * Declare Markup-by-Attribute is compatible with High-Performance Order Storage (HPOS).
 */
add_action('before_woocommerce_init', function() {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

/**
 * Define all plugin constants
 *
 * Centralizes all constant definitions for the plugin, including WordPress-dependent
 * paths, version information, configuration values, and UI elements.
 *
 * @since 1.0.0
 */
function define_constants(): void {
	// WordPress-dependent paths and URLs
	define('MT2MBA_PLUGIN_DIR', plugin_dir_path(__FILE__));
	define('MT2MBA_PLUGIN_URL', plugin_dir_url(__FILE__));
	define('MT2MBA_PLUGIN_BASENAME', plugin_basename(__FILE__));
	define('MT2MBA_SITE_URL', get_bloginfo('wpurl'));

	// Plugin version and compatibility
	define('MT2MBA_VERSION', '4.6.1');
	define('MT2MBA_SCHEMA_VERSION', '4.6.0');	// Last plugin version that included a database schema change
	define('MT2MBA_ADMIN_POINTER_PRIORITY', 1000);

	// Configuration and precision settings
	define('MT2MBA_INTERNAL_PRECISION', 6);
	define('MT2MBA_DEFAULT_MAX_VARIATIONS', 50);

	// Translatable strings and UI elements
	define('MT2MBA_TEXT_DOMAIN', 'markup-by-attribute-for-woocommerce');
	define('MT2MBA_PLUGIN_NAME', __('Markup by Attribute', MT2MBA_TEXT_DOMAIN));
	define('MT2MBA_PRICE_META', __('Product price', MT2MBA_TEXT_DOMAIN) . ' ');
	define('MT2MBA_MARKUP_NAME_PATTERN_ADD', '(' . __('Add', MT2MBA_TEXT_DOMAIN) . ' %s)');
	define('MT2MBA_MARKUP_NAME_PATTERN_SUBTRACT', '(' . __('Subtract', MT2MBA_TEXT_DOMAIN) . ' %s)');
	define('PRODUCT_MARKUP_DESC_BEG', '<span id="mbainfo">');
	define('PRODUCT_MARKUP_DESC_END', '</span>');

	// Option and meta key prefixes
	define('REWRITE_TERM_NAME_PREFIX', 'mt2mba_rewrite_attrb_name_');
	define('REWRITE_TERM_DESC_PREFIX', 'mt2mba_rewrite_attrb_desc_');
	define('DONT_OVERWRITE_THEME_PREFIX', 'mt2mba_dont_overwrite_theme_');

	// Price type constants (Used by WooCommerce, do not translate)
	define('REGULAR_PRICE', 'regular_price');
	define('SALE_PRICE', 'sale_price');
}

/**
 * Run pending database schema upgrades
 *
 * Discovers and executes upgrade modules from src/utility/upgrades/ that haven't
 * been applied yet. Each module is responsible for updating the installed schema
 * version as its last act. A 1-hour cooldown prevents retries after failures.
 *
 * Admin-only — never called on customer-facing page loads.
 *
 * @since 4.6.0
 */
function mt2mba_run_upgrades(): void {
	// Skip if a recent upgrade failed (1-hour cooldown)
	if (get_transient('mt2mba_upgrade_cooldown')) {
		return;
	}

	// Check if upgrades are needed
	$installed_version = get_option('mt2mba_db_version', '0');
	if (version_compare($installed_version, MT2MBA_SCHEMA_VERSION, '>=')) {
		return;
	}

	// Discover upgrade files
	$upgrade_dir = MT2MBA_PLUGIN_DIR . 'src/utility/upgrades/';
	$files = glob($upgrade_dir . 'db_upgrade_*.php');
	if (empty($files)) return;
	sort($files);

	// Load interface
	require_once $upgrade_dir . 'upgradeinterface.php';

	foreach ($files as $file) {
		require_once $file;

		// Derive fully-qualified class name from filename
		// db_upgrade_2_0.php -> DbUpgrade_2_0
		$basename = basename($file, '.php');
		$class_short = implode('_', array_map('ucfirst', explode('_', $basename)));
		$fqcn = 'mt2Tech\\MarkupByAttribute\\Utility\\Upgrades\\' . $class_short;

		// Validate class
		if (!class_exists($fqcn)) continue;
		$implements = class_implements($fqcn);
		if (!isset($implements['mt2Tech\\MarkupByAttribute\\Utility\\Upgrades\\UpgradeInterface'])) continue;

		// Skip upgrades already applied
		if (version_compare($fqcn::version(), $installed_version, '<=')) {
			continue;
		}

		// Run upgrade
		try {
			(new $fqcn)->run();
			// Re-read in case the upgrade stamped its version
			$installed_version = get_option('mt2mba_db_version', '0');
		} catch (\Exception $e) {
			set_transient('mt2mba_upgrade_cooldown', true, HOUR_IN_SECONDS);
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('MT2MBA upgrade failed at version ' . $fqcn::version() . ': ' . $e->getMessage());
			}
			return;
		}
	}
}

/**
 * Initialize the Markup-by-Attribute plugin
 *
 * Main initialization function that sets up constants, loads translations,
 * and initializes core components based on context (admin vs frontend).
 *
 * @since 1.0.0
 */
function mt2mba_main(): void {
	// Define all plugin constants
	define_constants();

	// Load translations
	load_plugin_textdomain(
		MT2MBA_TEXT_DOMAIN,
		false,
		dirname(plugin_basename(__FILE__)) . '/languages'
	);

	// Instantiate utility class (global for backward compatibility)
	global $mt2mba_utility;
	$mt2mba_utility = Utility\General::get_instance();

	// Initialize context-specific components
	if (is_admin()) {
		// Run pending schema upgrades (admin-only, with failure cooldown)
		mt2mba_run_upgrades();

		// Admin messages for notices
		$admin_messages = [
			'info' => [],
			'warning' => []
		];

		// Warn users who had the removed "Preserve Zero Prices" setting enabled
		if (get_option('mt2mba_allow_zero') === 'yes') {
			$admin_messages['warning'][] = [
				'allow_zero_removed',
				__('The <strong>Preserve Zero Prices</strong> setting has been removed. Markups now always apply to zero-priced variations. If you have free/giveaway products using global attributes that carry markups, reapplying markups could raise their price above zero. <a id="mt2mba_instructions" href="https://github.com/Mark-Tomlinson/markup-by-attribute-for-woocommerce/wiki/3.0_Settings#preserve-zero-prices-removed-in-460" target="_blank">See the wiki for details.</a>', 'markup-by-attribute-for-woocommerce')
			];
		}

		// Initialize backend components
		$notices = Utility\Notices::get_instance();
		$notices->sendNoticeArray($admin_messages);

		Utility\Pointers::get_instance();
		Backend\Attribute::get_instance();
		Backend\Term::get_instance();
		Backend\ProductList::get_instance();
		new Backend\Product();  // Product class cannot be singleton due to hook requirements
	} else {
		// Initialize frontend components
		Frontend\Options::get_instance();
	}

}

// Make sure this line is outside any function
add_action('woocommerce_init', __NAMESPACE__ . '\mt2mba_main');