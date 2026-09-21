<?php
/**
 * Item 20 — the mechanical half of docblock noise reduction.
 *
 * Only the parts a machine can judge are pinned here. "This description merely
 * restates the method name" is a judgment call that was made by hand, one site
 * at a time; a test that claimed to hold it would be pretending.
 *
 * What IS mechanical:
 *
 *   1. @since on a private or protected member. The tag promises an API date to
 *      callers, and a private member has none — nothing outside the class can
 *      reach it, so the version it appeared in documents nothing.
 *
 *   2. Singleton scaffolding. Seven classes carry identical get_instance() /
 *      __clone() / __wakeup() blocks whose only variation is the class name, and
 *      whose @return restates a `: self` already in the signature. One line each
 *      is the whole payload.
 *
 *   3. @author and @license outside the main plugin file. Nineteen copies of the
 *      same two facts; wp.org reads them from markup-by-attribute-for-woocommerce.php
 *      and nowhere else. @package stays — it tracks the namespace, which varies.
 *
 * NOT pinned, deliberately: @param and @return. Item 20's first draft proposed
 * cutting the ones that "only restate a declared type" — measurement killed that
 * clause. Zero of 118 @param tags are bare, and 82 of 136 functions have at least
 * one parameter with no type hint at all, so the tag is frequently the only type
 * information in the file. Anything that trims them is deleting data.
 *
 * Structure comes from the tokenizer; only the text INSIDE a comment is matched
 * with patterns. See CLAUDE.md, "Syntax Dialect".
 */
require __DIR__ . '/bootstrap.php';

// Optional root override, so these assertions can be aimed at a pre-sweep tree
// to watch them go red. run-tests.php passes no argument.
$root = $argv[1] ?? dirname(__DIR__);

$files = [];
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
	if ($file->getExtension() !== 'php') continue;
	$path = str_replace('\\', '/', $file->getPathname());
	if (strpos($path, '/tests/') !== false) continue;
	if (strpos($path, '/languages/') !== false) continue;
	if (strpos($path, '/.git/') !== false) continue;
	if (strpos($path, '/.claude/') !== false) continue;
	$files[] = $path;
}
sort($files);

// The three methods every singleton in this plugin repeats verbatim.
const SINGLETON_METHODS = ['get_instance', '__clone', '__wakeup'];
// A one-line summary plus the two fence lines. Anything longer is scaffolding.
const SINGLETON_MAX_LINES = 3;

$since_private  = [];
$fat_singleton  = [];
$stray_identity = [];
$docblocks_seen = 0;

foreach ($files as $path) {
	$rel = ltrim(str_replace(str_replace('\\', '/', $root), '', $path), '/');
	$is_main_file = basename($rel) === 'markup-by-attribute-for-woocommerce.php';

	$tokens = token_get_all(file_get_contents($path));
	$count  = count($tokens);

	for ($i = 0; $i < $count; $i++) {
		if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_DOC_COMMENT) continue;

		$doc  = $tokens[$i][1];
		$line = $tokens[$i][2];
		$docblocks_seen++;

		// --- What does this block document? --------------------------------
		// Walk forward past whitespace, comments and modifiers to the first
		// token that names a construct. Visibility is captured on the way.
		$visibility = '';
		$kind       = '';
		$name       = '';
		for ($j = $i + 1; $j < $count; $j++) {
			$token = $tokens[$j];
			if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT], true)) continue;
			if (is_array($token) && in_array($token[0], [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
				$visibility = strtolower($token[1]);
				continue;
			}
			if (is_array($token) && in_array($token[0], [T_STATIC, T_ABSTRACT, T_FINAL, T_VAR], true)) continue;
			// A typed property declaration: the type sits between the modifiers
			// and the variable, so step over it rather than giving up here.
			if (is_array($token) && in_array($token[0], [T_STRING, T_ARRAY, T_CALLABLE], true)) continue;
			if ($token === '?' || $token === '|') continue;

			if (is_array($token) && $token[0] === T_FUNCTION) {
				$kind = 'function';
				for ($k = $j + 1; $k < $count; $k++) {
					if (is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) continue;
					if (is_array($tokens[$k]) && $tokens[$k][0] === T_STRING) $name = $tokens[$k][1];
					break;
				}
			} elseif (is_array($token) && $token[0] === T_VARIABLE) {
				$kind = 'property';
				$name = $token[1];
			}
			break;
		}

		$lines = substr_count($doc, "\n") + 1;

		// --- 1. @since on a member nothing outside can call ------------------
		if (($visibility === 'private' || $visibility === 'protected')
			&& $kind !== ''
			&& preg_match('/^\s*\*\s*@since\b/m', $doc)) {
			$since_private[] = "$rel:$line ($visibility $name)";
		}

		// --- 2. Singleton scaffolding ----------------------------------------
		if ($kind === 'function' && in_array($name, SINGLETON_METHODS, true)
			&& $lines > SINGLETON_MAX_LINES) {
			$fat_singleton[] = "$rel:$line ($name(), $lines lines)";
		}

		// --- 3. @author / @license outside the main plugin file --------------
		if (!$is_main_file && preg_match('/^\s*\*\s*@(author|license)\b/m', $doc, $m)) {
			$stray_identity[] = "$rel:$line (@{$m[1]})";
		}
	}
}

// --- Guards: a scanner that found nothing must not pass silently ------------
// verify-20.php's first cut printed PASS after skipping every single file.
t_assert(count($files) >= 15, 'shipping PHP files scanned (' . count($files) . ')');
t_assert($docblocks_seen >= 100, "docblocks tokenized ($docblocks_seen)");

// --- The policy -------------------------------------------------------------
$show = fn(array $set) => $set ? ' — ' . implode(', ', array_slice($set, 0, 8))
	. (count($set) > 8 ? ' (+' . (count($set) - 8) . ' more)' : '') : '';

t_assert(empty($since_private),
	'no @since on private or protected members' . $show($since_private));
t_assert(empty($fat_singleton),
	'singleton scaffolding documented in one line' . $show($fat_singleton));
t_assert(empty($stray_identity),
	'@author/@license live only in the main plugin file' . $show($stray_identity));

t_done();
