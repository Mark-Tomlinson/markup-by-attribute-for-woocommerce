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
    var $digits_below_fixed         =   2;              // The default number of digits below the decimal point for fixed amount markups.
    var $digits_below_percentage    =   1;              // The default number of digits below the decimal point for percentage markups.
    var $max_variations             =   50;             // The default number or variation created per run.
    var $currency_symbol_before     =   '$';            // The default currency symbol for before the markup.
    var $currency_symbol_after      =   '';             // The default currency symbol for after the markup.

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
	public function set_dropdown_behavior( $data )
	{
        if ( empty( $data ) )
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
        if ( empty( $data ) )
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
	public function set_desc_behavior( $data )
	{
        if ( empty( $data ) )
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
        if ( empty( $data ) )
        {
            $data = $this->set_desc_behavior( $this->desc_behavior );
        }
        return $data;
	}
    
    /**
     * Set the Number of Digits Below the Decimal Point Option
     * @param   int $data   Number of digits below the decimal point
     * @param   int $type   Whether this is for a 'fixed' number or 'percentage' markup
     * @return  int         Number of digits below the decimal point or FALSE if update fails
     */
	public function set_decimal_points( $data, $type )
	{
        if ( empty( $data ) )
        {
            $data = $this->{"digits_below_$type"};
        }
        if( update_option( "digits_below_$type", $data ) );
        {
            return $data;
        }
        return FALSE;
	}

    /**
     * Get the number of decimal points option (or default if not present)
     * @param   string  $type           Whether this is for a 'fixed' number or 'percentage' markup
     * @uses    set_decimal_points()    Set the Number of Digits Below the Decimal Point Option
     * @return  int                     The number of digits below the decimal point in the markup
     */
    public function get_decimal_points( $type )
	{
        $data = get_option( "mt2mba_decimal_points_$type" );
        if ( empty( $data ) )
        {
            $data = $this->set_decimal_points( $this->{"digits_below_$type"}, $type );
        }
        return $data;
	}
    
    /**
     * Get the number of decimal points for fixed markups option (or default if not present)
     * @uses    get_decimal_points()    Get the number of decimal points option (or default if not present)
     * @return  int                     The number of digits below the decimal point in the markup
     */
    public function get_decimal_points_fixed()
	{
        return $this->get_decimal_points( 'fixed' );
    }
    
    /**
     * Get the number of decimal points for percentage markups option (or default if not present)
     * @uses    get_decimal_points()    Get the number of decimal points option (or default if not present)
     * @return  int                     The number of digits below the decimal point in the markup
     */
    public function get_decimal_points_percentage()
	{
        return $this->get_decimal_points( 'percentage' );
    }
    
    /**
     * Set the Max Variations option
     * @param   int $mv Maximum variations per run
     * @return  int     Maximum variations per run or FALSE
     */
	public function set_max_variations( $data )
	{
        if ( empty( $data ) )
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
        if ( empty( $data ) )
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
        $sections['mt2mba'] = __( 'Markup by Attribute' );
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

            // Begin New Product Settings Section
            $mt2mba_settings[] = array
                (
                    'name' => __( 'Markup by Attribute' ),
                    'type' => 'title', 
                    'desc' => __( 'The following options are used to configure variation markups by attribute.' . $this->error_msg ),
                    'id' => 'mt2mba',
                );
            
            // Symbol in Drop-down?
            register_setting( 'mt2mba', 'mt2mba_dropdown_behavior', array( $this, 'validate_mt2mba_dropdown_behavior_field' ) );
            $description = 'Should Markup-by-Attribute add the currency symbol to the markup in the options drop-down box?';
            $mt2mba_settings[] = array
                (
                    'title'    => __( 'Currency in Option Drop-down' ),
                    'desc'     => __( sprintf($this->format_desc, $description ) ),
                    'id'       => 'mt2mba_dropdown_behavior',
                    'type'     => 'radio',
                    'options'  => array
                        (
                            'add' => 'Add the currency symbol to the markup in the options drop-down box.',
                            'do_not_add' => 'Do NOT add the currency symbol to the markup in the options drop-down box.',
                        ),
                    'default'  => $this->dropdown_behavior,
                );

            // Description Behavior
            register_setting( 'mt2mba', 'mt2mba_desc_behavior', array( $this, 'validate_mt2mba_desc_behavior_field' ) );
            $description = 'How should Markup-by-Attribute handle adding price markup information to the variation description?';
            $mt2mba_settings[] = array
                (
                    'title'    => __( 'Description Behavior' ),
                    'desc'     => __( sprintf($this->format_desc, $description ) ),
                    'id'       => 'mt2mba_desc_behavior',
                    'type'     => 'radio',
                    'options'  => array
                        (
                            'overwrite' => 'Overwrite the variation description with price information.',
                            'append' => 'Add pricing information to the end of the existing description.',
                            'ignore' => 'Do not add pricing information to the description field.',
                        ),
                    'default'  => $this->desc_behavior,
                );
            
            // Number of Decimal Points for Fixed Number Markups
            register_setting( 'mt2mba', 'mt2mba_decimal_points_fixed', array( $this, 'validate_mt2mba_decimal_points_fixed_field' ) );
            $description = 'Number of digits that appear after the decimal point in fixed number markups. Valid values are 0 through 5.';
            $mt2mba_settings[] = array
                (
                    'title'    => __( 'Digits Below Deciaml Point For Fixed Value Markups' ),
                    'desc'     => __( sprintf($this->format_desc, $description ) ),
                    'id'       => 'mt2mba_decimal_points_fixed',
                    'default'  => $this->digits_below_fixed,
                    'type'     => 'text',
                );

            // Number of Decimal Points for Percentage Markups
            register_setting( 'mt2mba', 'mt2mba_decimal_points_percentage', array( $this, 'validate_mt2mba_decimal_points_percentage_field' ) );
            $description = 'Number of digits that appear after the decimal point in percentage markups. Valid values are 0 through 5.';
            $mt2mba_settings[] = array
                (
                    'title'    => __( 'Digits Below Deciaml Point For Percentage Markups' ),
                    'desc'     => __( sprintf($this->format_desc, $description ) ),
                    'id'       => 'mt2mba_decimal_points_percentage',
                    'default'  => $this->digits_below_percentage,
                    'type'     => 'text',
                );

            // Currency Symbol Before
            register_setting( 'mt2mba', 'mt2mba_symbol_before', array( $this, 'validate_mt2mba_symbol_before_field' ) );
            $description = 'What currency symbol should appear before the markup?  Leave blank for none.';
            $mt2mba_settings[] = array
                (
                    'title'    => __( 'Currency Symbol Before Markup' ),
                    'desc'     => __( sprintf($this->format_desc, $description ) ),
                    'id'       => 'mt2mba_symbol_before',
                    'default'  => $this->currency_symbol_before,
                    'type'     => 'text',
                );

            // Currency Symbol After
            register_setting( 'mt2mba', 'mt2mba_symbol_after', array( $this, 'validate_mt2mba_symbol_after_field' ) );
            $description = 'What currency symbol should appear after the markup?  Leave blank for none.';
            $mt2mba_settings[] = array
                (
                    'title'    => __( 'Currency Symbol After Markup' ),
                    'desc'     => __( sprintf($this->format_desc, $description ) ),
                    'id'       => 'mt2mba_symbol_after',
                    'default'  => $this->currency_symbol_after,
                    'type'     => 'text',
                );

            // Variation Max
            register_setting( 'mt2mba', 'mt2mba_variation_max', array( $this, 'validate_mt2mba_variation_max_field' ) );
            $description = 'Use Cautiously: WooCommerce limits the number of linked variations you can create at a time to 50 to prevent server overload.  ' .
                'To create more, you can run \'Create variations from all attributes\' again, but this creates variations out of order.  ' .
                'If you will have more than 50 variations of a product AND the order in the admin console is important, then set this number higher.';
            $mt2mba_settings[] = array
                (
                    'title'    => __( 'Variation Max' ),
                    'name'     => 'mt2mba_variation_max',
                    'desc'     => __( sprintf($this->format_desc, $description ) ),
                    'id'       => 'mt2mba_variation_max',
                    'default'  => $this->max_variations,
                    'type'     => 'text',
                );

            // End section
            $mt2mba_settings[] = array
                (
                    'type' => 'sectionend',
                    'id' => 'mt2mba'
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
            $this->error_msg .= sprintf( $this->format_error, "Please select an option for the options drop-down." );
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
            $this->error_msg .= sprintf( $this->format_error, "Please select an option for the description behavior." );
            return get_option( 'mt2mba_variation_max' );
        }
        return $input;
    }

    /**
     * Validate number of digits below the decimal point. Must be numeric between 0 and 5.
     * @param   int     $input  The number of digits below the decimal point
     * @param   string  $type   Whether this is for the fixed amount or percentage markup
     * @return  int             The current option or the default
     */
    function validate_mt2mba_decimal_points_field( $input, $type )
    {
        if( is_numeric( $input ) )
        {
            if( $input < 0 || $input > 5 )
            {
                $this->error_msg .= sprintf( $this->format_error, "Numbers Below Decimal Point for $type markups must be between 0 and 5.</br>Previous option retained." );
                return get_option( "mt2mba_decimal_points_$type" );
            }
        } else {
            $this->error_msg .= sprintf( $this->format_error, "Numbers Below Decimal Point for $type markups must be numeric.</br>Previous option retained." );
            return get_option( "mt2mba_decimal_points_$type" );
        }
        return $input;
    }

    /**
     * Validate number of digits below the decimal point for fixed number markups.
     * @param   int     $input                                  The number of digits below the decimal point
     * @uses            validate_mt2mba_decimal_points_field()  Validate number of digits below the decimal point
     * @return  int                                             The current option or the default
     */
    function validate_mt2mba_decimal_points_fixed_field( $input )
    {
        return $this->validate_mt2mba_decimal_points_field( $input, 'fixed' );
    }

    /**
     * Validate number of digits below the decimal point for percentage markups.
     * @param   int     $input                                  The number of digits below the decimal point
     * @uses            validate_mt2mba_decimal_points_field()  Validate number of digits below the decimal point
     * @return  int                                             The current option or the default
     */
    function validate_mt2mba_decimal_points_percentage_field( $input )
    {
        return $this->validate_mt2mba_decimal_points_field( $input, 'percentage' );
    }

    /**
     * Validate currency symbol
     * @param  string   $input  Currency symbol
     * @param  string   $type   Whether this is the 'before' or 'after' symbol
     * @return string           Cleaned currency symbol
     */
    function validate_mt2mba_symbol_field( $input, $type )
    {
        if ( strlen( $input ) > 1 )
        {
            $this->error_msg .= sprintf( $this->format_error, "Currency symbol ($type) can be only one character." );
            return get_option( "mt2mba_symbol_$type" );
        }
        if( $input === ' ' )
        {
            $this->error_msg .= sprintf( $this->format_error, "Currency symbol ($type) can not be a space.</br>" .
                "If you intended to have no currency symbol, delete the space." );
            return get_option( "mt2mba_symbol_$type" );
        }
        return $input;
    }

    /**
     * Validate currency symbol before markup
     * @param  string   $input                          Currency symbol
     * @uses            validate_mt2mba_symbol_field()  Generic symbol validator
     * @return string                                   Cleaned currency symbol
     */
    function validate_mt2mba_symbol_before_field( $input )
    {
        return $this->validate_mt2mba_symbol_field( $input, 'before' );
    }

    /**
     * Validate currency symbol after markup
     * @param  string   $input                          Currency symbol
     * @uses            validate_mt2mba_symbol_field()  Generic symbol validator
     * @return string                                   Cleaned currency symbol
     */
    function validate_mt2mba_symbol_after_field( $input )
    {
        return $this->validate_mt2mba_symbol_field( $input, 'after' );
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
            $this->error_msg .= sprintf( $this->format_error, "Variation Max must be a number, 1 or higher.</br>Previous option retained." );
            return get_option( 'mt2mba_variation_max' );
        }
    }

} // End MT2MBA_BACKEND_SETTINGS
?>