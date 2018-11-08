<?php
/**
 * Autoloads MyPlugin classes using WordPress convention.
 *
 * @author Carl Alexander
 */
class MT2MBA_AUTOLOADER
{
    /**
     * Registers class-mt2mba-autoloader as an SPL autoloader.
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
     * Handles autoloading of Markup-by-Attribute classes.
     *
     * @param string $class
     */
    public static function autoload( $class )
    {
        if ( 0 !== strpos( $class, MT2MBA_PLUGIN_PREFIX ) )
        {
            return;
        }
        else
        {
            if ( is_file( $file = strtolower( dirname( __FILE__ ) . str_replace( '_', '/', str_ireplace( MT2MBA_PLUGIN_PREFIX, '/src', $class ) ) . '.php' ) ) )
            {
                require_once $file;
                $class::init();
            }
        }
    }
}

?>