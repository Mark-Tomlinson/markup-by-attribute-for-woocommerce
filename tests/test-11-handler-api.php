<?php
/**
 * Item 7 — the handler API: state arrives once, through the constructor.
 *
 * Before this refactor every handler took ($bulk_action, $data, $product_id,
 * $variations) in its constructor AND took the same four again in
 * processProductMarkups(), where each implementation used some and ignored the
 * rest in favor of what it had stored. Two copies of the same state, with
 * nothing enforcing that they agree.
 *
 * The copies are gone: processProductMarkups() takes no arguments, so a caller
 * cannot hand a handler a product ID that disagrees with the one it was built
 * with. What is left to test is the shape itself, plus the two behaviors the old
 * shape was hiding — see the last two regions, both of which fail against the
 * pre-refactor code.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/utility/general.php';
require __DIR__ . '/../src/backend/handlers/bulkmetaio.php';
require __DIR__ . '/../src/backend/handlers/pricemarkuphandler.php';
require __DIR__ . '/../src/backend/handlers/pricesethandler.php';
require __DIR__ . '/../src/backend/handlers/priceupdatehandler.php';
require __DIR__ . '/../src/backend/handlers/markupdeletehandler.php';
require __DIR__ . '/../src/backend/product.php';

use mt2Tech\MarkupByAttribute\Backend\Handlers\PriceMarkupHandler;
use mt2Tech\MarkupByAttribute\Backend\Handlers\PriceSetHandler;
use mt2Tech\MarkupByAttribute\Backend\Handlers\PriceUpdateHandler;
use mt2Tech\MarkupByAttribute\Backend\Handlers\MarkupDeleteHandler;
use mt2Tech\MarkupByAttribute\Backend\Product;

const HANDLERS = [PriceSetHandler::class, PriceUpdateHandler::class, MarkupDeleteHandler::class];

//region The run method takes nothing — on the contract and on every implementation
$abstract = new ReflectionMethod(PriceMarkupHandler::class, 'processProductMarkups');
t_assert($abstract->getNumberOfParameters() === 0, 'the abstract processProductMarkups() declares no parameters');

foreach (HANDLERS as $class) {
	$method = new ReflectionMethod($class, 'processProductMarkups');
	$short  = (new ReflectionClass($class))->getShortName();
	t_assert($method->getNumberOfParameters() === 0, "$short::processProductMarkups() takes no arguments");
}

// removeVariationPrices() is public and reachable from outside, so it gets the
// same treatment rather than staying a two-argument straggler.
t_assert(
	(new ReflectionMethod(PriceSetHandler::class, 'removeVariationPrices'))->getNumberOfParameters() === 0,
	'PriceSetHandler::removeVariationPrices() takes no arguments'
);
//endregion

//region No parameter is named for being unused
// MarkupDeleteHandler used to carry $unused1, $unused2 and $unused4 through both
// its constructor and its run method — six dead parameters across two signatures.
foreach (HANDLERS as $class) {
	$short = (new ReflectionClass($class))->getShortName();
	$dead  = [];
	foreach ((new ReflectionClass($class))->getMethods() as $method) {
		if ($method->getDeclaringClass()->getName() !== $class) {
			continue;
		}
		foreach ($method->getParameters() as $param) {
			if (strpos($param->getName(), 'unused') === 0) {
				$dead[] = $method->getName() . '($' . $param->getName() . ')';
			}
		}
	}
	t_assert(empty($dead), "$short declares no \$unused parameters" . ($dead ? ': ' . implode(', ', $dead) : ''));
}

t_assert(
	(new ReflectionMethod(MarkupDeleteHandler::class, '__construct'))->getNumberOfParameters() === 1,
	'MarkupDeleteHandler::__construct() takes only the product ID'
);
//endregion

//region MarkupDeleteHandler actually stores its product ID
// It skips parent::__construct() on purpose (no price setup needed), and used to
// skip setting $product_id along with it — the property stayed null for the
// object's whole life, and the delete worked only because it read the argument
// instead. Any inherited helper touching $this->product_id would have faulted.
$deleter = new MarkupDeleteHandler(99);
$property = new ReflectionProperty(PriceMarkupHandler::class, 'product_id');
$property->setAccessible(true);

t_assert($property->getValue($deleter) === 99, 'MarkupDeleteHandler populates $this->product_id');

$GLOBALS['wpdb'] = new MT2MBA_Fake_WPDB();
$deleter->processProductMarkups();
t_assert(
	count($GLOBALS['wpdb']->queries) === 1 && strpos($GLOBALS['wpdb']->queries[0], 'post_id = 99') !== false,
	'MarkupDeleteHandler sweeps the product it was constructed with'
);
//endregion

//region The dispatcher builds each handler with its own signature
// product.php branches per class already, so the delete branch passing one
// argument instead of four costs nothing — but a mismatch here is a fatal, and
// this is the call site that would hit it first.
$GLOBALS['wpdb'] = new MT2MBA_Fake_WPDB();
(new Product())->handleBulkPriceAction('delete_all', [], 99, []);
t_assert(
	count(array_filter($GLOBALS['wpdb']->queries, fn($q) => strpos($q, 'DELETE') === 0)) === 1,
	'handleBulkPriceAction() constructs and runs the delete handler'
);
//endregion

//region Transaction ownership is forwarded through the nested handler
// PriceUpdateHandler recalculates a base price and then delegates to a fresh
// PriceSetHandler. That nested handler used to be built without the ownership
// flag, so it always defaulted to owning the transaction — meaning an
// increase/decrease run inside a caller's transaction would have issued a nested
// START TRANSACTION, implicitly committing the outer one. Nothing reached that
// path yet; the flag is forwarded now so nothing can.
function run_update(bool $owns_transaction): array {
	$GLOBALS['wpdb'] = new MT2MBA_Fake_WPDB();
	$GLOBALS['mt2mba_stub']['term_meta_in'] = [];
	$GLOBALS['mt2mba_stub']['meta'] = ['mt2mba_base_regular_price' => '100'];
	$GLOBALS['mt2mba_stub']['wpdb_results'] = t_wpdb_map([
		"LIKE 'attribute_pa_" => [t_meta_row(201, 'attribute_pa_color', 'red')],
	]);
	t_product(99, ['pa_color' => [t_term(11, 'red', 'Red', '5')]]);

	(new PriceUpdateHandler('variable_regular_price_increase', ['value' => '10'], 99, [201], $owns_transaction))
		->processProductMarkups();

	return $GLOBALS['wpdb']->queries;
}

$owned = run_update(true);
t_assert(in_array('START TRANSACTION', $owned, true), 'an update that owns its transaction still starts one');

$deferred = run_update(false);
t_assert(!in_array('START TRANSACTION', $deferred, true), 'an update deferring to a caller starts no nested transaction');
t_assert(!in_array('COMMIT', $deferred, true), 'and commits nothing either');
//endregion

//region The nested handler targets the outer handler's product and variations
// The old code passed the increase action and the increase amount down to a
// *set* handler, which was only harmless because the set handler ignored both
// parameters. Now there is nothing to pass: the values below can only have come
// from the constructor.
$queries = run_update(true);
$price_insert = array_values(array_filter($queries, fn($q) => strpos($q, "INSERT") === 0 && strpos($q, '_regular_price') !== false));

t_assert(count($price_insert) === 1, 'the nested set handler wrote regular prices exactly once');
// Base 100 + 10 = 110, plus the +5 markup on Red
t_assert(
	strpos($price_insert[0], "(201, '_regular_price', '115.00')") !== false,
	'the nested handler priced variation 201 off the recalculated base (100 + 10, +5 markup)'
);
t_assert(
	strpos($queries[0], 'post_id = 99') !== false,
	'and wrote markup meta against the product the outer handler was built with'
);
//endregion

t_done();
