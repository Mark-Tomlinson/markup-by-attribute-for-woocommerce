<?php
/**
 * Item 18 — one syntax dialect in the shipping code: short array syntax,
 * lowercase true/false/null, and single quotes unless the string earns its
 * double quotes. Guards new files as much as swept ones.
 *
 * Everything here runs through PHP's own tokenizer rather than regex, because
 * only the tokenizer can tell a quote that is *syntax* from a quote that is
 * *content* — `'<option value="'` is correct as written and must never be
 * flagged, while a naive pattern cannot see the difference.
 *
 * The quote rule is FEWER ESCAPES WINS, measured on the string's value, and it
 * runs in both directions. Single quotes are the default
 * because they cost nothing; double quotes earn their place only when they let
 * the literal carry fewer backslashes. Counting rather than eyeballing is the
 * point — the donation blurb in settings.php reads like a double-quoted string
 * (three escaped apostrophes) right up until you notice the `<a href="%1$s">`
 * inside it costs five escapes the other way, `\$s` among them, one deleted
 * backslash away from silently emptying the link.
 *
 * A literal whose source uses an escape sequence single quotes cannot express
 * (\n, \x41, \u{066A}, octal) is exempt: it has no choice.
 */
require __DIR__ . '/bootstrap.php';

// Optional root override, so these assertions can be aimed at a pre-sweep tree
// to watch them go red. A style test that has only ever run against already-
// conforming code has proven nothing; run-tests.php passes no argument.
$root = $argv[1] ?? dirname(__DIR__);

// --- Collect the hand-written shipping PHP ---------------------------------
// tests/ are excluded on test-17's precedent: the policy covers what ships.
// languages/*.l10n.php are excluded because they are not ours to style —
// WordPress 6.5+ generates them from the .po files, and the i18n tooling
// rewrites them wholesale on the next build.
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

$long_array   = [];
$upper_const  = [];
$wrong_quote  = [];
$strings_seen = 0;

foreach ($files as $path) {
	$rel = ltrim(str_replace(str_replace('\\', '/', $root), '', $path), '/');
	$tokens = token_get_all(file_get_contents($path));

	foreach ($tokens as $i => $token) {
		if (is_string($token)) continue;
		list($id, $text, $line) = $token;

		// `array(` — the construct, not the `array` type hint. The hint is
		// followed by whitespace and a variable; the construct by '('.
		if ($id === T_ARRAY) {
			for ($j = $i + 1; $j < count($tokens); $j++) {
				$next = $tokens[$j];
				if (is_array($next) && $next[0] === T_WHITESPACE) continue;
				if ($next === '(') $long_array[] = "$rel:$line";
				break;
			}
			continue;
		}

		// TRUE / FALSE / NULL as code. Tokenizing means the same words inside
		// a comment or a string never reach here.
		if ($id === T_STRING && in_array(strtolower($text), ['true', 'false', 'null'], true)
			&& $text !== strtolower($text)) {
			$upper_const[] = "$rel:$line ($text)";
			continue;
		}

		if ($id === T_CONSTANT_ENCAPSED_STRING) {
			$strings_seen++;
			$body = substr($text, 1, -1);

			// Escape sequences single quotes cannot express — the literal is
			// double-quoted out of necessity, not preference. \\ \$ \" are
			// deliberately NOT in this list: they are mere escaping, and a
			// single-quoted literal carries those characters directly.
			if ($text[0] === '"'
				&& preg_match('/\\\\([nrtvef]|[0-7]{1,3}|x[0-9A-Fa-f]{1,2}|u\{)/', $body)) {
				continue;
			}

			// eval() is safe on a T_CONSTANT_ENCAPSED_STRING: by definition it
			// is a literal with no interpolation, taken from a file that just
			// parsed. It is also the only way to count escapes against the
			// value rather than against one particular spelling of it.
			$value = eval('return ' . $text . ';');

			$as_single = substr_count($value, "'") + substr_count($value, '\\');
			// In double quotes a lone $ is literal; only one starting a valid
			// interpolation has to be escaped.
			$as_double = substr_count($value, '"') + substr_count($value, '\\')
				+ preg_match_all('/\$[A-Za-z_{]/', $value);

			$is_single = $text[0] === "'";
			$wants_single = $as_single <= $as_double;

			if ($is_single !== $wants_single) {
				$wrong_quote[] = "$rel:$line (" . ($is_single ? 'single' : 'double')
					. ", costs $as_single vs $as_double)";
			}
		}
	}
}

// --- Guards: a scanner that found nothing must not pass silently ------------
t_assert(count($files) >= 15, 'shipping PHP files scanned (' . count($files) . ')');
t_assert($strings_seen >= 500, "string literals tokenized ($strings_seen)");

// --- The policy -------------------------------------------------------------
$show = fn(array $set) => $set ? ' — ' . implode(', ', array_slice($set, 0, 8))
	. (count($set) > 8 ? ' (+' . (count($set) - 8) . ' more)' : '') : '';

t_assert(empty($long_array),
	'short array syntax everywhere, no array()' . $show($long_array));
t_assert(empty($upper_const),
	'true/false/null are lowercase' . $show($upper_const));
t_assert(empty($wrong_quote),
	'each string uses the delimiter needing fewer escapes' . $show($wrong_quote));

t_done();
