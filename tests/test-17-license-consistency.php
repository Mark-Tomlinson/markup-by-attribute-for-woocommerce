<?php
/**
 * Item 17 — the plugin ships one license, GPLv3 *or later*: that is what LICENSE
 * contains, what the plugin header declares, and what the wp.org listing says.
 * Every @license docblock tag must agree. Guards new files as much as old ones.
 *
 * "or later" is deliberate: a fork should be able to move to a future GPL
 * version without tracking the author down for permission.
 */
require __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);

// --- Every @license tag in shipping PHP ------------------------------------
$files = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$offenders = [];
$tagged = 0;
foreach ($files as $file) {
	if ($file->getExtension() !== 'php') continue;

	$path = str_replace('\\', '/', $file->getPathname());
	// tests/ carry no license header; .git and vendor are not ours to police
	if (strpos($path, '/tests/') !== false || strpos($path, '/.git/') !== false) continue;

	$source = file_get_contents($path);
	if (!preg_match('/@license\s+(\S+)/', $source, $m)) continue;

	$tagged++;
	if ($m[1] !== 'GPL-3.0-or-later') {
		$offenders[] = basename($path) . ' says ' . $m[1];
	}
}

// Item 20 cut @license and @author down to the one file wp.org actually reads, so
// the population is exactly one — and a guard that only counts would go quietly
// green on a tree with zero. Name the file instead: test-27 keeps the tag out of
// the others.
t_assert($tagged >= 1, "@license tags found to check ($tagged)");
t_assert((bool) preg_match('/@license\s+GPL-3\.0-or-later/',
	file_get_contents($root . '/markup-by-attribute-for-woocommerce.php')),
	'the main plugin file carries the @license tag');
t_assert(empty($offenders),
	'every @license docblock says GPL-3.0-or-later' . ($offenders ? ' — ' . implode(', ', $offenders) : ''));

// --- The user-visible declarations -----------------------------------------
$main = file_get_contents($root . '/markup-by-attribute-for-woocommerce.php');
t_assert((bool) preg_match('/^\s*\*\s*License:\s+GPLv3 or later\s*$/m', $main),
	'plugin header License: GPLv3 or later');
t_assert(strpos($main, 'https://www.gnu.org/licenses/gpl-3.0.html') !== false,
	'plugin header License URI points at GPL-3.0');
// Flattened: the boilerplate is wrapped across docblock lines, so collapse
// whitespace and the leading ' * ' before matching
$main_flat = preg_replace('/\s*\n\s*\*\s*/', ' ', $main);
t_assert(strpos($main_flat, 'either version 3 of the License, or (at your option) any later version') !== false,
	'main file states the or-later grant in full (FSF boilerplate wording)');

$readme = file_get_contents($root . '/readme.txt');
t_assert((bool) preg_match('/^License:\s+GPLv3 or later\s*$/m', $readme),
	'readme.txt License: GPLv3 or later');

t_assert(stripos(file_get_contents($root . '/README.md'), 'or later') !== false,
	'README.md says or later');

t_assert(strpos(file_get_contents($root . '/LICENSE'), 'Version 3, 29 June 2007') !== false,
	'LICENSE is the GPL v3 text');

t_done();
