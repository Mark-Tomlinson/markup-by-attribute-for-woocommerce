<?php
/**
 * This file is part of the Markup by Attribute for WooCommerce plugin by Mark Tomlinson
 *
 * @package     markup-by-attribute-for-woocommerce
 * @author      Mark Tomlinson
 * @version     2.4
 * @copyright   Mark Tomlinson  2018
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Plugin Name:          Markup by Attribute for WooCommerce - MT² Tech
 * Description:          This plugin adds product variation markup by attribute to WooCommerce -- the ability to add a markup (or markdown) to an attribute term and have that change the regular and sale price of the associated product variations.
 * Plugin URI:           https://wordpress.org/plugins/markup-by-attribute-for-woocommerce/
 * Tags:                 WooCommerce, Attribute, Price, Variation, Markup
 * Author:               MarkTomlinson
 * Contributors:         MarkTomlinson
 * Donate link:          https://www.paypal.me/MT2Dev/15
 * License:              GPLv3
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.html
 * Version:              2.4
 * Stable tag:           trunk
 * Text Domain:          markup-by-attribute
 * Domain path:          /languages
 * Requires at least:    4.6
 * Tested up to:         4.9.8
 * Requires PHP:         5.2.4
 * WC requires at least: 3.0
 * WC tested up to:      3.4.5
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit( );

// If WooCommerce is active 
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )
{
	define( 'MT2MBA_PLUGIN_PREFIX', 'MT2MBA' );
	define( 'MT2MBA_PLUGIN_NAME', _x( 'text-Plugin-name', 'Global', 'markup-by-attribute' ) );
	// Set plugin Version
	define( 'MT2MBA_VERSION', 2.4 );
	define( 'MT2MBA_DB_VERSION', 2.1 );
	define( 'MT2MBA_SITE_URL', get_bloginfo( 'wpurl' ) );
	define( 'MT2MBA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

	global $mt2mba_price_meta;
	$mt2mba_price_meta          = _x( 'text-Product-price', 'Global', 'markup-by-attribute' ) . ' ';
	global $product_markup_desc_beg;
	$product_markup_desc_beg    = '<span id="mbainfo">';
	global $product_markup_desc_end;
	$product_markup_desc_end    = '</span>';
	global $attrb_markup_desc_beg;
	$attrb_markup_desc_beg      = '(' . _x( 'text-Markup', 'Global', 'markup-by-attribute' ) . ': ';
	global $attrb_markup_desc_end;
	$attrb_markup_desc_end      = ')';

	// -------------------------
	//       MAIN ROUTINE
	// -------------------------

	// Register class autoloader
	require_once( MT2MBA_PLUGIN_DIR . 'src/class-mt2mba-autoloader.php' );
	MT2MBA_AUTOLOADER::register();

	// Instantiate utility class. Done here in case upgrade required.
	global $mt2mba_utility;
	$mt2mba_utility = new MT2MBA_UTILITY;

    // Pull in correct code depending on whether we are in the shop (frontend) or on the admin page (backend).
	if ( is_admin( ) )
	{
		// -------------
		// Back end code
		// -------------

		/**
		 * Function to add links to plugin page
		 */
		function add_links( $links )
		{
			// Pop deactivation link from array
			$deactivate_link = array_pop($links);
			// Add Settings link
			$links['settings'] =
				'<a id="mt2mba_settings" href="admin.php?page=wc-settings&tab=products&section=mt2mba">' .
				_x( 'text-Settings', 'Plugin page', 'markup-by-attribute' ) .
				'</a>';
			// Add Instructions link
			$links['instructions'] =
				'<a id="mt2mba_instructions" href="https://wordpress.org/plugins/markup-by-attribute-for-woocommerce/#installation" target="_blank">' .
				_x( 'text-Instructions', 'Plugin page', 'markup-by-attribute' ) .
				'</a>';
			// Restore deactivation link to end of array
			$links['deactivate'] = $deactivate_link;
			
			return $links;
		}
		  
		// Add settings and instruction links to plugin page
		add_filter( "plugin_action_links_" . plugin_basename( __FILE__ ), 'add_links' );

		// Instantiate admin notices
		new MT2MBA_BACKEND_NOTICES;
		
		// Instantiate admin pointers
		new MT2MBA_BACKEND_POINTERS;

		// Instantiate attribute admin
		new MT2MBA_BACKEND_ATTRB;

		// Instantiate product admin
		new MT2MBA_BACKEND_PRODUCT;
	}
	else
	{
		// --------------
		// Front end code
		// --------------
		new MT2MBA_FRONTEND;
	}
}

?>