<?php
namespace mt2Tech\MarkupByAttribute;
use mt2Tech\MarkupByAttribute\Backend	as Backend;
use mt2Tech\MarkupByAttribute\Frontend	as Frontend;
use mt2Tech\MarkupByAttribute\Utility	as Utility;
/**
 * This file is part of the Markup by Attribute for WooCommerce plugin by Mark Tomlinson
 *
 * @package	markup-by-attribute-for-woocommerce
 * @version	4.2
 * @license	GPL-2.0+
 */

/**
 * Plugin Name:				Markup by Attribute for WooCommerce
 * Description:				Adds product variation markup by attribute to WooCommerce.
 * Plugin URI:				https://wordpress.org/plugins/markup-by-attribute-for-woocommerce/
 * Tags:					WooCommerce, Attribute, Price, Variation, Markup
 * Author:					MarkTomlinson
 * Contributors:			MarkTomlinson
 * Donate link:				https://www.paypal.me/MT2Dev/5
 * License:					GPLv3
 * License URI:				https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:				markup-by-attribute
 * Domain Path:				/languages
 * Version:					4.2
 * Build:					202449.03
 * Stable tag:				trunk
 * Tested up to:			6.7.1
 * Requires at least:		4.6
 * PHP tested up to:		8.3.14
 * Requires PHP:			5.6
 * WC tested up to:			9.4.3
 * WC requires at least:	3.0
 * MySQL tested up to:		8.0.40
 */

// Sanity check. Exit if accessed directly.
if (!defined('ABSPATH')) exit;

// Register class autoloader
require_once __DIR__ . '/autoloader.php';
Autoloader::register();

/**
 * Adds links to the plugin page.
 *
 * @param	array	$links	Existing links.
 * @return	array			Modified links with settings and instructions.
 */
function add_links($links) {
	$mt2mba_links = [
		'settings' => '<a id="mt2mba_settings" href="admin.php?page=wc-settings&tab=products&section=mt2mba">' . __('Settings', 'markup-by-attribute') . '</a>',
		'instructions' => '<a id="mt2mba_instructions" href="https://wordpress.org/plugins/markup-by-attribute-for-woocommerce/#installation" target="_blank">' . __('Instructions', 'markup-by-attribute') . '</a>'
	];
	return array_merge($mt2mba_links, $links);
}
// Add settings and instruction links to plugin page
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), __NAMESPACE__ . '\add_links' );

/**
 * Enqueues custom admin stylesheet for WooCommerce product edit pages.
 * The stylesheet is used to hide the 'Add price' button for product variations.
 *
 * @param	string	$hook	The current admin page hook.
 */
function enqueue_custom_admin_styles($hook) {
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
 * Main initialization function for the plugin.
 */
// Move this function outside of any other function or class
function mt2mba_main() {
	// Load translations
	load_plugin_textdomain('markup-by-attribute', false, dirname(plugin_basename(__FILE__)) . '/languages');

	// Set plugin information
	define('MT2MBA_PLUGIN_PREFIX', 'MT2MBA');
	define('MT2MBA_VERSION', '4.2');
	define('MT2MBA_BUILD', 202449.03);
	define('MT2MBA_DB_VERSION', 2.2);
	define('MT2MBA_SITE_URL', get_bloginfo('wpurl'));
	define('MT2MBA_PLUGIN_DIR', plugin_dir_path(__FILE__));
	define('MT2MBA_PLUGIN_URL', plugin_dir_url(__FILE__));
	define('MT2MBA_PLUGIN_BASENAME', plugin_basename(__FILE__));
	define('MT2MBA_PLUGIN_NAME', __('Markup by Attribute', 'markup-by-attribute'));
	define('MT2MBA_PRICE_META', __('Product price', 'markup-by-attribute') . ' ');
	define('PRODUCT_MARKUP_DESC_BEG', '<span id="mbainfo">');
	define('PRODUCT_MARKUP_DESC_END', '</span>');
	define('REWRITE_OPTION_PREFIX', 'mt2mba_rewrite_attrb_name_');
	define('ATTRB_MARKUP_DESC_BEG', '(' . __('Markup:', 'markup-by-attribute') . ' ');
	define('ATTRB_MARKUP_NAME_BEG', ' (');
	define('ATTRB_MARKUP_END', ')');

	$admin_messages = [
		'info' => [
			/* Add administrative info messages in the following format
			 *
			array("message_name", "This is a dismissable messages."),
			 *
			 */
		],
		'warning' => [
			/* Add any warning messages here in the above format */
		]
	];

	// Instantiate utility class
	global $mt2mba_utility;
	$mt2mba_utility = Utility\General::get_instance();

	if (is_admin()) {
		// -- Back end code --
		$notices = Utility\Notices::get_instance();
		$notices->send_notice_array($admin_messages);

		Utility\Pointers::get_instance();
		Backend\Term::get_instance();
		Backend\ProductList::get_instance();
		new Backend\Product;	// Product class cannot be singleton

	} else {
		// -- Front end code --
		Frontend\Options::get_instance();
	}
}

// Make sure this line is outside of any function
add_action('woocommerce_init', __NAMESPACE__ . '\mt2mba_main');
?>