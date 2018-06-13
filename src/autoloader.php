<?php
/**
 * Autoloads MyPlugin classes using PSR-0 standard.
 *
 * @author Carl Alexander
 */
class MyPlugin_Autoloader
{
    /**
     * Registers MyPlugin_Autoloader as an SPL autoloader.
     * @param boolean $prepend
     */
    public static function register($prepend = false)
    {
        if (version_compare(phpversion(), '5.3.0', '>=')) {
            spl_autoload_register(array(new self, 'autoload'), true, $prepend);
        } else {
            spl_autoload_register(array(new self, 'autoload'));
        }
    }
 
    /**
     * Handles autoloading of MyPlugin classes.
     * @param string $class
     */
    public static function autoload($class)
    {
        if (0 !== strpos($class, 'MyPlugin')) {
            return;
        }
 
        if (is_file($file = dirname(__FILE__).'/../'.str_replace(array('_', "\0"), array('/', ''), $class).'.php')) {
            require_once $file;
        }
    }
}

?>