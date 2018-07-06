<?php
/**
 * Contains markup capabilities related to the backend product admin page. Specifically, increase 
 * the variation limit and supply code to override regular and sale prices based on options selected.
 *
 * @author  Mark Tomlinson
 * 
  */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit( );

class MT2MBA_BACKEND_PRODUCT
{
	/**
	 * Initialization method visible before instantiation
	 */
	public static function init()
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
	public function on_loaded()
	{
		// Load settings
		$settings = new MT2MBA_BACKEND_SETTINGS;
		// Override the max variation threshold with value from settings
		define( 'WC_MAX_LINKED_VARIATIONS', $settings->get_max_variations() );
		// Hook mt2mba markup code into bulk actions
		add_action( 'woocommerce_bulk_edit_variations', array( $this, 'mt2mba_apply_markup_to_price' ), 10, 4 );
	}

	/**
	 * Unfortunately have to recreate the increase/decrease price logic found in class WC_AJAX
	 * since those functions are private.
	 * @param   string	$bulk_action	The selection from the variation bulk actions menu
	 * @param   string  $data           The amount or percentage to increase or decrease by
	 * @param   float   $base_price     The original base price that we are changing
	 * @return	float                   The new base price (before markup)
	 */
	private function recalc_base_price( $bulk_action, $data, $base_price )
	{
		// Indicate whether we are increasing or decreasing
		$signed_data = ( strpos( $bulk_action, 'decrease' ) ) ? 0 - floatval( $data ) : floatval( $data );
		// Calc based on whether it is a percentage or fixed number
		if( strpos( $data, '%' ) )
		{
			return $base_price + ($base_price * $signed_data / 100);
		}
		else
		{
			return $base_price + $signed_data;
		}
	}

	/**
	 * Hook into bulk edit actions and adjust price after setting new one
	 * @param string $bulk_action  The selection from the variation bulk actions menu
	 * @param array  $data         Values passed in from JScript pop-up
	 * @param string $product_id   ID of the variable product
	 * @param array  $variations   List of variation IDs for the variable product
	 */
	public function mt2mba_apply_markup_to_price( $bulk_action, $data, $product_id, $variations )
	{
		// Set string for testing and SET functions later
		$price_type     = substr( $bulk_action, 9, strpos( $bulk_action, '_price' ) - 3 );
		
		// Method is hooked into 'woocommerce_bulk_edit_variations', which runs with
		// every bulk edit action. So we only want to execute it if the bulk action
		// is setting the regular or sale price.
		if ( $bulk_action == 'variable_regular_price' || $bulk_action == 'variable_sale_price' )
		{
			// Get settings
			$settings           = new MT2MBA_BACKEND_SETTINGS;
			$desc_behavior      = $settings->get_desc_behavior();
			$decimal_points	    = $settings->get_decimal_points();
			$symbol_before      = $settings->get_currency_symbol_before();
			$symbol_after       = $settings->get_currency_symbol_after();
			// Set markup format
			$currency_format	= "%s%01.{$decimal_points}f%s";

			// Catch original price
			$orig_price        = $data[ 'value' ] ;
			$orig_price_stored = FALSE;

			// Clear out old metadata
			delete_post_meta( $product_id, "base_price_{$price_type}" );
			delete_post_meta( $product_id, '%_markup_amount' );
			delete_post_meta( $product_id, 'mt2mba_%' );
			
			// -- Build markup table --
			// Loop through product attributes
			foreach ( wc_get_product( $product_id )->get_attributes() as $pa_attrb ) {

				// Loop through attribute terms
				foreach ( get_terms( $pa_attrb->get_name() ) as $term ) {
					
					$markup = get_term_meta( $term->term_id, 'mt2mba_markup', TRUE );

					// If term_markup has a value other than zero, add/update the value to the metadata table
					if ( strpos( $markup, "%" ) )
					{
						// Markup is a percentage, calculate against original price
						$markup = sprintf( "%+01.2f", $orig_price * floatval( $markup ) / 100 );
					}
					else
					{
						// Straight markup, get directly from attribute term description
						$markup = floatval( $markup );
					}

					// Set up post metadata key
					$meta_key = 'mt2mba_' . $term->term_id . '_markup_amount';

					// If there is a markup (or markdown) present ...
					if ( $markup <> 0 )
					{
						// Store original price
						if( ! $orig_price_stored )
						{
							update_post_meta( $product_id, "mt2mba_base_{$price_type}", $orig_price );
							$orig_price_stored = TRUE;
						}
						if ( $price_type == 'regular_price' )
						{
							// Format a description of the markup
							if ( $markup > 0 ) {
								$markup_desc_format = "Add ". $currency_format . " for %s";
							} else {
								$markup_desc_format = "Subtract ". $currency_format . " for %s";
							}
							$markup_desc = sprintf( $markup_desc_format, $symbol_before, abs( $markup ), $symbol_after, $term->name );
						
							// Add term, markup, and description to markup table for use below with each variation
    						$markup_table[$term->taxonomy][$term->slug]["markup"] = $markup;
							$markup_table[$term->taxonomy][$term->slug]["description"] = $markup_desc;
							
							// Save actual markup value for term as post metadata for use in product attribute dropdown
							$meta_value = sprintf( "%+g", $markup );
							update_post_meta( $product_id, $meta_key, $meta_value );
						}
					}
					else
					{
						// If no markup present, remove any previous markup metadata
						delete_post_meta( $product_id, $meta_key );
					}
				}
			}
			// -- Parse through variations and reprice --
			// Loop through each variation
			foreach ( $variations as $variation_id )
			{
				$has_orig_price  = FALSE;

				$variation       = wc_get_product( $variation_id );
				$attributes      = $variation->get_attributes();

				// Starting variation price is whatever was passed in
				$variation_price = $orig_price;

				// Trim any previous markup information out of description
				$description     = $variation->get_description();
				$markup_desc_beg = '<span id="mba_markupinfo">';
				$markup_desc_end = '</span>';
				$utility         = new MT2MBA_UTILITY;
				$description     = trim( $utility->remove_pricing_info( $markup_desc_beg, $markup_desc_end, $description ) );

				// Loop through each attribute within variation
				foreach ( $attributes as $attribute_id => $term_id )
				{
					// Does this variation have a markup?
					if ( isset( $markup_table[$attribute_id][$term_id] ) )
					{
						// Add markup to price
						$markup = (float)$markup_table[$attribute_id][$term_id]["markup"];
						$variation_price = $variation_price + $markup;

						// Make sure markup wasn't a reduction that creates
						// a negative price, then set price accordingly
						if ( $variation_price > 0 )
						{
							$variation->{"set_$price_type"}( $variation_price );
						}
						else
						{
							$variation->{"set_$price_type"}( 0.00 );
						}

						// Update description if Descritption Behavior is NOT 'ignore'.
						if ( ! ($desc_behavior == 'ignore') )
						{
							// Build description (for regular price calculation only)
							if ( $price_type == 'regular_price' )
							{
								// Put regular price in description if absent
								if ( ! $has_orig_price )
								{
									if ( $desc_behavior == 'overwrite' )
									{
										// Start with an empty description
										$description = "";
									}
									// Set markup opening tag
									$description .= PHP_EOL . $markup_desc_beg;
									// Open description with original price
									$description .= sprintf( "Product price {$currency_format}", $symbol_before, $orig_price, $symbol_after ) . PHP_EOL;
									// Flip flag
									$has_orig_price = TRUE;
								}
								// Add markup description to variation description
								$description .= $markup_table[$attribute_id][$term_id]["description"] . PHP_EOL;
							}
						}
					}
				}	// End attribute loop

				// Rewrite variation description if setting the regular price
				if ( $price_type == 'regular_price' )
				{
					if ( strpos( $description, $markup_desc_beg ) )
					{
						// Close markup tags 
						$description .= $markup_desc_end;
					}
					// Rewrite description
					$variation->set_description( trim( $description ) );
				}
				// And save
				$variation->save();
			}	// END variation loop
		}
		else
		{
			// Bulk action is not setting a price. Is it to increase or decrease a price?
			if( strpos( $bulk_action, 'price_increase' ) || strpos( $bulk_action, 'price_decrease' ) )
			{
				// If base price metadata is present, that means the product contains variables with attribute pricing.
				if ( $base_price = get_metadata( 'post', $product_id, "mt2mba_base_{$price_type}", TRUE ) )
				{
					// Recalculate a new base price according to the bulk action.
					// Bulk action could be any of
					//     * variable_regular_price_increase
					//     * variable_regular_price_decrease
					//     * variable_sale_price_increase
					//     * variable_sale_price_decrease
					$new_data[ 'value' ] = $this->recalc_base_price( $bulk_action, $data[ 'value' ], $base_price );
					// And then loop back through this very same function, changing the bulk action type to
					// one of the two 'set price' options. This will reset the prices on all variations to the
					// new base regular/sale price plus the attribute markup.
					$this->mt2mba_apply_markup_to_price( "variable_{$price_type}", $new_data, $product_id, $variations );
				}
			}
		}
		
	}	// END function mt2mba_apply_markup_to_price


}	// End  class MT2MBA_PRODUCT_BACKEND

?>