<?php
/**
 * Item 2 — An empty variation list must never produce "WHERE post_id IN ()".
 *
 * removeVariationPrices() and fetchVariationData() both build IN-clause
 * placeholders with implode over array_fill(0, count($ids), '%d'); with zero
 * IDs that is malformed SQL. The base-price meta cleanup must still happen
 * for a variation-less product, but no IN-clause query may be issued.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/utility/general.php';
require __DIR__ . '/../src/backend/handlers/pricemarkuphandler.php';
require __DIR__ . '/../src/backend/handlers/pricesethandler.php';

use mt2Tech\MarkupByAttribute\Backend\Handlers\PriceSetHandler;

$GLOBALS['wpdb'] = new MT2MBA_Fake_WPDB();

// Blanked-out regular price on a product with zero variations
$handler = new PriceSetHandler('variable_regular_price', ['value' => ''], 99, [], true);
$handler->removeVariationPrices(99, []);

$deleted_keys = array_column($GLOBALS['mt2mba_test']['post_meta'], 2);
t_assert(in_array('mt2mba_base_regular_price', $deleted_keys, true), 'base regular-price meta still removed with zero variations');
t_assert(in_array('mt2mba_base_sale_price', $deleted_keys, true), 'base sale-price meta still removed with zero variations');

$bad = array_filter($GLOBALS['wpdb']->queries, fn($q) => preg_match('/IN\s*\(\s*\)/i', $q));
t_assert(empty($bad), 'removeVariationPrices issues no "IN ()" SQL for an empty variation list');

// fetchVariationData is private — exercise via reflection
$GLOBALS['wpdb']->queries = [];
$method = new ReflectionMethod($handler, 'fetchVariationData');
$method->setAccessible(true);
$result = $method->invoke($handler, []);

t_assert($result === [[], []], 'fetchVariationData returns empty lookups for an empty variation list');
t_assert(empty($GLOBALS['wpdb']->queries), 'fetchVariationData issues no SQL at all for an empty variation list');

t_done();
