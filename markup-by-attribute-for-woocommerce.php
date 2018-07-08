<?php
/**
 * This file is part of the Markup by Attribute for WooCommerce plugin by Mark Tomlinson
 *
 * @package markup-by-attribute-for-woocommerce
 * @author Mark Tomlinson
 * @version 1.3.2
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
 * Version:              1.3.2
 * Author:               Mark Tomlinson
 * Author URI:           https://profiles.wordpress.org/marktomlinson
 * License:              GPL2
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          markup-by-attribute-for-woocommerce
 * Domain Path:	         /languages
 * Tested up to:         4.9.5
 * WC requires at least: 3.0
 * WC tested up to:      3.3.4
 *
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit( );

// If WooCommerce is active 
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )
{
	// Set plugin Version
	define( 'MT2MBA_VERSION', '1.3.2' );
	define( 'MT2MBA_MINIMUM_WP_VERSION', '3.0' );
	define( 'MT2MBA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

	global $markup_desc_beg;
	$markup_desc_beg = '<span id="mba_markupinfo">';
	global $markup_desc_end;
	$markup_desc_end = '</span>';


	// -------------------------
	//       MAIN ROUTINE
	// -------------------------

	// Register class autoloader
	require_once( MT2MBA_PLUGIN_DIR . 'src/class-mt2mba-autoloader.php' );
	MT2MBA_AUTOLOADER::register();

	function mt2mba_activation()
	{
		// --------------------------------------------------------------
		// Update database from version 1.x. Leave 1.x data for fallback.
		// --------------------------------------------------------------
		global $wpdb;
		// Add prefix to attribute markup meta data
		$results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}termmeta WHERE meta_key LIKE 'markup'" );
		foreach( $results as $row )
		{
			if( strpos($row->meta_key, 'mt2mba_' ) === FALSE )
			{
				add_term_meta( $row->term_id, "mt2mba_" . $row->meta_key, $row->meta_value, TRUE );
			}
		}

		// Add prefix to product markup meta data
		$results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}postmeta WHERE `meta_key` LIKE '%_markup_amount'" );
		foreach( $results as $row )
		{
			if( strpos($row->meta_key, 'mt2mba_' ) === FALSE )
			{
				add_post_meta( $row->post_id, "mt2mba_" . $row->meta_key, $row->meta_value, TRUE );
			}
		}
		// Bracket description and save base regular price
		global $markup_desc_beg;
		global $markup_desc_end;
		$results = $wpdb->get_results( "SELECT * FROM `wp_postmeta` WHERE `meta_value` LIKE 'Product price %' AND `meta_value` NOT LIKE '{}'" );
		foreach( $results as $row )
		{
			if( strpos( $row->meta_value, $markup_desc_beg ) === FALSE )
			{
				update_post_meta( $row->post_id, $row->meta_key, $markup_desc_beg . $row->meta_value . $markup_desc_end );
			}
			$beg = strpos($row->meta_value,$markup_desc_beg)+strlen($markup_desc_beg);
			$end = strpos($row->value,PHP_EOL);
			$base_price = substr($row->meta_value, $beg, $end-$beg);
			error_log($base_price . " " . floatval($base_price));
		}
	}
	
	register_activation_hook( __FILE__, 'mt2mba_activation' );

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