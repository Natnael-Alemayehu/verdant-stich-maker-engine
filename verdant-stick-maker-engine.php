<?php
/**
 * Plugin Name:     The Verdant Stich - Maker Progress & Rewards Engine
 * Plugin URI:      https://github.com/natnael-alemayehu/verdant-stich-maker
 * Description:     Custom REST API for tracking customer crafting progress, milestone submissions, mastery scoring,
 *                  WooCommerce discrount integration for the Verdant Stich subscription box.
 * Version:         1.0.0
 * Author:          Nate
 * Requires PHP:    8.0
 * 
 * @package VerdantStich 
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constants
define( 'VERDANT_VERSION', '1.0.0' );
define( 'VERDANT_PLUGIN_DIR', plugin_dir_path(__FILE__) );
define( 'VERDANR_PLUGIN_URL', plugin_dir_url(__FILE__) );
define( 'VERDANT_PLUGIN_FILE', __FILE__ );

// Autoloader (PSR-4 style for the includes/ folder)
spl_autoload_register( function (string $class): void {
    $prefix =  'VerdantStich\\';
    $base_dir = VERDANT_PLUGIN_DIR . 'includes/';

    if( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $file = $base_dir . 'class-' . strtolower(str_replace(['\\','_'], ['/', '-'], $relative_class)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
} );

// Bootstrap
require_once VERDANT_PLUGIN_DIR . 'includes/class-verdant-activator.php';
require_once VERDANT_PLUGIN_DIR . 'includes/class-verdant-deactivator.php';
require_once VERDANT_PLUGIN_DIR . 'includes/class-verdant-dataabse.php';
require_once VERDANT_PLUGIN_DIR . 'includes/class-verdant-maker-profile.php';
require_once VERDANT_PLUGIN_DIR . 'includes/class-verdant-woocommerce-bridge.php';
require_once VERDANT_PLUGIN_DIR . 'includes/class-verdant-rest-controller.php';
require_once VERDANT_PLUGIN_DIR . 'includes/class-verdant-auth.php';
require_once VERDANT_PLUGIN_DIR . 'includes/class-verdant-admin.php';

register_activation_hook( __FILE__, ['VerdantStich_Activator', 'activate'] );
register_deactivation_hook( __FILE__, ['VerdantStich_Deactivator', 'deactivate'] );

/**
 * Main plugin instance (singleton).
 */
function verdant_stich(): VerdantStich_Plugin {
    static $instance = null;
    if (null == $instance) {
        $instance = new VerdantStich_Plugin();
    }
    return $instance;
}
add_action('plugins_loaded', 'verdant_stich');

/**
 * Core plugin class - wires everything together.
 */
class VerdantStich_Plugin {
    public VerdantStich_Database            $db;
    public VerdantStich_Maker_Profile       $maker_profile;
    public VerdantStich_Mastery_Engine      $mastery_engine;
    public VerdantStich_WooCommerce_Bridge  $wc_bridge;
    public VerdantStich_REST_Controller     $rest;
    public VerdantStich_Auth                $auth;

    public function __construct() {
        $this->db               = new VerdantStich_Database();
        $this->maker_profile    = new VerdantStich_Maker_Profile( $this->db );
        $this->mastery_engine   = new VerdantStich_Mastery_Engine( $this->db );
        $this->wc_bridge        = new VerdantStich_WooCommerce_Bridge( $this->mastery_engine );
        $this->auth             = new VerdantStich_Auth();
        $this->rest             = new VerdantStich_REST_Controller(
            $this->maker_profile,
            $this->mastery_engine,
            $this->wc_bridge,
            $this->auth
        );

        add_action( 'rest_api_init', [$this->rest, 'register_routes'] );

        if( is_admin() ) {
            new VerdantStich_Admin($this->maker_profile, $this->mastery_engine);
        }

        // Recalculate mastery whenever a project is updated.
        add_action('verdant_project_updated', [$this->mastery_engine, 'recalculate_user_mastery']);
    }
} 