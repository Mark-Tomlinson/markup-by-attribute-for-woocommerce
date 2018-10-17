<?php
/**
 * Contains admin notices
 * @author  Mark Tomlinson
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit( );

class MT2MBA_UTILITY_NOTICES
{
//    private static  $_instance;

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
     * Initialization method visible before instantiation
     */
    public function on_loaded()
    {
        //  Enqueue notice dismissal JScript
        add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );
        //  Action to set the message dismissal option
        add_action( 'admin_init', array( $this, 'action_admin_init' ) );
    }

    /**
     * Enqueue the JScript to clear notices.
     */
    public function action_admin_enqueue_scripts()
    {
        wp_enqueue_script
        (
            'jq-mt2mba-clear-notices',
            MT2MBA_PLUGIN_URL . 'src/js/jq-mt2mba-clear-notices.js',
            array( 'jquery' )
        );
    }

    /**
     * If admin page is called with 'mt2mba_dismiss=' in the query string,
     * add 'dismissed' code to database
     */
    public function action_admin_init()
    {
        $dismiss_option = filter_input( INPUT_GET, 'mt2mba_dismiss', FILTER_SANITIZE_STRING );
        if ( is_string( $dismiss_option ) ) {
            update_option( "mt2mba_dismissed_$dismiss_option", true );
            wp_die();
        }
    }

    /**
     * Display admin notices sent in as an array
     * 
     * @param   array   $admin_notices  A two dimensional array; array( notice_type => array( notice_id => notice ) )
     */
    public function send_notice_array( $admin_notices )
    {
        foreach( $admin_notices as $type => $notices )
        {
            foreach( $notices as $notices_id => $notices )
            {
                $this->{$type}( $notices, $notices_id );
            };
        };
    }

    /**
     * Display error notice
     * @param   string  $message           Error message.
     * @param   string  $dismiss_option    Identifier for recording dismissal.
     */
    public function error( $message, $dismiss_option = FALSE )
    {
        $this->notice( 'error', $message, $dismiss_option );
    }

    /**
     * Display warning notice
     * @param   string  $message           Warning message.
     * @param   string  $dismiss_option    Identifier for recording dismissal.
     */
    public function warning( $message, $dismiss_option = FALSE )
    {
        $this->notice( 'warning', $message, $dismiss_option );
    }

    /**
     * Display success notice
     * @param   string  $message           Success message.
     * @param   string  $dismiss_option    Identifier for recording dismissal.
     */
    public function success( $message, $dismiss_option = FALSE )
    {
        $this->notice( 'success', $message, $dismiss_option );
    }

    /**
     * Display info notice
     * @param   string  $message           Informational message.
     * @param   string  $dismiss_option    Identifier for recording dismissal.
     */
    public function info( $message, $dismiss_option = FALSE )
    {
        $this->notice( 'info', $message, $dismiss_option );
    }

    /**
     * Generic display notice routine
     * @param   string  $type              Type of message ('error', 'warning', 'success', 'info')
     * @param   string  $message           Error message.
     * @param   string  $dismiss_option    Identifier for recording dismissal.
     */
    private function notice( $type, $message, $dismiss_option )
    {
        add_action
        (
            'admin_notices',
            function() use ( $type, $message, $dismiss_option )
            {
                $dismiss_url = add_query_arg
                (
                    array( 'mt2mba_dismiss' => $dismiss_option ),
                    admin_url()
                );
                if ( ! get_option( "mt2mba_dismissed_{$dismiss_option}" ) )
                {
                    ?><div
                        class="notice mt2mba-notice notice-<?php echo $type;
                        if ( $dismiss_option )
                        {
                            echo ' is-dismissible" data-dismiss-url="' . esc_url( $dismiss_url );
                        } ?>">
                        <strong><em><?php echo( MT2MBA_PLUGIN_NAME . ' ' . $type ); ?></em></strong>
                        <p><?php echo( $message ); ?></p>
                    </div><?php
                }   //  End if
            }   //  End function
        );
    }

}    // End  class MT2MBA_UTILITY_NOTICES

?>