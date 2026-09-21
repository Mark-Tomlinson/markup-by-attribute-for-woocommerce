<?php
/**
 * Item 26 — comments are written for the plugin's audience, not its authors
 *
 * The shipping code is read by strangers. A comment there tells them why the code
 * is not done the obvious way, in the present tense. It does not carry the record
 * of how it got that way: the people, the dates, the earlier attempts. Git holds
 * all of that with better provenance than a comment ever could.
 *
 * Only the mechanical signature of a session log is pinned here — a person's name
 * in parentheses, or an ISO date. "This comment is three times longer than it needs
 * to be" is a judgment made by hand, one site at a time, and a test claiming to hold
 * it would be pretending (test-27's precedent).
 *
 * PHP comments come from the tokenizer, so a date inside a string literal cannot
 * trip this. JS has no stdlib tokenizer; its comments are scanned as lines, which
 * is adequate for a pattern this specific.
 */
require __DIR__ . '/bootstrap.php';

// Optional root override, so these assertions can be aimed at a pre-sweep tree
// to watch them go red. run-tests.php passes no argument.
$root = $argv[1] ?? dirname(__DIR__);
$root = str_replace('\\', '/', $root);

const SESSION_LOG = '/\((?:Mark|Akina)\b|\bMark\'s\b|\b20\d\d-\d\d-\d\d\b/u';

$php_files = [];
$js_files  = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
	$path = str_replace('\\', '/', $file->getPathname());
	foreach (['/tests/', '/languages/', '/.git/', '/.claude/'] as $skip) {
		if (strpos($path, $skip) !== false) continue 2;
	}
	if ($file->getExtension() === 'php') $php_files[] = $path;
	if ($file->getExtension() === 'js')  $js_files[]  = $path;
}
sort($php_files);
sort($js_files);

$comments_seen = 0;
$offenders     = [];

foreach ($php_files as $path) {
	$rel = ltrim(substr($path, strlen($root)), '/');
	foreach (token_get_all(file_get_contents($path)) as $token) {
		if (!is_array($token) || !in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
		$comments_seen++;
		if (preg_match(SESSION_LOG, $token[1], $m)) {
			$offenders[] = "$rel:{$token[2]} ('{$m[0]}')";
		}
	}
}

foreach ($js_files as $path) {
	$rel = ltrim(substr($path, strlen($root)), '/');
	$in_block = false;
	foreach (file($path) as $n => $line) {
		$is_comment = $in_block || preg_match('~^\s*(//|/\*)~', $line);
		if (strpos($line, '/*') !== false) $in_block = true;
		if (strpos($line, '*/') !== false) $in_block = false;
		if (!$is_comment) continue;
		$comments_seen++;
		if (preg_match(SESSION_LOG, $line, $m)) {
			$offenders[] = "$rel:" . ($n + 1) . " ('{$m[0]}')";
		}
	}
}

// --- Guards: a scanner that found nothing must not pass silently ------------
t_assert(count($php_files) >= 15, 'shipping PHP files scanned (' . count($php_files) . ')');
t_assert(count($js_files) >= 3, 'shipping JS files scanned (' . count($js_files) . ')');
t_assert($comments_seen >= 300, "comments examined ($comments_seen)");

// --- The policy -------------------------------------------------------------
$show = $offenders ? ' — ' . implode(', ', array_slice($offenders, 0, 8))
	. (count($offenders) > 8 ? ' (+' . (count($offenders) - 8) . ' more)' : '') : '';
t_assert(empty($offenders), 'no shipping comment names a person or carries a date' . $show);

t_done();
