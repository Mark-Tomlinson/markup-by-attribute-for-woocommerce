<?php
/**
 * Standalone test runner — executes each tests/test-*.php in its own PHP
 * process (isolating singletons, constants, and stub state) and summarizes.
 *
 * Usage: php tests/run-tests.php
 */
$tests = glob(__DIR__ . '/test-*.php');
sort($tests);

$failed = [];
foreach ($tests as $test) {
	echo "\n=== " . basename($test) . " ===\n";
	passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($test), $exit_code);
	if ($exit_code !== 0) {
		$failed[] = basename($test);
	}
}

echo "\n" . str_repeat('-', 50) . "\n";
if ($failed) {
	echo 'FAILED: ' . count($failed) . ' of ' . count($tests) . ' test files: ' . implode(', ', $failed) . "\n";
	exit(1);
}
echo 'PASSED: all ' . count($tests) . " test files\n";
