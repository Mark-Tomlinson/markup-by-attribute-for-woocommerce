<?php
/**
 * Utility functions used by Markup-by-Attribute
 * 
 * @author  Mark Tomlinson
 * 
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit( );

class MT2MBA_UTILITY
{

    /**
    * Doesn't do anything, but has to exist for autoloader
    * 
    */
    public static function init() { }

    /**
    * Remove pricing information from string
    * @param  string $beginning    Marker at the begining of the string to be removed
    * @param  string $ending       Marker at the ending of the string to be removed
    * @param  string $string       The string to be processed
    * @return string               The string minus the text to be removed and the begining and ending markers
    */
    public function remove_pricing_info($beginning, $ending, $string)
	{
		$beginningPos = strpos( $string, $beginning, 0 );
		$endingPos    = strpos( $string, $ending, $beginningPos );
		if ( $beginningPos === FALSE || $endingPos === FALSE )
		{
			return $string;
		}
		$textToDelete = substr( $string, $beginningPos, ( $endingPos + strlen( $ending ) ) - $beginningPos );
		return str_replace( $textToDelete, '', $string );
    }
}
?>