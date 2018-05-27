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
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	// Set plugin Version
	define( 'MT2MBA_VERSION', '1.3.2' );
	define( 'MT2MBA_MINIMUM_WP_VERSION', '3.0' );
	define( 'MT2MBA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

	/* -------------------------
	 *       MAIN ROUTINE
	 * ------------------------- */

	// Pull in correct code depending on whether we are in the shop (frontend) or on the admin page (backend).
	if ( is_admin( ) ) {
		/**
		 * Backend Code
		 */
		// Add Instructions link to plugin page
		add_filter( "plugin_action_links_" . plugin_basename( __FILE__ ), function( $links ) {
			$link = '<a id="mt2mba_instructions" href="https://wordpress.org/plugins/markup-by-attribute-for-woocommerce/#installation" target="_blank">' . __( 'Instructions' ) . '</a>';
			array_push( $links, $link );
			return $links;
		} );
		// Instantiate admin pointers
		require_once( MT2MBA_PLUGIN_DIR . 'src/class-mt2-markup-backend-pointers.php' );
		MT2MBA_BACKEND_POINTERS::init();
		// Instantiate attribute admin
		require_once( MT2MBA_PLUGIN_DIR . 'src/class-mt2-markup-backend-attrb.php' );
		MT2MBA_BACKEND_ATTRB::init();
		// Instantiate product admin
		require_once( MT2MBA_PLUGIN_DIR . 'src/class-mt2-markup-backend-product.php' );
		MT2MBA_BACKEND_PRODUCT::init();
		/*
		 * End Backend Code
		 */
	} else {
		/*
		 * Frontend Code
		 */
		require_once( MT2MBA_PLUGIN_DIR . 'src/class-mt2-markup-frontend.php' );
		MT2MBA_FRONTEND::init( );
		/*
		 * End Frontend Code
		 */
	}
}

?>