<?php
namespace mt2Tech\MarkupByAttribute\Backend;
use mt2Tech\MarkupByAttribute\Utility as Utility;
use WP_Meta_Query;
/**
 * Contains markup capabilities related to the backend attribute term admin page.
 * Specifically, add metadata field for markup to product attribute terms.
 *
 * @author	Mark Tomlinson
 *
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit();

class Term {
	/**
	 * Singleton because we only want one instance at a time.
	 */
	private static $instance = null;
	private $markup_label;
	private $markup_description;
	private $rewrite_label;
	private $rewrite_description;
	private $text_add;
	private $text_subtract;
	private $placeholder;
    private $validation_error = null;

	// Public method to get the instance
	public static function get_instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// Prevent cloning of the instance
	public function __clone() {}

	// Prevent unserializing of the instance
	public function __wakeup() {}

	// Private constructor
	private function __construct() {
		// Define labels and contents.
		$this->markup_label			= __('Markup (or markdown)', 'markup-by-attribute');
		$this->markup_description	= __('Markup or markdown associated with this option. Signed, floating point numeric allowed.', 'markup-by-attribute');
		$this->rewrite_label		= __('Add Markup to Name?', 'markup-by-attribute');
		$this->rewrite_description	= __('Rename the attribute to include the markup. Often needed if the option drop-down box is overwritten by another plugin or theme and markup is no longer visible.', 'markup-by-attribute');
		$this->text_add				= __('(Add', 'markup-by-attribute');
		$this->text_subtract		= __('(Subtract', 'markup-by-attribute');
		$this->placeholder			= "[+|-]" . wc_format_localized_decimal('0.00') ." or [+|-]" . wc_format_localized_decimal('00.0%');

		// Get all attributes
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		// Hook into the 'new', 'edit', and 'delete' attribute panel
		add_action("woocommerce_after_add_attribute_fields", array($this, 'mt2mba_add_attribute_fields'), 10, 2);
		add_action("woocommerce_after_edit_attribute_fields", array($this, 'mt2mba_edit_attribute_fields'), 10, 2);
		add_action("woocommerce_before_attribute_delete", function () {
				// Delete attribute option or meta
				delete_option(REWRITE_OPTION_PREFIX . $_GET['delete']);
			},
			10, 2);

		// Loop through attributes adding hooks
		foreach ($attribute_taxonomies as $attribute_taxonomy) {
			// Build taxonomy name
			$taxonomy = 'pa_' . $attribute_taxonomy->attribute_name;

			// Hook into 'new' and 'edit' term panels
			add_action("{$taxonomy}_add_form_fields", array($this, 'mt2mba_add_form_fields'), 10, 2);
			add_action("{$taxonomy}_edit_form_fields", array($this, 'mt2mba_edit_form_fields'), 10, 2);

			// Hook save function into both the 'new' and 'edit' functions
			add_action("created_{$taxonomy}", array($this, 'mt2mba_save_markup_to_metadata'), 10, 2);
			add_action("edited_{$taxonomy}", array($this, 'mt2mba_save_markup_to_metadata'), 10, 2);

			// Add 'Markup' column to 'edit' term panels
			add_filter("manage_edit-{$taxonomy}_columns", function ($columns) {
					// Add Markup column to term list
					$columns['markup'] = __('Markup', 'markup-by-attribute');
					return $columns;
				},
				10);
			add_action("manage_{$taxonomy}_custom_column", function ($string, $column_name, $term_id) {
					// Add content to rows in Markup column
					if	($column_name == 'markup') echo wc_format_localized_decimal(get_term_meta($term_id, 'mt2mba_markup', true));
					return;
				},
				10, 3);

			// Make 'Markup' column sortable
			add_filter("manage_edit-{$taxonomy}_sortable_columns", function ($columns) {
					// Make Markup column sortable
					$columns['markup'] = 'markup';
					return $columns;
				},
				10);
			add_filter('pre_get_terms', array($this, 'mt2mba_sort_on_markup_column'), 10);
		}

		// Add check for markup validation errors
		add_action('admin_notices', array($this, 'display_markup_error'));
	}

	public function display_markup_error() {
		$error = get_transient('mt2mba_markup_error');
		if ($error) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html($error)
			);
			delete_transient('mt2mba_markup_error');
		}
	}

	/**
	 * Markup column is sortable, but it is a term-meta item which
	 * must JOINed with the term table to make the sort happen.
	 */
	function mt2mba_sort_on_markup_column($term_query) {
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

	/**
	 * Build <DIV> to add markup to the 'Add New' attribute term panel
	 * Save the flag if the [Add attribute] button was pressed
	 */
	function mt2mba_add_attribute_fields() {
		if (isset($_POST['add_new_attribute'])) {
			// [Add attribute] button pressed, save the rewrite flag
			$rewrite_flag	= isset($_POST['term_name_rewrite']) ? 'yes' : 'no';

			// Get all attributes
			$attribute_taxonomies = wc_get_attribute_taxonomies();
			// Find new attribute ID and write options
			foreach ($attribute_taxonomies as $attribute_taxonomy) {
				if ($attribute_taxonomy->attribute_label == $_POST['attribute_label']) {
					update_option(REWRITE_OPTION_PREFIX . $attribute_taxonomy->attribute_id, $rewrite_flag);
				}
			}
		}

		// Build <DIV>
		?>
		<div class="form-field">
			<label for="term_name_rewrite"><input type="checkbox" name="term_name_rewrite" id="term_name_add_rewrite" value=""> <?php echo($this->rewrite_label); ?></label>
			<p class="description"><?php echo($this->rewrite_description); ?></p>
		</div>
		<?php
	}

	/**
	 * Build <TR> to add markup to the 'Edit' attribute term panel
	 * Save the flag if the [Save attribute] button was pressed
	 */
	function mt2mba_edit_attribute_fields() {
		// Retrieve the existing rewrite flag for this attribute(NULL results are valid)
		if (isset($_POST['save_attribute'])) {
			// [Update] button pressed, set rewrite flag and save
			$rewrite_flag	= isset($_POST['term_name_rewrite']) ? 'yes' : 'no';
			update_option(REWRITE_OPTION_PREFIX . $_GET['edit'], $rewrite_flag);
		} else {
			// First time in, set rewrite flag from Options database
			$rewrite_flag	= get_option(REWRITE_OPTION_PREFIX . $_GET['edit'], FALSE);
		}

		// Build row and fill field with current markup
		$checked_flag		= $rewrite_flag == 'yes' ? ' checked' : "";
		?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="term_name_rewrite"><?php echo($this->rewrite_label); ?></label></th>
			<td>
				<input type="checkbox" name="term_name_rewrite" id="term_name_edit_rewrite"<?php echo $checked_flag; ?>>
				<p class="description"><?php echo($this->rewrite_description); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Build <DIV> to add markup to the 'Add New' attribute term panel
	 *
	 *	@param string $taxonomy
	 */
	function mt2mba_add_form_fields($taxonomy) {
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
	 * Build <TR> to add markup to the 'Edit' attribute term panel
	 *
	 *@param	string	$term
	 *
	 */
	function mt2mba_edit_form_fields($term) {
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

	/**
	 * Validate markup value
	 * @param string $markup The markup value to validate
	 * @return array ['valid' => bool, 'message' => string]
	 */
	private function validate_markup($markup) {
		// Remove any whitespace
		$markup = trim($markup);

		// Check if it's a valid number format
		if (!preg_match('/^[-+]?\d*\.?\d+%?$/', $markup)) {
			return [
				'valid' => false,
				'message' => __('Invalid markup format. Use numbers with optional decimal point, percentage sign, and plus/minus signs.', 'markup-by-attribute')
			];
		}

		// Check if it's a percentage
		if (strpos($markup, '%') !== false) {
			// Remove % for numeric check
			$value = floatval(str_replace('%', '', $markup));
			if ($value < -99) {
				return [
					'valid' => false,
					'message' => __('Percentage markdown cannot exceed -99% or will result in negative pricing.', 'markup-by-attribute')
				];
			}
		}

		return ['valid' => true, 'message' => ''];
	}

	/**
	 * Save the term's markup as metadata
	 * @param	string	$term_id
	 */
	function mt2mba_save_markup_to_metadata($term_id) {
		// Sanity check
		if (!isset($_POST['term_markup'])) return;

		// Prevent recursion when wp_update_term() is called later
		if (defined('MT2MBA_ATTRB_RECURSION')) return;
		define('MT2MBA_ATTRB_RECURSION', TRUE);

		global			$mt2mba_utility;
		$term			= get_term($term_id);
		$taxonomy_name	= sanitize_key($term->taxonomy);

		// Remove any previous markup information from term name.
		$name			= $term->name;
		$name			= trim($mt2mba_utility->remove_bracketed_string(ATTRB_MARKUP_NAME_BEG, ATTRB_MARKUP_END, $name));
		// Clean up legacy descriptions.
		$description	= $term->description;
		$description	= trim($mt2mba_utility->remove_bracketed_string(ATTRB_MARKUP_DESC_BEG, ATTRB_MARKUP_END, $description));

		// Remove old metadata, regardless of next steps
		delete_term_meta($term_id, 'mt2mba_markup');

		// Add Markup metadata if present
		if (esc_attr($_POST['term_markup'] <> "" && $_POST['term_markup'] <> 0)) {
			$term_markup = esc_attr($_POST['term_markup']);
			
			// Validate markup
			$validation = $this->validate_markup($term_markup);
			if (!$validation['valid']) {
				if (wp_doing_ajax()) {
					wp_send_json_error([
						'success' => $validation['message']
					]);
					exit;
				} else {
					// Our existing transient approach for non-AJAX
					set_transient('mt2mba_markup_error', $validation['message'], 45);
					return;
				}
			}

			// If term_markup has a value other than zero, add/update the value to the metadata table
			if (strpos($term_markup, "%")) {
				// If term_markup has a percentage sign, save as a formatted percent
				$markup = sprintf("%+g%%", wc_format_decimal($term_markup));
			} else {
				// If term_markup does not have percentage sign, save as a formatted floating point number
				$markup = sprintf("%+g", wc_format_decimal($term_markup));
			}
			update_term_meta($term_id, 'mt2mba_markup', $markup);

			// Update term name, if rewrite flag is set, so markup is visible in the name
			$rewrite_flag	= get_option(REWRITE_OPTION_PREFIX . wc_attribute_taxonomy_id_by_name($taxonomy_name));
			if ($rewrite_flag == 'yes') {
				$markup = $mt2mba_utility->format_option_markup($markup);
				$markup = strpos($markup, "+") ? str_replace("(+", $this->text_add . " ", $markup) : str_replace("(-", $this->text_subtract . " ", $markup);
				$name	.= $markup;
			}
		}
		// Rewrite term if name or description changed
		if ($term->name != $name ||
			$term->description != $description) {
				wp_update_term($term_id, $taxonomy_name, array('description' => trim($description), 'name' => trim($name)));
			}
	}

}	// End	class MT2MBA_BACKEND_TERM
?>