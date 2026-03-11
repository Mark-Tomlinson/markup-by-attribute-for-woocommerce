<?php
namespace mt2Tech\MarkupByAttribute\Utility\Upgrades;

/**
 * Database upgrade: schema version 3.0
 *
 * Cleans up the removed "Preserve Zero Prices" setting and its dismissal
 * tracking option. Waits until the admin notice has been dismissed before
 * deleting, so users have a chance to see the warning about reapplying markups.
 *
 * @package   mt2Tech\MarkupByAttribute\Utility\Upgrades
 * @author    Mark Tomlinson
 * @license   GPL-2.0+
 * @since     4.6.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit();

class Db_Upgrade_3_0 implements UpgradeInterface {

	/**
	 * @inheritDoc
	 */
	public static function version(): string {
		return '3.0';
	}

	/**
	 * @inheritDoc
	 *
	 * Skips (without error) if the allow_zero option exists but the user
	 * hasn't dismissed the admin notice yet. This causes the runner to
	 * re-attempt on the next admin page load, which is lightweight
	 * (two get_option calls) and intentional.
	 */
	public function run(): void {
		$allow_zero_exists = get_option('mt2mba_allow_zero', false) !== false;
		$notice_dismissed  = get_option('mt2mba_dismissed_allow_zero_removed', false) !== false;

		if ($allow_zero_exists && !$notice_dismissed) {
			// Option exists but user hasn't seen the warning yet — skip for now
			return;
		}

		// Either the option was never set, or the notice has been dismissed
		delete_option('mt2mba_allow_zero');
		delete_option('mt2mba_dismissed_allow_zero_removed');

		// Stamp version as last act of successful upgrade
		update_option('mt2mba_db_version', self::version(), false);
	}
}
