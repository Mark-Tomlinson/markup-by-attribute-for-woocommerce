<?php
/**
 * Item 1 — Attribute options must be saved under the real taxonomy ID,
 * even when the user's custom slug differs from sanitize_title(label).
 *
 * Scenario: label "Color", slug "colour". wc_attribute_taxonomy_id_by_name()
 * resolves the SLUG, so looking it up with sanitize_title('Color') = 'color'
 * returns 0 and the options are orphaned as mt2mba_..._0.
 *
 * Desired behavior: the render callbacks perform no writes at all; option
 * saves happen on WooCommerce's woocommerce_attribute_added / _updated hooks,
 * which hand us the authoritative attribute ID.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/backend/attribute.php';

use mt2Tech\MarkupByAttribute\Backend\Attribute;

// The store's real slug is 'colour'; taxonomy ID 42
$GLOBALS['mt2mba_stub']['taxonomy_ids'] = ['colour' => 42];

$attribute = Attribute::get_instance();

// --- ADD path: user submits label "Color", custom slug "colour", two boxes checked
$_POST = [
	'add_new_attribute'    => 'Add attribute',
	'attribute_label'      => 'Color',
	'attribute_name'       => 'colour',
	'term_name_rewrite'    => 'on',
	'dont_overwrite_theme' => 'on',
];

$writes_before_render = count($GLOBALS['mt2mba_test']['option_log']);
ob_start();
$attribute->addAttributeFields();
ob_end_clean();
$render_writes = count($GLOBALS['mt2mba_test']['option_log']) - $writes_before_render;

t_assert($render_writes === 0, 'add-form render callback performs no option writes');

// WooCommerce fires this after a successful save, handing us the real ID
do_action('woocommerce_attribute_added', 42, ['attribute_label' => 'Color', 'attribute_name' => 'colour']);

$options = $GLOBALS['mt2mba_test']['options'];
t_assert(
	!isset($options[MT2MBA_REWRITE_TERM_NAME_PREFIX . '0'])
	&& !isset($options[MT2MBA_DONT_OVERWRITE_THEME_PREFIX . '0']),
	'no options orphaned under taxonomy ID 0'
);
t_assert(($options[MT2MBA_REWRITE_TERM_NAME_PREFIX . '42'] ?? '') === 'yes', 'name-rewrite saved under real taxonomy ID 42');
t_assert(($options[MT2MBA_DONT_OVERWRITE_THEME_PREFIX . '42'] ?? '') === 'yes', 'dont-overwrite-theme saved under real taxonomy ID 42');
t_assert(!isset($options[MT2MBA_REWRITE_TERM_DESC_PREFIX . '42']), 'unchecked desc-rewrite not written on add');

// --- EDIT path: uncheck name-rewrite, keep dont-overwrite checked
$GLOBALS['mt2mba_test']['options'][MT2MBA_REWRITE_TERM_NAME_PREFIX . '42'] = 'yes';
$_POST = [
	'save_attribute'       => 'Update',
	'attribute_label'      => 'Color',
	'attribute_name'       => 'colour',
	'dont_overwrite_theme' => 'on',
];
$_GET = ['edit' => '42'];

$writes_before_render = count($GLOBALS['mt2mba_test']['option_log']);
ob_start();
$attribute->editAttributeFields();
ob_end_clean();
$render_writes = count($GLOBALS['mt2mba_test']['option_log']) - $writes_before_render;

t_assert($render_writes === 0, 'edit-form render callback performs no option writes');

do_action('woocommerce_attribute_updated', 42, ['attribute_label' => 'Color', 'attribute_name' => 'colour'], 'colour');

$options = $GLOBALS['mt2mba_test']['options'];
t_assert(!isset($options[MT2MBA_REWRITE_TERM_NAME_PREFIX . '42']), 'edit: unchecked name-rewrite option deleted');
t_assert(($options[MT2MBA_DONT_OVERWRITE_THEME_PREFIX . '42'] ?? '') === 'yes', 'edit: checked dont-overwrite-theme retained');

t_done();
