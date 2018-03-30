<?php
/**
 * Filename:	class_markup_settings.php
 * 
 * Description:	
 * Author:     	Mark Tomlinson
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit( );

class MT2MBA_MARKUP_SETTINGS {
    /**
     * @var mt2_markup_options
     */
    private $options;
 
    /**
     * Constructor.
     *
     * @param  $options
     */
    public function __construct( $options)
    {
        $this->options = $options;
    }
 
    /**
     * Adds the admin page to the menu.
     */
    public function addAdminPage()
    {
        add_options_page(__('WordPress Meme Shortcode', 'wp_meme_shortcode'), __('Meme Shortcode', 'wp_meme_shortcode'), 'install_plugins', 'wp_meme_shortcode', array($this, 'render'));
    }
 
    /**
     * Configure the option page using the settings API.
     */
    public function configure()
    {
        // Register settings
        register_setting('wp_meme_shortcode', 'wp_meme_shortcode');
 
        // General Section
        add_settings_section('wp_meme_shortcode_general', __('General', 'wp_meme_shortcode'), array($this, 'renderGeneralSection'), 'wp_meme_shortcode');
        add_settings_field('wp_meme_shortcode_size', __('Default Image Size', 'wp_meme_shortcode'), array($this, 'renderSizeField'), 'wp_meme_shortcode', 'wp_meme_shortcode_general');
    }
 
    /**
     * Renders the admin page using the Settings API.
     */
    public function render()
    {
        ?>
        <div class="wrap" id="wp-meme-shortcode-admin">
            <h2><?php _e('WordPress Meme Shortcode', 'wp_meme_shortcode'); ?></h2>
            <form action="options.php" method="POST">
                <?php settings_fields('wp_meme_shortcode'); ?>
                <?php do_settings_sections('wp_meme_shortcode'); ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
 
    /**
     * Renders the general section.
     */
    public function renderGeneralSection()
    {
        ?>
        <p><?php _e('Configure WordPress Meme Shortcode.', 'wp_meme_shortcode'); ?></p>
        <?php
    }
 
    /**
     * Renders the size field.
     */
    public function renderSizeField()
    {
        ?>
        <input id="wp_meme_shortcode_size" name="wp_meme_shortcode[size]" type="number" value="<?php echo $this->options->get('size', '500'); ?>" />
        <?php
    }
}

?>