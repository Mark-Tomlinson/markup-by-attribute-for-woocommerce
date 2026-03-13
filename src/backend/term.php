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

	/** @var string Placeholder text for markup input */
	private $placeholder;
	//endregion

	//region INSTANCE MANAGEMENT
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
	private function __clone() {}

	/**
	 * Prevent object unserialization
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {}

	/**
	 * Initialize the class and set up hooks
	 *
	 * Sets up WordPress hooks for term management, including
	 * form field generation, data saving, and admin interface integration.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->initializeLabels();
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
		$this->placeholder = "[+|-]" . wc_format_localized_decimal('0.00') ." or [+|-]" . wc_format_localized_decimal('00.0%');
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

	//region TERM FORM HANDLERS
	/**
	 * Build form fields for term add panel
	 */
	public function addTermFields(string $taxonomy) {
		// Build <DIV>
		?>
		<div class="form-field">
			<?php wp_nonce_field('mt2mba_add_term', 'mt2mba_term_nonce'); ?>
			<label for="term_markup"><?php echo($this->markup_label); ?></label>
			<input type="text" placeholder="<?php echo($this->placeholder); ?>" name="term_markup" id="term_add_markup" value="">
			<p class="description"><?php echo($this->markup_description); ?></p>
		</div>
		<?php
	}

	/**
	 * Build form fields for term edit panel
	 */
	public function editTermFields(object $term) {
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
	public function handleTermMarkupSave(int $term_id) {
		// Sanity check
		if (!isset($_POST['term_markup'])) return;

		// Check if user has permission to edit terms
		if (!current_user_can('manage_product_terms')) {
			return;
		}

		// WordPress nonce verification for CSRF protection
		$term = get_term($term_id);
		$taxonomy_name = sanitize_key($term->taxonomy);

		// Determine operation type and validate appropriate nonce
		$is_edit = isset($_POST['_wpnonce']);
		$is_add = isset($_POST['mt2mba_term_nonce']);

		if ($is_edit) {
			// Edit operation - validate WordPress's standard edit nonce
			if (!wp_verify_nonce($_POST['_wpnonce'], 'update-tag_' . $term_id)) {
				// Invalid nonce for edit operation - reject
				return;
			}
		} elseif ($is_add) {
			// Add operation - validate our custom add nonce
			if (!wp_verify_nonce($_POST['mt2mba_term_nonce'], 'mt2mba_add_term')) {
				// Invalid nonce for add operation - reject
				return;
			}
		} else {
			// No valid nonce present - reject to prevent CSRF
			return;
		}

		// Prevent infinite recursion: wp_update_term() triggers this hook again
		// Use a constant flag to detect if we're already processing this term
		if (defined('MT2MBA_ATTRB_RECURSION')) return;
		define('MT2MBA_ATTRB_RECURSION', TRUE);

		global $mt2mba_utility;

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
	public function handleMarkupColumnSort(object $term_query) {
		// WP_Term_Query does not define a get() or a set() method,
		// so the query_vars member must be manipulated directly
		if (isset($_GET['orderby']) && 'markup' == sanitize_text_field(wp_unslash($_GET['orderby']))) {
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
