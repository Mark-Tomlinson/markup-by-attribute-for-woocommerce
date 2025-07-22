<?php
namespace mt2Tech\MarkupByAttribute\Utility;

/**
 * Admin pointer management for user onboarding
 * 
 * Provides contextual help pointers to guide users through the plugin interface.
 * Manages the display and dismissal of WordPress admin pointers on relevant pages
 * to improve user experience and onboarding.
 *
 * @package   mt2Tech\MarkupByAttribute\Utility
 * @author    Mark Tomlinson
 * @license   GPL-2.0+
 * @since     1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit();

class Pointers {
	//region PROPERTIES
	/**
	 * Singleton instance
	 * 
	 * @var self|null
	 * @since 1.0.0
	 */
	private static ?self $instance = null;
	
	/**
	 * Title for pointer messages
	 * 
	 * @var string
	 * @since 1.0.0
	 */
	private string $pointer_title;
	//endregion

	//region INSTANCE MANAGEMENT
	/**
	 * Get singleton instance
	 * 
	 * @since 1.0.0
	 * @return Pointers Single instance of this class
	 */
	public static function get_instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Prevent object cloning
	 * 
	 * @since 1.0.0
	 */
	public function __clone(): void {}

	/**
	 * Prevent object unserialization
	 * 
	 * @since 1.0.0
	 */
	public function __wakeup(): void {}

	/**
	 * Initialize pointer management and register hooks
	 * 
	 * Sets up WordPress hooks for admin pointer display and management.
	 * 
	 * @since 1.0.0
	 */
	private function __construct() {
		add_action('admin_enqueue_scripts', array($this, 'mt2mba_admin_pointer_load'), MT2MBA_ADMIN_POINTER_PRIORITY);
		// Admin pointers for attribute term edit screen
		add_filter('mt2mba_admin_pointers-edit-term', array($this, 'mt2mba_admin_pointers_edit_term'));
		// Admin pointer for plugin page
		add_filter('mt2mba_admin_pointers-plugins', array($this, 'mt2mba_admin_pointers_plugins'));
	}
	//endregion

	//region HOOKS & CALLBACKS
	/**
	 * Load admin pointers for current screen
	 * 
	 * Checks for non-dismissed pointers on the current admin screen
	 * and enqueues necessary scripts and styles for display.
	 * 
	 * @since 1.0.0
	 * @param string $hook_suffix Current admin page hook (unused)
	 */
	function mt2mba_admin_pointer_load(string $hook_suffix): void {
		 // Don't run on WP < minimum version
		if (get_bloginfo('version') < MT2MBA_MIN_WP_VERSION) return;

		// Get pointers for this screen
		$screen = get_current_screen();
		$screen_id = strpos($screen->id, 'edit-pa_') === FALSE ? $screen->id : 'edit-term';
		$pointer_filter = 'mt2mba_admin_pointers-' . $screen_id;

		$pointers = apply_filters($pointer_filter, array());

		if (!$pointers || !is_array($pointers)) return;

		// Get dismissed pointers
		$dismissed = explode(',', (string) get_user_meta(get_current_user_id(), 'dismissed_wp_pointers', true));
		$valid_pointers = array();

		// Check pointers and remove dismissed ones.
		foreach ($pointers as $pointer_id => $pointer) {
			// Sanity check
			if (in_array($pointer_id, $dismissed) || empty($pointer)	|| empty($pointer_id) || empty($pointer['target']) || empty($pointer['options']))
				continue;

			$pointer['pointer_id'] = $pointer_id;

			// Add the pointer to $valid_pointers array
			$valid_pointers['pointers'][] =	$pointer;
		}

		// No valid pointers? Stop here.
		if (empty($valid_pointers)) return;

		// Add pointers style to queue.
		wp_enqueue_style('wp-pointer');

		// Add pointers JScript to queue. Add custom script.
		wp_enqueue_script (
			'mt2mba-pointer',
			MT2MBA_PLUGIN_URL . 'src/js/jq-mt2mba-pointers.js',
			array('wp-pointer')
		);

		// Add pointer options to script.
		wp_localize_script('mt2mba-pointer', 'mt2mbaPointer', $valid_pointers);
	}

	/**
	 * Define pointers for attribute term pages
	 * 
	 * Configures contextual help pointers for the add and edit term
	 * pages to guide users in setting markup values.
	 * 
	 * @since 1.0.0
	 * @param array $pointers Existing pointers array
	 * @return array          Enhanced pointers array with term-specific pointers
	 */
	function mt2mba_admin_pointers_edit_term(array $pointers): array {
		$pointer_content = sprintf (
			'<h3><em>%s</em></h3><p>%s</p>',
			MT2MBA_PLUGIN_NAME,
			__('Markups can be fixed values such as <code>5</code> or <code>5.95</code>, or percentages such as <code>5%</code> or <code>1.23%</code>. Use plus or minus signs (like <code>+5.95</code> or <code>-1.23%</code>) for increases or decreases.<br/>Markups are applied when setting prices or reapplying markups to variations.',
			'markup-by-attribute-for-woocommerce')
		);
		
		$pointers = array (
			'mt2mba-term_add_markup' => array (
				'target' => '#term_add_markup',
				'options' => array (
					'content' => $pointer_content,
					'position' => array('edge' => 'left', 'align' => 'middle')
				)
			),

			'mt2mba-term_edit_markup' => array (
				'target' => '#term_edit_markup',
				'options' => array (
					'content' => $pointer_content,
					'position' => array('edge' => 'top', 'align' => 'middle')
				)
			),

		);
		return $pointers;
	}

	/**
	 * Define pointer for plugins page
	 * 
	 * Configures a help pointer on the plugins page to guide users
	 * to the plugin instructions and documentation.
	 * 
	 * @since 1.0.0
	 * @param array $pointers Existing pointers array
	 * @return array          Enhanced pointers array with plugin page pointer
	 */
	function mt2mba_admin_pointers_plugins(array $pointers): array {
		$pointer_content = sprintf (
			'<h3>%s</h3><p>%s</p>',
			MT2MBA_PLUGIN_NAME,
			__('Using this plugin is simple, but might be a little obscure. This link to the instructions may help get you started.<br/>We\'ll just leave the instructions link right here.', 'markup-by-attribute-for-woocommerce')
		);

		$pointers = array (
			'mt2mba-instructions' => array (
				'target' => '#mt2mba_instructions',
				'options' => array (
					'content' => $pointer_content,
					'position' => array('edge' => 'left', 'align' => 'middle')
				)
			),

		);
		return $pointers;
	}
	//endregion

}