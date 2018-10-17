<?php
/**
 * Contains markup capabilities related to the backend attribute admin page. Specifically,
 * add metadata field for markup to product attribute terms.
 * 
 * @author         Mark Tomlinson
 * 
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit();

class MT2MBA_BACKEND_ATTRB
{
    private $field_label;
    private $field_description;
    
    /**
     * Initialization method visible before instantiation
     */
    public static function init()
    {
        // As a static method, it can not use '$this' and must use an
        // instantiated version of itself
        $self    = new self( );
        // Set initialization method to run on 'wp_loaded'.
        add_filter( 'wp_loaded', array( $self, 'on_loaded' ) );
    }

    /**
     * Hook into Wordpress and WooCommerce
     * Method runs on 'wp_loaded' hook
     */
    public function on_loaded()
    {
        // Define label and content.
        $this->field_label        = __( 'Markup (or markdown)', 'markup-by-attribute' );
        $this->field_description  = __( 'Markup or markdown associated with this option. Signed, floating point numeric allowed.', 'markup-by-attribute' );

        // Get all attributes
        $attribute_taxonomies = wc_get_attribute_taxonomies();

        // Loop through attributes adding hooks
        foreach ( $attribute_taxonomies as $attribute_taxonomy )
        {
            // Build taxonomy name
            $taxonomy = 'pa_' . $attribute_taxonomy->attribute_name;

            // Hook into 'new' attribute term panel
            add_action( "{$taxonomy}_add_form_fields", array( $this, 'mt2mba_add_form_fields' ), 10, 2 );

            // Hook into 'edit' attribute term panel
            add_action( "{$taxonomy}_edit_form_fields", array( $this, 'mt2mba_edit_form_fields' ), 10, 2 );

            // Hook save function into both the 'new' and 'edit' functions
            add_action( "created_{$taxonomy}", array( $this, 'mt2mba_save_markup_to_metadata' ), 10, 2 );
            add_action( "edited_{$taxonomy}", array( $this, 'mt2mba_save_markup_to_metadata' ), 10, 2 );
        }
    }

    /**
     * Build <DIV> to add markup to the 'Add New' attribute term panel
     * 
     *  @param string $taxonomy
     * 
     */
    function mt2mba_add_form_fields( $taxonomy )
    {
        // Build <DIV>
        ?>
        <div class="form-field">
            <label for="term_markup"><?php echo( $this->field_label ); ?></label>
            <input type="text" placeholder="[ +|- ]0.00 or [ +|- ]00%" name="term_markup" id="term_add_markup" value="">
            <p class="description"><?php echo( $this->field_description ); ?></p>
        </div>
        <?php
    }
    
    /**
     * Build <TR> to add markup to the 'Edit' attribute term panel
     * 
     * @param string $term
     * 
     */
     function mt2mba_edit_form_fields( $term )
     {
        // Retrieve the existing markup for this term (NULL results are valid)
        $term_meta = get_term_meta( $term->term_id, "mt2mba_markup", TRUE );
        // Build row and fill field with current markup
        ?>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="term_markup"><?php echo( $this->field_label ); ?></label></th>
            <td>
                <input type="text" placeholder="[ +|- ]0.00 or [ +|- ]00%" name="term_markup" id="term_edit_markup" value="<?php echo esc_attr( $term_meta ) ? esc_attr( $term_meta ) : ''; ?>">
                <p class="description"><?php echo( $this->field_description ); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save the term's markup as metadata
     * @param string $term_id
     */
    public function mt2mba_save_markup_to_metadata( $term_id )
    {
        // Prevent recursion when wp_update_term() is called later
        if ( defined( 'MT2MBA_ATTRB_RECURSION' ) )
        {
            return;
        }
        define( 'MT2MBA_ATTRB_RECURSION', TRUE );

        global           $mt2mba_utility;
        $term            = get_term( $term_id );
        $taxonomy        = sanitize_key( $term->taxonomy );
        // Remove any previous markup information from description
        $description     = $term->description;
        $description     = trim( $mt2mba_utility->remove_bracketed_string( ATTRB_MARKUP_DESC_BEG, ATTRB_MARKUP_DESC_END, $description ) );
        
        // Remove old metadata, regardless of next step
        delete_term_meta( $term_id, 'mt2mba_markup' );

        if ( esc_attr( $_POST[ 'term_markup' ] <> 0 ) )
        {
            $term_markup = esc_attr( $_POST[ 'term_markup' ]);
            
            // If term_markup has a value other than zero, add/update the value to the metadata table
            if ( strpos( $term_markup, "%" ) )
            {
                // If term_markup has a percentage sign, save as a formatted percent
                $markup = sprintf( "%+g%%", sanitize_text_field( $term_markup ) );
            }
            else
            {
                // If term_markup does not have percentage sign, save as a formatted floating point number
                $markup = sprintf( "%+g", sanitize_text_field( $term_markup ) );
            }
            update_term_meta( $term_id, 'mt2mba_markup', $markup );
            // Update term description so markups are visible in the term list
            $description .= PHP_EOL . ATTRB_MARKUP_DESC_BEG . $markup . ATTRB_MARKUP_DESC_END;
        }

        // Rewrite description
        wp_update_term( $term_id, $taxonomy, array( 'description' => trim( $description ) ) );
    }

}    // End  class MT2MBA_ATTRB_BACKEND

?>