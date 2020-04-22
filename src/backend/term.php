<?php
/**
 * Contains markup capabilities related to the backend attribute term admin page.
 * Specifically, add metadata field for markup to product attribute terms.
 * 
 * @author         Mark Tomlinson
 * 
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit();

class MT2MBA_BACKEND_TERM
{
    private $markup_label;
    private $markup_description;
    private $rewrite_label;
    private $rewrite_description;
    private $text_add;
    private $text_subtract;

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
        // Define labels and contents.
        $this->markup_label         = __( 'Markup (or markdown)', 'markup-by-attribute' );
        $this->markup_description   = __( 'Markup or markdown associated with this option. Signed, floating point numeric allowed.', 'markup-by-attribute' );
        $this->rewrite_label        = __( 'Add Markup to Name?', 'markup-by-attribute' );
        $this->rewrite_description  = __( 'Rename the attribute to include the markup. Often needed if the option drop-down box is overwritten by another plugin or theme and markup is no longer visible.', 'markup-by-attribute' );
        $this->text_add             = __( '(Add', 'markup-by-attribute' );
        $this->text_subtract        = __( '(Subtract', 'markup-by-attribute' );
        define( 'ATTRB_MARKUP_DESC_BEG',    '(' . __( 'Markup:', 'markup-by-attribute' ) . ' '  );
        define( 'ATTRB_MARKUP_NAME_BEG',    ' ('												);
        define( 'ATTRB_MARKUP_END',			')'                                                 );
    
        // Get all attributes
        $attribute_taxonomies = wc_get_attribute_taxonomies();

        // Hook into the 'new', 'edit', and 'delete' attribute panel
        add_action( "woocommerce_after_add_attribute_fields", array( $this, 'mt2mba_add_attribute_fields' ), 10, 2 );
        add_action( "woocommerce_after_edit_attribute_fields", array( $this, 'mt2mba_edit_attribute_fields' ), 10, 2 );
        add_action( "woocommerce_before_attribute_delete", array( $this, 'mt2mba_delete_attribute' ), 10, 2 );

        // Loop through attributes adding hooks
        foreach ( $attribute_taxonomies as $attribute_taxonomy )
        {
            // Build taxonomy name
            $taxonomy = 'pa_' . $attribute_taxonomy->attribute_name;

            // Hook into 'new' and 'edit' term panels
            add_action( "{$taxonomy}_add_form_fields", array( $this, 'mt2mba_add_form_fields' ), 10, 2 );
            add_action( "{$taxonomy}_edit_form_fields", array( $this, 'mt2mba_edit_form_fields' ), 10, 2 );

            // Hook save function into both the 'new' and 'edit' functions
            add_action( "created_{$taxonomy}", array( $this, 'mt2mba_save_markup_to_metadata' ), 10, 2 );
            add_action( "edited_{$taxonomy}", array( $this, 'mt2mba_save_markup_to_metadata' ), 10, 2 );
        }
    }

    /**
     * Build <DIV> to add markup to the 'Add New' attribute term panel
     * Save the flag if the [Add attribute] button was pressed
     */
    function mt2mba_add_attribute_fields( )
    {
        if ( isset( $_POST[ 'add_new_attribute' ] ) )
        {
            // [Add attribute] button pressed, save the rewrite flag
            $rewrite_flag   = isset( $_POST[ 'term_name_rewrite' ] ) ? 'yes' : 'no';

            // Get all attributes
            $attribute_taxonomies = wc_get_attribute_taxonomies();
            // Find new attribute ID and write options
            foreach ( $attribute_taxonomies as $attribute_taxonomy )
            {
                if ( $attribute_taxonomy->attribute_label == $_POST[ 'attribute_label' ] )
                {
                    update_option( REWRITE_OPTION_PREFIX . $attribute_taxonomy->attribute_id, $rewrite_flag );
                }
            }
        }

        // Build <DIV>
        ?>
        <div class="form-field">
            <label for="term_name_rewrite"><input type="checkbox" name="term_name_rewrite" id="term_name_add_rewrite" value=""> <?php echo( $this->rewrite_label ); ?></label>
            <p class="description"><?php echo( $this->rewrite_description ); ?></p>
        </div>
        <?php
    }
    
    /**
     * Build <TR> to add markup to the 'Edit' attribute term panel
     * Save the flag if the [Save attribute] button was pressed
     */
    function mt2mba_edit_attribute_fields( )
    {
        // Retrieve the existing rewrite flag for this attribute (NULL results are valid)
        if ( isset( $_POST[ 'save_attribute' ] ) )
        {
            // [Update] button pressed, set rewrite flag and save
            $rewrite_flag   = isset( $_POST[ 'term_name_rewrite' ] ) ? 'yes' : 'no';
            update_option( REWRITE_OPTION_PREFIX . $_GET['edit'], $rewrite_flag );
        } else {
            // First time in, set rewrite flag from Options database
            $rewrite_flag   = get_option( REWRITE_OPTION_PREFIX . $_GET['edit'], FALSE );
        }

        // Build row and fill field with current markup
        $checked_flag       = $rewrite_flag == 'yes' ? ' checked' : "";
        ?>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="term_name_rewrite"><?php echo( $this->rewrite_label ); ?></label></th>
            <td>
                <input type="checkbox" name="term_name_rewrite" id="term_name_edit_rewrite"<?php echo $checked_flag; ?>>
                <p class="description"><?php echo( $this->rewrite_description ); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Delete attribute option or meta
     */
    function mt2mba_delete_attribute( )
    {
        //delete_option( self::get_option_name( $_GET ) );
        delete_option( REWRITE_OPTION_PREFIX . $_GET['delete'] );
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
            <label for="term_markup"><?php echo( $this->markup_label ); ?></label>
            <input type="text" placeholder="[ +|- ]0.00 or [ +|- ]00%" name="term_markup" id="term_add_markup" value="">
            <p class="description"><?php echo( $this->markup_description ); ?></p>
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
        $term_markup        = get_term_meta( $term->term_id, "mt2mba_markup", TRUE );

        // Build row and fill field with current markup
        ?>
       <tr class="form-field">
           <th scope="row" valign="top"><label for="term_markup"><?php echo( $this->markup_label ); ?></label></th>
           <td>
               <input type="text" placeholder="[ +|- ]0.00 or [ +|- ]00%" name="term_markup" id="term_edit_markup" value="<?php echo esc_attr( $term_markup ) ? esc_attr( $term_markup ) : ''; ?>">
               <p class="description"><?php echo( $this->markup_description ); ?></p>
           </td>
       </tr>
        <?php
    }

    /**
     * Save the term's markup as metadata
     * @param string $term_id
     */
    function mt2mba_save_markup_to_metadata( $term_id )
    {
        // Prevent recursion when wp_update_term() is called later
        if ( defined( 'MT2MBA_ATTRB_RECURSION' ) )
        {
            return;
        }
        define( 'MT2MBA_ATTRB_RECURSION', TRUE );

        global          $mt2mba_utility;
        $term           = get_term( $term_id );
        $taxonomy_name  = sanitize_key( $term->taxonomy );

        // Remove any previous markup information from description and name
        $name           = $term->name;
        $name           = trim( $mt2mba_utility->remove_bracketed_string( ATTRB_MARKUP_NAME_BEG, ATTRB_MARKUP_END, $name ) );
        $description    = $term->description;
        $description    = trim( $mt2mba_utility->remove_bracketed_string( ATTRB_MARKUP_DESC_BEG, ATTRB_MARKUP_END, $description ) );

        // Remove old metadata, regardless of next steps
        delete_term_meta( $term_id, 'mt2mba_markup' );

        // Add Markup metadata if present
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
            $description .= PHP_EOL . ATTRB_MARKUP_DESC_BEG . $markup . ATTRB_MARKUP_END;

            // Update term name, if rewrite flag is set, so markup is visible in the name
            $rewrite_flag   = get_option( REWRITE_OPTION_PREFIX . wc_attribute_taxonomy_id_by_name( $taxonomy_name ) );
            if ( $rewrite_flag == 'yes' )
            {
                $markup = $mt2mba_utility->format_option_markup( $markup );
                $markup = strpos( $markup, "+" ) ? str_replace( "(+", $this->text_add . " ", $markup ) : str_replace( "(-", $this->text_subtract . " ", $markup );
                $name   .= $markup;
            }
        }

        // Rewrite description
        wp_update_term( $term_id, $taxonomy_name, array( 'description' => trim( $description ), 'name' => trim( $name ) ) );
    }

}    // End  class MT2MBA_BACKEND_TERM

?>