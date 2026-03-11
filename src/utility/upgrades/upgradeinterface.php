<?php
namespace mt2Tech\MarkupByAttribute\Utility\Upgrades;

/**
 * Interface for database schema upgrade modules
 *
 * Each upgrade module targets a specific schema version and contains the
 * migration logic to bring the database up to that version. Modules must
 * be idempotent — safe to re-run if a previous attempt partially completed.
 *
 * @package   mt2Tech\MarkupByAttribute\Utility\Upgrades
 * @author    Mark Tomlinson
 * @license   GPL-2.0+
 * @since     4.6.0
 */
interface UpgradeInterface {
	/**
	 * The schema version this upgrade brings the database to
	 *
	 * @return string Version string (e.g., '2.0', '3.0')
	 */
	public static function version(): string;

	/**
	 * Execute the migration
	 *
	 * Must be idempotent. Must update 'mt2mba_db_version' option to
	 * self::version() as its last act on success.
	 *
	 * @return void
	 * @throws \Exception on failure (triggers cooldown in runner)
	 */
	public function run(): void;
}
