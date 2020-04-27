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
            // Not Markup by Attribute, leave
            return;
        }
        else
        {
            // Markup by Attribute class, get file name
            if ( is_file( $file = dirname( __FILE__ ) . str_replace( '_', '/', strtolower( str_ireplace( MT2MBA_PLUGIN_PREFIX, '/src', $class ) ) . '.php' ) ) )
            {
                // Valid class name, load
                require_once $file;
                $class::init();
            }
            else
            {
                $error_msg = MT2MBA_PLUGIN_NAME . " can not find " . $class . " at " . $file . ".</br>" .
                    "To correct, you will have to use FTP or your hosting administration panel to remove " . dirname( __FILE__ ) . ".";
                exit( $error_msg );
            }
        }
        return;
    }
}

?>