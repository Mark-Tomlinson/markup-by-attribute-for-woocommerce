<?php
/**
 * Contains admin notices
 * @author  Mark Tomlinson
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit( );

class MT2MBA_BACKEND_NOTICES {

    private static  $_instance;
    private         $admin_notices;
    const           TYPES = 'error,warning,info,success';

    /**
     * Initialization method visible before instantiation
     */
    public static function init()
    {
        // As a static method, it can not use '$this' and must use an
        // instantiated version of itself
        $self = new self();
        // Set initialization method to run on 'wp_loaded'.
        add_filter( 'wp_loaded', array( $self, 'on_loaded' ) );
        $self->admin_notices = new stdClass();
        foreach ( explode( ',', self::TYPES ) as $type ) {
            $self->admin_notices->{$type} = array();
        }
        add_action( 'admin_init', array( &$self, 'action_admin_init' ) );
        add_action( 'admin_notices', array( &$self, 'action_admin_notices' ) );
        add_action( 'admin_enqueue_scripts', array( &$self, 'action_admin_enqueue_scripts' ) );
        $self->warning( "Whatever, dude.", 'XYZ' );
    }

    /**
     * Initialization method visible before instantiation
     */
    public function on_loaded()
    {
    }

    /**
     * 
     */
    public function action_admin_init()
    {
        $dismiss_option = filter_input( INPUT_GET, 'myplugin_dismiss', FILTER_SANITIZE_STRING );
        if ( is_string( $dismiss_option ) )
        {
            update_option( "myplugin_dismissed_$dismiss_option", TRUE );
            wp_die();
        }
    }

    public function error( $message, $dismiss_option = FALSE )
    {
        $this->notice( 'error', $message, $dismiss_option );
    }

    public function warning( $message, $dismiss_option = FALSE )
    {
        $this->notice( 'warning', $message, $dismiss_option );
    }

    public function success( $message, $dismiss_option = FALSE )
    {
        $this->notice( 'success', $message, $dismiss_option );
    }

    public function info( $message, $dismiss_option = FALSE )
    {
        $this->notice( 'info', $message, $dismiss_option );
    }

    private function notice( $type, $message, $dismiss_option )
    {
        $notice                 = new stdClass();
        $notice->message        = $message;
        $notice->dismiss_option = $dismiss_option;

        $this->admin_notices->{$type}[] = $notice;
        error_log(print_r($this->admin_notices,true));
    }

    public function action_admin_notices()
    {
        error_log('action_admin_notice');
        foreach ( explode( ',', self::TYPES ) as $type )
        {
            foreach ( $this->admin_notices->{$type} as $admin_notice )
            {
                $dismiss_url = add_query_arg(
                    array(
                        'myplugin_dismiss' => $admin_notice->dismiss_option
                    ),
                    admin_url()
                );

                if ( ! get_option( "myplugin_dismissed_{$admin_notice->dismiss_option}" ) )
                {
                    ?><div
                        class="notice myplugin-notice notice-<?php echo $type;

                        if ( $admin_notice->dismiss_option ) {
                            echo ' is-dismissible" data-dismiss-url="' . esc_url( $dismiss_url );
                        } ?>">

                        <h2><?php echo "My Plugin $type"; ?></h2>
                        <p><?php echo $admin_notice->message; ?></p>

                    </div><?php
                }
            }
        }
    }

    public function action_admin_enqueue_scripts() {
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script(
            'myplugin-notify',
            plugins_url( 'js/myplugin-notify.js', __FILE__ ),
            array( 'jquery' )
        );
    }

}    // End  class MT2MBA_BACKEND_NOTICES

?>