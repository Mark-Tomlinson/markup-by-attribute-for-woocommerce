<?php
/**
 * MT2MBA_Price_Markup_Handler creates an abstract shell class with basic Markup-by-Attribute
 * product variation functions. It is extended by the appropriate classes depending on which
 * bulk editing functions are being invoked.
 */
abstract class MT2MBA_Price_Markup_Handler
{
    protected $price_type;
    protected $product_id;
    protected $base_price;
    protected $base_price_formatted;

	/**
	 * MT2MBA_Price_Markup_Handler constructor. Creates variables used throughout.
	 * @param	string	$bulk_action	The selection from the variation bulk actions' menu.
	 * @param	string	$product_id		ID of the variable product.
	 * @param	float	$base_price		The original base price of the product.
	 */
    public function __construct($bulk_action, $product_id, $base_price)
    {
        // Extract price_type from bulk_action
        if ($bulk_action) {
            $bulk_action_array = explode("_", $bulk_action);
            $this->price_type = $bulk_action_array[1] . "_" . $bulk_action_array[2];
        }
        // Save product_id as class variable
        $this->product_id = $product_id;
        // Save base_price as class variable
        $this->base_price = $base_price;
        // Format base_price into local currency format
        $this->base_price_formatted = strip_tags(wc_price(abs($this->base_price)));
    }

	/**
	 * Apply markup to product price
	 * @param	string	$price_type	The type of price (regular or sale).
	 * @param	float	$base_price	The base price of the product.
	 * @param	string	$product_id	ID of the product.
	 * @param	array	$variations	List of product variations.
	 */
    abstract public function applyMarkup($price_type, $data, $product_id, $variations);
}

/**
 * Concrete class for handling product price setting, which extends MT2MBA_Price_Markup_Handler
 * and overrides its abstract methods.
 */
class MT2MBA_Price_Set_Handler extends MT2MBA_Price_Markup_Handler
{
	/**
	 * MT2MBA_Price_Set_Handler constructor.
	 * @param	string	$bulk_action	The selection from the variation bulk actions menu.
	 * @param	array	$data		Values passed in from JScript pop-up.
	 * @param	string	$product_id	ID of the variable product.
	 * @param	array	$variations List of variation IDs for the variable product.
	 */
    public function __construct($bulk_action, $data, $product_id, $variations)
    {
        parent::__construct($bulk_action, $product_id, is_numeric($data["value"]) ? (float) $data["value"] : 0);
        $this->price_decimals = MT2MBA_ROUND_MARKUP == "yes" ? 0 : wc_get_price_decimals();
    }

	/**
	 * Build a table of markups and their descriptions
	 * @param	array	$terms		Array of term objects.
	 * @param	string	$product_id	ID of the variable product.
	 * @return	array				An array containing term markups and descriptions.
	 */
    protected function build_markup_table($terms, $product_id)
    {
        global $mt2mba_utility;
        $markup_table = [];

        foreach ($terms as $term) {
            // Set metadata key for product (post) metadata table
            $meta_key = "mt2mba_{$term->term_id}_markup_amount";

            // Get current markup for attribute term meta table
            $markup = get_term_meta($term->term_id, "mt2mba_markup", true);

            // If there isn't a markup...
            if (empty($markup)) {
                // No markup, remove previous product markup metadata
                delete_post_meta($product_id, $meta_key);

            } else {
                // If this is a regular_price markup, or we are allowed to do sale_price markups
                if (
                    $this->price_type === "regular_price" ||
                    MT2MBA_SALE_PRICE_MARKUP === "yes"
                ) {
                    // Calculate new markup_value
                    if (strpos($markup, "%")) {
                        // Markup is a percentage, calculate against the original price, rounding as necessary
                        $markup_value = round(($this->base_price * floatval($markup)) / 100, $this->price_decimals);
                    } else {
                        // Straight markup, get directly from attribute term description
                        $markup_value = floatval($markup);
                    }

                    // Under certain rounding conditions, the markup might be zero
                    if ($markup_value) {
                        // Write new markup_value if it is non-zero
                        update_post_meta($product_id, $meta_key, $markup_value);

                        // Then, update markup_table with new markup
                        $markup_table[$term->taxonomy][$term->slug]['markup'] = $markup_value;
                        if (MT2MBA_DESC_BEHAVIOR !== "ignore") {
                            $markup_table[$term->taxonomy][$term->slug]['description'] =
                                $mt2mba_utility->format_description_markup($markup_value, $term->name);
                        }
                    } else {
                        // Delete any potential old markup_values if it is zero
                        delete_post_meta($product_id, $meta_key);
                    }

                // Not a regular_price markup and we are not doing sale price_markups, so just use what we already have
                } else {
                    $markup_table[$term->taxonomy][$term->slug]['markup'] = get_metadata("post", $product_id, $meta_key, true);
                }
            }
        }
        return $markup_table;
    }

	/**
	 * Apply markup to product price and set it.
	 * @param	string	$bulk_action	The selection from the variation bulk actions menu.
	 * @param	array	$data			Values passed in from JScript pop-up.
	 * @param	string	$product_id		ID of the variable product.
	 * @param	array	$variations 	List of variation IDs for the variable product.
	 */
    public function applyMarkup($bulk_action, $data, $product_id, $variations) {
        // Get globals
        global $mt2mba_utility;
        $mt2mba_utility->get_mba_globals();

        // If sale price is zero... (Using type coercion to force non-numeric to zero)
        if ($this->base_price == 0 && $this->price_type === "sale_price") {
            // Clear out old base price metadata
            delete_post_meta($product_id, "mt2mba_base_{$this->price_type}");

            // And, if description potentially contains sale price markups...
            // Switch price to regular and circle back through to clean up markup values and descriptions
            $this->price_type = "regular_price";
            $data['value'] = get_metadata("post", $product_id, "mt2mba_base_{$this->price_type}", true);
            $handler = new MT2MBA_Price_Set_Handler("variable_{$this->price_type}", $data, $product_id, $variations);
            $handler->applyMarkup($bulk_action, $data, $product_id, $variations); // Recurse through this function

            return; // Ignore the rest of this function, since we already went through it above.
        }

        // -- 1) Build markup table --
        $markup_table = [];
        $all_terms = [];

        // Loop through product attributes
        foreach (wc_get_product($product_id)->get_attributes() as $pa_attrb) {
            $taxonomy = $pa_attrb->get_name();
            // Retrieve all attribute terms
            $terms = get_terms(["taxonomy" => $taxonomy, "hide_empty" => false]);
            $all_terms = array_merge($all_terms, $terms);
        }
        $markup_table = $this->build_markup_table($all_terms, $product_id);

        // Exit if no markups
        if (empty($markup_table)) {
            return;
        }

        // 2) -- Store the original product price --
        if ($this->base_price !== 0) update_post_meta($product_id, "mt2mba_base_{$this->price_type}", $this->base_price);

        // Create a description of the base_price
        $base_price_description = MT2MBA_HIDE_BASE_PRICE === 'no' ? html_entity_decode(MT2MBA_PRICE_META . $this->base_price_formatted) : '';

        // 3) -- Parse through variations and reprice --
        foreach ($variations as $variation_id) {
            $variation = wc_get_product($variation_id);

            // Loop through each attribute within variation and adjust price and description
            $variation_price = $this->base_price;
            $markup_description = '';
            $attributes = $variation->get_attributes();
            foreach ($attributes as $attribute_id => $term_id) {
                // Does this variation have a markup?
                if (isset($markup_table[$attribute_id][$term_id])) {
                    // Add markup to price
                    $markup = (float) $markup_table[$attribute_id][$term_id]["markup"];
                    $variation_price += $markup;

                    // Add markup description to variation description, if there is one
                    if (isset($markup_table[$attribute_id][$term_id]["description"])) {
                        $markup_description .= PHP_EOL . $markup_table[$attribute_id][$term_id]["description"];
                    }
                }
            } // End attribute loop

            // Make sure markup wasn't a reduction that creates a negative price, then set price accordingly.
            $variation_price = max($variation_price, 0);
            $variation->{"set_$this->price_type"}($variation_price);

            if ($this->price_type === 'regular_price' || MT2MBA_SALE_PRICE_MARKUP === "yes") {
                // Clear existing markup from description
                if (MT2MBA_DESC_BEHAVIOR === "overwrite") {
                    // Start with an empty description if we're overwriting the existing one
                    $description = "";
                } else {
                    // Or, trim any previous markup information out of the existing description
                    // (Do this even if DESC_BEHAVIOR is 'ignore')
                    $description = $variation->get_description();
                    $description = $mt2mba_utility->remove_bracketed_string(PRODUCT_MARKUP_DESC_BEG, PRODUCT_MARKUP_DESC_END, $description);
                }

                //  Add markup_description if there is a markup.
                if ($markup_description) {
                    // Add markup description
                    $description .=
                        PHP_EOL .
                        PRODUCT_MARKUP_DESC_BEG .
                        $base_price_description .
                        $markup_description .
                        PRODUCT_MARKUP_DESC_END;
                }

                // Set new description
                $variation->set_description(trim($description));
            }

            // And save the variation
            $variation->save();
        } // END variation loop
    }
}

/**
 * Concrete class for handling product price increase/decrease, which extends
 * MT2MBA_Price_Markup_Handler and overrides its abstract methods.
 */
class MT2MBA_Price_Update_Handler extends MT2MBA_Price_Markup_Handler
{
	/**
	 * MT2MBA_Price_Update_Handler constructor.
	 * @param	string	$bulk_action	The selection from the variation bulk actions menu.
	 * @param	array	$data		Values passed in from JScript pop-up.
	 * @param	string	$product_id	ID of the variable product.
	 * @param	array	$variations List of variation IDs for the variable product.
	 */
    public function __construct($bulk_action, $data, $product_id, $variations)
    {
        parent::__construct($bulk_action, $product_id, is_numeric($data["value"]) ? (float) $data["value"] : 0);
    }

	/**
	 * Recalculate base price based on bulk action and markup.
	 * @param	string	$bulk_action	The selection from the variation bulk actions menu.
	 * @param	string	$markup         The amount or percentage to increase or decrease by.
	 * @param	float	$base_price     The original base price that we are changing.
	 * @return	float                   The new base price (before markup).
	 */
    private function recalc_base_price($bulk_action, $markup, $base_price)
    {
        // Indicate whether we are increasing or decreasing
        $signed_data = strpos($bulk_action, "decrease") ? 0 - floatval($markup) : floatval($markup);
        // Calc based on whether it is a percentage or fixed number
        if (strpos($markup, "%")) {
            return $base_price + ($base_price * $signed_data) / 100;
        } else {
            return $base_price + $signed_data;
        }
    }

	/**
	 * Increase or decrease product price and apply markup.
	 * @param	string	$bulk_action	The selection from the variation bulk actions menu.
	 * @param	array	$data			Values passed in from JScript pop-up.
	 * @param	string	$product_id		ID of the variable product.
	 * @param	array	$variations 	List of variation IDs for the variable product.
	 */
    public function applyMarkup($bulk_action, $data, $product_id, $variations)
    {
        // If base price metadata is present, that means the product contains variables with attribute pricing.
        $base_price = get_metadata("post", $product_id, "mt2mba_base_{$this->price_type}", true);
        if ($base_price) {
            // Recalculate a new base price according to the bulk action.
            // Bulk action could be any of
            //     * variable_regular_price_increase
            //     * variable_regular_price_decrease
            //     * variable_sale_price_increase
            //     * variable_sale_price_decrease
            $new_data = [];
            $new_data["value"] = $this->recalc_base_price($bulk_action, $data["value"], $base_price);
            // And then loop back through changing the bulk action type to one of the two 'set price' options.
            // This will reset the prices on all variations to the new base regular/sale price plus the
            // attribute markup.
            //     * variable_regular_price
            //     * variable_sale_price
            $handler = new MT2MBA_Price_Set_Handler("variable_{$this->price_type}", $new_data, $product_id, $variations);
            $handler->applyMarkup($bulk_action, $data, $product_id, $variations);
        }
    }
}

/**
 * Concrete class for handling product markup deletion, which extends
 * MT2MBA_Price_Markup_Handler and overrides its abstract methods.
 */
class MT2MBA_Markup_Delete_Handler extends MT2MBA_Price_Markup_Handler
{
	/**
	 * MT2MBA_Markup_Delete_Handler constructor. Does nothing (required to prevent parent::__construct() from firing).
	 * @param	string	$var1   Empty string to satisfy $handler->applyMarkup().
	 * @param	string	$var2   Empty string to satisfy $handler->applyMarkup().
	 * @param	string	$var3   The product whose metadata is to be deleted.
	 * @param	array	$var4   Empty array to satisfy $handler->applyMarkup().
	 */
    public function __construct($var1, $var2, $var3, $var4)
    {
        // Nothing here (required to prevent parent::__construct() from firing)
    }

	/**
	 * Delete all Markup-by-Attribute metadata for product whose variations are deleted 
	 * @param	string	$var1       Empty string to satisfy $handler->applyMarkup().
	 * @param	string	$var2       Empty string to satisfy $handler->applyMarkup().
	 * @param	string	$product_id The product whose metadata is to be deleted.
	 * @param	array	$var4       Empty array to satisfy $handler->applyMarkup().
	 */
    public function applyMarkup($var1, $var2, $product_id, $var4)
    {
        // Delete all Markup-by-Attribute metadata for product
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->postmeta} WHERE post_id = '{$product_id}' AND meta_key LIKE 'mt2mba_%'"
        );
    }
}

/**
 * Main class for handling product backend actions, such as hooking into WordPress and WooCommerce
 * to apply markup to product prices based on various bulk actions.
 */
class MT2MBA_BACKEND_PRODUCT
{
	/**
	 * Initialization method visible before instantiation.
	 */
    public static function init()
    {
        // As a static method, it can not use '$this' and must use an
        // instantiated version of itself
        $self = new self();
        // Set initialization method to run on 'wp_loaded'.
        add_filter("wp_loaded", [$self, "on_loaded"]);
    }

	/**
	 * Hook into WordPress and WooCommerce. Method runs on 'wp_loaded' hook.
	 */
    public function on_loaded()
    {
        // Load settings
        $settings = new MT2MBA_BACKEND_SETTINGS();
        // Override the max variation threshold with value from settings
        if (!defined("WC_MAX_LINKED_VARIATIONS")) {
            define("WC_MAX_LINKED_VARIATIONS", $settings->get_max_variations());
        }
        // Hook mt2mba markup code into bulk actions
        add_action("woocommerce_bulk_edit_variations", [$this, "mt2mba_apply_markup_to_price"], 10, 4);
    }

	/**
	 * Hook into woocommerce_bulk_edit_variations and adjust price after setting new one.
	 * @param	string	$bulk_action    The selection from the variation bulk actions menu.
	 * @param	array	$data	    	Values passed in from JScript pop-up.
	 * @param	string	$product_id	    ID of the variable product.
	 * @param	array	$variations     List of variation IDs for the variable product.
	 */
    public function mt2mba_apply_markup_to_price($bulk_action, $data, $product_id, $variations) {
        // Determine which class should extend MT2MBA_Price_Markup_Handler based on the bulk_action
        if (
            $bulk_action == "variable_regular_price" ||
            $bulk_action == "variable_sale_price"
        ) {
            // Set either the regular price or the sale price
            $handler = new MT2MBA_Price_Set_Handler($bulk_action, $data, $product_id, $variations);

        } elseif (
            strpos($bulk_action, "_price_increase") ||
            strpos($bulk_action, "_price_decrease")
        ) {
            // Increase or decrease the regular price or the sale price
            $handler = new MT2MBA_Price_Update_Handler($bulk_action, $data, $product_id, $variations);

        } elseif ($bulk_action == "delete_all") {
            // Delete all markup metadata for product
            $handler = new MT2MBA_Markup_Delete_Handler("", [], $product_id, []);

        } else {
            // If none of the above, leave and don't execute $handler
            return;
        }

        // Invoke the applyMarkup() function from the class that was decided above
        $handler->applyMarkup(
            (string) $bulk_action,
            (array) $data,
            (string) $product_id,
            (array) $variations
        );
    }
}
?>