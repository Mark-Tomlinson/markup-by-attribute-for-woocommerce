<?php
/**
 * Item 4 — Upgrade runner must execute modules in version order, not
 * filename order. Lexicographic sort runs db_upgrade_10_0.php before
 * db_upgrade_3_0.php; the v10 module then stamps mt2mba_db_version = 10.0
 * and the version_compare guard skips every earlier upgrade.
 *
 * Uses fixture modules under tests/fixtures/src/utility/upgrades/ with
 * versions (3.0, 10.0) that do not collide with real upgrade classes.
 */
define('ABSPATH', '/');
define('MT2MBA_PLUGIN_DIR', __DIR__ . '/fixtures/');
define('MT2MBA_SCHEMA_VERSION', '10.0');
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../markup-by-attribute-for-woocommerce.php';

// A v2-era store: both fixture upgrades (3.0, 10.0) are pending
$GLOBALS['mt2mba_test']['options']['mt2mba_db_version'] = '2.5';

mt2Tech\MarkupByAttribute\mt2mba_run_upgrades();

t_assert(
	$GLOBALS['mt2mba_test']['upgrade_log'] === ['3.0', '10.0'],
	'upgrades ran in version order 3.0 then 10.0 (got: ' . implode(', ', $GLOBALS['mt2mba_test']['upgrade_log']) . ')'
);
t_assert(
	get_option('mt2mba_db_version') === '10.0',
	'db version stamped at 10.0 after all upgrades'
);
t_assert(
	get_transient('mt2mba_upgrade_cooldown') === false,
	'no failure cooldown set'
);

t_done();
