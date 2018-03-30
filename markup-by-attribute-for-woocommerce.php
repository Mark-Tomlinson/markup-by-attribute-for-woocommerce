<?php
/*
 * This file is part of the Markup by Attribute WooCommerce plugin.
 *
 * (c) Mark Tomlinson
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Plugin Name: Markup by Attribute for WooCommerce - MT² Tech
 * Plugin URI:  https://wordpress.org/plugins/markup-by-attribute-for-woocommerce/
 * Description: This plugin adds product variation markup by attribute to WooCommerce -- the ability to add a markup (or markdown) to an attribute term and have that change the regular and sale price of the associated product variations.
 * Version:     1.1.1
 * Author:      Mark Tomlinson
 * Author URI:  https://profiles.wordpress.org/marktomlinson
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: markup-by-attribute-for-woocommerce
 * Domain Path: /languages
 * WC requires at least: 3.0
 * WC tested up to:      3.3.1
 * 
 * @author Mark Tomlinson
 * @version 1.0.0
 *
 */

/* -------------------------
 *      HOUSEKEEPING
 * ------------------------- */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit( );

// If WooCommerce is active 
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

//	namespace MarkupByAttrb;

	// Set plugin Version
	define( 'MT2MBA_VERSION', '0.1.0' );
	define( 'MT2MBA_MINIMUM_WP_VERSION', '4.0' );
	define( 'MT2MBA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

	/* -------------------------
	 *       MAIN ROUTINE
	 * ------------------------- */

	// Pull in correct code depending on whether we are in the shop (frontend) or on the admin page (backend).
	if ( is_admin( ) ) {
		/*
		 * Backend Code
		 */
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