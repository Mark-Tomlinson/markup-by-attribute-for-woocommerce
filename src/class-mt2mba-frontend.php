<?php
/**
 * Set the dropdown box with available options and the associated markup.
 * @author	Mark Tomlinson
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit( );

class MT2MBA_FRONTEND {

	/**
	 * Initialization method visible before instantiation
	 */
	public static function init( )
	{
		// As a static method, it can not use '$this' and must use an
		// instantiated version of itself
		$self	= new self( );
		// Set initialization method to run on 'wp_loaded'.
		add_filter( 'wp_loaded', array( $self, 'on_loaded' ) );
	}

	/**
	 * Hook into Wordpress and WooCommerce
	 * Method runs on 'wp_loaded' hook
	 */
	public function on_loaded( )
	{
		// Hook dropdown box build into product page
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( $this, 'mt2mba_dropdown_options_markup_html' ), 10, 2);
	}

	/**
	 * The hooked function that will add the variation description to the dropdown options elements
	 * @param	string	$html	The HTML of the options drop-down box
	 * @param	array	$args   Array of available options
	 * @return	string          The modified HTML of the options drop-down box
	 */
	public function mt2mba_dropdown_options_markup_html( $html, $args )
	{
		// Extract all needed content from $args
		$options                = $args['options'];
		$product                = $args['product'];
		$attribute              = $args['attribute'];
		$name                   = $args['name'] ? $args['name'] : 'attribute_' . sanitize_title( $attribute );
		$id                     = $args['id'] ? $args['id'] : sanitize_title( $attribute );
		$class                  = $args['class'];
		$show_option_none       = $args['show_option_none'] ? TRUE : FALSE;
		$show_option_none_text  = $args['show_option_none'] ? $args['show_option_none'] : __( 'Choose an option', 'woocommerce' ); 

		// Get settings
		$settings               = new MT2MBA_BACKEND_SETTINGS;
		$decimal_points         = $settings->get_decimal_points();
		// Get currency symbol if required
		if ( $settings->get_dropdown_behavior() == 'add' )
		{
			$symbol_before      = $settings->get_currency_symbol_before();
			$symbol_after       = $settings->get_currency_symbol_after();
		}
		else
		{
			$symbol_before      = '';
			$symbol_after       = '';
		}
		// Set markup format
		$markup_format          = " (%s%s%01.{$decimal_points}f%s)";

		// If $options is empty, get them from the product attributes
		if ( empty( $options ) && !empty( $product ) && !empty( $attribute ) )
		{
			$attributes			= $product->get_variation_attributes();
			$options			= $attributes[ $attribute ];
		}

		// Start building output HTML
		// Open <SELECT> and set 'Choose an option' <OPTION> text
		$html =
			PHP_EOL .
			'<select id="' . esc_attr( $id ) . 
			'" class="' . esc_attr( $class ) .
			'" name="' . esc_attr( $name ) .
			'" data-attribute_name="attribute_'	. esc_attr( sanitize_title( $attribute ) ) .
			'" data-show_option_none="' . ( $show_option_none ? 'yes' : 'no' ) . '">' .
			PHP_EOL .
			'<option value="">' . esc_html( $show_option_none_text ) . '</option>';

		// Build <OPTION>s within <SELECT>
		if ( !empty( $options ) )
		{
			if ( $product && taxonomy_exists( $attribute ) )
			{
				// Need to add option with markup if present
				$terms = wc_get_product_terms( $product->get_id( ), $attribute, array( 'fields' => 'all' ) );
				foreach ( $terms as $term )
				{
					// Add markup if present
					if ( in_array( $term->slug, $options ) )
					{
						// Add markup if metadata exists, else leave blank
						if ( ! $markup = get_metadata( 'post', $product->get_id(), 'mt2mba_' . $term->term_id . '_markup_amount', TRUE ) )
						{
							$markup = get_metadata( 'term', $term->term_id, 'mt2mba_markup', TRUE );
						}

						// ... and format it properly or null it if empty
						$add_sub_sign = $markup > 0 ? "+" : "-";
						$markup = $markup ? sprintf( $markup_format, $add_sub_sign, $symbol_before, abs( $markup ), $symbol_after ) : '';

						// And build <OPTION> into $html
						$html .= PHP_EOL .
							'<option value="' .
							esc_attr( $term->slug ) . '"' .
							selected( sanitize_title( $args['selected'] ), $term->slug, false ) . '>' .
							esc_html( apply_filters( 'woocommerce_variation_option_name', $term->name ) ) .
							esc_html( $markup ) .
							'</option>';
					}
				}
			}
			else
			{
				// Need only add option, no markups available
				foreach ( $options as $option )
				{
					$html .= PHP_EOL . '<option value="' .
						esc_attr( $option ) . '"' .
						selected( $args['selected'], sanitize_title( $option ), false ) . '>' .
						esc_html( apply_filters( 'woocommerce_variation_option_name', $option ) ) .
						'</option>';
				}
			}
		}
		// Close <SELECT> and return HTML
		return $html . PHP_EOL . '</select>';
	}	// END function mt2mba_dropdown_options_markup_html()

}	// END class MT2MBA_MARKUP_FRONTEND

?>