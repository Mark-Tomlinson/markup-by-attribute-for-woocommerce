<?php
namespace mt2Tech\MarkupByAttribute\Utility\Upgrades;

/**
 * Database upgrade: schema version 2.0
 *
 * Migrates data from plugin v1.x to v2.0 schema:
 * - Adds 'mt2mba_' prefix to attribute and product markup meta keys
 * - Wraps variation markup descriptions in span tags
 * - Extracts and stores base regular prices
 * - Removes deprecated formatting options
 *
 * Original v1.x data is left intact as fallback.
 *
 * @package   mt2Tech\MarkupByAttribute\Utility\Upgrades
 * @author    Mark Tomlinson
 * @license   GPL-2.0+
 * @since     2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit();

class Db_Upgrade_2_0 implements UpgradeInterface {

	/**
	 * @inheritDoc
	 */
	public static function version(): string {
		return '2.0';
	}

	/**
	 * @inheritDoc
	 */
	public function run(): void {
		global $wpdb;

		// Add prefix to attribute markup meta data key
		$results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}termmeta WHERE meta_key LIKE 'markup'");
		foreach ($results as $row) {
			if (strpos($row->meta_key, 'mt2mba_') === FALSE) {
				add_term_meta($row->term_id, "mt2mba_" . $row->meta_key, $row->meta_value, TRUE);
			}
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
			$v_product = get_post($row->post_id, 'ARRAY_A');
			if ($last_parent_id != $v_product['post_parent']) {
				$beg        = strpos($row->meta_value, MT2MBA_PRICE_META) + strlen(MT2MBA_PRICE_META);
				$end        = strpos($row->meta_value, PHP_EOL);
				$base_price = preg_replace('/[^\p{L}\p{N}\s\.]/u', '', substr($row->meta_value, $beg, $end - $beg));
				update_post_meta($v_product['post_parent'], 'mt2mba_base_regular_price', (float) $base_price);
				$last_parent_id = $v_product['post_parent'];
			}
		}

		// Clean up deprecated formatting options
		$wpdb->delete("{$wpdb->prefix}options", array('option_name' => 'mt2mba_decimal_points'));
		$wpdb->delete("{$wpdb->prefix}options", array('option_name' => 'mt2mba_symbol_before'));
		$wpdb->delete("{$wpdb->prefix}options", array('option_name' => 'mt2mba_symbol_after'));

		// Stamp version as last act of successful upgrade
		update_option('mt2mba_db_version', self::version(), false);
	}
}
