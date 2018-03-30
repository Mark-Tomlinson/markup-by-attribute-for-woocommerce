<?php
/**
 * Filename:	class_markup_backend_product.php
 * 
 * Description:	Contains markup capabilities related to the backend product admin page. Specifically, increase the variation limit and supply code to override regular and sale prices based on options selected.
 * Author:     	Mark Tomlinson
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit( );

class MT2MBA_BACKEND_PRODUCT {

	var $max_variations		=	250;	// The maximum number or variation created per run.

	/**
	 * Initialization method visible before instantiation
	 */
	public static function init( ) {
		
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
	public function on_loaded() {

		// Override the max variation threshold
		define( 'WC_MAX_LINKED_VARIATIONS', $this->get_max_variations( ) );
		
		// Hook mt2mba markup code into bulk actions
		add_action( 'woocommerce_bulk_edit_variations', array( $this, 'mt2mba_apply_markup_to_price' ), 10, 4 );
	}

	public function set_max_variations( $mxvar ) {
 		$this->max_variations = $mxvar;
	}
 
 	public function get_max_variations( ) {
		return $this->max_variations;
	}
	
	/*
	 * Hook into bulk edit actions and adjust price afer setting new one
	 */
	public function mt2mba_apply_markup_to_price( $bulk_action, $data, $product_id, $variations ) {
		
		// Method is hooked into 'woocommerce_bulk_edit_variations', which runs with
		// every bulk edit action. So we only want to execute it if the bulk action
		// is setting the regular or sale price.
		if ( $bulk_action == 'variable_regular_price' || $bulk_action == 'variable_sale_price' ) {

			// Set string for testing and SET functions later
			$price_type     = substr( $bulk_action, 9 );
			$orig_price     = $data['value'];

			// -- Build markup table --
			// Loop through product attributes
			foreach ( wc_get_product( $product_id )->get_attributes() as $pa_attrb ) {

				// Loop through attribute terms
				foreach ( get_terms( $pa_attrb->get_name() ) as $term ) {
					
					// Get markup from attribute term description
					$markup = (float)get_term_meta( $term->term_id, 'markup', TRUE );
					
					// If there is a markup (or markdown) present ...
					if ( $markup <> 0 ) {
						
						// Format a description of the markup
						if ( $markup > 0 ) {
							$markup_desc_format = "Add $%01.2f for %s";
						} else {
							$markup_desc_format = "Subtract $%01.2f for %s";
						}
						$markup_desc = sprintf( $markup_desc_format, abs( $markup ), $term->name );
						
						// Add term, markup, and description to markup table
    					$markup_table[$term->taxonomy][$term->slug]["markup"] = $markup;
    					$markup_table[$term->taxonomy][$term->slug]["description"] = $markup_desc;
					}
				}
			}

			// -- Parse through variations and reprice --
			// Loop through each variation
			foreach ( $variations as $variation_id ) {

				$variation       = wc_get_product( $variation_id );
				$attributes      = $variation->get_attributes();
				$description     = '';
				$variation_price = $variation->{ "get_$price_type" }( 'edit' );

				// There seems to be a bug in WooCommerce where sometimes sale_price isn't set
				// In that case, we want to leave it alone and not calculate a markup
				if ( is_numeric( $variation_price ) ) {

					// Loop through each attribute within variation
					foreach ( $attributes as $attribute_id => $term_id ) {

						// Does this variation have a markup?
						if ( isset( $markup_table[$attribute_id][$term_id] ) ) {

							// Put regular price in description if not present
							if ( $description == '' ) {
								$description = sprintf( "Product price $%01.2f", $orig_price ) . PHP_EOL;
							}

							// Add markup to price
							$markup = (float)$markup_table[$attribute_id][$term_id]["markup"];
							$variation_price = $variation_price + $markup;

							// Make sure markup wasn't a reduction that creates
							// a negative price, then set price accordingly
							if ( $variation_price > 0 ) {
								$variation->{"set_$price_type"}( $variation_price );
							} else {
								$variation->{"set_$price_type"}( 0.00 );
							}

							// Add markup description to variation description
							$description .= $markup_table[$attribute_id][$term_id]["description"] . PHP_EOL;
						}
					}	// End attribute loop
				
					// Rewrite variation description if setting the regular price and trim unwanted EOL
					if ( $price_type == 'regular_price' ) {
						$variation->set_description( rtrim( $description, PHP_EOL ) );
					}

					// And save
					$variation->save( );
				} // END if is_numeric( $variation_price )

			}	// END variation loop
			
		}	// END if bulk_action
		
	}	// END function mt2mba_apply_markup_to_price


}	// End  class MT2MBA_PRODUCT_BACKEND

?>