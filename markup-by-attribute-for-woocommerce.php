<?php
/**
 * This file is part of the Markup by Attribute for WooCommerce plugin by Mark Tomlinson
 *
 * @package markup-by-attribute-for-woocommerce
 * @author  Mark Tomlinson
 * @version 2.0
 * 
 * (c) Mark Tomlinson
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Plugin Name:          Markup by Attribute for WooCommerce - MT² Tech
 * Plugin URI:           https://wordpress.org/plugins/markup-by-attribute-for-woocommerce/
 * Description:          This plugin adds product variation markup by attribute to WooCommerce -- the ability to add a markup (or markdown) to an attribute term and have that change the regular and sale price of the associated product variations.
 * Version:              2.0
 * Author:               Mark Tomlinson
 * Author URI:           https://profiles.wordpress.org/marktomlinson
 * License:              GPL2
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          markup-by-attribute-for-woocommerce
 * Domain Path:	         /languages
 * Tested up to:         4.9.7
 * WC requires at least: 3.0
 * WC tested up to:      3.4.3
 *
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit( );

// If WooCommerce is active 
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )
{
	// Set plugin Version
	define( 'MT2MBA_VERSION', 2.0 );
	define( 'MT2MBA_DB_VERSION', 2.0 );
	define( 'MT2MBA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

	global $mt2mba_price_meta;
	$mt2mba_price_meta          = __( 'Product price ' );
	global $product_markup_desc_beg;
	$product_markup_desc_beg    = __( '<span id="mbainfo">' );
	global $product_markup_desc_end;
	$product_markup_desc_end    = __( '</span>' );
	global $attrb_markup_desc_beg;
	$attrb_markup_desc_beg      = __( '(Markup: ' );
	global $attrb_markup_desc_end;
	$attrb_markup_desc_end      = __( ')' );

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

		// Function to add links to plugin page
		function add_links( $links )
		{
			// Pop deactivation link from array
			$deactivate_link = array_pop($links);
			// Add Settings link
			$links['settings'] =
				'<a id="mt2mba_settings" href="admin.php?page=wc-settings&tab=products&section=mt2mba">' .
				__( 'Settings' ) .
				'</a>';
			// Add Instructions link
			$links['instructions'] =
				'<a id="mt2mba_instructions" href="https://wordpress.org/plugins/markup-by-attribute-for-woocommerce/#installation" target="_blank">' .
				__( 'Instructions' ) .
				'</a>';
			// Restore deactivation link to end of array
			$links['deactivate'] = $deactivate_link;
			
			return $links;
		}
		
		// Add settings and instruction links to plugin page
		add_filter( "plugin_action_links_" . plugin_basename( __FILE__ ), 'add_links' );

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