<?php
namespace mt2Tech\MarkupByAttribute\Backend;
use mt2Tech\MarkupByAttribute\Utility as Utility;
use WP_Meta_Query;

/**
 * Attribute term management with markup functionality
 *
 * Manages markup metadata fields for WooCommerce product attribute terms.
 * Handles the admin interface for adding markup values to global attribute terms,
 * including form generation, data validation, and metadata storage.
 *
 * @package   mt2Tech\MarkupByAttribute\Backend
 * @author    Mark Tomlinson
 * @license   GPL-2.0+
 * @since     1.0.0
 */
class Term {
	//region PROPERTIES
	/**
	 * Singleton instance
	 * @var self|null
	 */
	private static ?self $instance = null;

	/** @var string Label for markup field */
	private $markup_label;

	/** @var string Description for markup field */
	private $markup_description;

	/** @var string Label for name rewrite option */
	private $rewrite_name_label;

	/** @var string Description for name rewrite option */
	private $rewrite_name_description;

	/** @var string Label for description rewrite option */
	private $rewrite_desc_label;

	/** @var string Description for description rewrite option */
	private $rewrite_desc_description;

	/** @var string Label for theme overwriting option */
	private $dont_overwrite_theme_label;

	/** @var string Description for theme overwriting option */
	private $dont_overwrite_theme_description;

	/** @var string Text for add action */
	private $text_add;

	/** @var string Text for subtract action */
	private $text_subtract;

	/** @var string Placeholder text for markup input */
	private $placeholder;
	//endregion

	//region INSTANCE MANAGEMENT
	/**
	 * Methods for managing the singleton instance
	 */

	/**
	 * Get singleton instance
	 *
	 * @since 1.0.0
	 * @return Term Single instance of this class
	 */
	public static function get_instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Prevent object cloning
	 *
	 * @since 1.0.0
	 */
	private function __clone(): void {}

	/**
	 * Prevent object unserialization
	 *
	 * @since 1.0.0
	 */
	public function __wakeup(): void {}

	/**
	 * Initialize the class and set up hooks
	 *
	 * Sets up WordPress hooks for attribute and term management, including
	 * form field generation, data saving, and admin interface integration.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->initializeLabels();
		$this->registerAttributeHooks();
		$this->registerTaxonomyHooks();
	}

	/**
	 * Initialize text labels and descriptions
	 *
	 * Sets up all translatable strings used in the admin interface.
	 *
	 * @since 3.0.0
	 */
	private function initializeLabels(): void {
		$this->markup_label = __('Markup (or markdown)', 'markup-by-attribute-for-woocommerce');
		$this->markup_description = __('Markup or markdown associated with this option. Signed, floating point numeric allowed.', 'markup-by-attribute-for-woocommerce');
		$this->rewrite_name_label = __('Add Markup to Name?', 'markup-by-attribute-for-woocommerce');
		$this->rewrite_name_description = sprintf(
			__('Rename the option to include the markup.', 'markup-by-attribute-for-woocommerce') . ' ' .
			__('Often needed if the option drop-down box is overwritten by another plugin or theme and markup is no longer visible.', 'markup-by-attribute-for-woocommerce')
		);
		$this->rewrite_desc_label = __('Add Markup to Description?', 'markup-by-attribute-for-woocommerce');
		$this->rewrite_desc_description = sprintf(
			__('Add the markup to the option\'s description.', 'markup-by-attribute-for-woocommerce') . ' ' .
			__('Often needed if the option drop-down box is overwritten by another plugin or theme and markup is no longer visible.', 'markup-by-attribute-for-woocommerce')
		);
		$this->dont_overwrite_theme_label = __('Do Not Overwrite Theme', 'markup-by-attribute-for-woocommerce');
		$this->dont_overwrite_theme_description = sprintf(
			__('Do not overwrite the option selection mechanism provided with your theme.', 'markup-by-attribute-for-woocommerce') . ' ' .
			__('Use this if the option drop-down box is overwriting a preferred method provided by another plugin or your theme.', 'markup-by-attribute-for-woocommerce')
		);
		$this->placeholder = "[+|-]" . wc_format_localized_decimal('0.00') ." or [+|-]" . wc_format_localized_decimal('00.0%');
	}

	/**
	 * Register hooks for attribute actions
	 *
	 * Sets up WordPress hooks for global attribute management.
	 *
	 * @since 3.0.0
	 */
	private function registerAttributeHooks(): void {
		// Add fields to forms
		add_action("woocommerce_after_add_attribute_fields", array($this, 'addAttributeFields'), 10, 2);
		add_action("woocommerce_after_edit_attribute_fields", array($this, 'editAttributeFields'), 10, 2);

		// Delete options when attribute is deleted
		add_action("woocommerce_before_attribute_delete", function () {
			delete_option(REWRITE_TERM_NAME_PREFIX . $_GET['delete']);
			delete_option(REWRITE_TERM_DESC_PREFIX . $_GET['delete']);
			delete_option(DONT_OVERWRITE_THEME_PREFIX . $_GET['delete']);
		}, 10, 2);
	}

	/**
	 * Register hooks for taxonomies
	 */
	private function registerTaxonomyHooks(): void {
		// Get all WooCommerce global attributes (like Color, Size, etc.)
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		foreach ($attribute_taxonomies as $attribute_taxonomy) {
			// WooCommerce prefixes attribute taxonomies with 'pa_' (Product Attribute)
			// e.g., 'color' becomes 'pa_color'
			$taxonomy = 'pa_' . $attribute_taxonomy->attribute_name;
			$this->registerTermHooks($taxonomy);
			$this->registerColumnHooks($taxonomy);
		}
	}

	/**
	 * Register term-related hooks for a taxonomy
	 */
	private function registerTermHooks(string $taxonomy): void {
		// WordPress dynamically creates hooks for each taxonomy
		// Add our markup fields to the term add/edit forms
		add_action("{$taxonomy}_add_form_fields", array($this, 'addTermFields'), 10, 2);
		add_action("{$taxonomy}_edit_form_fields", array($this, 'editTermFields'), 10, 2);

		// Process markup data when terms are saved
		// 'created_' fires when new terms are added, 'edited_' when existing terms are updated
		add_action("created_{$taxonomy}", array($this, 'handleTermMarkupSave'), 10, 2);
		add_action("edited_{$taxonomy}", array($this, 'handleTermMarkupSave'), 10, 2);
	}

	/**
	 * Register column-related hooks for a taxonomy
	 */
	private function registerColumnHooks(string $taxonomy): void {
		// Add 'Markup' column
		add_filter("manage_edit-{$taxonomy}_columns", function ($columns) {
			$columns['markup'] = __('Markup', 'markup-by-attribute-for-woocommerce');
			return $columns;
		}, 10);

		// Add content to Markup column
		add_action("manage_{$taxonomy}_custom_column", function ($string, $column_name, $term_id) {
			if ($column_name == 'markup') {
				global $mt2mba_utility;
				$markup = get_term_meta($term_id, 'mt2mba_markup', true);
				echo esc_html($mt2mba_utility->sanitizeMarkupForDisplay(wc_format_localized_decimal($markup)));
			}
			return;
		}, 10, 3);

		// Make Markup column sortable
		add_filter("manage_edit-{$taxonomy}_sortable_columns", function ($columns) {
			$columns['markup'] = 'markup';
			return $columns;
		}, 10);

		add_filter('pre_get_terms', array($this, 'handleMarkupColumnSort'), 10);
	}
	//endregion

	//region ATTRIBUTE FORM HANDLERS
	/**
	 * Build form fields for attribute add panel
	 */
	function addAttributeFields(): void {
		if (isset($_POST['add_new_attribute'])) {
			$taxonomy_id = wc_attribute_taxonomy_id_by_name(sanitize_title($_POST['attribute_label']));

			$options = [
				REWRITE_TERM_NAME_PREFIX . $taxonomy_id => [
					'value' => isset($_POST['term_name_rewrite']),
					'autoload' => true
				],
				REWRITE_TERM_DESC_PREFIX . $taxonomy_id => [
					'value' => isset($_POST['term_desc_rewrite']),
					'autoload' => false
				],
				DONT_OVERWRITE_THEME_PREFIX . $taxonomy_id => [
					'value' => isset($_POST['dont_overwrite_theme']),
					'autoload' => true
				]
			];

			foreach ($options as $option_name => $settings) {
				if ($settings['value']) {
					update_option($option_name, 'yes', $settings['autoload']);
				}
			}
		}

		// Build <DIV>
		?>
		<div class="form-field">
			<label for="dont_overwrite_theme"><input type="checkbox" name="dont_overwrite_theme" id="dont_overwrite_theme" value="">
			<?php echo($this->dont_overwrite_theme_label); ?></label>
			<p class="description"><?php echo($this->dont_overwrite_theme_description); ?></p>
		</div>
		<div class="form-field">
			<label for="term_name_rewrite"><input type="checkbox" name="term_name_rewrite" id="term_name_rewrite" value="">
			<?php echo($this->rewrite_name_label); ?></label>
			<p class="description"><?php echo($this->rewrite_name_description); ?></p>
		</div>
		<div class="form-field">
			<label for="term_desc_rewrite"><input type="checkbox" name="term_desc_rewrite" id="term_desc_rewrite" value="">
			<?php echo($this->rewrite_desc_label); ?></label>
			<p class="description"><?php echo($this->rewrite_desc_description); ?></p>
		</div>
		<?php
	}

	/**
	 * Build form fields for attribute edit panel
	 */
	function editAttributeFields(): void {
		// Retrieve the existing rewrite name flag for this attribute (NULL results are valid)
		if (isset($_POST['save_attribute'])) {
			$attribute_id = $_GET['edit'];
			$options = [
				REWRITE_TERM_NAME_PREFIX . $attribute_id => [
					'value' => isset($_POST['term_name_rewrite']),
					'autoload' => true
				],
				REWRITE_TERM_DESC_PREFIX . $attribute_id => [
					'value' => isset($_POST['term_desc_rewrite']),
					'autoload' => false
				],
				DONT_OVERWRITE_THEME_PREFIX . $attribute_id => [
					'value' => isset($_POST['dont_overwrite_theme']),
					'autoload' => true
				]
			];

			foreach ($options as $option_name => $settings) {
				if ($settings['value']) {
					update_option($option_name, 'yes', $settings['autoload']);
				} else {
					delete_option($option_name);
				}
			}
		}
		// Set flags from Options database
		$rewrite_name_flag			= get_option(REWRITE_TERM_NAME_PREFIX . $_GET['edit'], false);
		$rewrite_desc_flag			= get_option(REWRITE_TERM_DESC_PREFIX . $_GET['edit'], false);
		$dont_overwrite_theme_flag	= get_option(DONT_OVERWRITE_THEME_PREFIX . $_GET['edit'], false);

		// Build row and fill field with current markup
		$checked_name_flag = $rewrite_name_flag == 'yes' ? ' checked' : "";
		$checked_desc_flag = $rewrite_desc_flag == 'yes' ? ' checked' : "";
		$checked_overwrite_flag = $dont_overwrite_theme_flag == 'yes' ? ' checked' : "";
		?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="dont_overwrite_theme"><?php echo($this->dont_overwrite_theme_label); ?></label></th>
			<td>
				<input type="checkbox" name="dont_overwrite_theme" id="dont_overwrite_theme_edit"<?php echo $checked_overwrite_flag; ?>>
				<p class="description"><?php echo($this->dont_overwrite_theme_description); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="term_name_rewrite"><?php echo($this->rewrite_name_label); ?></label></th>
			<td>
				<input type="checkbox" name="term_name_rewrite" id="term_name_edit_rewrite"<?php echo $checked_name_flag; ?>>
				<p class="description"><?php echo($this->rewrite_name_description); ?></p>
			</td>
		</tr>
		<tr class="form-field">
		<th scope="row" valign="top"><label for="term_desc_rewrite"><?php echo($this->rewrite_desc_label); ?></label></th>
		<td>
				<input type="checkbox" name="term_desc_rewrite" id="term_desc_edit_rewrite"<?php echo $checked_desc_flag; ?>>
				<p class="description"><?php echo($this->rewrite_desc_description); ?></p>
			</td>
		</tr>
		<?php
	}
	//endregion

	//region TERM FORM HANDLERS
	/**
	 * Build form fields for term add panel
	 */
	function addTermFields(string $taxonomy): void {
		// Build <DIV>
		?>
		<div class="form-field">
			<label for="term_markup"><?php echo($this->markup_label); ?></label>
			<input type="text" placeholder="<?php echo($this->placeholder); ?>" name="term_markup" id="term_add_markup" value="">
			<p class="description"><?php echo($this->markup_description); ?></p>
		</div>
		<?php
	}

	/**
	 * Build form fields for term edit panel
	 */
	function editTermFields(object $term): void {
		// Retrieve the existing markup for this term(NULL results are valid)
		$term_markup = wc_format_localized_decimal(get_term_meta($term->term_id, "mt2mba_markup", TRUE));

		// Build row and fill field with current markup
		?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="term_markup"><?php echo($this->markup_label); ?></label></th>
			<td>
				<input type="text" placeholder="<?php echo($this->placeholder); ?>" name="term_markup" id="term_edit_markup" value="<?php echo esc_attr($term_markup) ? esc_attr($term_markup) : ''; ?>">
				<p class="description"><?php echo($this->markup_description); ?></p>
			</td>
		</tr>
		<?php
	}
	//endregion

	//region TERM METADATA HANDLERS
	/**
	 * Save the term markup metadata
	 */
	function handleTermMarkupSave(int $term_id): void {
		// Sanity check
		if (!isset($_POST['term_markup'])) return;

		// WordPress nonce verification for CSRF protection
		// Different nonce actions are used for editing existing vs. creating new terms
		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'update-tag_' . $term_id)) {
			// Fallback: check if this is a new term creation (uses different nonce action)
			if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'add-tag')) {
				// Neither nonce verified - reject the request
				return;
			}
		}

		// Prevent infinite recursion: wp_update_term() triggers this hook again
		// Use a constant flag to detect if we're already processing this term
		if (defined('MT2MBA_ATTRB_RECURSION')) return;
		define('MT2MBA_ATTRB_RECURSION', TRUE);

		global $mt2mba_utility;
		$term = get_term($term_id);
		$taxonomy_name = sanitize_key($term->taxonomy);

		// Clean slate: remove any existing markup annotations from term data
		// This ensures we don't duplicate markup text when reapplying
		$name = $mt2mba_utility->stripMarkupAnnotation($term->name);
		$description = $mt2mba_utility->stripMarkupAnnotation($term->description);

		// Clear existing markup metadata first (will be re-added if validation passes)
		delete_term_meta($term_id, 'mt2mba_markup');

		// Get and validate the markup input
		$raw_markup = sanitize_text_field($_POST['term_markup']);

		// Validate markup using centralized validation
		$validated_markup = $mt2mba_utility->validateMarkupValue($raw_markup);

		// Only proceed if markup validation passed and isn't empty
		if ($validated_markup !== false && $validated_markup !== '') {
			// Final sanitization pass before database storage
			$markup = $mt2mba_utility->sanitizeMarkupForStorage($validated_markup);

			// Save markup to term metadata table
			update_term_meta($term_id, 'mt2mba_markup', $markup);

			// Check global attribute settings for term name/description rewriting
			// These options control whether markup should be visible in dropdowns
			$rewrite_name_flag	= get_option(REWRITE_TERM_NAME_PREFIX . wc_attribute_taxonomy_id_by_name($taxonomy_name));
			$rewrite_desc_flag	= get_option(REWRITE_TERM_DESC_PREFIX . wc_attribute_taxonomy_id_by_name($taxonomy_name));

			// Check markup sign for proper formatting (discount vs. surcharge)
			$is_negative = strpos($markup, '-') === 0;

			// Conditionally modify term name based on attribute settings
			// e.g., "Blue" becomes "Blue (+$5.00)" if name rewriting is enabled
			if ($rewrite_name_flag == 'yes') {
				$name = $mt2mba_utility->addMarkupToName($name, $markup, $is_negative);
			}

			// Conditionally modify term description for markup visibility
			if ($rewrite_desc_flag == 'yes') {
				$description = $mt2mba_utility->addMarkupToTermDescription($description, $markup, $is_negative);
			}
		} elseif ($validated_markup === false) {
			// Invalid markup - add admin notice
			add_action('admin_notices', function() use ($raw_markup) {
				echo '<div class="notice notice-error is-dismissible"><p>' .
					sprintf(
						__('Invalid markup value "%s". Please use format like "5.00", "-2.50", "10%" or "-5%".', 'markup-by-attribute-for-woocommerce'),
						esc_html($raw_markup)
					) .
					'</p></div>';
			});
		}

		// Rewrite term if name and/or description have changed
		if ($term->name != $name || $term->description != $description) {
			wp_update_term(
				$term_id,
				$taxonomy_name,
				array(
					'name' => sanitize_text_field(trim($name)),
					'description' => sanitize_textarea_field(trim($description))
				)
			);
		}
	}
	//endregion

	//region COLUMN HANDLERS
	/**
	* Handle markup column sorting
	*/
	function handleMarkupColumnSort(object $term_query): void {
		// WP_Term_Query does not define a get() or a set() method,
		// so the query_vars member must be manipulated directly
		if (isset($_GET['orderby']) && 'markup' == $_GET['orderby']) {
			$meta_query = array(
				'relation' => 'OR',
				array('key' => 'mt2mba_markup', 'compare' => 'NOT EXISTS'),
				array('key' => 'mt2mba_markup')
			);
			$term_query->meta_query = new WP_Meta_Query($meta_query);
			$term_query->query_vars['orderby'] = 'mt2mba_markup';
		}
	}
	//endregion
}
?>