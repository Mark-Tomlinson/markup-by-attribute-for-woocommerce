<?php
/**
 * Release metadata must agree with itself, and carry no placeholders
 *
 * The version lives in SIX places and the readme carries three more strings that
 * only a human re-reading the file would ever notice were stale. Both known
 * misses were of exactly that kind:
 *
 *   - 4.7.0 shipped with three markup-validation fixes and NO changelog entry for
 *     them (item 21, found 2026-08-21, documented in 4.8.0 instead).
 *   - 4.7.0 also shipped with "= New in Version 4.6 =" still in the readme, a
 *     whole release out of date (found 2026-08-23 while bumping to 4.8.0).
 *
 * Neither was a coding error and neither could fail a test, because nothing
 * compared these strings to anything. This does.
 *
 * ⚠️ THE ONE THING IT CANNOT GUARD: whether the release *date* is the right one.
 * It enforces "a real month and year" rather than "TBD", so a placeholder can no
 * longer ship — but a month that slips from August to September still has to be
 * corrected by hand.
 */
require __DIR__ . '/bootstrap.php';

$root   = dirname(__DIR__);
$main   = file_get_contents($root . '/markup-by-attribute-for-woocommerce.php');
$readme = file_get_contents($root . '/readme.txt');

/** First capture group of $pattern, or a legible marker when it does not match. */
function t29_capture(string $subject, string $pattern): string {
	return preg_match($pattern, $subject, $m) ? trim($m[1]) : '(not found)';
}

//region All six version strings agree
// Any one of these left behind ships a plugin whose header, constant and readme
// disagree — which wp.org reads for the update banner and WordPress reads for the
// installed version.
$versions = [
	'@version (main docblock)'   => t29_capture($main,   '/^\s*\*\s*@version\s+(\S+)/m'),
	'Version: (plugin header)'   => t29_capture($main,   '/^\s*\*\s*Version:\s+(\S+)/m'),
	'Stable tag: (plugin header)' => t29_capture($main,  '/^\s*\*\s*Stable tag:\s+(\S+)/m'),
	'MT2MBA_VERSION constant'    => t29_capture($main,   "/define\(\s*'MT2MBA_VERSION'\s*,\s*'([^']+)'/"),
	'Version: (readme)'          => t29_capture($readme, '/^Version:\s+(\S+)/m'),
	'Stable tag: (readme)'       => t29_capture($readme, '/^Stable tag:\s+(\S+)/m'),
];

$version = $versions['MT2MBA_VERSION constant'];

t29_assert_version_shape($version, 'MT2MBA_VERSION is a three-part version number');

foreach ($versions as $where => $found) {
	t_assert($found === $version,
		$found === $version
			? "$where reads $version"
			: "$where should read $version but reads $found");
}
//endregion

//region The changelog and upgrade notice lead with THIS version
// "= Unreleased =" was the real 4.8.0 heading until the version bump renamed it,
// and nothing but a human's memory stood between that and the wp.org page.
$changelog_top = t29_capture($readme, '/==\s*Changelog\s*==\s*\R+=\s*([^=\r\n]+?)\s*=/');
t_assert($changelog_top === $version,
	$changelog_top === $version
		? "the changelog opens with $version"
		: "the changelog should open with $version but opens with '$changelog_top'");

$upgrade_top = t29_capture($readme, '/==\s*Upgrade Notice\s*==\s*\R+=\s*([^=\r\n]+?)\s*=/');
t_assert($upgrade_top === $version,
	$upgrade_top === $version
		? "the upgrade notice opens with $version"
		: "the upgrade notice should open with $version but opens with '$upgrade_top'");

// Belt and braces: the word must not survive anywhere, under any heading.
t_assert(preg_match('/^=\s*Unreleased\s*=/mi', $readme) === 0,
	'no "= Unreleased =" heading survives the version bump');
//endregion

//region The release date is a date, not a placeholder
// TBD is fine while the release is being built and fatal the moment it ships.
$release_date = t29_capture($readme, '/==\s*Changelog\s*==\s*\R+=[^=\r\n]+=\s*\R+\*Release Date:\s*([^*\r\n]+?)\s*\*/');
$months = 'January|February|March|April|May|June|July|August|September|October|November|December';

t_assert((bool) preg_match("/^($months)\s+\d{4}$/", $release_date),
	preg_match("/^($months)\s+\d{4}$/", $release_date)
		? "the release date reads '$release_date'"
		: "the release date must be a month and year, not '$release_date'");
//endregion

//region "New in Version" tracks the release, not one from two bumps ago
// This is the one that actually got missed: 4.7.0 shipped advertising 4.6.
$highlight = t29_capture($readme, '/^=\s*New in Version\s+([0-9.]+)\s*=/m');
$expected  = implode('.', array_slice(explode('.', $version), 0, 2));

t_assert($highlight === $expected,
	$highlight === $expected
		? "the \"New in Version\" section reads $expected"
		: "the \"New in Version\" section should read $expected but reads $highlight");
//endregion

//region The schema version is a real, already-released version
// MT2MBA_SCHEMA_VERSION means "last release that changed the database", so it can
// equal the current version but must never run ahead of it — the upgrade runner
// compares the stored db version against it and would look for migrations that
// do not exist.
$schema = t29_capture($main, "/define\(\s*'MT2MBA_SCHEMA_VERSION'\s*,\s*'([^']+)'/");
t29_assert_version_shape($schema, 'MT2MBA_SCHEMA_VERSION is a three-part version number');

t_assert(version_compare($schema, $version, '<='),
	version_compare($schema, $version, '<=')
		? "the schema version ($schema) is at or behind the plugin version ($version)"
		: "the schema version ($schema) runs AHEAD of the plugin version ($version)");
//endregion

/** Shared shape check so a '(not found)' cannot quietly pass a comparison. */
function t29_assert_version_shape(string $value, string $description): void {
	t_assert((bool) preg_match('/^\d+\.\d+\.\d+$/', $value),
		preg_match('/^\d+\.\d+\.\d+$/', $value) ? $description : "$description — found '$value'");
}

t_done();
