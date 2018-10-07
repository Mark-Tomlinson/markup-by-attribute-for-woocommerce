<?php
/**
 * Contains admin notices
 * @author  Mark Tomlinson
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit( );

class MT2MBA_BACKEND_NOTICES
{
    private static  $_instance;
    private         $admin_notices;
    const           TYPES               = 'error,warning,info,success';
    private         $warning_messages;

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
        // Set notice title (Done here to allow for translation)
        add_action( 'admin_init', array( &$this, 'action_admin_init' ) );
        add_action( 'admin_notices', array( &$this, 'action_admin_notices' ) );
        add_action( 'admin_enqueue_scripts', array( &$this, 'action_admin_enqueue_scripts' ) );
                         
        add_filter( 'gettext', array( $this, 'ctf_filter_gettext' ), 99, 3 );

        $this->admin_notices = new stdClass();
        foreach ( explode( ',', self::TYPES ) as $type )
        {
            $this->admin_notices->{$type} = array();
        }
        // Need to figure out how to move this to main module. But right now the 'warning'
        // method does not work correctly when invoked from outside of this class.
        $this->warning_messages = array(
            // Version 2.4 Upgrade notice
            'ver2_4_upgrade' => sprintf( '%1$s %2$s %3$s %4$s',
                __( 'PLEASE NOTE: As of version 2.4, Markup-by-Attribute no longer has it\'s own currency format settings. It now uses the', 'markup-by-attribute' ),
                sprintf( __( '<a href="%s/wp-admin/admin.php?page=wc-settings">WooCommerce currency settings</a>.<br/>', 'markup-by-attribute' ), MT2MBA_SITE_URL ),
                __( 'You may still control the markup display behavior of the options drop-down and the product description with the', 'markup-by-attribute' ),
                sprintf( __( '<a href="%s/wp-admin/admin.php?page=wc-settings&tab=products&section=mt2mba">Markup-by-Attribute settings</a>.', 'markup-by-attribute' ), MT2MBA_SITE_URL )
            )
            // Next message
    	//	'key' => __( 'message', 'markup-by-attribute' ),
        );

        foreach( $this->warning_messages as $message_key => $message )
		{
			$this->warning( $message, $message_key );
		}
    }

    function ctf_filter_gettext( $translated = 'trans', $original = 'orig', $domain = 'domain' ) {
//        error_log($translated . ' | ' . $original . ' | ' . $domain);
        return $translated;
    }

    /**
     * Sanity check
     */
    public function action_admin_init()
    {
        $dismiss_option = filter_input( INPUT_GET, 'mt2mba_dismiss', FILTER_SANITIZE_STRING );
        if ( is_string( $dismiss_option ) )
        {
            update_option( "mt2mba_dismissed_{$dismiss_option}", TRUE );
            wp_die();
        }
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
        $notice                 = new stdClass();
        $notice->message        = $message;
        $notice->dismiss_option = $dismiss_option;

        $this->admin_notices->{$type}[] = $notice;
    }

    /**
     * Loop through notice types and notices, displaying each if not already dismissed
     */
    public function action_admin_notices()
    {
        foreach ( explode( ',', self::TYPES ) as $type )
        {
            foreach ( $this->admin_notices->{$type} as $admin_notice )
            {
                $dismiss_url = add_query_arg(
                    array(
                        'mt2mba_dismiss' => $admin_notice->dismiss_option
                    ),
                    admin_url()
                );

                if ( ! get_option( "mt2mba_dismissed_{$admin_notice->dismiss_option}" ) )
                {
                    ?><div
                        class="notice mt2mba-notice notice-<?php echo $type;

                        if ( $admin_notice->dismiss_option )
                        {
                            echo ' is-dismissible" data-dismiss-url="' . esc_url( $dismiss_url );
                        } ?>">
                        <h3><?php echo( MT2MBA_PLUGIN_NAME . ' ' . $type ); ?></h3>
                        <p><?php echo( $admin_notice->message ); ?></p>

                    </div><?php
                }
            }
        }
    }

    /**
     * Enqueue the JScript to clear notices.
     */
    public function action_admin_enqueue_scripts() {
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script(
            'jq-mt2mba-clear-notices',
            plugins_url( 'js/jq-mt2mba-clear-notices.js', __FILE__ ),
            array( 'jquery' )
        );
    }

}    // End  class MT2MBA_BACKEND_NOTICES

?>