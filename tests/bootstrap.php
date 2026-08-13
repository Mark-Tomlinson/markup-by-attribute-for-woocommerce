<?php
/**
 * Standalone test bootstrap — minimal WordPress/WooCommerce stubs
 *
 * Lets individual plugin classes be loaded and exercised in a bare PHP CLI
 * process, no WordPress install required. Each test file runs in its own
 * process (see run-tests.php) so singletons and constants stay isolated.
 *
 * Stub behavior is configured through $GLOBALS['mt2mba_stub'] and recorded
 * activity (options written, queries run, hooks added) lands in
 * $GLOBALS['mt2mba_test'] for assertions.
 *
 * Dev-only: this directory is excluded from the wp.org SVN build.
 *
 * @package markup-by-attribute-for-woocommerce
 */

error_reporting(E_ALL);

// Surface notices/warnings as exceptions so latent bugs fail tests loudly
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
	if (!(error_reporting() & $errno)) return false;
	throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

$GLOBALS['mt2mba_test'] = [
	'options'      => [],   // option key => value
	'option_log'   => [],   // every update_option/delete_option call
	'transients'   => [],
	'actions'      => [],   // hook => [callbacks]
	'term_meta'    => [],   // recorded update/delete_term_meta calls
	'post_meta'    => [],   // recorded delete_post_meta calls
	'term_updates' => [],   // recorded wp_update_term calls
	'nonce_checks' => [],   // recorded wp_verify_nonce calls as [nonce, action]
	'enqueued'     => [],   // recorded wp_enqueue_script/style calls, handle => args
	'upgrade_log'  => [],   // execution order of fixture upgrade modules
	'meta_reads'   => [],   // recorded get_metadata calls
	'cache_flushes'=> [],   // recorded clean_post_cache calls
];
$GLOBALS['mt2mba_stub'] = [
	'can'          => true,   // current_user_can()
	'nonce_ok'     => true,   // wp_verify_nonce(): bool, or callable($nonce, $action)
	'taxonomy_ids' => [],     // wc_attribute_taxonomy_id_by_name() map
	'get_term'     => null,   // callable($term_id)
	// Rows returned by $wpdb->get_results(). Either a plain row list (every query
	// gets it) or a callable($sql) — see t_wpdb_map() for the usual case where two
	// SELECTs in one code path must return different rows.
	'wpdb_results' => [],
	'products'     => [],     // product_id => MT2MBA_Fake_Product
	'terms'        => [],     // taxonomy => [MT2MBA_Fake_Term, ...]
	'attrb_labels' => [],     // taxonomy => display label
	'term_meta_in' => [],     // term_id => [meta_key => value]  (get_term_meta reads)
];

if (!defined('ABSPATH'))                          define('ABSPATH', '/');
if (!defined('MINUTE_IN_SECONDS'))                define('MINUTE_IN_SECONDS', 60);
if (!defined('HOUR_IN_SECONDS'))                  define('HOUR_IN_SECONDS', 3600);

//region Constants normally defined in define_constants()
// A test that loads the real plugin file and calls define_constants() itself sets
// $GLOBALS['mt2mba_skip_plugin_constants'] = true before requiring this bootstrap;
// define() on an already-defined constant would warn, and the error handler above
// turns warnings into thrown exceptions.
if (empty($GLOBALS['mt2mba_skip_plugin_constants'])) {
if (!defined('MT2MBA_VERSION'))                   define('MT2MBA_VERSION', 'test');
if (!defined('MT2MBA_PLUGIN_URL'))                define('MT2MBA_PLUGIN_URL', 'http://test/');
if (!defined('MT2MBA_PLUGIN_NAME'))               define('MT2MBA_PLUGIN_NAME', 'Markup by Attribute');
if (!defined('MT2MBA_REWRITE_TERM_NAME_PREFIX'))  define('MT2MBA_REWRITE_TERM_NAME_PREFIX', 'mt2mba_rewrite_attrb_name_');
if (!defined('MT2MBA_REWRITE_TERM_DESC_PREFIX'))  define('MT2MBA_REWRITE_TERM_DESC_PREFIX', 'mt2mba_rewrite_attrb_desc_');
if (!defined('MT2MBA_DONT_OVERWRITE_THEME_PREFIX')) define('MT2MBA_DONT_OVERWRITE_THEME_PREFIX', 'mt2mba_dont_overwrite_theme_');
if (!defined('MT2MBA_REGULAR_PRICE'))             define('MT2MBA_REGULAR_PRICE', 'regular_price');
if (!defined('MT2MBA_SALE_PRICE'))                define('MT2MBA_SALE_PRICE', 'sale_price');
if (!defined('MT2MBA_PRODUCT_MARKUP_DESC_BEG'))   define('MT2MBA_PRODUCT_MARKUP_DESC_BEG', '<span id="mbainfo">');
if (!defined('MT2MBA_PRODUCT_MARKUP_DESC_END'))   define('MT2MBA_PRODUCT_MARKUP_DESC_END', '</span>');
if (!defined('MT2MBA_PRICE_META'))                define('MT2MBA_PRICE_META', 'Product price ');
if (!defined('MT2MBA_INTERNAL_PRECISION'))        define('MT2MBA_INTERNAL_PRECISION', 6);
if (!defined('MT2MBA_DEFAULT_MAX_VARIATIONS'))    define('MT2MBA_DEFAULT_MAX_VARIATIONS', 50);
if (!defined('MT2MBA_ADMIN_POINTER_PRIORITY'))    define('MT2MBA_ADMIN_POINTER_PRIORITY', 1000);
if (!defined('MT2MBA_MARKUP_NAME_PATTERN_ADD'))       define('MT2MBA_MARKUP_NAME_PATTERN_ADD', '(Add %s)');
if (!defined('MT2MBA_MARKUP_NAME_PATTERN_SUBTRACT'))  define('MT2MBA_MARKUP_NAME_PATTERN_SUBTRACT', '(Subtract %s)');

// Settings-derived constants — defaults matching the Settings class, so tests
// can load the real Utility\General instead of stubbing it
if (!defined('MT2MBA_DESC_BEHAVIOR'))             define('MT2MBA_DESC_BEHAVIOR', 'append');
if (!defined('MT2MBA_DROPDOWN_BEHAVIOR'))         define('MT2MBA_DROPDOWN_BEHAVIOR', 'add');
if (!defined('MT2MBA_INCLUDE_ATTRB_NAME'))        define('MT2MBA_INCLUDE_ATTRB_NAME', 'no');
if (!defined('MT2MBA_HIDE_BASE_PRICE'))           define('MT2MBA_HIDE_BASE_PRICE', 'no');
if (!defined('MT2MBA_SALE_PRICE_MARKUP'))         define('MT2MBA_SALE_PRICE_MARKUP', 'yes');
if (!defined('MT2MBA_ROUND_MARKUP'))              define('MT2MBA_ROUND_MARKUP', 'no');
if (!defined('MT2MBA_MAX_VARIATIONS'))            define('MT2MBA_MAX_VARIATIONS', 50);
if (!defined('MT2MBA_CURRENCY_SYMBOL'))           define('MT2MBA_CURRENCY_SYMBOL', '$');
}
//endregion

//region WordPress core stubs
function __($text, $domain = null) { return $text; }
// WordPress escapes with double_encode = FALSE, so an already-encoded entity
// passes through untouched ('&amp;' stays '&amp;', it does not become
// '&amp;amp;'). A stub that double-encodes would invent double-escaping bugs
// that cannot happen in production — and hide the fact that a genuinely
// double-escaped pipeline looks correct in the browser.
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false); }
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false); }
function esc_url($url) { return $url; }
function wp_kses_post($text) { return $text; }
function wp_kses($text, $allowed_html = [], $allowed_protocols = []) { return $text; }
function wp_unslash($value) { return $value; }
function absint($n) { return abs((int) $n); }

function sanitize_title($title) {
	$title = strtolower(trim((string) $title));
	$title = preg_replace('/[^a-z0-9_\- ]/', '', $title);
	return preg_replace('/[\s]+/', '-', $title);
}
function sanitize_key($key) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)); }
function sanitize_text_field($str) { return trim(strip_tags((string) $str)); }
function sanitize_textarea_field($str) { return trim(strip_tags((string) $str)); }

function add_action($hook, $callback, $priority = 10, $args = 1) {
	$GLOBALS['mt2mba_test']['actions'][$hook][] = $callback;
}
function add_filter($hook, $callback, $priority = 10, $args = 1) {
	add_action($hook, $callback, $priority, $args);
}
function do_action($hook, ...$args) {
	foreach ($GLOBALS['mt2mba_test']['actions'][$hook] ?? [] as $callback) {
		call_user_func_array($callback, $args);
	}
}
function apply_filters($hook, $value, ...$args) {
	foreach ($GLOBALS['mt2mba_test']['actions'][$hook] ?? [] as $callback) {
		$value = call_user_func_array($callback, array_merge([$value], $args));
	}
	return $value;
}

function get_option($key, $default = false) {
	return $GLOBALS['mt2mba_test']['options'][$key] ?? $default;
}
function update_option($key, $value, $autoload = null) {
	$GLOBALS['mt2mba_test']['options'][$key] = $value;
	$GLOBALS['mt2mba_test']['option_log'][] = ['update', $key, $value, $autoload];
	return true;
}
function delete_option($key) {
	unset($GLOBALS['mt2mba_test']['options'][$key]);
	$GLOBALS['mt2mba_test']['option_log'][] = ['delete', $key, null, null];
	return true;
}

function get_transient($key) { return $GLOBALS['mt2mba_test']['transients'][$key] ?? false; }
function set_transient($key, $value, $expiration = 0) {
	$GLOBALS['mt2mba_test']['transients'][$key] = $value;
	return true;
}
function delete_transient($key) {
	unset($GLOBALS['mt2mba_test']['transients'][$key]);
	return true;
}

function current_user_can($cap) { return $GLOBALS['mt2mba_stub']['can']; }
function wp_verify_nonce($nonce, $action) {
	$GLOBALS['mt2mba_test']['nonce_checks'][] = [$nonce, $action];
	// A callable can accept one action's nonce and reject another's. The plain
	// boolean cannot tell the two apart, so it would pass a handler that
	// verified the WRONG nonce for the operation it was performing.
	$ok = $GLOBALS['mt2mba_stub']['nonce_ok'];
	return is_callable($ok) ? $ok($nonce, $action) : $ok;
}
function wp_create_nonce($action = '') { return 'testnonce'; }
function wp_nonce_field($action = '', $name = '_wpnonce') { echo ''; }
function get_current_user_id() { return 7; }
function get_user_meta($user_id, $key = '', $single = false) {
	return $GLOBALS['mt2mba_stub']['user_meta'][$key] ?? '';
}
function get_current_screen() {
	return (object) ['id' => $GLOBALS['mt2mba_stub']['screen_id'] ?? ''];
}

function get_term($term_id) {
	$fn = $GLOBALS['mt2mba_stub']['get_term'];
	return $fn ? $fn($term_id) : null;
}
function get_term_meta($term_id, $key = '', $single = false) {
	return $GLOBALS['mt2mba_stub']['term_meta_in'][$term_id][$key] ?? '';
}
function get_terms($args = []) {
	$taxonomy = is_array($args) ? ($args['taxonomy'] ?? '') : $args;
	$terms = $GLOBALS['mt2mba_stub']['terms'][$taxonomy] ?? [];

	// WordPress treats an EMPTY 'include' as no restriction at all, not as "no
	// terms". Modeled faithfully because that is the trap: code that passes an
	// empty array of selected terms silently gets the whole taxonomy back.
	if (is_array($args) && !empty($args['include'])) {
		$include = array_map('intval', (array) $args['include']);
		$terms = array_values(array_filter($terms, function ($term) use ($include) {
			return in_array((int) $term->term_id, $include, true);
		}));
	}

	return $terms;
}
function update_term_meta($term_id, $key, $value) {
	$GLOBALS['mt2mba_test']['term_meta'][] = ['update', $term_id, $key, $value];
	return true;
}
function delete_term_meta($term_id, $key) {
	$GLOBALS['mt2mba_test']['term_meta'][] = ['delete', $term_id, $key, null];
	return true;
}
function wp_update_term($term_id, $taxonomy, $args = []) {
	$GLOBALS['mt2mba_test']['term_updates'][] = [$term_id, $taxonomy, $args];
	return ['term_id' => $term_id];
}
function delete_post_meta($post_id, $key) {
	$GLOBALS['mt2mba_test']['post_meta'][] = ['delete', $post_id, $key];
	return true;
}
function get_metadata($meta_type, $object_id, $key = '', $single = false) {
	$GLOBALS['mt2mba_test']['meta_reads'][] = [$meta_type, $object_id, $key];
	return $GLOBALS['mt2mba_stub']['meta'][$key] ?? '';
}
function update_post_meta($post_id, $key, $value) {
	$GLOBALS['mt2mba_test']['post_meta'][] = ['update', $post_id, $key, $value];
	return true;
}

function clean_post_cache($post_id) {
	$GLOBALS['mt2mba_test']['cache_flushes'][] = $post_id;
}

function is_admin() { return $GLOBALS['mt2mba_stub']['is_admin'] ?? true; }
function wp_doing_ajax() { return $GLOBALS['mt2mba_stub']['doing_ajax'] ?? (defined('DOING_AJAX') && DOING_AJAX); }
function wp_die($message = '') { throw new RuntimeException('wp_die: ' . $message); }
function load_plugin_textdomain($domain, $deprecated = false, $path = false) { return true; }
function plugin_basename($file) { return basename($file); }
function plugin_dir_path($file) { return rtrim(dirname($file), '/\\') . '/'; }
function plugin_dir_url($file) { return 'http://test/'; }
function get_bloginfo($show = '') { return 'http://test'; }
// Recorded by handle so a test can assert the version and in-footer arguments,
// which are invisible in a browser until a stale cached script bites someone
function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false) {
	$GLOBALS['mt2mba_test']['enqueued']['script'][$handle] = compact('src', 'deps', 'ver', 'in_footer');
}
function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all') {
	$GLOBALS['mt2mba_test']['enqueued']['style'][$handle] = compact('src', 'deps', 'ver', 'media');
}
function wp_localize_script($handle, $name, $data) { $GLOBALS["mt2mba_test"]["localized"][$name] = $data; return true; }
function add_query_arg($args, $url = '') { return $url . '?' . http_build_query($args); }
function admin_url($path = '') { return 'http://test/wp-admin/' . $path; }
function error_log_stub($msg) {}

class WP_Term {
	public $term_id = 0;
	public $name = '';
	public $slug = '';
	public $term_group = 0;
	public $term_taxonomy_id = 0;
	public $taxonomy = '';
	public $description = '';
	public $parent = 0;
	public $count = 0;
}
class WP_Error {}
class WP_Meta_Query {
	public function __construct($query = false) {}
}
//endregion

//region WooCommerce stubs
function wc_attribute_taxonomy_id_by_name($name) {
	return $GLOBALS['mt2mba_stub']['taxonomy_ids'][$name] ?? 0;
}
function wc_get_attribute_taxonomies() {
	// Objects with ->attribute_name; Term prefixes each with 'pa_'
	return $GLOBALS['mt2mba_stub']['attribute_taxonomies'] ?? [];
}
function wc_get_price_decimal_separator() {
	return $GLOBALS['mt2mba_stub']['decimal_separator'] ?? '.';
}

// Models WooCommerce's own parser: the store's decimal separator becomes '.',
// then everything that is not a digit, dot or minus is dropped — which is how
// thousands separators disappear. A naive is_numeric() check here made every
// comma-decimal locale look invalid and hid real i18n behavior.
function wc_format_decimal($number, $dp = false, $trim_zeros = false) {
	if (!is_float($number)) {
		$number = str_replace(wc_get_price_decimal_separator(), '.', (string) $number);
		$number = preg_replace('/[^0-9\.\-]/', '', $number);
	}
	if ($number === '' || $number === null) return '';
	if ($dp !== false) $number = number_format((float) $number, (int) $dp, '.', '');
	if ($trim_zeros && strpos($number, '.') !== false) {
		$number = rtrim(rtrim($number, '0'), '.');
	}
	return $number;
}
// Overridable so a test can model a suffix-symbol locale ("5,00 kr"). The
// dropdown's strip-currency-symbol mode behaves differently there — the space
// in front of the symbol has to go with it — and a fixed '$'-prefix stub
// cannot reach that path at all.
function wc_price($price, $args = []) {
	$format = $GLOBALS['mt2mba_stub']['price_format'] ?? null;
	return $format ? $format((float) $price) : '$' . number_format((float) $price, 2);
}
function wc_get_price_decimals() { return 2; }
function wc_format_localized_decimal($value) { return $value; }
function get_woocommerce_currency() { return $GLOBALS['mt2mba_stub']['currency'] ?? 'USD'; }
function get_woocommerce_currency_symbol($currency = '') { return $GLOBALS['mt2mba_stub']['currency_symbol'] ?? '&#36;'; }

// Settings extends this but calls none of its methods
class WC_Settings_API {}

/**
 * Minimal stand-ins for the WooCommerce product/attribute objects that
 * PriceSetHandler::getAttributeData() walks. Only the two methods it calls.
 */
class MT2MBA_Fake_Attribute {
	private $name;
	private $is_taxonomy;
	private $options;
	private $is_variation;
	public function __construct($name, $is_taxonomy = true, $options = [], $is_variation = true) {
		$this->name = $name;
		$this->is_taxonomy = $is_taxonomy;
		$this->options = $options;
		$this->is_variation = $is_variation;
	}
	public function is_taxonomy() { return $this->is_taxonomy; }
	public function get_name() { return $this->name; }
	/** Term IDs this product actually selected — NOT every term in the taxonomy. */
	public function get_options() { return $this->options; }
	public function get_variation() { return $this->is_variation; }
}
class MT2MBA_Fake_Product {
	private $attributes;
	public function __construct(array $attributes) { $this->attributes = $attributes; }
	public function get_attributes() { return $this->attributes; }
}

function wc_get_product($product_id) {
	return $GLOBALS['mt2mba_stub']['products'][$product_id] ?? null;
}
function wc_attribute_label($taxonomy) {
	return $GLOBALS['mt2mba_stub']['attrb_labels'][$taxonomy]
		?? ucfirst(str_replace('pa_', '', $taxonomy));
}
//endregion

//region Fake wpdb — records every SQL statement for assertions
class MT2MBA_Fake_WPDB {
	public $prefix = 'wp_';
	public $postmeta = 'wp_postmeta';
	public $options = 'wp_options';
	public $queries = [];
	public $last_error = '';

	public function prepare($query, ...$args) {
		// Flatten array args the way wpdb does
		$flat = [];
		array_walk_recursive($args, function ($v) use (&$flat) { $flat[] = $v; });
		$i = 0;
		return preg_replace_callback('/%[dsf]/', function ($m) use (&$flat, &$i) {
			$val = $flat[$i++] ?? '';
			return $m[0] === '%d' ? (string) (int) $val : "'" . addslashes((string) $val) . "'";
		}, $query);
	}
	public function esc_like($text) {
		return addcslashes((string) $text, '_%\\');
	}
	public function get_results($query) {
		$this->queries[] = $query;
		// A callable lets one code path's several SELECTs return different rows;
		// a plain array keeps the simple "same rows for everything" case terse.
		$results = $GLOBALS['mt2mba_stub']['wpdb_results'];
		return is_callable($results) ? $results($query) : $results;
	}
	public function query($query) {
		$this->queries[] = $query;
		return 0;
	}
}
//endregion

//region Fixture helpers
/**
 * Build a wpdb_results callable from [sql_substring => rows]. First match wins,
 * so order the map most-specific-first; anything unmatched returns no rows.
 */
function t_wpdb_map(array $map): callable {
	return function ($sql) use ($map) {
		foreach ($map as $needle => $rows) {
			if (strpos($sql, $needle) !== false) return $rows;
		}
		return [];
	};
}

/** A postmeta row as $wpdb->get_results() hands it back (stdClass, string values). */
function t_meta_row($post_id, $meta_key, $meta_value) {
	return (object) [
		'post_id'    => (string) $post_id,
		'meta_key'   => $meta_key,
		'meta_value' => (string) $meta_value,
	];
}

/** A term as get_terms() hands it back, with its mt2mba_markup registered. */
function t_term($term_id, $slug, $name, $markup = '') {
	$term = new WP_Term();
	$term->term_id = $term_id;
	$term->slug    = $slug;
	$term->name    = $name;
	if ($markup !== '') {
		$GLOBALS['mt2mba_stub']['term_meta_in'][$term_id]['mt2mba_markup'] = $markup;
	}
	return $term;
}

/**
 * Register a variable product: $taxonomies is [taxonomy => [WP_Term, ...]].
 * Wires up wc_get_product(), get_terms() and wc_attribute_label() together.
 *
 * $selected is [taxonomy => [term_id, ...]] for the terms the product actually
 * picked; omit a taxonomy and the product selects all of them, which is what
 * every fixture written before terms and selections could differ means. Pass an
 * empty array to model an attribute with nothing selected.
 */
function t_product($product_id, array $taxonomies, array $labels = [], array $selected = []) {
	$attributes = [];
	foreach ($taxonomies as $taxonomy => $terms) {
		$options = array_key_exists($taxonomy, $selected)
			? $selected[$taxonomy]
			: array_map(function ($term) { return (int) $term->term_id; }, $terms);
		$attributes[$taxonomy] = new MT2MBA_Fake_Attribute($taxonomy, true, $options);
		$GLOBALS['mt2mba_stub']['terms'][$taxonomy] = $terms;
		if (isset($labels[$taxonomy])) {
			$GLOBALS['mt2mba_stub']['attrb_labels'][$taxonomy] = $labels[$taxonomy];
		}
	}
	$GLOBALS['mt2mba_stub']['products'][$product_id] = new MT2MBA_Fake_Product($attributes);
}
//endregion

//region Assertion helpers
$GLOBALS['t_fail'] = [];
function t_assert($condition, $message) {
	if ($condition) {
		echo "  ok    $message\n";
	} else {
		$GLOBALS['t_fail'][] = $message;
		echo "  FAIL  $message\n";
	}
}
function t_done() {
	$failures = count($GLOBALS['t_fail']);
	echo $failures ? "RESULT: FAIL ($failures)\n" : "RESULT: PASS\n";
	exit($failures ? 1 : 0);
}
//endregion
