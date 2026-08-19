/**
 * Runs the JS normalizeMarkupNotation() against the shared notation table and
 * prints one JSON array of results, for test-08 to compare with the PHP results.
 *
 * The function is extracted from the plugin source rather than duplicated here,
 * so this checks the code that actually ships. Each case carries its own decimal
 * separator, which is injected the same way WordPress injects it at runtime
 * (window.mt2mbaMarkup, via wp_localize_script).
 *
 *   node tests/js-normalize-check.js
 */
const fs = require('fs');
const path = require('path');

const source = fs.readFileSync(
	path.join(__dirname, '..', 'src', 'js', 'jq-mt2mba-validate-markup.js'),
	'utf8'
);

// Lift the normalizer and the pattern out of the jQuery ready() wrapper
const fnMatch = source.match(/\tfunction normalizeMarkupNotation\(raw\) \{[\s\S]*?\n\t\}/);
if (!fnMatch) {
	console.error('FATAL: could not find normalizeMarkupNotation() in the plugin JS');
	process.exit(2);
}
const patternMatch = source.match(/const MARKUP_PATTERN = (\/.*\/);/);
if (!patternMatch) {
	console.error('FATAL: could not find MARKUP_PATTERN in the plugin JS');
	process.exit(2);
}
const MARKUP_PATTERN = eval(patternMatch[1]);

const body = fnMatch[0]
	.replace(/^\tfunction normalizeMarkupNotation\(raw\) \{/, '')
	.replace(/\n\t\}$/, '');

// DECIMAL_SEPARATOR is a const in the plugin's closure; here it is a parameter so
// the same source can be exercised under both store configurations.
const normalizeMarkupNotation = new Function('raw', 'DECIMAL_SEPARATOR', body);

const table = JSON.parse(
	fs.readFileSync(path.join(__dirname, 'fixtures', 'notation-table.json'), 'utf8')
);

const results = table.cases.map(function (testCase) {
	const separator = testCase.separator || '.';
	const normalized = normalizeMarkupNotation(testCase.input.trim(), separator);
	return {
		input: testCase.input,
		separator: separator,
		normalized: normalized,
		valid: normalized === '' || MARKUP_PATTERN.test(normalized)
	};
});

process.stdout.write(JSON.stringify(results));
