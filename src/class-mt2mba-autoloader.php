<?php
/**
 * Autoloads MyPlugin classes using WordPress convention.
 *
 * @author Carl Alexander
 */
class MT2MBA_AUTOLOADER
{
    /**
     * Registers MyPlugin_Autoloader as an SPL autoloader.
     *
     * @param boolean $prepend
     */
    public static function register( $prepend = FALSE )
    {
        if ( version_compare( phpversion(), '5.3.0', '>=' ) )
        {
            spl_autoload_register( array( new self, 'autoload' ), TRUE, $prepend );
        }
        else
        {
            spl_autoload_register( array( new self, 'autoload' ) );
        }
    }

    /**
     * Handles autoloading of MyPlugin classes.
     *
     * @param string $class
     */
    public static function autoload( $class )
    {
        if ( 0 !== strpos( $class, 'MT2MBA' ) )     // Change string to match plugin class prefix
        {
            return;
        }
        else
        {
            if ( is_file( $file = dirname( __FILE__ ) . '/class-' . strtolower( str_replace( array( '_', "\0" ), array( '-', '' ), $class ).'.php' ) ) )
            {
                require_once $file;
                $class::init();
            }
        }
    }
}

?>