<?php
namespace mt2Tech\MarkupByAttribute\Backend;

/**
 * Attribute management for markup settings
 *
 * Manages the per-attribute options (name rewriting, description rewriting,
 * theme overwrite prevention) on the WooCommerce global attribute admin pages.
 *
 * @package   mt2Tech\MarkupByAttribute\Backend
 * @author    Mark Tomlinson
 * @license   GPL-2.0+
 * @since     4.6.0
 */
class Attribute {
	//region PROPERTIES
	/**
	 * Singleton instance
	 * @var self|null
	 */
	private static ?self $instance = null;

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
	//endregion

	//region INSTANCE MANAGEMENT
	/**
	 * Get singleton instance
	 *
	 * @since 4.6.0
	 * @return Attribute Single instance of this class
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
	 * @since 4.6.0
	 */
	private function __clone() {}

	/**
	 * Prevent object unserialization
	 *
	 * @since 4.6.0
	 */
	public function __wakeup() {}

	/**
	 * Initialize the class and set up hooks
	 *
	 * @since 4.6.0
	 */
	private function __construct() {
		$this->initializeLabels();
		$this->registerAttributeHooks();
	}

	/**
	 * Initialize text labels and descriptions
	 *
	 * @since 4.6.0
	 */
	private function initializeLabels(): void {
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
	}

	/**
	 * Register hooks for attribute actions
	 *
	 * @since 3.0.0
	 */
	private function registerAttributeHooks(): void {
		// Add fields to forms
		add_action("woocommerce_after_add_attribute_fields", array($this, 'addAttributeFields'), 10, 2);
		add_action("woocommerce_after_edit_attribute_fields", array($this, 'editAttributeFields'), 10, 2);

		// Delete options when attribute is deleted
		add_action("woocommerce_before_attribute_delete", function () {
			$delete_id = isset($_GET['delete']) ? absint($_GET['delete']) : 0;
			if ($delete_id > 0) {
				delete_option(REWRITE_TERM_NAME_PREFIX . $delete_id);
				delete_option(REWRITE_TERM_DESC_PREFIX . $delete_id);
				delete_option(DONT_OVERWRITE_THEME_PREFIX . $delete_id);
			}
		}, 10, 2);
	}
	//endregion

	//region ATTRIBUTE FORM HANDLERS
	/**
	 * Build form fields for attribute add panel
	 */
	public function addAttributeFields() {
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
	public function editAttributeFields() {
		// Sanitize the attribute ID from GET parameter once at the top
		$attribute_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;

		// Early return if invalid ID
		if ($attribute_id <= 0) {
			return;
		}

		if (isset($_POST['save_attribute'])) {
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
		$rewrite_name_flag			= get_option(REWRITE_TERM_NAME_PREFIX . $attribute_id, false);
		$rewrite_desc_flag			= get_option(REWRITE_TERM_DESC_PREFIX . $attribute_id, false);
		$dont_overwrite_theme_flag	= get_option(DONT_OVERWRITE_THEME_PREFIX . $attribute_id, false);

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
}
