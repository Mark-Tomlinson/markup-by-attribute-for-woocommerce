<?php
/**
 * Filename:	class_markup_backend_settings.php
 * 
 * Description:	Contains markup-by-attribute settings and settings page.
 * Author:     	Mark Tomlinson
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit( );

class MT2MBA_BACKEND_SETTINGS
{

    var $max_variations     =   50;	        // The default number or variation created per run.
    var $desc_behavior      =   'append';   // The default behavior for writing the pricing information into the variation description.
    var $error_msg          =   '';
    var $format_desc        =   '<div class=\"description\">%s<\/div>';
    var $format_error       =   '<div class=\"error notice\"><p><strong>%s</strong></p></div>';

	/**
	 * Initialization method visible before instantiation
	 */
    public static function init( )
    {
		// As a static method, it can not use '$this' and must use an
		// instantiated version of itself
		$self	= new self( );
		// Hook mt2mba markup code into bulk actions
        add_filter( 'woocommerce_get_sections_products', array( $self, 'mt2mba_add_settings_section' ) );
        add_filter( 'woocommerce_get_settings_products', array( $self, 'mt2mba_all_settings' ), 10, 2 );
	}

    // *******************
    // GETTERS AND SETTERS
    // *******************

    /**
     * Set the Max Variations option
     */
	public function set_max_variations( $mv )
	{
        if ( empty( $mv ) )
        {
            $mv = $this->max_variations;
        }
        $ret = update_option( 'mt2mba_variation_max', $mv );
        if ( $ret )
        {
            return $mv;
        }
        return FALSE;
	}

    /**
     * Get the Max Variations option (and set it if not present)
     */
    public function get_max_variations( )
	{
        $mv = get_option( 'mt2mba_variation_max' );
        if ( empty( $mv ) )
        {
            $mv = $this->set_max_variations( $this->max_variations );
        }
        return $mv;
	}
    
    /**
     * Set the description behavior option
     */
	public function set_desc_behavior( $bv )
	{
        if ( empty( $bv ) )
        {
            $bv = $this->desc_behavior;
        }
        $ret = update_option( 'mt2mba_desc_behavior', $bv );
        if ( $ret )
        {
            return $bv;
        }
        return FALSE;
	}

    /**
     * Get the Description Behavior option (and set it if not present)
     */
    public function get_desc_behavior( )
	{
        $bv = get_option( 'mt2mba_desc_behavior' );
        if ( empty( $bv ) )
        {
            $bv = $this->set_desc_behavior( $this->desc_behavior );
        }
        return $bv;
	}
    
    // *************
    // SETTINGS PAGE
    // *************

    /**
     * Add section to Product settings
     */
    function mt2mba_add_settings_section( $sections )
    {
        $sections['mt2mba'] = __( 'Markup by Attribute' );
        return $sections;
    }

    /**
     * Add settings to the specific section we created before
     */
    function mt2mba_all_settings( $settings, $current_section )
    {
        /**
         * Check the current section is what we want
         **/
        if ( $current_section == 'mt2mba' )
        {
            $mt2mba_settings = array();

            // Add Title to the Settings
            $mt2mba_settings[] = array
                (
                    'name' => __( 'Markup by Attribute' ),
                    'type' => 'title', 
                    'desc' => __( 'The following options are used to configure variation markups by attribute.' . $this->error_msg ),
                    'id' => 'mt2mba',
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
                    'default'  => 200,
                    'type'     => 'text',
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
                    'default'  => 'append',
                );
            
            // End section
            $mt2mba_settings[] = array
                (
                    'type' => 'sectionend',
                    'id' => 'mt2mba'
                );

            return $mt2mba_settings;

        // If not, return the standard settings
        } else {
            return $settings;
        }
    }

    function validate_mt2mba_variation_max_field( $input )
    {
        if ( is_numeric( $input ) )
        {
            return $input;
        } else {
            $this->error_msg .= sprintf( $this->format_error, "Variation Max must be numeric.</br>Previous option retained." );
            return get_option( 'mt2mba_variation_max' );
        }
    }
    
    function validate_mt2mba_desc_behavior_field( $input )
    {
        if ( $input === NULL )
        {
            $this->error_msg .= sprintf( $this->format_error, "Please select an option for the description behavior." );
            return get_option( 'mt2mba_variation_max' );
        } else {
            return $input;
        }
    }


} // End MT2MBA_BACKEND_SETTINGS
?>