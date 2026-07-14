<?php
namespace mt2Tech\MarkupByAttribute\Utility\Upgrades;

// Fixture upgrade module: logs its run and stamps its version.
class Db_Upgrade_10_0 implements UpgradeInterface {
	public static function version(): string { return '10.0'; }
	public function run(): void {
		$GLOBALS['mt2mba_test']['upgrade_log'][] = self::version();
		update_option('mt2mba_db_version', self::version());
	}
}
