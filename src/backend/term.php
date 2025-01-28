<?php
namespace mt2Tech\MarkupByAttribute\Backend;
use mt2Tech\MarkupByAttribute\Utility as Utility;
use WP_Meta_Query;

/**
 * Contains markup capabilities related to the backend attribute term admin page.
 * Manages markup metadata fields for product attribute terms.
 *
 * @package mt2Tech\MarkupByAttribute\Backend
 */
class Term {
	//region PROPERTIES
	/**
	 * Singleton instance
	 * @var Term|null
	 */
	private static $instance = null;

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
	 */
	public static function get_instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __clone() {}

	public function __wakeup() {}

	/**
	 * Initialize the class and set up hooks
	 */
	private function __construct() {
		$this->initializeLabels();
		$this->registerAttributeHooks();
		$this->registerTaxonomyHooks();
	}

	/**
	 * Initialize text labels and descriptions
	 */
	private function initializeLabels() {
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
	 */
	private function registerAttributeHooks() {
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
	private function registerTaxonomyHooks() {
		$attribute_taxonomies = wc_get_attribute_taxonomies();
		
		foreach ($attribute_taxonomies as $attribute_taxonomy) {
			$taxonomy = 'pa_' . $attribute_taxonomy->attribute_name;
			$this->registerTermHooks($taxonomy);
			$this->registerColumnHooks($taxonomy);
		}
	}

	/**
	 * Register term-related hooks for a taxonomy
	 */
	private function registerTermHooks($taxonomy) {
		// Hook into 'new' and 'edit' term panels
		add_action("{$taxonomy}_add_form_fields", array($this, 'addTermFields'), 10, 2);
		add_action("{$taxonomy}_edit_form_fields", array($this, 'editTermFields'), 10, 2);

		// Hook save function into both the 'new' and 'edit' functions
		add_action("created_{$taxonomy}", array($this, 'handleTermMarkupSave'), 10, 2);
		add_action("edited_{$taxonomy}", array($this, 'handleTermMarkupSave'), 10, 2);
	}

	/**
	 * Register column-related hooks for a taxonomy
	 */
	private function registerColumnHooks($taxonomy) {
		// Add 'Markup' column
		add_filter("manage_edit-{$taxonomy}_columns", function ($columns) {
			$columns['markup'] = __('Markup', 'markup-by-attribute-for-woocommerce');
			return $columns;
		}, 10);

		// Add content to Markup column
		add_action("manage_{$taxonomy}_custom_column", function ($string, $column_name, $term_id) {
			if ($column_name == 'markup') {
				echo wc_format_localized_decimal(get_term_meta($term_id, 'mt2mba_markup', true));
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
	function addAttributeFields() {
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
	function editAttributeFields() {
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
	function addTermFields($taxonomy) {
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
	function editTermFields($term) {
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
	function handleTermMarkupSave($term_id) {
		// Sanity check
		if (!isset($_POST['term_markup'])) return;
	
		// Prevent recursion when wp_update_term() is called later
		if (defined('MT2MBA_ATTRB_RECURSION')) return;
		define('MT2MBA_ATTRB_RECURSION', TRUE);
	
		global $mt2mba_utility;
		$term = get_term($term_id);
		$taxonomy_name = sanitize_key($term->taxonomy);
	
		// Remove any previous markup information from term name and description
		$name = $mt2mba_utility->stripMarkupAnnotation($term->name);
		$description = $mt2mba_utility->stripMarkupAnnotation($term->description);

		// Remove old metadata, regardless of next steps
		delete_term_meta($term_id, 'mt2mba_markup');
	
		// Add Markup metadata if present
		if (esc_attr($_POST['term_markup'] <> "" && $_POST['term_markup'] <> 0)) {
			$term_markup = esc_attr($_POST['term_markup']);
	
			// If term_markup has a value other than zero, add/update the value to the metadata table
			if (strpos($term_markup, "%")) {
				// If term_markup has a percentage sign, save as a formatted percent
				$markup = sprintf("%+g%%", wc_format_decimal($term_markup));
			} else {
				// If term_markup does not have percentage sign, save as a formatted floating point number
				$markup = sprintf("%+g", wc_format_decimal($term_markup));
			}
			update_term_meta($term_id, 'mt2mba_markup', $markup);

			// See if we are rewriting anything
			$rewrite_name_flag	= get_option(REWRITE_TERM_NAME_PREFIX . wc_attribute_taxonomy_id_by_name($taxonomy_name));
			$rewrite_desc_flag	= get_option(REWRITE_TERM_DESC_PREFIX . wc_attribute_taxonomy_id_by_name($taxonomy_name));

			// Determine if the markup is negative
			$is_negative = strpos($markup, '-') === 0;

			// Update term name if rewrite flag is set, so markup is visible in the name
			if ($rewrite_name_flag == 'yes') {
				$name = $mt2mba_utility->addMarkupToName($name, $markup, $is_negative);
			}

			// Update term description if rewrite flag is set, so markup is visible in the description
			if ($rewrite_desc_flag == 'yes') {
				$description = $mt2mba_utility->addMarkupToTermDescription($description, $markup, $is_negative);
			}
		}

		// Rewrite term if name and/or description have changed
		if ($term->name != $name || $term->description != $description) {
			wp_update_term(
				$term_id,
				$taxonomy_name,
				array(
					'name' => trim($name),
					'description' => trim($description)
				)
			);
		}
	}
	//endregion

	//region COLUMN HANDLERS
	/**
	* Handle markup column sorting
	*/
	function handleMarkupColumnSort($term_query) {
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