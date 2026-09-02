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

	/** @var bool Re-entrancy guard: true only while wp_update_term() is running */
	private static $is_rewriting_term = false;
	//endregion

	//region INSTANCE MANAGEMENT
	/** Singleton accessor. @since 1.0.0 */
	public static function get_instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Singleton: cloning is not supported. */
	private function __clone() {}

	/** Singleton: unserialization is not supported. @since 1.0.0 */
	public function __wakeup() {}

	/**
	 * Initialize the class and set up hooks
	 *
	 * Sets up WordPress hooks for term management, including
	 * form field generation, data saving, and admin interface integration.
	 */
	private function __construct() {
		$this->initializeLabels();
		$this->registerTaxonomyHooks();

		// Client-side markup validation on the term add/edit forms (WP-native
		// form-invalid styling; blocks the submit so garbage never reaches PHP)
		add_action('admin_enqueue_scripts', [$this, 'enqueueMarkupValidation']);
	}

	/**
	 * Initialize text labels and descriptions
	 *
	 * Sets up all translatable strings used in the admin interface.
	 */
	private function initializeLabels(): void {
		$this->markup_label = __('Markup (or markdown)', 'markup-by-attribute-for-woocommerce');
		$this->markup_description = __('Markup or markdown associated with this option. Signed, floating point numeric allowed.', 'markup-by-attribute-for-woocommerce');
		$this->placeholder = '[+|-]' . wc_format_localized_decimal('0.00') .' or [+|-]' . wc_format_localized_decimal('00.0%');
	}

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
		add_action("{$taxonomy}_add_form_fields", [$this, 'addTermFields'], 10, 2);
		add_action("{$taxonomy}_edit_form_fields", [$this, 'editTermFields'], 10, 2);

		// Process markup data when terms are saved
		// 'created_' fires when new terms are added, 'edited_' when existing terms are updated
		add_action("created_{$taxonomy}", [$this, 'handleTermMarkupSave'], 10, 2);
		add_action("edited_{$taxonomy}", [$this, 'handleTermMarkupSave'], 10, 2);
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

		// Markup column content. For term columns this hook is a filter and core
		// echoes the return value, so append to $string rather than echoing;
		// returning only our own content would wipe other plugins' columns.
		add_filter("manage_{$taxonomy}_custom_column", function ($string, $column_name, $term_id) {
			if ($column_name == 'markup') {
				$markup = get_term_meta($term_id, 'mt2mba_markup', true);
				$string .= esc_html(wc_format_localized_decimal($markup));
			}
			return $string;
		}, 10, 3);

		// Make Markup column sortable
		add_filter("manage_edit-{$taxonomy}_sortable_columns", function ($columns) {
			$columns['markup'] = 'markup';
			return $columns;
		}, 10);

		add_filter('pre_get_terms', [$this, 'handleMarkupColumnSort'], 10);
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
			<label for="term_markup"><?php echo esc_html($this->markup_label); ?></label>
			<input type="text" placeholder="<?php echo esc_attr($this->placeholder); ?>" name="term_markup" id="term_add_markup" value="">
			<p class="description"><?php echo esc_html($this->markup_description); ?></p>
		</div>
		<?php
	}

	/**
	 * Build form fields for term edit panel
	 */
	public function editTermFields(object $term) {
		// Retrieve the existing markup for this term(NULL results are valid)
		$term_markup = wc_format_localized_decimal(get_term_meta($term->term_id, 'mt2mba_markup', true));

		// Build row and fill field with current markup
		?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="term_markup"><?php echo esc_html($this->markup_label); ?></label></th>
			<td>
				<input type="text" placeholder="<?php echo esc_attr($this->placeholder); ?>" name="term_markup" id="term_edit_markup" value="<?php echo esc_attr($term_markup); ?>">
				<p class="description"><?php echo esc_html($this->markup_description); ?></p>
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
		// wp_update_term() below re-fires this hook for the same term. The flag is
		// raised only around that call, so the re-entrant pass is skipped while
		// every other term in a batch is still processed.
		if (self::$is_rewriting_term) return;

		// Sanity check
		if (!isset($_POST['term_markup'])) return;

		// Check if user has permission to edit terms
		if (!current_user_can('manage_product_terms')) return;

		// Guard against a deleted term (race between hook fire and handler run)
		// or a plugin conflict — get_term() can return null or WP_Error
		$term = get_term($term_id);
		if (!$term instanceof \WP_Term) return;

		if (!$this->verifyTermSaveNonce($term_id)) return;

		// Clear existing markup metadata first (re-added below if validation passes)
		delete_term_meta($term_id, 'mt2mba_markup');

		// Validated exactly once, here at the boundary. Validation is not idempotent
		// (see General::validateMarkupValue()), so its result must never be fed back in.
		$markup = Utility\General::sanitizeMarkupForStorage(sanitize_text_field($_POST['term_markup']));

		// Empty means a cleared field or rejected input; both store nothing. The
		// user-facing rejection is client-side (jq-mt2mba-validate-markup.js): an
		// admin notice cannot reach either save path (AJAX add, redirecting edit).
		if ($markup !== '') update_term_meta($term_id, 'mt2mba_markup', $markup);

		$this->maybeRewriteTermNameAndDesc($term, $markup);
	}

	/**
	 * Verify the nonce belonging to whichever save path this is
	 *
	 * @param  int  $term_id Term being saved; the edit nonce action is per-term
	 * @return bool          True when the request carries a valid nonce
	 */
	private function verifyTermSaveNonce(int $term_id): bool {
		// Edit operation — WordPress's own field, present on the edit form. Checked
		// first, so if both fields somehow arrive its verdict is the one that counts.
		if (isset($_POST['_wpnonce'])) {
			return (bool) wp_verify_nonce($_POST['_wpnonce'], 'update-tag_' . $term_id);
		}

		// Add operation — our field, rendered by addTermFields()
		if (isset($_POST['mt2mba_term_nonce'])) {
			return (bool) wp_verify_nonce($_POST['mt2mba_term_nonce'], 'mt2mba_add_term');
		}

		// Neither nonce present; reject rather than write unverified
		return false;
	}

	/**
	 * Re-annotate the term name and description, and save if either changed
	 *
	 * Runs on every save, including one that cleared the markup: the old annotation
	 * is stripped before anything is added back, so removing a markup also removes
	 * its annotation.
	 *
	 * @param \WP_Term $term   The term as it currently stands in the database
	 * @param string   $markup Validated markup, or '' when there is none
	 */
	private function maybeRewriteTermNameAndDesc(\WP_Term $term, string $markup): void {
		$taxonomy_name = sanitize_key($term->taxonomy);

		// Clean slate: remove any existing markup annotations from term data
		// This ensures we don't duplicate markup text when reapplying
		$new_name = Utility\General::stripMarkupAnnotation($term->name);
		$new_description = Utility\General::stripMarkupAnnotation($term->description);

		if ($markup !== '') {
			// Check global attribute settings for term name/description rewriting
			// These options control whether markup should be visible in dropdowns
			$taxonomy_id = wc_attribute_taxonomy_id_by_name($taxonomy_name);
			$rewrite_name_flag = get_option(MT2MBA_REWRITE_TERM_NAME_PREFIX . $taxonomy_id);
			$rewrite_desc_flag = get_option(MT2MBA_REWRITE_TERM_DESC_PREFIX . $taxonomy_id);

			// Conditionally modify term name based on attribute settings
			// e.g., "Blue" becomes "Blue (+$5.00)" if name rewriting is enabled.
			// The sign is already in $markup, so nothing has to be told about it.
			if ($rewrite_name_flag == 'yes') {
				$new_name = Utility\General::addMarkupToName($new_name, $markup);
			}

			// Conditionally modify term description for markup visibility. The
			// description deliberately keeps the word form for both markup types.
			if ($rewrite_desc_flag == 'yes') {
				$new_description = Utility\General::addMarkupToTermDescription(
					$new_description,
					$markup,
					strpos($markup, '-') === 0
				);
			}
		}

		// Skip the write when nothing changed: no pointless DB update, and no
		// edited_{taxonomy} re-fire for every other plugin listening.
		if (($term->name == $new_name) && ($term->description == $new_description)) return;

		// Raise the guard only around this call (it re-fires edited_{taxonomy}); lower
		// it immediately after so the next term in a batch processes normally.
		self::$is_rewriting_term = true;
		wp_update_term(
			$term->term_id,
			$taxonomy_name,
			[
				'name' => sanitize_text_field(trim($new_name)),
				'description' => sanitize_textarea_field(trim($new_description))
			]
		);
		self::$is_rewriting_term = false;
	}
	//endregion

	/**
	 * Enqueue markup-field validation on term add/edit screens
	 *
	 * @param string $hook Current admin page hook suffix
	 */
	public function enqueueMarkupValidation(string $hook): void {
		// Only the term list/add screen (edit-tags.php) and term edit screen (term.php)
		if ($hook !== 'edit-tags.php' && $hook !== 'term.php') return;

		// Only product-attribute taxonomies carry the markup field
		$taxonomy = sanitize_key($_GET['taxonomy'] ?? '');
		if (strpos($taxonomy, 'pa_') !== 0) return;

		wp_enqueue_script(
			'mt2mba-validate-markup',
			MT2MBA_PLUGIN_URL . 'src/js/jq-mt2mba-validate-markup.js',
			['jquery'],
			MT2MBA_VERSION,
			true
		);

		// The validator normalizes notation exactly as the server does, and that
		// needs the store's decimal separator: "1.235,12" is correct in a comma
		// store and meaningless in a dot store
		wp_localize_script(
			'mt2mba-validate-markup',
			'mt2mbaMarkup',
			['decimalSeparator' => wc_get_price_decimal_separator()]
		);

		// Carries the .mt2mba-invalid red-border rule (see admin-style.css for
		// why core's form-required mechanism isn't used)
		wp_enqueue_style(
			'mt2mba-admin-styles',
			MT2MBA_PLUGIN_URL . 'src/css/admin-style.css',
			[],
			MT2MBA_VERSION
		);
	}
	//endregion

	//region COLUMN HANDLERS
	/**
	* Handle markup column sorting
	*/
	public function handleMarkupColumnSort(object $term_query) {
		// pre_get_terms fires on frontend queries too; a frontend request carrying
		// ?orderby=markup must not have its query vars rewritten.
		if (!is_admin()) return;

		// WP_Term_Query does not define a get() or a set() method,
		// so the query_vars member must be manipulated directly
		if (isset($_GET['orderby']) && 'markup' == sanitize_text_field(wp_unslash($_GET['orderby']))) {
			$meta_query = [
				'relation' => 'OR',
				['key' => 'mt2mba_markup', 'compare' => 'NOT EXISTS'],
				['key' => 'mt2mba_markup']
			];
			$term_query->meta_query = new WP_Meta_Query($meta_query);
			$term_query->query_vars['orderby'] = 'mt2mba_markup';
		}
	}
	//endregion
}
