<?php
namespace mt2Tech\MarkupByAttribute\Utility;
use mt2Tech\MarkupByAttribute\Backend as Backend;
/**
 * Utility functions used by Markup-by-Attribute
 *
 * @author	Mark Tomlinson
 *
 */
// Exit if accessed directly
if (!defined('ABSPATH')) exit();

class General {
	/**
	 * Singleton because we only want one instance at a time.
	 */
	private static $instance = null;

	// Public method to get the instance
	public static function get_instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// Prevent cloning of the instance
	public function __clone() {}

	// Prevent unserializing of the instance
	public function __wakeup() {}

	// Private constructor
	private function __construct() {
		// Check database version
		if (get_site_option('mt2mba_db_version') < MT2MBA_DB_VERSION) {
			// And upgrade if necessary
			$this->mt2mba_db_upgrade();
		}
		// Set global values used throughout the code
		if (!defined('MT2MBA_CURRENCY_SYMBOL')) {
			$settings = Backend\Settings::get_instance();
			define('MT2MBA_DESC_BEHAVIOR', get_option('mt2mba_desc_behavior', $settings->desc_behavior));
			define('MT2MBA_DROPDOWN_BEHAVIOR', get_option('mt2mba_dropdown_behavior', $settings->dropdown_behavior));
			define('MT2MBA_INCLUDE_ATTRB_NAME', get_option('mt2mba_include_attrb_name', $settings->include_attrb_name));
			define('MT2MBA_HIDE_BASE_PRICE', get_option('mt2mba_hide_base_price', $settings->hide_base_price));
			define('MT2MBA_SALE_PRICE_MARKUP', get_option('mt2mba_sale_price_markup', $settings->sale_price_markup));
			define('MT2MBA_ROUND_MARKUP', get_option('mt2mba_round_markup', $settings->round_markup));
			define('MT2MBA_SHOW_ATTRB_LIST', get_option('mt2mba_show_attrb_list', $settings->show_attrb_list));
			define('MT2MBA_MAX_VARIATIONS', get_option('mt2mba_max_variations', $settings->max_variations));
			define('MT2MBA_CURRENCY_SYMBOL', get_woocommerce_currency_symbol(get_woocommerce_currency()));
		}
	}

	/**
	 * Database has been determined to be wrong version; upgrade
	 */
	function mt2mba_db_upgrade() {
		// Failsafe
		$current_db_version = get_site_option('mt2mba_db_version', 1);
		if ($current_db_version >= MT2MBA_DB_VERSION) return;

		global $wpdb;

		// --------------------------------------------------------------
		// Update database from version 1.x. Leave 1.x data for fallback.
		// --------------------------------------------------------------
		if ($current_db_version < 2.0) {
			// Add prefix to attribute markup meta data key
			$results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}termmeta WHERE meta_key LIKE 'markup'");
			foreach ($results as $row) {
				if (strpos($row->meta_key, 'mt2mba_') === FALSE) {
					add_term_meta($row->term_id, "mt2mba_" . $row->meta_key, $row->meta_value, TRUE);
				}
			}

			// Add markup description to attribute terms
			$results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}termmeta WHERE meta_key LIKE 'mt2mba_markup'");
			foreach ($results as $row) {
				$term = get_term((integer) $row->term_id);
				$description = trim($this->remove_bracketed_string(ATTRB_MARKUP_DESC_BEG, ATTRB_MARKUP_END, trim($term->description)));
				$description .= PHP_EOL . ATTRB_MARKUP_DESC_BEG . $row->meta_value . ATTRB_MARKUP_END;
				wp_update_term($row->term_id, $term->taxonomy, array('description' => trim($description)));
			}

			// Add prefix to product markup meta data
			$results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}postmeta WHERE `meta_key` LIKE '%_markup_amount'");
			foreach ($results as $row) {
				if (strpos($row->meta_key, 'mt2mba_') === FALSE) {
					add_post_meta($row->post_id, "mt2mba_" . $row->meta_key, $row->meta_value, TRUE);
				}
			}

			// Bracket description and save base regular price
			$last_parent_id = '';
			$results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}postmeta WHERE `meta_value` LIKE '%" . MT2MBA_PRICE_META . "%'");
			foreach ($results as $row) {
				if ((strpos($row->meta_value, PRODUCT_MARKUP_DESC_BEG) === FALSE) && (strpos($row->meta_value, MT2MBA_PRICE_META) !== FALSE)) {
					update_post_meta($row->post_id, $row->meta_key, PRODUCT_MARKUP_DESC_BEG . $row->meta_value . PRODUCT_MARKUP_DESC_END);
				}
				$v_product	= get_post($row->post_id, 'ARRAY_A');
				if ($last_parent_id != $v_product['post_parent']) {
					$beg			= strpos($row->meta_value, MT2MBA_PRICE_META) + strlen(MT2MBA_PRICE_META);
					$end			= strpos($row->meta_value, PHP_EOL);
					$base_price	 = preg_replace('/[^\p{L}\p{N}\s\.]/u', '', substr($row->meta_value, $beg, $end - $beg));
					update_post_meta($v_product['post_parent'], 'mt2mba_base_regular_price', (float) $base_price);
					$last_parent_id = $v_product['post_parent'];
				}
			}
		}

		// -----------------------------------------------
		// Clean database for conversion from version 2.3.
		// -----------------------------------------------
		$wpdb->delete("{$wpdb->prefix}options", array('option_name'=>'mt2mba_decimal_points'));
		$wpdb->delete("{$wpdb->prefix}options", array('option_name'=>'mt2mba_symbol_before'));
		$wpdb->delete("{$wpdb->prefix}options", array('option_name'=>'mt2mba_symbol_after'));

		// Made it this far, update database version
		update_option('mt2mba_db_version', MT2MBA_DB_VERSION);
	}


	/**
	 * Remove bracketed substring from string
	 *
	 * @param	string $beginning	Marker at the beginning of the string to be removed
	 * @param	string $ending		Marker at the ending of the string to be removed
	 * @param	string $string		The string to be processed
	 * @return	string				The string minus the text to be removed and the beginning and ending markers
	 */
	public function remove_bracketed_string($beginning, $ending, $string) {
		$beginningPos = strpos($string, $beginning, 0);
		$endingPos	= strpos($string, $ending, $beginningPos);

		if ($beginningPos === FALSE || $endingPos === FALSE) return trim($string);

		$textToDelete = substr($string, $beginningPos, ($endingPos + strlen($ending)) - $beginningPos);

		return trim(str_replace($textToDelete, '', $string));
	}

	/**
	 * Clean up the price or markup and reformat according to currency options
	 *
	 * @param	string	$price	A number that will be reformatted into the local currency
	 * @return	string			Properly formatted price with currency indicator
	 */
	public function clean_up_price($price) {
		if (is_numeric($price))	{	// Might be a percentage in rare cases
			return strip_tags(wc_price(abs($price)));
		}
		return '';
	}

	/**
	 * Format the markup that appears in the options drop-down box
	 *
	 * @param	float	$markup	Signed markup amount
	 * @return	string			Formatted markup
	 */
	function format_option_markup($markup) {
		if ($markup <> "" && $markup <> 0) {
			// Jump out if markup is not to be displayed.
			if (MT2MBA_DROPDOWN_BEHAVIOR == 'hide') {
				return '';
			}

			// Set sign
			$sign = $markup < 0 ? "-" : "+";
			// There are instances where the markup for the product is not in the database.
			// Where this is the case and the markup is a percentage, show only the percentage.
			if (strpos($markup, '%')) {
				// Return formatted with percentage
				$markup = trim(html_entity_decode($markup));
			} elseif (MT2MBA_DROPDOWN_BEHAVIOR == 'add') {
				// Return formatted with symbol
				$markup = html_entity_decode($sign . $this->clean_up_price($markup));
			} else {
				// Return formatted without symbol
				$markup = html_entity_decode($sign . trim(str_replace(MT2MBA_CURRENCY_SYMBOL, "", $this->clean_up_price($markup))));
			}
			return " (" . $markup . ")";
		}
		// No markup; return empty string
		return '';
	}

	/**
	 * Format the add and subtract line items that appears in the variation description
	 * @param float  $markup     Signed markup amount
	 * @param string $attrb_name Attribute name that the markup applies to
	 * @param string $term_name  Attribute term that the markup applies to
	 * @return string           Formatted description 
	 */
	function format_description_markup($markup, $attrb_name, $term_name) {
		if ($markup <> "" && $markup <> 0) {
			// Two different translation strings based on whether attribute name is included
			if (MT2MBA_INCLUDE_ATTRB_NAME == 'yes') {
				// Translators; %1$s is the formatted price, %2$s is the attribute name, %3$s is the term name
				$desc_format = $markup < 0 ? 
					__('Subtract %1$s for %2$s: %3$s', 'markup-by-attribute') : 
					__('Add %1$s for %2$s: %3$s', 'markup-by-attribute');
				
				return html_entity_decode(
					sprintf(
						$desc_format,
						$this->clean_up_price($markup),
						$attrb_name,
						$term_name
					)
				);
			} else {
				// Translators; %1$s is the formatted price, %2$s is the term name
				$desc_format = $markup < 0 ? 
					__('Subtract %1$s for %2$s', 'markup-by-attribute') : 
					__('Add %1$s for %2$s', 'markup-by-attribute');
				
				return html_entity_decode(
					sprintf(
						$desc_format,
						$this->clean_up_price($markup),
						$term_name
					)
				);
			}
		}
		// No markup; return empty string
		return '';
	}

}	//	End class MT2MBA_UTILITY_GENERAL
?>