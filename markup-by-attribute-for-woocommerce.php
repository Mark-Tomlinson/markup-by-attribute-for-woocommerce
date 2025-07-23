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
 * @version   4.3.8
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
 * Version:                 4.3.8
 * Stable tag:              4.3.8
 * Tested up to:            6.8.2
 * Requires at least:       4.6
 * PHP tested up to:        8.4.5
 * Requires PHP:            7.4
 * WC tested up to:         10.0.2
 * WC requires at least:    3.0
 * MySQL tested up to:      8.4.5
 */

// Sanity check. Exit if accessed directly.
if (!defined('ABSPATH')) exit;

// Define plugin constants
define('MT2MBA_VERSION', '4.3.8');
define('MT2MBA_DB_VERSION', 2.2);
define('MT2MBA_TEXT_DOMAIN', 'markup-by-attribute-for-woocommerce');
define('MT2MBA_MIN_WP_VERSION', '3.3');
define('MT2MBA_ADMIN_POINTER_PRIORITY', 1000);
define('MT2MBA_INTERNAL_PRECISION', 6);
define('MT2MBA_DEFAULT_MAX_VARIATIONS', 50);

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
 * Initialize the Markup-by-Attribute plugin
 *
 * Main initialization function that sets up constants, loads translations,
 * instantiates core classes, and initializes frontend or backend functionality
 * based on the current context.
 *
 * This function is called on the 'woocommerce_init' hook to ensure WooCommerce
 * is fully loaded before plugin initialization.
 *
 * @since 1.0.0
 */
function mt2mba_main(): void {
	// Define WordPress-dependent constants
	define('MT2MBA_PLUGIN_DIR', plugin_dir_path(__FILE__));
	define('MT2MBA_PLUGIN_URL', plugin_dir_url(__FILE__));
	define('MT2MBA_PLUGIN_BASENAME', plugin_basename(__FILE__));
	define('MT2MBA_SITE_URL', get_bloginfo('wpurl'));
	define('MT2MBA_PLUGIN_NAME', __('Markup by Attribute', MT2MBA_TEXT_DOMAIN));
	define('MT2MBA_PRICE_META', __('Product price', MT2MBA_TEXT_DOMAIN) . ' ');
	define('PRODUCT_MARKUP_DESC_BEG', '<span id="mbainfo">');
	define('PRODUCT_MARKUP_DESC_END', '</span>');
	define('REWRITE_TERM_NAME_PREFIX', 'mt2mba_rewrite_attrb_name_');
	define('REWRITE_TERM_DESC_PREFIX', 'mt2mba_rewrite_attrb_desc_');
	define('DONT_OVERWRITE_THEME_PREFIX', 'mt2mba_dont_overwrite_theme_');
	define('MT2MBA_MARKUP_NAME_PATTERN_ADD', '(' . __('Add', MT2MBA_TEXT_DOMAIN) . ' %s)');
	define('MT2MBA_MARKUP_NAME_PATTERN_SUBTRACT', '(' . __('Subtract', MT2MBA_TEXT_DOMAIN) . ' %s)');
	define('REGULAR_PRICE', 'regular_price');
	define('SALE_PRICE', 'sale_price');

	// Load translations
	load_plugin_textdomain(
		MT2MBA_TEXT_DOMAIN,
		false,
		dirname(plugin_basename(__FILE__)) . '/languages'
	);

	// Define admin messages for notices
	$admin_messages = [
		'info' => [
			/* Add administrative info messages in the following format
			 *
			array("message_name", "This is a dismissable messages."),
			 */
		],
		'warning' => [
			/* Add any warning messages here in the above format */
		]
	];

	// Initialize core components
	initializeCoreComponents($admin_messages);
}

/**
 * Initialize core plugin components based on context
 *
 * Separates initialization logic for better maintainability and testing.
 * Instantiates different components for admin vs frontend contexts.
 *
 * @since 4.4.0
 * @param array $admin_messages Array of admin notice messages
 */
function initializeCoreComponents(array $admin_messages): void {
	// Instantiate utility class (global for backward compatibility)
	global $mt2mba_utility;
	$mt2mba_utility = Utility\General::get_instance();

	if (is_admin()) {
		// Backend components
		initializeBackendComponents($admin_messages);
	} else {
		// Frontend components
		initializeFrontendComponents();
	}
}

/**
 * Initialize backend (admin) components
 *
 * @since 4.4.0
 * @param array $admin_messages Array of admin notice messages
 */
function initializeBackendComponents(array $admin_messages): void {
	$notices = Utility\Notices::get_instance();
	$notices->send_notice_array($admin_messages);

	Utility\Pointers::get_instance();
	Backend\Term::get_instance();
	Backend\ProductList::get_instance();
	new Backend\Product();  // Product class cannot be singleton due to hook requirements
}

/**
 * Initialize frontend components
 *
 * @since 4.4.0
 */
function initializeFrontendComponents(): void {
	Frontend\Options::get_instance();
}

// Make sure this line is outside any function
add_action('woocommerce_init', __NAMESPACE__ . '\mt2mba_main');
?>