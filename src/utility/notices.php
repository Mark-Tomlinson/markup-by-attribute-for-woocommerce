<?php
namespace mt2Tech\MarkupByAttribute\Utility;

/**
 * Admin notice management for Markup-by-Attribute
 * 
 * Handles the display and dismissal of admin notices throughout the plugin.
 * Provides functionality for showing dismissible messages to administrators
 * and storing dismissal preferences in the database.
 *
 * @package   mt2Tech\MarkupByAttribute\Utility
 * @author    Mark Tomlinson
 * @license   GPL-2.0+
 * @since     1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit();

class Notices {
	//region PROPERTIES
	/**
	 * Singleton instance
	 * 
	 * @var self|null
	 * @since 1.0.0
	 */
	private static ?self $instance = null;
	//endregion

	//region INSTANCE MANAGEMENT
	/**
	 * Get singleton instance
	 * 
	 * @since 1.0.0
	 * @return Notices Single instance of this class
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
	public function __clone(): void {}

	/**
	 * Prevent object unserialization
	 * 
	 * @since 1.0.0
	 */
	public function __wakeup(): void {}

	/**
	 * Initialize notice handling and register hooks
	 * 
	 * Sets up admin notice scripts and dismissal handling.
	 * 
	 * @since 1.0.0
	 */
	private function __construct() {
		//	Enqueue notice dismissal JScript
		add_action('admin_enqueue_scripts', array($this, 'action_admin_enqueue_scripts'));
		//	Action to set the message dismissal option
		add_action('admin_init', array($this, 'action_admin_init'));
	}
	//endregion

	//region HOOKS & CALLBACKS
	/**
	 * Enqueue JavaScript for notice dismissal
	 * 
	 * Loads the script that handles dismissible notice functionality
	 * in the WordPress admin.
	 * 
	 * @since 1.0.0
	 */
	public function action_admin_enqueue_scripts(): void {
		wp_enqueue_script (
			'jq-mt2mba-clear-notices',
			MT2MBA_PLUGIN_URL . 'src/js/jq-mt2mba-clear-notices.js',
			array('jquery')
		);
	}

	/**
	 * Handle notice dismissal requests
	 * 
	 * Processes admin requests to dismiss notices by checking for the
	 * 'mt2mba_dismiss' query parameter and storing dismissal status.
	 * 
	 * @since 1.0.0
	 */
	public function action_admin_init(): void {
		if (isset($_GET['mt2mba_dismiss'])) {
			$dismiss_option = htmlspecialchars($_GET['mt2mba_dismiss']);
			update_option("mt2mba_dismissed_$dismiss_option", true, false);
			wp_die();
		}
	}
	//endregion

	//region PUBLIC API
	/**
	 * Display admin notices from structured array
	 * 
	 * Processes an array of notices organized by type and displays them
	 * to administrators with appropriate styling and dismissal options.
	 * 
	 * @since 1.0.0
	 * @param array $admin_notices Structured array of notices:
	 *                             - type (string): 'error', 'warning', 'success', 'info'
	 *                             - messages (array): Each containing [name, message]
	 */
	public function send_notice_array(array $admin_notices): void {
		foreach ($admin_notices as $type => $notices) {
			foreach ($notices as $notice_id => $notice) {
				$this->notice($type, $notice[1], $notice[0]);
			};
		};
	}
	//endregion

	//region PRIVATE METHODS
	/**
	 * Display a single admin notice
	 * 
	 * Creates and displays an admin notice of the specified type with
	 * optional dismissal functionality.
	 * 
	 * @since 1.0.0
	 * @param string $type           Notice type: 'error', 'warning', 'success', 'info'
	 * @param string $message        The notice message content
	 * @param string $dismiss_option Unique identifier for dismissal tracking
	 */
	private function notice(string $type, string $message, string $dismiss_option): void {
		add_action (
			'admin_notices',
			function() use ($type, $message, $dismiss_option) {
				$dismiss_url = add_query_arg (
					array('mt2mba_dismiss' => $dismiss_option),
					admin_url()
				);
				if (!get_option("mt2mba_dismissed_{$dismiss_option}")) {
					?><div
						class="notice mt2mba-notice notice-<?php echo $type;
						if ($dismiss_option) {
							echo ' is-dismissible" data-dismiss-url="' . esc_url($dismiss_url);
						} ?>">
						<strong><em><?php echo(MT2MBA_PLUGIN_NAME . ' ' . $type); ?></em></strong>
						<p><?php echo($message); ?></p>
					</div><?php
				}	//	End if
			}	//	End function
		);
	}
	//endregion

}