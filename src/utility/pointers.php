<?php
/**
 * Contains admin pointers to assist in onboarding
 * @author  Mark Tomlinson
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit( );

class MT2MBA_UTILITY_POINTERS {

    private $pointer_title;

    /**
     * Initialization method visible before instantiation
     */
    public static function init( )
    {
        // As a static method, it can not use '$this' and must use an
        // instantiated version of itself
        $self    = new self( );
        // Set pointer titles (Had to do it here to allow translation)
        // Enqueue the JQuery
        add_action( 'admin_enqueue_scripts', array( $self, 'mt2mba_admin_pointer_load' ), 1000 );
        // Admin pointers for attribute term edit screen
        add_filter( 'mt2mba_admin_pointers-edit-term', array( $self, 'mt2mba_admin_pointers_edit_term' ) );
        // Admin pointer for plugin page
        add_filter( 'mt2mba_admin_pointers-plugins', array( $self, 'mt2mba_admin_pointers_plugins' ) );
    }
 
    /**
     * Find pointers that have not been dismissed
     * and add the scripts to those pages
     * 
     * @param   string  $hook_suffix    Unused
     * 
     */
    function mt2mba_admin_pointer_load( $hook_suffix )
    {
         // Don't run on WP < 3.3
        if ( get_bloginfo( 'version' ) < '3.3' ) return;

        // Get pointers for this screen
        $screen = get_current_screen();
        $screen_id = strpos( $screen->id, 'edit-pa_' ) === FALSE ? $screen->id : 'edit-term';
        $pointer_filter = 'mt2mba_admin_pointers-' . $screen_id;
         
        $pointers = apply_filters( $pointer_filter, array() );
 
        if ( ! $pointers || ! is_array( $pointers ) ) return;

        // Get dismissed pointers
        $dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
        $valid_pointers = array();
 
        // Check pointers and remove dismissed ones.
        foreach ( $pointers as $pointer_id => $pointer )
        {
            // Sanity check
            if ( in_array( $pointer_id, $dismissed ) || empty( $pointer )  || empty( $pointer_id ) || empty( $pointer['target'] ) || empty( $pointer['options'] ) )
                continue;
 
            $pointer['pointer_id'] = $pointer_id;
 
            // Add the pointer to $valid_pointers array
            $valid_pointers['pointers'][] =  $pointer;
        }
 
        // No valid pointers? Stop here.
        if ( empty( $valid_pointers ) ) return;

        // Add pointers style to queue.
        wp_enqueue_style( 'wp-pointer' );
 
        // Add pointers JScript to queue. Add custom script.
        wp_enqueue_script
        (
            'mt2mba-pointer',
            MT2MBA_PLUGIN_URL . 'src/js/jq-mt2mba-pointers.js',
            array( 'wp-pointer' )
        );

        // Add pointer options to script.
        wp_localize_script( 'mt2mba-pointer', 'mt2mbaPointer', $valid_pointers );
    }

    /**
     * Define pointers for Add and Edit term pages
     */
    function mt2mba_admin_pointers_edit_term( $pointers )
    {
        $pointer_content = sprintf
        (
            '<h3><em>%s</em></h3><p>%s</p>',
            MT2MBA_PLUGIN_NAME,
            __( 'Markups can be fixed values such as <code>5</code> or <code>5.95</code>. Or they can be percentages such as <code>5%</code> or <code>1.23%</code>. Markups can start with a plus or minus sign such as <code>+5.95</code> or <code>-1.23%</code>.<br/>The markup will be applied during the product variation <em>Set regular price</em> and <em>Set sale price</em> bulk edit action.',
            'markup-by-attribute' )
        );
        $pointers = array
        (
            'mt2mba-term_add_markup' => array
            (
                'target' => '#term_add_markup',
                'options' => array
                (
                    'content' => $pointer_content,
                    'position' => array( 'edge' => 'left', 'align' => 'middle' )
                )
            ),

            'mt2mba-term_edit_markup' => array
            (
                'target' => '#term_edit_markup',
                'options' => array
                (
                    'content' => $pointer_content,
                    'position' => array( 'edge' => 'top', 'align' => 'middle' )
                )
            ),

        );
        return $pointers;
    }

    /**
     * Define pointer for plugins page
     */
    function mt2mba_admin_pointers_plugins( $pointers )
    {
        $pointer_content = sprintf
        (
            '<h3>%s</h3><p>%s</p>',
            MT2MBA_PLUGIN_NAME,
            __( 'Using this plugin is simple, but might be a little obscure. This link to the instructions may help get you started.<br/>We\'ll just leave the instructions link right here.',
                'markup-by-attribute' )
        );

        $pointers = array
        (
            'mt2mba-instructions' => array
            (
                'target' => '#mt2mba_instructions',
                'options' => array
                (
                    'content' => $pointer_content,
                    'position' => array( 'edge' => 'left', 'align' => 'middle' )
                )
            ),

        );
        return $pointers;
    }

}    // End  class MT2MBA_UTILITY_POINTERS

?>