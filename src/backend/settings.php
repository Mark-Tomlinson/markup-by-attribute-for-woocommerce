<?php
/**
 * Contains markup-by-attribute settings and settings page.
 * @author      Mark Tomlinson
 * @copyright   Mark Tomlinson  2018
 * 
 */
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit( );

class MT2MBA_BACKEND_SETTINGS
{
    var $dropdown_behavior          =   'do_not_add';   // The default behavior for displaying the currency symbol in the options drop-down.
    var $desc_behavior              =   'append';       // The default behavior for writing the pricing information into the variation description.
    var $round_markup               =   'no';           // The default behavior for rounding percentage markups.
    var $max_variations             =   50;             // The default number or variation created per run.

    var $error_msg                  =   '';
    var $format_desc                =   '<div class=\"description\">%s<\/div>';
    var $format_error               =   '<div class=\"error notice\"><p><strong>%s</strong></p></div>';

    /**
     * Initialization method visible before instantiation
     * @uses    mt2mba_add_settings_section()
     * @uses    mt2mba_all_settings()
     */
    public static function init()
    {
        // As a static method, it can not use '$this' and must use an
        // instantiated version of itself
        $self = new self();
        // Hook mt2mba markup code into bulk actions
        add_filter( 'woocommerce_get_sections_products', array( $self, 'mt2mba_add_settings_section' ) );
        add_filter( 'woocommerce_get_settings_products', array( $self, 'mt2mba_all_settings' ), 10, 2 );
    }

    // *******************
    // GETTERS AND SETTERS
    // *******************

    /**
     * Set the Options Drop-down Behavior option
     * @param   string  $bv The description writing behavior
     * @return  string      The description writing behavior or FALSE if the update failed
     */
    private function set_dropdown_behavior( $data )
    {
        if ( $data === '' )
        {
            $data = $this->dropdown_behavior;
        }
        if ( update_option( 'mt2mba_dropdown_behavior', $data ) )
        {
            return $data;
        }
        return FALSE;
    }

    /**
     * Get the Options Drop-down Behavior option (or default if not present)
     * @uses    set_dropdown_behavior() Set the Options Drop-down Behavior option
     * @return  string                  Either 'add', or 'do_not_add'
     */
    public function get_dropdown_behavior()
    {
        $data = get_option( 'mt2mba_dropdown_behavior' );
        if ( $data === FALSE )
        {
            $data = $this->set_dropdown_behavior( $this->dropdown_behavior );
        }
        return $data;
    }
    
    /**
     * Set the description behavior option
     * @param   string  $data   The description writing behavior
     * @return  string          The description writing behavior
     */
    private function set_desc_behavior( $data )
    {
        if ( $data === '' )
        {
            $data = $this->desc_behavior;
        }
        if ( update_option( 'mt2mba_desc_behavior', $data ) );
        {
            return $data;
        }
        return FALSE;
    }

    /**
     * Get the Description Behavior option (or default if not present)
     * @uses    set_desc_behavior() Set the description behavior option
     * @return  string              Either 'overwrite', 'append', or 'ignore'
     */
    public function get_desc_behavior()
    {
        $data = get_option( 'mt2mba_desc_behavior' );
        if ( $data === FALSE )
        {
            $data = $this->set_desc_behavior( $this->desc_behavior );
        }
        return $data;
    }

    /**
     * Set the Round Markup option
     * @param   boolean $data   Whether percentage markups will be rounded
     * @return  boolean         Whether percentage markups will be rounded
     */
    private function set_round_markup( $data )
    {
        if ( $data === '' )
        {
            $data = $this->round_markup;
        }
        if ( update_option( 'mt2mba_round_markup', $data ) )
        {
            return $data;
        }
        return FALSE;
    }

    /**
     * Get the Round Markup option (and set it if not present)
     * @uses    set_round_markup()  Set the Round Markup option
     * @return  boolean             Whether percentage markups will be rounded
     */
    public function get_round_markup()
    {
        $data = get_option( 'mt2mba_round_markup' );
        if ( ! isset ( $data ) )
        {
            $data = $this->set_round_markup( $this->round_markup );
        }
        return $data;
    }

    /**
     * Set the Max Variations option
     * @param   int $mv Maximum variations per run
     * @return  int     Maximum variations per run or FALSE
     */
    private function set_max_variations( $data )
    {
        if ( $data === '' )
        {
            $data = $this->max_variations;
        }
        if ( update_option( 'mt2mba_variation_max', $data ) )
        {
            return $data;
        }
        return FALSE;
    }

    /**
     * Get the Max Variations option (and set it if not present)
     * @uses    set_max_variations()   Set the Max Variations option
     * @return  int                    Maximum variations per run
     */
    public function get_max_variations()
    {
        $data = get_option( 'mt2mba_variation_max' );
        if ( $data === FALSE )
        {
            $data = $this->set_max_variations( $this->max_variations );
        }
        return $data;
    }
    
    // *************
    // SETTINGS PAGE
    // *************

    /**
     * Create 'Markup by Attribute' section on Product settings page
     * @param   array   $sections   Array of sections on Product settings page
     * @return  array               Array of sections on Product settings page with 'Markup by Attribute' added
     */
    function mt2mba_add_settings_section( $sections )
    {
        $sections['mt2mba'] = MT2MBA_PLUGIN_NAME;
        return $sections;
    }

    /**
     * Add settings to the 'Markup by Attribute' section we created above
     * @param   array   $settings           The current settings
     * @param   string  $current_section    The ID of the current section
     * @return  array                       The current settings page elements
     */
    function mt2mba_all_settings( $settings, $current_section )
    {
        /**
         * Check the current section is what we want
         */
        if ( $current_section == 'mt2mba' )
        {
            $mt2mba_settings = array();

            // Begin Markup by Attribute settings
            $mt2mba_settings[] = array
                (
                    'name'     => MT2MBA_PLUGIN_NAME,
                    'type'     => 'title',
                    'desc'     => sprintf (
                        __( 'The following options are used to configure variation markups by attribute.<br/>Additional help can be found in the <a href="%1$s" target="_blank">Markup by Attribute wiki</a> on the <code>Settings</code> page.', 'markup-by-attribute' ),
                        'https://github.com/Mark-Tomlinson/markup-by-attribute-for-woocommerce/wiki' ),
                        $this->error_msg,
                    'id'       => 'mt2mba',
                );

            // Format markup in Drop-down
            register_setting( 'mt2mba', 'mt2mba_dropdown_behavior', array( $this, 'validate_mt2mba_dropdown_behavior_field' ) );
            $description =
                __( 'Should Markup-by-Attribute add the markup to the options drop-down box, and should the currency symbol be displayed?', 'markup-by-attribute' ) . '<br/>' .
                '<em>' . __( 'This setting affects all products and takes effect immediately.', 'markup-by-attribute' ) . '</em>';
            $mt2mba_settings[] = array
                (
                    'title'    => __( 'Option Drop-down Behavior', 'markup-by-attribute' ),
                    'desc'     => sprintf($this->format_desc, $description ),
                    'id'       => 'mt2mba_dropdown_behavior',
                    'type'     => 'radio',
                    'options'  => array
                        (
                            'hide'          => __( 'Do NOT show the markup in the options drop-down box.', 'markup-by-attribute' ),
                            'add'           => __( 'Show the markup WITH the currency symbol in the options drop-down box.', 'markup-by-attribute' ),
                            'do_not_add'    => __( 'Show the markup WITHOUT the currency symbol in the options drop-down box.', 'markup-by-attribute' ),
                        ),
                    'default'  => $this->dropdown_behavior,
                );

            // Description Behavior
            register_setting( 'mt2mba', 'mt2mba_desc_behavior', array( $this, 'validate_mt2mba_desc_behavior_field' ) );
            $description =
                __( 'How should Markup-by-Attribute handle adding price markup information to the variation description?', 'markup-by-attribute' ) . ' <br/>' .
                '<em>' . __( 'This setting affects products individually and takes effect when you recalculate the regular price for the product.', 'markup-by-attribute' ) . '</em>';
            $mt2mba_settings[] = array
                (
                    'title'    => __( 'Description Behavior', 'markup-by-attribute' ),
                    'desc'     => sprintf($this->format_desc, $description ),
                    'id'       => 'mt2mba_desc_behavior',
                    'type'     => 'radio',
                    'options'  => array
                        (
                            'ignore'        => __( 'Do NOT add pricing information to the description field.', 'markup-by-attribute' ),
                            'append'        => __( 'Add pricing information to the end of the existing description.', 'markup-by-attribute' ),
                            'overwrite'     => __( 'Overwrite the variation description with price information.', 'markup-by-attribute' ),
                        ),
                    'default'  => $this->desc_behavior,
                );
            
            // Round off percentage markups
            register_setting( 'mt2mba', 'mt2mba_round_markup' );
            $description = __(
                'Some stores want prices with specific numbers below the decimal place (such as xx.00 or xx.95). Rounding percentage markups will keep the value below the decimal.',
                'markup-by-attribute' );
            $mt2mba_settings[] = array
                (
                    'title'    => __( 'Round Markup', 'markup-by-attribute' ),
                    'name'     => 'mt2mba_round_markup',
                    'desc'     => sprintf($this->format_desc, $description ),
                    'id'       => 'mt2mba_round_markup',
                    'default'  => $this->round_markup,
                    'type'     => 'checkbox',
                );

            // Variation Max
            register_setting( 'mt2mba', 'mt2mba_variation_max', array( $this, 'validate_mt2mba_variation_max_field' ) );
            $description = __(
                'Use Cautiously: WooCommerce limits the number of linked variations you can create at a time to 50 to prevent server overload.  To create more, you can run \'Create variations from all attributes\' again, but this creates variations out of order.  If you will have more than 50 variations of a product AND the order in the admin console is important, then set this number higher.',
                'markup-by-attribute' );
            $mt2mba_settings[] = array
                (
                    'title'    => __( 'Variation Max', 'markup-by-attribute' ),
                    'name'     => 'mt2mba_variation_max',
                    'desc'     => sprintf($this->format_desc, $description ),
                    'id'       => 'mt2mba_variation_max',
                    'default'  => $this->max_variations,
                    'type'     => 'text',
                );

            // --------------------------------------------------
            // End Markup by Attribute settings
            $mt2mba_settings[] = array
                (
                    'type'     => 'sectionend',
                    'id'       => 'mt2mba'
                );

            return $mt2mba_settings;

        // If not the correct section, return the standard settings
        }
        else
        {
            return $settings;
        }
    }

    // **************
    // ERROR HANDLING
    // **************
    /**
     * Validate that drop-down behavior is set
     * @param   string  $input  The current selection
     * @return  string          The current option or the default
     */
    function validate_mt2mba_dropdown_behavior_field( $input )
    {
        if ( $input === NULL )
        {
            $this->error_msg .= sprintf( $this->format_error, __( "Please select an option for the options drop-down.", 'markup-by-attribute' ) );
            return get_option( 'mt2mba_dropdown_behavior' );
        }
        return $input;
    }

    /**
     * Validate that description writing behavior is set
     * @param   string  $input  The current selection
     * @return  string          The current option or the default
     */
    function validate_mt2mba_desc_behavior_field( $input )
    {
        if ( $input === NULL )
        {
            $this->error_msg .= sprintf( $this->format_error, __( "Please select an option for the description behavior.", 'markup-by-attribute' ) );
            return get_option( 'mt2mba_variation_max' );
        }
        return $input;
    }

    /**
     * Validate The maximum number of variations that can be created per run
     * @param  string   $input  The max variations 
     * @return string           Cleaned max variations
     */
    function validate_mt2mba_variation_max_field( $input )
    {
        if ( is_numeric( $input ) && $input > 1 )
        {
            return $input;
        } else {
            $this->error_msg .= sprintf( $this->format_error, __( "Variation Max must be a number, 1 or higher.</br>Previous option retained.", 'markup-by-attribute' ) );
            return get_option( 'mt2mba_variation_max' );
        }
    }

} // End MT2MBA_BACKEND_SETTINGS
?>