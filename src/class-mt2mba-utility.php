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
	 * Initialization method visible before instantiation
	 */
	public static function init()
	{
        global $mt2mba_db_version;

		// As a static method, it can not use '$this' and must use an
		// instantiated version of itself
		$self	= new self();

        // Check database version
		if ( get_site_option( 'mt2mba_db_version' ) != $mt2mba_db_version )
		{
            // And upgrade if necessary
            $self->mt2mba_db_upgrade();
		}
	}

    /**
     * Database has been determined to be wrong version; upgrade
     */
	function mt2mba_db_upgrade()
	{
		// --------------------------------------------------------------
		// Update database from version 1.x. Leave 1.x data for fallback.
        // --------------------------------------------------------------
		global $wpdb;
        global $mt2mba_db_version;
        global $mt2mba_price_meta;

        // Failsafe
        if ( get_site_option( 'mt2mba_db_version' ) == $mt2mba_db_version ) { return; }

        // Add prefix to attribute markup meta data
		$results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}termmeta WHERE meta_key LIKE 'markup'" );
		foreach( $results as $row )
		{
			if( strpos($row->meta_key, 'mt2mba_' ) === FALSE )
			{
				add_term_meta( $row->term_id, "mt2mba_" . $row->meta_key, $row->meta_value, TRUE );
			}
		}

		// Add prefix to product markup meta data
		$results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}postmeta WHERE `meta_key` LIKE '%_markup_amount'" );
		foreach( $results as $row )
		{
			if( strpos($row->meta_key, 'mt2mba_' ) === FALSE )
			{
				add_post_meta( $row->post_id, "mt2mba_" . $row->meta_key, $row->meta_value, TRUE );
			}
        }
        
		// Bracket description and save base regular price
		global $markup_desc_beg;
        global $markup_desc_end;

        $results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}postmeta WHERE `meta_value` LIKE '%{$mt2mba_price_meta}%'" );
		foreach( $results as $row )
		{
			if( ( strpos( $row->meta_value, $markup_desc_beg ) === FALSE ) && ( strpos( $row->meta_value, $mt2mba_price_meta ) !== FALSE ) )
			{
                update_post_meta( $row->post_id, $row->meta_key, $markup_desc_beg . $row->meta_value . $markup_desc_end );
			}
			$beg = strpos( $row->meta_value, $mt2mba_price_meta ) + strlen( $mt2mba_price_meta );
			$end = strpos( $row->meta_value, PHP_EOL );
            $base_price = preg_replace( '/[^\p{L}\p{N}\s\.]/u', '', substr( $row->meta_value, $beg, $end - $beg ) );
            //update_post_meta( $row->post_id, 'mt2mba_base_regular_price', $base_price );
        }

        // Made it this far, update databse version
        update_option( 'mt2mba_db_version', $mt2mba_db_version );
	}

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