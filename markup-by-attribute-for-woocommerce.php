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
 * Text Domain:          markup-by-attribute
 * Domain path:          /languages
 * Version:              3.1
 * Build:                201847.01
 * Stable tag:           trunk
 * Requires at least:    4.6
 * Tested up to:         4.9.8
 * Requires PHP:         5.6
 * WC requires at least: 3.0
 * WC tested up to:      3.5.1
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit( );

// If WooCommerce is active 
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )
{
  	// Load translations
	load_plugin_textdomain( 'markup-by-attribute', FALSE, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Set plugin information
	define( 'MT2MBA_PLUGIN_PREFIX',     'MT2MBA'                                            );
	define( 'MT2MBA_VERSION',           3.1                                                 );
	define( 'MT2MBA_BUILD',             201847.01                                           );
	define( 'MT2MBA_DB_VERSION',        2.1                                                 );
	define( 'MT2MBA_SITE_URL',          get_bloginfo( 'wpurl')                              );
	define( 'MT2MBA_PLUGIN_DIR',        plugin_dir_path( __FILE__ )                         );
	define( 'MT2MBA_PLUGIN_URL',        plugin_dir_url( __FILE__ )                          );
	define( 'MT2MBA_PLUGIN_BASENAME',   plugin_basename( __FILE__ )                         );
	define( 'MT2MBA_PLUGIN_NAME',       __( 'Markup by Attribute', 'markup-by-attribute' )	);

	define( 'MT2MBA_PRICE_META',        __( 'Product price', 'markup-by-attribute' ) . ' '  );
	define( 'PRODUCT_MARKUP_DESC_BEG',	'<span id="mbainfo">'                               );
	define( 'PRODUCT_MARKUP_DESC_END',  '</span>'                                           );
	define( 'ATTRB_MARKUP_DESC_BEG',    '(' . __( 'Markup:', 'markup-by-attribute' ) . ' '  );
	define( 'ATTRB_MARKUP_DESC_END',    ')'                                                 );

	$admin_messages = array
	(	//	Update with dismissible info and warning messages that get displayed at startup
		'info' => array
		(
			// Version 2.4 Upgrade notice
			'ver2_4_upgrade' => sprintf
			(
				'%1$s %2$s %3$s %4$s',
				__( 'PLEASE NOTE: As of version 2.4, Markup-by-Attribute no longer has it\'s own currency format settings. It now uses the', 'markup-by-attribute' ),
				sprintf( '<a href="%s">' . __( 'WooCommerce currency settings', 'markup-by-attribute' ) . '</a>.<br/>', MT2MBA_SITE_URL . '/wp-admin/admin.php?page=wc-settings' ),
				__( 'You may still control the markup display behavior of the options drop-down and the product description with the', 'markup-by-attribute' ),
				sprintf( '<a href="%s">' . __( 'Markup-by-Attribute settings', 'markup-by-attribute' ) . '</a>.', MT2MBA_SITE_URL . '/wp-admin/admin.php?page=wc-settings&tab=products&section=mt2mba' )
			),
			//	Info message #2
//			'unique_message_identifier' => __( 'message', 'markup-by-attribute' ),
),
		'warning' => array
		(
			//	Warning message #1
//			'unique_message_identifier' => __( 'message', 'markup-by-attribute' ),
		),
	);


	// Register class autoloader
	require_once( MT2MBA_PLUGIN_DIR . '/autoloader.php' );
	MT2MBA_AUTOLOADER::register();

	// Add settings and instruction links to plugin page
    add_filter( "plugin_action_links_" . MT2MBA_PLUGIN_BASENAME, 'add_links' );

    // Instantiate utility class. Done here in case upgrade required.
	global $mt2mba_utility;
    $mt2mba_utility = new MT2MBA_UTILITY_GENERAL;

	// Pull in correct code depending on whether we are in the shop (frontend) or on the admin page (backend).
	if ( is_admin( ) )
	{
		// -------------
		// Back end code
		// -------------

		// Instantiate admin notices
		$notices = new MT2MBA_UTILITY_NOTICES;
		$notices->send_notice_array( $admin_messages );

		// Instantiate admin pointers
		new MT2MBA_UTILITY_POINTERS;

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
		new MT2MBA_FRONTEND_OPTIONS;
	}
}


/**
* Add links to plugin page
* 
* @param   array   $links  Usually just the 'Deactivate' link
* @return  array           Settings and instructions links plus any that came in
* 
*/
function add_links( $links )
{
	// Add Settings link
	$mt2mba_links['settings'] =
		'<a id="mt2mba_settings" href="admin.php?page=wc-settings&tab=products&section=mt2mba">' .
		__( 'Settings', 'markup-by-attribute' ) .
		'</a>';
	// Add Instructions link
	$mt2mba_links['instructions'] =
		'<a id="mt2mba_instructions" href="https://wordpress.org/plugins/markup-by-attribute-for-woocommerce/#installation" target="_blank">' .
		__( 'Instructions', 'markup-by-attribute' ) .
		'</a>';
	// Restore deactivation link to end of array
	
	return array_merge( $mt2mba_links, $links );
}

?>