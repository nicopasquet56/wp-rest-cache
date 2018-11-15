<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link:       http://www.acato.nl
 * @since      2018.1
 *
 * @package    WP_Rest_Cache
 * @subpackage WP_Rest_Cache/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    WP_Rest_Cache
 * @subpackage WP_Rest_Cache/admin
 * @author:       Richard Korthuis - Acato <richardkorthuis@acato.nl>
 */
class WP_Rest_Cache_Admin
{

    /**
     * The ID of this plugin.
     *
     * @since    2018.1
     * @access   private
     * @var      string $plugin_name The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    2018.1
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    private $notices;

    /**
     * Initialize the class and set its properties.
     *
     * @since    2018.1
     * @param      string $plugin_name The name of this plugin.
     * @param      string $version The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {

        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->notices = [];

    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    2018.1
     */
    public function enqueue_styles()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in WP_Rest_Cache_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The WP_Rest_Cache_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/wp-rest-cache-admin.css', [], $this->version, 'all');

    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    2018.1
     */
    public function enqueue_scripts()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in WP_Rest_Cache_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The WP_Rest_Cache_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/wp-rest-cache-admin.js', ['jquery'], $this->version, false);

    }


    public function create_menu()
    {
        //create new top-level menu
        add_submenu_page('options-general.php', 'WP REST Cache', 'WP REST Cache', 'administrator', 'wp-rest-cache', [$this, 'settings_page']);

        //call register settings function
        add_action('admin_init', [$this, 'register_settings']);
    }


    public function register_settings()
    {
        register_setting('wp-rest-cache-settings', 'wp_rest_cache_timeout');
    }

    public function settings_page()
    {
        require_once(__DIR__ . '/partials/settings-page.php');
    }

    // Add Toolbar Menus
    function admin_bar_item()
    {
        global $wp_admin_bar;

        $args = [
            'id' => 'wp-rest-cache-clear',
            'title' => __('Clear rest cache', 'wp-rest-cache'),
            'href' => self::empty_cache_url(),
        ];

        $wp_admin_bar->add_menu($args);
    }

    public static function empty_cache_url()
    {
        return wp_nonce_url(admin_url('options-general.php?page=wp-rest-cache&clear=1'), 'rest_cache_options', 'rest_cache_nonce');
    }

    public function handle_actions()
    {
        if (isset($_REQUEST['rest_cache_nonce']) && wp_verify_nonce($_REQUEST['rest_cache_nonce'], 'rest_cache_options')) {
            if (isset($_GET['clear']) && 1 == $_GET['clear']) {
                if (WP_Rest_Cache_Item_Api::clear_cache()) {
                    $this->add_notice('success', __( 'The cache has been successfully cleared', 'wp-rest-cache' ) );
                } else {
                    $this->add_notice('error', __( 'There were no items cached', 'wp-rest-cache' ) );
                }
            }
        }
    }

    protected function add_notice( $type, $message, $dismissible = true ) {
        $this->notices[ $type ][] = [ 'message' => $message, 'dismissible' => $dismissible ];
    }

    /**
     * Check if the MU plugin was created, if not display a warning.
     */
    public function check_muplugin_existence() {
        if ( ! file_exists( WPMU_PLUGIN_DIR . '/wp-rest-cache.php' ) ) {
            $from = '<code>' . substr(
                    plugin_dir_path( __DIR__ ) . 'sources/wp-rest-cache.php',
                    strpos( plugin_dir_path( __DIR__ ), '/wp-content/' )
                ) . '</code>';
            $to   = '<code>' . substr(
                    WPMU_PLUGIN_DIR . '/wp-rest-cache.php',
                    strpos( WPMU_PLUGIN_DIR, '/wp-content/' )
                ) . '</code>';

            $this->add_notice(
                'warning',
                sprintf(
                    __( 'You are not getting the best caching result! <br/>Please copy %s to %s', 'wp-rest-cache' ),
                    $from,
                    $to
                ),
                false );
        }
    }

    /**
     * Display notices (if any) on the Admin dashboard
     */
    public function display_notices() {
        if ( count( $this->notices ) ) {
            foreach ( $this->notices as $type => $messages ) {
                foreach ( $messages as $message ) {
                    ?>
                    <div
                        class="notice notice-<?php echo $type; ?> <?php echo $message['dismissible'] ? 'is-dismissible' : ''; ?>">
                        <p><strong>WP REST Cache:</strong> <?php echo $message['message']; ?></p>
                    </div>
                    <?php
                }
            }
        }
    }

}
