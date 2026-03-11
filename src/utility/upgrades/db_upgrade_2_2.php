<?php
namespace mt2Tech\MarkupByAttribute\Utility\Upgrades;

/**
 * Database upgrade: schema version 2.2
 *
 * Removes the discontinued 'mt2mba_show_attrb_list' setting.
 *
 * @package   mt2Tech\MarkupByAttribute\Utility\Upgrades
 * @author    Mark Tomlinson
 * @license   GPL-2.0+
 * @since     2.2.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit();

class Db_Upgrade_2_2 implements UpgradeInterface {

	/**
	 * @inheritDoc
	 */
	public static function version(): string {
		return '2.2';
	}

	/**
	 * @inheritDoc
	 */
	public function run(): void {
		global $wpdb;

		// Delete discontinued setting
		$wpdb->delete("{$wpdb->prefix}options", array('option_name' => 'mt2mba_show_attrb_list'));

		// Stamp version as last act of successful upgrade
		update_option('mt2mba_db_version', self::version(), false);
	}
}
