<?php
namespace mt2Tech\MarkupByAttribute\Backend;
use WC_Settings_API;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

if (!class_exists('Settings')) :

class Settings extends WC_Settings_API {
	/**
	 * Singleton because we only want one instance at a time.
	 */
	private static $instance = null;

	// Default values as properties
	public $desc_behavior		= 'append';
	public $dropdown_behavior	= 'add';
	public $include_attrb_name	= 'no';
	public $hide_base_price		= 'no';
	public $sale_price_markup	= 'yes';
	public $round_markup		= 'no';
	public $allow_zero			= 'no';
	public $max_variations		= 50;

	// Public method to get the instance
	public static function get_instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// Prevent cloning of the instance
	public function __clone() {}

	// Prevent unserializing of the instance
	public function __wakeup() {}

	// Private constructor
	private function __construct() {
		add_filter('woocommerce_get_sections_products', array($this, 'add_section'));
		add_filter('woocommerce_get_settings_products', array($this, 'get_settings'), 10, 2);
	}

	/**
	 * Add a new section to the Product settings tab.
	 *
	 * @param	array	$sections	Existing sections.
	 * @return	array				Sections with new section added.
	 */
	public function add_section($sections) {
		$sections['mt2mba'] = __('Markup by Attribute', 'markup-by-attribute');
		return $sections;
	}

	/**
	 * Get settings array.
	 *
	 * @param	array	$settings			Existing settings.
	 * @param	string	$current_section	Current section name.
	 * @return	array
	 */
	public function get_settings($settings, $current_section) {
		if ('mt2mba' === $current_section) {
			// Repeating strings
			$immediately = __('This setting affects all products and takes effect immediately.', 'markup-by-attribute');
			$individually = __('This setting affects products individually and takes effect when you recalculate prices or reapply markups.', 'markup-by-attribute');

			// Create settings array
			$mt2mba_settings = array();

			// Add title to the settings page
			$mt2mba_settings[] = array(
				'name'		=> __('Markup by Attribute Settings', 'markup-by-attribute'),
				'type'		=> 'title',
				'desc'		=> __('The following options are used to configure variation markups by attribute.', 'markup-by-attribute') . ' ' .
					sprintf (
						__('Additional help can be found in the <a href="%1$s" target="_blank">Markup by Attribute wiki</a> on the <code>Settings</code> page.', 'markup-by-attribute'),
						'https://github.com/Mark-Tomlinson/markup-by-attribute-for-woocommerce/wiki'
					) . '<br\>' .
					sprintf (
						__('If you find this plugin helpful, please consider <a href="%1$s" target="_blank">a small donation</a>.', 'markup-by-attribute'),
						'https://github.com/Mark-Tomlinson/markup-by-attribute-for-woocommerce/wiki/Donate'
					),
				'id'	=> 'mt2mba'
			);

			// *** Display settings ***
			$mt2mba_settings[] = array(
				'name'	=> __('Markup Display', 'markup-by-attribute'),
				'type'	=> 'title',
				'id'	=> 'mt2mba_display'
			);

			/** -- Option Drop-down Behavior --
			 *	Should Markup-by-Attribute add the markup to the options drop-down box, and should the currency
			 *	symbol be displayed?
			 *	This setting affects all products and takes effect immediately.
			 */
			$mt2mba_settings[] = array(
				'name'		=> __('Option Drop-down Behavior', 'markup-by-attribute'),
				'desc'		=> __('Should Markup-by-Attribute add the markup to the options drop-down box, and should the currency symbol be displayed?', 'markup-by-attribute') . '<br/>' .
					'<em>' . $immediately . '</em>',
				'id'		=> 'mt2mba_dropdown_behavior',
				'type'		=> 'radio',
				'options'	=> array(
					'hide'			=> __('Do NOT show the markup in the options drop-down box.', 'markup-by-attribute'),
					'add'			=> __('Show the markup WITH the currency symbol in the options drop-down box.', 'markup-by-attribute'),
					'do_not_add'	=> __('Show the markup WITHOUT the currency symbol in the options drop-down box.', 'markup-by-attribute'),
				),
				'default'	=> $this->dropdown_behavior
			);

			/** -- Variation Description Behavior --
			 *	How should Markup-by-Attribute handle adding price markup information to the product variation
			 *	description?
			 */
			$mt2mba_settings[] = array(
				'name'		=> __('Variation Description Behavior', 'markup-by-attribute'),
				'desc'		=> __('How should Markup-by-Attribute handle adding price markup information to the product variation description?', 'markup-by-attribute') . '<br/>' .
					'<em>' . $individually . '</em>',
				'id'		=> 'mt2mba_desc_behavior',
				'type'		=> 'radio',
				'options'	=> array(
					'ignore'	=> __('Do NOT add pricing information to the description field.', 'markup-by-attribute'),
					'append'	=> __('Add pricing information to the end of the existing description.', 'markup-by-attribute'),
					'overwrite' => __('Overwrite the variation description with price information.', 'markup-by-attribute'),
				),
				'default'	=> $this->desc_behavior
			);

			/** -- Include Attribute Name --
			 *	Include the name of the attribute in the variatiable product's decription. 'Add $1.50 for Blue' becomes 'Add $1.50 for Color Blue'.
			 */
			$mt2mba_settings[] = array(
				'name'		=> __('Include Attribute Names in Variation Descriptions', 'markup-by-attribute'),
				'desc'		=> __("Include the name of the attribute in the variable product's description. <b>Add $1.50 for Blue</b> becomes <b>Add $1.50 for Color: Blue</b>.", 'markup-by-attribute') . ' <br/>' .
					'<em>' . $individually . '</em>',
				'id'		=> 'mt2mba_include_attrb_name',
				'type'		=> 'checkbox',
				'default'	=> $this->include_attrb_name
			);

			/** -- Hide Base Price --
			 *	Do NOT show the base price in the product description.
			 *	This setting affects products individually and takes effect when you recalculate the regular price
			 *	for the product.
			 */
			$mt2mba_settings[] = array(
				'name'		=> __('Hide Base Price', 'markup-by-attribute'),
				'desc'		=> __('Do NOT show the base price in the product description.', 'markup-by-attribute') . ' <br/>' .
					'<em>' . $individually . '</em>',
				'id'		=> 'mt2mba_hide_base_price',
				'type'		=> 'checkbox',
				'default'	=> $this->hide_base_price
			);

			$mt2mba_settings[] = array(
				'type'		=> 'sectionend',
				'id'		=> 'mt2mba_display'
			);

			// *** Markup Calculation settings ***
			$mt2mba_settings[] = array(
				'name'		=> __('Markup Calculation', 'markup-by-attribute'),
				'type'		=> 'title',
				'id'		=> 'mt2mba_calc'
			);

			/** -- Sale Price Markup --
			 *	Should Markup-by-Attribute calculate percentage markups on sale prices?
			 *	A 10% markup on a $30 regular price yields a $3 markup. If you set a $20 sale price, setting this
			 *	option ON yields a $2 markup, setting it OFF leaves the markup at $3.
			 *	This setting affects products individually and takes effect when you recalculate the sale price for
			 *	the product.
			 */
			$mt2mba_settings[] = array(
				'name'		=> __('Sale Price Markup', 'markup-by-attribute'),
				'desc'		=> __('Should Markup-by-Attribute calculate percentage markups on sale prices?', 'markup-by-attribute') . ' <br/>' .
					__('A 10% markup on a $30 regular price yields a $3 markup. If you set a $20 sale price, setting this option ON yields a $2 markup, setting it OFF leaves the markup at $3.',
					'markup-by-attribute') . ' <br/>' . '<em>' . $individually . '</em>',
				'id'		=> 'mt2mba_sale_price_markup',
				'type'		=> 'checkbox',
				'default'	=> $this->sale_price_markup
			);

			/** -- Round Markup --
			 *	Round percentage markups to keep the value below the decimal intact?
			 *	Some stores want prices with specific numbers below the decimal place (such as xx.00 or xx.95).
			 *	Rounding percentage markups will keep the value below the decimal intact.
			 *	This setting affects products individually and takes effect when you recalculate the regular price
			 *	for the product.
			 */
			$mt2mba_settings[] = array(
				'name'		=> __('Round Markup', 'markup-by-attribute'),
				'desc'		=> __('Round percentage markups to keep the value below the decimal intact?', 'markup-by-attribute') . '<br/>' .
					__('Some stores want prices with specific numbers below the decimal place (such as xx.00 or xx.95). Rounding percentage markups will keep the value below the decimal intact.',
					'markup-by-attribute') . ' <br/>' . '<em>' . $individually . '</em>',
				'id'		=> 'mt2mba_round_markup',
				'type'		=> 'checkbox',
				'default'	=> $this->round_markup
			);

			/** -- Allow Zero Base Price --
			 *  Should Markup-by-Attribute process variations with zero base price?
			 *  Some stores use markups to set the entire price, setting the base price to zero.
			 *  Others want zero-priced variations to remain at zero for giveaways. This setting
			 *  lets you choose which behavior you want.
			 */
			$mt2mba_settings[] = array(
				'name'	=> __('Allow Zero Price', 'markup-by-attribute'),
				'desc'	=> __('Should Markup-by-Attribute ignore variations with a zero price?', 'markup-by-attribute') . '<br/>' .
					__('When set OFF, markup calculations proceed normally even when the base price is zero. This allows using attributes to determine the entire price.', 'markup-by-attribute') . '<br/>' .
					__('When set ON, variations with zero prices remain at zero, ignoring any markups. This preserves zero prices for giveaway items.', 'markup-by-attribute') . ' <br/>' .
					'<em>' . $individually . '</em>',
				'id'	  => 'mt2mba_allow_zero',
				'type'	=> 'checkbox',
				'default' => $this->allow_zero
			);

			$mt2mba_settings[] = array(
				'type'		=> 'sectionend',
				'id'		=> 'mt2mba_calc'
			);

			// *** Other settings ***
			$mt2mba_settings[] = array(
				'name'		=> __('Other', 'markup-by-attribute'),
				'type'		=> 'title',
				'id'		=> 'mt2mba_other'
			);

			/** -- Max Variations --
			 *	Maximum number of variations that can be created per run.
			 *	Use Cautiously: WooCommerce limits the number of linked variations you can create at a time to 50
			 *	to prevent server overload. Setting the number too high can cause timeout errors; you may have to
			 *	experiment. You can always create more by running 'Create variations from all attributes' again.
			 */
			$mt2mba_settings[] = array(
				'name'		=> __('Max Variations', 'markup-by-attribute'),
				'desc'		=> __('Maximum number of variations that can be created per run.', 'markup-by-attribute') . '<br/>' .
					__('<em>Use Cautiously:</em> WooCommerce limits the number of linked variations you can create at a time to 50 to prevent server overload. ' .
					'Setting the number too high can cause timeout errors; you may have to experiment. ' .
					'You can always create more by running \'Create variations from all attributes\' again.', 'markup-by-attribute'),
				'id'		=> 'mt2mba_max_variations',
				'type'		=> 'number',
				'custom_attributes' => array(
					'min'	=> 50,
					'step'	=> 1
				),
				'default'	=> $this->max_variations
			);

			$mt2mba_settings[] = array(
				'type'		=> 'sectionend',
				'id'		=> 'mt2mba_other'
			);

			return $mt2mba_settings;
		} else {
			return $settings;
		}
	}
}

endif;