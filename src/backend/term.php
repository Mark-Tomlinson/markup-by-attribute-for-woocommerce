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
			$this->registerBulkActionHooks($taxonomy);
		}

		// Reports the outcome of the bulk action, and warns when terms and
		// attribute settings disagree. Registered once, not per taxonomy.
		add_action('admin_notices', [$this, 'showTermNotices']);
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
				$string .= esc_html(Utility\General::formatStoredMarkupForDisplay((string) $markup));
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

	/**
	 * Register the term-list bulk action for a taxonomy
	 *
	 * The screen backing edit-tags.php is 'edit-{taxonomy}', which is what both
	 * hook names are built from.
	 */
	private function registerBulkActionHooks(string $taxonomy): void {
		add_filter("bulk_actions-edit-{$taxonomy}", [$this, 'addTermBulkAction'], 10);
		add_filter("handle_bulk_actions-edit-{$taxonomy}", [$this, 'handleTermBulkAction'], 10, 3);
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
		$term_markup = Utility\General::formatStoredMarkupForDisplay(
			(string) get_term_meta($term->term_id, 'mt2mba_markup', true)
		);

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
	private function maybeRewriteTermNameAndDesc(\WP_Term $term, string $markup): bool {
		$pending = $this->pendingTermRewrite($term, $markup);

		// Nothing changed: no pointless DB update, and no edited_{taxonomy}
		// re-fire for every other plugin listening.
		if ($pending === null) return false;

		// Raise the guard only around this call (it re-fires edited_{taxonomy}); lower
		// it immediately after so the next term in a batch processes normally.
		self::$is_rewriting_term = true;
		wp_update_term($term->term_id, sanitize_key($term->taxonomy), $pending);
		self::$is_rewriting_term = false;

		return true;
	}

	/**
	 * Work out what the attribute's settings say this term should look like
	 *
	 * Callers that only need to know whether a term is out of step with its
	 * attribute test the return for null; a rewrite and that test therefore share
	 * one computation and cannot disagree about which terms need attention.
	 *
	 * @param  \WP_Term   $term   The term as it currently stands in the database
	 * @param  string     $markup Validated markup, or '' when there is none
	 * @return array|null         wp_update_term() arguments, or null when in step
	 */
	private function pendingTermRewrite(\WP_Term $term, string $markup): ?array {
		$target = $this->targetTermNameAndDesc($term, $markup);

		// Compare before trimming: a term whose only difference is stray
		// whitespace still needs the write that tidies it.
		if (($term->name == $target['name']) && ($term->description == $target['description'])) return null;

		return [
			'name' => sanitize_text_field(trim($target['name'])),
			'description' => sanitize_textarea_field(trim($target['description']))
		];
	}

	/**
	 * Build the name and description the attribute's settings call for
	 *
	 * Returned untrimmed and unsanitized so callers can compare against the stored
	 * values exactly as pendingTermRewrite() does.
	 *
	 * @param  \WP_Term $term   The term as it currently stands in the database
	 * @param  string   $markup Validated markup, or '' when there is none
	 * @return array            ['name' => string, 'description' => string]
	 */
	private function targetTermNameAndDesc(\WP_Term $term, string $markup): array {
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

		return ['name' => $new_name, 'description' => $new_description];
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
			// Without a type the meta sorts as text, putting '10' ahead of '8' and
			// a legacy '+8' ahead of '1'. Both clauses carry it: WP_Term_Query
			// casts using the FIRST clause, so leaving one untyped restores the
			// text sort the moment the clauses are reordered.
			$meta_query = [
				'relation' => 'OR',
				['key' => 'mt2mba_markup', 'compare' => 'NOT EXISTS', 'type' => 'DECIMAL(10,4)'],
				['key' => 'mt2mba_markup', 'type' => 'DECIMAL(10,4)']
			];
			$term_query->meta_query = new WP_Meta_Query($meta_query);
			$term_query->query_vars['orderby'] = 'mt2mba_markup';
		}
	}
	//endregion

	//region BULK ACTION
	/**
	 * Add the reapply action to the term list's bulk-action menu
	 *
	 * Deliberately not named "Reapply Markups" like the product-list action: that
	 * one rewrites prices, this one rewrites names and descriptions.
	 *
	 * @since 4.8.0
	 * @param  array $bulk_actions Existing actions
	 * @return array               Actions with ours appended
	 */
	public function addTermBulkAction(array $bulk_actions): array {
		$bulk_actions['mt2mba_reapply_settings'] = __('Reapply Attribute Settings', 'markup-by-attribute-for-woocommerce');
		return $bulk_actions;
	}

	/**
	 * Reapply the attribute's settings to the selected terms
	 *
	 * Terms are not filtered by markup: the rewrite strips any existing annotation
	 * before adding one back, so a term without a markup is either untouched or
	 * has a stale annotation removed.
	 *
	 * @since 4.8.0
	 * @param  string|false $location Redirect URL, or false when core has none yet
	 * @param  string       $action   Bulk action chosen
	 * @param  array        $term_ids Terms the user checked
	 * @return string|false           Redirect URL, carrying the count when we ran
	 */
	public function handleTermBulkAction($location, string $action, array $term_ids) {
		if ($action !== 'mt2mba_reapply_settings') return $location;

		// Defense-in-depth: core verifies the 'bulk-tags' nonce and the taxonomy
		// capability before this filter fires, but guard our own writes too.
		if (!current_user_can('manage_product_terms')) return $location;

		$rewritten = 0;
		foreach ($term_ids as $term_id) {
			$term = get_term((int) $term_id);
			if (!$term instanceof \WP_Term) continue;

			$markup = (string) get_term_meta($term->term_id, 'mt2mba_markup', true);
			if ($this->maybeRewriteTermNameAndDesc($term, $markup)) $rewritten++;
		}

		// edit-tags.php seeds $location as false and only falls back to the referer
		// AFTER this filter returns. Handing back a bare query string counts as a
		// location, so core skips that fallback and redirects to an edit-tags.php
		// with no taxonomy on it — the Tags screen.
		if (!$location) {
			$location = remove_query_arg(
				['_wp_http_referer', '_wpnonce'],
				wp_unslash($_SERVER['REQUEST_URI'] ?? '')
			);
		}

		return add_query_arg('mt2mba_rewritten', $rewritten, $location);
	}
	//endregion

	//region TERM LIST NOTICES
	/**
	 * Report the bulk action's result, and warn about terms out of step
	 *
	 * @since 4.8.0
	 */
	public function showTermNotices(): void {
		$taxonomy = sanitize_key(wp_unslash($_GET['taxonomy'] ?? ''));

		// Term list screen for a product attribute only
		$screen = get_current_screen();
		if (strpos($taxonomy, 'pa_') !== 0 || !$screen || $screen->id !== "edit-{$taxonomy}") return;

		if (isset($_GET['mt2mba_rewritten'])) {
			$rewritten = absint(wp_unslash($_GET['mt2mba_rewritten']));
			$this->printNotice('success', sprintf(
				/* translators: %s: number of terms */
				_n('%s term updated.', '%s terms updated.', $rewritten, 'markup-by-attribute-for-woocommerce'),
				number_format_i18n($rewritten)
			), true);
		}

		$this->printOutOfStepNotices($taxonomy);
	}

	/**
	 * Warn, per field, when terms disagree with the attribute's settings
	 *
	 * The name and the description are asked about separately and never combined:
	 * each either has something to say or stays quiet, so a shopowner sees at most
	 * two messages and each one stands on its own.
	 *
	 * @param string $taxonomy Attribute taxonomy being listed
	 */
	private function printOutOfStepNotices(string $taxonomy): void {
		$counts = $this->countOutOfStepTerms($taxonomy);
		$attribute_id = wc_attribute_taxonomy_id_by_name($taxonomy);

		if ($counts['name'] > 0) {
			$this->printNotice('warning', $this->outOfStepMessage(
				get_option(MT2MBA_REWRITE_TERM_NAME_PREFIX . $attribute_id) == 'yes',
				$counts['name'],
				/* translators: %s: number of terms. Keep the <strong> tags around the setting's state. */
				_n('"Add Markup to Name?" is <strong>on</strong>, but %s term\'s name does not match its markup.',
					'"Add Markup to Name?" is <strong>on</strong>, but %s terms\' names do not match their markup.',
					$counts['name'], 'markup-by-attribute-for-woocommerce'),
				/* translators: %s: number of terms. Keep the <strong> tags around the setting's state. */
				_n('"Add Markup to Name?" is <strong>off</strong>, but %s term still shows a markup in its name.',
					'"Add Markup to Name?" is <strong>off</strong>, but %s terms still show a markup in their names.',
					$counts['name'], 'markup-by-attribute-for-woocommerce')
			));
		}

		if ($counts['description'] > 0) {
			$this->printNotice('warning', $this->outOfStepMessage(
				get_option(MT2MBA_REWRITE_TERM_DESC_PREFIX . $attribute_id) == 'yes',
				$counts['description'],
				/* translators: %s: number of terms. Keep the <strong> tags around the setting's state. */
				_n('"Add Markup to Description?" is <strong>on</strong>, but %s term\'s description does not match its markup.',
					'"Add Markup to Description?" is <strong>on</strong>, but %s terms\' descriptions do not match their markup.',
					$counts['description'], 'markup-by-attribute-for-woocommerce'),
				/* translators: %s: number of terms. Keep the <strong> tags around the setting's state. */
				_n('"Add Markup to Description?" is <strong>off</strong>, but %s term still shows a markup in its description.',
					'"Add Markup to Description?" is <strong>off</strong>, but %s terms still show a markup in their descriptions.',
					$counts['description'], 'markup-by-attribute-for-woocommerce')
			));
		}
	}

	/**
	 * Assemble one out-of-step message and its instruction
	 *
	 * @param  bool   $flag_is_on Whether the attribute asks for the annotation
	 * @param  int    $count      Terms that disagree
	 * @param  string $on_text    Message when the annotation is wanted
	 * @param  string $off_text   Message when it is not
	 * @return string             Message ready for escaping
	 */
	private function outOfStepMessage(bool $flag_is_on, int $count, string $on_text, string $off_text): string {
		return sprintf($flag_is_on ? $on_text : $off_text, number_format_i18n($count))
			. ' '
			. sprintf(
				/* translators: %s: name of the bulk action, as it reads in the menu */
				__('Select them below and apply "%s".', 'markup-by-attribute-for-woocommerce'),
				__('Reapply Attribute Settings', 'markup-by-attribute-for-woocommerce')
			);
	}

	/**
	 * Count terms whose name or description disagrees with the attribute
	 *
	 * Counted per field, because the two answers are independent and routinely
	 * differ. The comparison normalizes entities and line endings on both sides so
	 * only a genuine annotation mismatch counts; stored text can differ from the
	 * computed text in ways that have nothing to do with markup, and a notice that
	 * fired on those would never clear.
	 *
	 * @param  string $taxonomy Attribute taxonomy to examine
	 * @return array            ['name' => int, 'description' => int]
	 */
	private function countOutOfStepTerms(string $taxonomy): array {
		$counts = ['name' => 0, 'description' => 0];

		$terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
		if (!is_array($terms)) return $counts;

		foreach ($terms as $term) {
			if (!$term instanceof \WP_Term) continue;

			$markup = (string) get_term_meta($term->term_id, 'mt2mba_markup', true);
			$target = $this->targetTermNameAndDesc($term, $markup);

			if (!self::annotationMatches($term->name, $target['name'])) $counts['name']++;
			if (!self::annotationMatches($term->description, $target['description'])) $counts['description']++;
		}

		return $counts;
	}

	/**
	 * Compare stored text against computed text, ignoring cosmetic differences
	 *
	 * Entities and CRLF line endings survive in stored terms but not through
	 * stripMarkupAnnotation(), so a correctly annotated term can differ from its
	 * computed form. Those differences are real and the bulk action tidies them;
	 * they are just not worth a warning.
	 */
	private static function annotationMatches(string $stored, string $computed): bool {
		return self::normalizeForComparison($stored) === self::normalizeForComparison($computed);
	}

	/** Reduce text to the form both sides can be compared in. */
	private static function normalizeForComparison(string $text): string {
		return trim(preg_replace('/\R/u', "\n", html_entity_decode($text)));
	}

	/**
	 * Echo one admin notice
	 *
	 * Emphasis is allowed through because the messages bold the setting's state,
	 * and a translator may need to move those tags. Nothing user-supplied reaches
	 * here — the text is plugin literals and formatted counts — so wp_kses is
	 * narrowing what our own strings may contain, not sanitizing input.
	 *
	 * @param string $type      'warning' or 'success'
	 * @param string $message   Already-translated text, may contain <strong>
	 * @param bool   $transient Fade the notice out once it has been read
	 */
	private function printNotice(string $type, string $message, bool $transient = false): void {
		printf(
			'<div class="notice notice-%s%s"><p><strong>%s</strong> &mdash; %s</p></div>',
			esc_attr($type),
			$transient ? ' mt2mba-notice-transient' : '',
			esc_html(MT2MBA_PLUGIN_NAME),
			wp_kses($message, ['strong' => []])
		);
	}
	//endregion
}
