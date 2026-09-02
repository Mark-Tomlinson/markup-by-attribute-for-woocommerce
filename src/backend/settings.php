<?php
namespace mt2Tech\MarkupByAttribute\Backend;
use WC_Settings_API;

/**
 * WooCommerce settings integration for Markup-by-Attribute
 *
 * Extends WooCommerce's settings API to provide configuration options for the plugin.
 * Manages all plugin settings including markup behavior, display options, and limits.
 *
 * @package   mt2Tech\MarkupByAttribute\Backend
 * @since     2.0.0
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

if (!class_exists('Settings')) :

class Settings extends WC_Settings_API {
	//region PROPERTIES
	/**
	 * Singleton instance
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * How markup descriptions are handled in variation descriptions
	 * @var string
	 */
	public string $desc_behavior = 'append';

	/**
	 * How markup is displayed in dropdown options
	 * @var string
	 */
	public string $dropdown_behavior = 'add';

	/**
	 * Whether to include attribute name in markup descriptions
	 * @var string
	 */
	public string $include_attrb_name = 'no';

	/**
	 * Whether to hide base price in variation descriptions
	 * @var string
	 */
	public string $hide_base_price = 'no';

	/**
	 * How percentage markups are calculated for sale prices
	 * @var string
	 */
	public string $sale_price_markup = 'yes';

	/**
	 * Whether to round markup calculations to whole numbers
	 * @var string
	 */
	public string $round_markup = 'no';

	/**
	 * Maximum number of variations to process at once
	 * @var int
	 */
	public int $max_variations = MT2MBA_DEFAULT_MAX_VARIATIONS;

	/**
	 * Options this plugin owns, in settings-page order
	 *
	 * Spelled out rather than harvested from the settings array at runtime so the
	 * autoload fixup does not depend on having just built that array.
	 * @var string[]
	 */
	private const OPTION_NAMES = [
		'mt2mba_dropdown_behavior',
		'mt2mba_desc_behavior',
		'mt2mba_include_attrb_name',
		'mt2mba_hide_base_price',
		'mt2mba_sale_price_markup',
		'mt2mba_round_markup',
		'mt2mba_max_variations',
	];
	//endregion

	//region INSTANCE MANAGEMENT
	/** Singleton accessor. @since 2.0.0 */
	public static function get_instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Singleton: cloning is not supported. @since 2.0.0 */
	public function __clone() {}

	/** Singleton: unserialization is not supported. @since 2.0.0 */
	public function __wakeup(): void {}

	/**
	 * Initialize settings and register WooCommerce hooks
	 */
	private function __construct() {
		add_filter('woocommerce_get_sections_products', [$this, 'addSection']);
		add_filter('woocommerce_get_settings_products', [$this, 'getSettings'], 10, 2);
		add_filter('sanitize_option_mt2mba_max_variations', [$this, 'validateMaxVariations'], 10, 1);
		// WooCommerce composes this as woocommerce_update_options_{tab}_{section}
		// and fires it after save_fields() has written the options
		add_action('woocommerce_update_options_products_mt2mba', [$this, 'setOptionsNotAutoloaded']);
	}
	//endregion

	//region WOOCOMMERCE INTEGRATION
	/**
	 * Add a new section to the Product settings tab
	 *
	 * @since 2.0.0
	 * @param array $sections Existing sections
	 * @return array          Sections with markup-by-attribute section added
	 */
	public function addSection(array $sections): array {
		$sections['mt2mba'] = __('Markup by Attribute', 'markup-by-attribute-for-woocommerce');
		return $sections;
	}

	/**
	 * Get settings array for markup-by-attribute section
	 *
	 * @since 2.0.0
	 * @param array  $settings         Existing settings
	 * @param string $current_section  Current section name
	 * @return array                   Complete settings configuration array
	 */
	public function getSettings(array $settings, string $current_section): array {
		if ('mt2mba' === $current_section) {
			// Repeating strings
			$immediately = __('This setting affects all products and takes effect immediately.', 'markup-by-attribute-for-woocommerce');
			$individually = __('This setting affects products individually and takes effect when you recalculate prices or reapply markups.', 'markup-by-attribute-for-woocommerce');

			// Create settings array
			$mt2mba_settings = [];

			// Add title to the settings page
			$mt2mba_settings[] = [
				'name'		=> __('Markup by Attribute Settings', 'markup-by-attribute-for-woocommerce'),
				'type'		=> 'title',
				'desc'		=> __('The following options are used to configure variation markups by attribute.', 'markup-by-attribute-for-woocommerce') . ' ' .
					sprintf (
						__('Additional help can be found in the <a href="%1$s" target="_blank">Markup by Attribute wiki</a> on the <code>Settings</code> page.', 'markup-by-attribute-for-woocommerce'),
						'https://github.com/Mark-Tomlinson/markup-by-attribute-for-woocommerce/wiki'
					),
				'id'	=> 'mt2mba'
			];

			// *** Display settings ***
			$mt2mba_settings[] = [
				'name'	=> __('Markup Display', 'markup-by-attribute-for-woocommerce'),
				'type'	=> 'title',
				'id'	=> 'mt2mbaDisplaySection'
			];

			/** -- Option Drop-down Behavior --
			 *	Should Markup-by-Attribute add the markup to the options drop-down box, and should the currency
			 *	symbol be displayed?
			 *	This setting affects all products and takes effect immediately.
			 */
			$mt2mba_settings[] = [
				'name'		=> __('Option Drop-down Behavior', 'markup-by-attribute-for-woocommerce'),
				'desc'		=> __('Should Markup-by-Attribute add the markup to the options drop-down box, and should the currency symbol be displayed?', 'markup-by-attribute-for-woocommerce') . '<br/>' .
					'<em>' . $immediately . '</em>',
				'id'		=> 'mt2mba_dropdown_behavior',
				'type'		=> 'radio',
				'options'	=> [
					'hide'			=> __('Do NOT show the markup in the options drop-down box.', 'markup-by-attribute-for-woocommerce'),
					'add'			=> __('Show the markup WITH the currency symbol in the options drop-down box.', 'markup-by-attribute-for-woocommerce'),
					'do_not_add'	=> __('Show the markup WITHOUT the currency symbol in the options drop-down box.', 'markup-by-attribute-for-woocommerce'),
				],
				'default'	=> $this->dropdown_behavior
			];

			/** -- Variation Description Behavior --
			 *	How should Markup-by-Attribute handle adding price markup information to the product variation
			 *	description?
			 */
			$mt2mba_settings[] = [
				'name'		=> __('Variation Description Behavior', 'markup-by-attribute-for-woocommerce'),
				'desc'		=> __('How should Markup-by-Attribute handle adding price markup information to the product variation description?', 'markup-by-attribute-for-woocommerce') . '<br/>' .
					'<em>' . $individually . '</em>',
				'id'		=> 'mt2mba_desc_behavior',
				'type'		=> 'radio',
				'options'	=> [
					'ignore'	=> __('Do NOT add pricing information to the description field.', 'markup-by-attribute-for-woocommerce'),
					'append'	=> __('Add pricing information to the end of the existing description.', 'markup-by-attribute-for-woocommerce'),
					'overwrite' => __('Overwrite the variation description with price information.', 'markup-by-attribute-for-woocommerce'),
				],
				'default'	=> $this->desc_behavior
			];

			/** -- Include Attribute Name --
			 *	Include the name of the attribute in the variatiable product's decription. 'Add $1.50 for Blue' becomes 'Add $1.50 for Color Blue'.
			 */
			$mt2mba_settings[] = [
				'name'		=> __('Include Attribute Names in Variation Descriptions', 'markup-by-attribute-for-woocommerce'),
				'desc'		=> __("Include the name of the attribute in the variable product's description. <b>Add $1.50 for Blue</b> becomes <b>Add $1.50 for Color: Blue</b>.", 'markup-by-attribute-for-woocommerce') . ' <br/>' .
					'<em>' . $individually . '</em>',
				'id'		=> 'mt2mba_include_attrb_name',
				'type'		=> 'checkbox',
				'default'	=> $this->include_attrb_name
			];

			/** -- Hide Base Price --
			 *	Do NOT show the base price in the product description.
			 *	This setting affects products individually and takes effect when you recalculate the regular price
			 *	for the product.
			 */
			$mt2mba_settings[] = [
				'name'		=> __('Hide Base Price', 'markup-by-attribute-for-woocommerce'),
				'desc'		=> __('Do NOT show the base price in the product description.', 'markup-by-attribute-for-woocommerce') . ' <br/>' .
					'<em>' . $individually . '</em>',
				'id'		=> 'mt2mba_hide_base_price',
				'type'		=> 'checkbox',
				'default'	=> $this->hide_base_price
			];

			$mt2mba_settings[] = [
				'type'		=> 'sectionend',
				'id'		=> 'mt2mbaDisplaySection'
			];

			// *** Markup Calculation settings ***
			$mt2mba_settings[] = [
				'name'		=> __('Markup Calculation', 'markup-by-attribute-for-woocommerce'),
				'type'		=> 'title',
				'id'		=> 'mt2mbaCalcSection'
			];

			/** -- Sale Price Markup --
			 *	Should Markup-by-Attribute calculate percentage markups on sale prices?
			 *	A 10% markup on a $30 regular price yields a $3 markup. If you set a $20 sale price, setting this
			 *	option ON yields a $2 markup, setting it OFF leaves the markup at $3.
			 *	This setting affects products individually and takes effect when you recalculate the sale price for
			 *	the product.
			 */
			$mt2mba_settings[] = [
				'name'		=> __('Sale Price Markup', 'markup-by-attribute-for-woocommerce'),
				'desc'		=> __('Should Markup-by-Attribute calculate percentage markups on sale prices?', 'markup-by-attribute-for-woocommerce') . ' <br/>' .
					__('A 10% markup on a $30 regular price yields a $3 markup. If you set a $20 sale price, setting this option ON yields a $2 markup, setting it OFF leaves the markup at $3.',
					'markup-by-attribute-for-woocommerce') . ' <br/>' . '<em>' . $individually . '</em>',
				'id'		=> 'mt2mba_sale_price_markup',
				'type'		=> 'checkbox',
				'default'	=> $this->sale_price_markup
			];

			/** -- Round Markup --
			 *	Round percentage markups to keep the value below the decimal intact?
			 *	Some stores want prices with specific numbers below the decimal place (such as xx.00 or xx.95).
			 *	Rounding percentage markups will keep the value below the decimal intact.
			 *	This setting affects products individually and takes effect when you recalculate the regular price
			 *	for the product.
			 */
			$mt2mba_settings[] = [
				'name'		=> __('Round Markup', 'markup-by-attribute-for-woocommerce'),
				'desc'		=> __('Round percentage markups to keep the value below the decimal intact?', 'markup-by-attribute-for-woocommerce') . '<br/>' .
					__('Some stores want prices with specific numbers below the decimal place (such as xx.00 or xx.95). Rounding percentage markups will keep the value below the decimal intact.',
					'markup-by-attribute-for-woocommerce') . ' <br/>' . '<em>' . $individually . '</em>',
				'id'		=> 'mt2mba_round_markup',
				'type'		=> 'checkbox',
				'default'	=> $this->round_markup
			];

			$mt2mba_settings[] = [
				'type'		=> 'sectionend',
				'id'		=> 'mt2mbaCalcSection'
			];

			// *** Other settings ***
			$mt2mba_settings[] = [
				'name'		=> __('Other', 'markup-by-attribute-for-woocommerce'),
				'type'		=> 'title',
				'id'		=> 'mt2mbaOtherSection'
			];

			/** -- Max Variations --
			 *	Maximum number of variations that can be created per run.
			 *	Use Cautiously: WooCommerce limits the number of linked variations you can create at a time to 50
			 *	to prevent server overload. Setting the number too high can cause timeout errors; you may have to
			 *	experiment. You can always create more by running 'Create variations from all attributes' again.
			 */
			$mt2mba_settings[] = [
				'name'		=> __('Max Variations', 'markup-by-attribute-for-woocommerce'),
				'desc'		=> __('Maximum number of variations that can be created per run.', 'markup-by-attribute-for-woocommerce') . '<br/>' .
				__("<em>Use Cautiously:</em> WooCommerce limits the number of linked variations you can create at a time to 50 to prevent server overload. Setting the number too high can cause timeout errors; you may have to experiment. You can always create more by running 'Create variations from all attributes' again.",
				'markup-by-attribute-for-woocommerce'),
				'id'		=> 'mt2mba_max_variations',
				'type'		=> 'number',
				'custom_attributes' => [
					'min'	=> MT2MBA_DEFAULT_MAX_VARIATIONS,
					'step'	=> 1
				],
				'default'	=> $this->max_variations
			];

			$mt2mba_settings[] = [
				'type'		=> 'sectionend',
				'id'		=> 'mt2mbaOtherSection'
			];

			// *** Donate section ***
			$mt2mba_settings[] = [
				'name'	=> __('Support Markup by Attribute', 'markup-by-attribute-for-woocommerce'),
				'type'	=> 'title',
				'desc'	=> sprintf(
					__('Markup by Attribute is a hobby project I\'ve maintained since 2018. If it\'s saved you time or money, and you\'d like to see it continue, <a href="%1$s" target="_blank">a small donation</a> means a lot. Thank you!', 'markup-by-attribute-for-woocommerce'),
					'https://github.com/Mark-Tomlinson/markup-by-attribute-for-woocommerce/wiki/4.0_Donate'
				),
				'id'	=> 'mt2mbaDonateSection'
			];

			$mt2mba_settings[] = [
				'type'	=> 'sectionend',
				'id'	=> 'mt2mbaDonateSection'
			];

			return $mt2mba_settings;
		} else {
			return $settings;
		}
	}

	/**
	 * Stop this plugin's settings from being autoloaded on every page request
	 *
	 * WooCommerce writes these options with update_option(), which leaves a new
	 * row at WordPress's autoload default of 'yes' — loaded on every request, front
	 * end included, to serve an admin settings page.
	 *
	 * Must run AFTER WooCommerce has written the options: hooked any earlier it
	 * misses rows that do not exist yet, and a first save leaves them autoloaded.
	 * WC has already checked the nonce and capability by the time this fires.
	 *
	 * @since 4.7.0
	 */
	public function setOptionsNotAutoloaded(): void {
		global $wpdb;

		// 'no' rather than 'off': WP 6.6 introduced the on/off/auto-* vocabulary,
		// but min-WP here is 5.7 and 6.6+ still reads 'no' as not-autoloaded.
		// wp_set_option_autoload_values() would be the modern call — it needs 6.4.
		foreach (self::OPTION_NAMES as $option_name) {
			$wpdb->query($wpdb->prepare("
				UPDATE {$wpdb->options}
				SET autoload = 'no'
				WHERE option_name = %s
			", $option_name));
		}
	}
	//endregion

	/**
	 * Validate and sanitize max variations setting
	 *
	 * WordPress filter callback for sanitizing the mt2mba_max_variations option.
	 * Ensures the value is a positive integer >= 50. Rejects invalid input and keeps old value.
	 * No arbitrary upper limit - users know their server capabilities best.
	 *
	 * @since 4.3.10
	 * @param mixed $value The submitted value
	 * @return int Validated value or old value if validation fails
	 */
	public function validateMaxVariations($value): int {
		// Convert to positive integer (handles NULL, empty string, '0000050', etc.)
		$validated = absint($value);

		// Check minimum - no arbitrary maximum
		if ($validated < MT2MBA_DEFAULT_MAX_VARIATIONS) {
			// Get the old value to preserve it
			$old_value = get_option('mt2mba_max_variations', MT2MBA_DEFAULT_MAX_VARIATIONS);

			// Show error message on next page load
			add_action('admin_notices', function() {
				echo '<div class="notice notice-error is-dismissible"><p>' .
					sprintf(
						__('Invalid value. Max Variations must be at least %d.', 'markup-by-attribute-for-woocommerce'),
						MT2MBA_DEFAULT_MAX_VARIATIONS
					) .
					'</p></div>';
			});

			// Return the old value unchanged
			return $old_value;
		}

		return $validated;
	}

}

endif;
