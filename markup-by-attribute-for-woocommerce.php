<?php
/**
 * This file is part of the Markup by Attribute for WooCommerce plugin by Mark Tomlinson
 *
 * @package     markup-by-attribute-for-woocommerce
 * @version     3.12
 * @license     GPL-3.0+
 */

/**
 * Plugin Name:             Markup by Attribute for WooCommerce
 * Description:             Adds product variation markup by attribute to WooCommerce.
 * Plugin URI:              https://wordpress.org/plugins/markup-by-attribute-for-woocommerce/
 * Tags:                    WooCommerce, Attribute, Price, Variation, Markup
 * Author:                  Mark Tomlinson
 * Author URI:              https://www.example.com
 * Contributors:            Mark Tomlinson
 * Donate link:             https://www.paypal.me/MT2Dev/20
 * License:                 GPLv3
 * License URI:             https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:             markup-by-attribute
 * Domain Path:             /languages
 * Version:                 3.12.4
 * Build:                   202428.01
 * Stable tag:              trunk
 * Tested up to:            6.6
 * Requires at least:       4.6
 * PHP tested up to:        8.3.9
 * Requires PHP:            5.6
 * WC tested up to:         9.0.2
 * WC requires at least:    3.0
 * MySQL tested up to:      8.0.37
 */

// Sanity check. Exit if accessed directly.
if (!defined('ABSPATH')) exit;

/**
 * Adds links to the plugin page.
 * 
 * @param array $links Existing links.
 * @return array Modified links with settings and instructions.
 */
function add_links($links) {
    $mt2mba_links = [
        'settings' => '<a id="mt2mba_settings" href="admin.php?page=wc-settings&tab=products&section=mt2mba">' . __('Settings', 'markup-by-attribute') . '</a>',
        'instructions' => '<a id="mt2mba_instructions" href="https://wordpress.org/plugins/markup-by-attribute-for-woocommerce/#installation" target="_blank">' . __('Instructions', 'markup-by-attribute') . '</a>'
    ];
    return array_merge($mt2mba_links, $links);
}
add_filter("plugin_action_links_" . plugin_basename(__FILE__), 'add_links');

/**
 * Enqueues custom admin stylesheet for WooCommerce product edit pages.
 * The stylesheet is used to hide the 'Add price' button for product variations.
 *
 * @param string $hook The current admin page hook.
 */
function enqueue_custom_admin_styles($hook) {
    global $post_type;

    if (($hook === 'post.php' || $hook === 'post-new.php') && $post_type === 'product') {
        $css_url = plugin_dir_url(__FILE__) . 'src/css/admin-style.css';
        wp_enqueue_style('custom-admin-style', $css_url);
    }
}
add_action('admin_enqueue_scripts', 'enqueue_custom_admin_styles');

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
function mt2mba_main() {
    // Load translations
    load_plugin_textdomain('markup-by-attribute', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Set plugin information
    define('MT2MBA_PLUGIN_PREFIX', 'MT2MBA');
    define('MT2MBA_VERSION', '3.12.4');
    define('MT2MBA_BUILD', 202428.01);
    define('MT2MBA_DB_VERSION', 2.1);
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
            'HPOS_Compatability' => __('<em>Markup-by-Attribute</em> is now compatible with HPOS. If <em>Markup-by-Attribute</em> was keeping you from fully utilizing HPOS, you may now be able to enable it.<ol><li>Go to &#39;WooCommerce >> Settings >> Advanced >> Features&#39;.</li><li>Look for &#39;Order data storage&#39; and select &#39;High-performance order storage&#39; (If this is not an option, a list of other incompatible plugins will appear.)</li><li>Save your changes.</li></ol>', 'markup-by-attribute')
        ],
        'warning' => [
            // Add any warning messages here
        ]
    ];

    // Register class autoloader
    require_once(MT2MBA_PLUGIN_DIR . '/autoloader.php');
    MT2MBA_AUTOLOADER::register();

    // Add settings and instruction links to plugin page
    add_filter("plugin_action_links_" . MT2MBA_PLUGIN_BASENAME, 'add_links');

    // Instantiate utility class
    global $mt2mba_utility;
    $mt2mba_utility = new MT2MBA_UTILITY_GENERAL;
    $mt2mba_utility->get_mba_globals();

    if (is_admin()) {
        // Back end code
        $notices = new MT2MBA_UTILITY_NOTICES;
        $notices->send_notice_array($admin_messages);

        new MT2MBA_UTILITY_POINTERS;
        new MT2MBA_BACKEND_TERM;
        new MT2MBA_BACKEND_PRODUCTLIST;
        new MT2MBA_BACKEND_PRODUCT;
    } else {
        // Front end code
        new MT2MBA_FRONTEND_OPTIONS;
    }
}
add_action('woocommerce_init', 'mt2mba_main');

?>