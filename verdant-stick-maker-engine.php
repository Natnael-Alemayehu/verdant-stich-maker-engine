<?php
/**
 * Plugin Name:       The Verdant Stitch – Maker Progress & Rewards Engine
 * Plugin URI:        https://github.com/your-org/verdant-stitch-maker-engine
 * Description:       Custom REST API for tracking customer crafting progress, milestone submissions, mastery scoring, and WooCommerce discount integration for The Verdant Stitch subscription box.
 * Version:           1.0.0
 * Author:            Your Name
 * Author URI:        https://yoursite.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       verdant-stitch
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * WC requires at least: 7.0
 *
 * @package VerdantStitch
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────
define( 'VERDANT_VERSION',     '1.0.0' );
define( 'VERDANT_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'VERDANT_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'VERDANT_PLUGIN_FILE', __FILE__ );

// ─────────────────────────────────────────────────────────────────
// Autoloader (PSR-4 style for the includes/ folder)
// ─────────────────────────────────────────────────────────────────
spl_autoload_register( function ( string $class ): void {
    $prefix   = 'VerdantStitch\\';
    $base_dir = VERDANT_PLUGIN_DIR . 'includes/';

    if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, strlen( $prefix ) );
    $file           = $base_dir . 'class-' . strtolower( str_replace( [ '\\', '_' ], [ '/', '-' ], $relative_class ) ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
} );

// ─────────────────────────────────────────────────────────────────
// Bootstrap
// ─────────────────────────────────────────────────────────────────
require_once VERDANT_PLUGIN_DIR . 'includes/class-verdant-activator.php';
require_once VERDANT_PLUGIN_DIR . 'includes/class-verdant-deactivator.php';
require_once VERDANT_PLUGIN_DIR . 'includes/class-verdant-database.php';
require_once VERDANT_PLUGIN_DIR . 'includes/class-verdant-maker-profile.php';
require_once VERDANT_PLUGIN_DIR . 'includes/class-verdant-mastery-engine.php';
require_once VERDANT_PLUGIN_DIR . 'includes/class-verdant-woocommerce-bridge.php';
require_once VERDANT_PLUGIN_DIR . 'includes/class-verdant-rest-controller.php';
require_once VERDANT_PLUGIN_DIR . 'includes/class-verdant-auth.php';
require_once VERDANT_PLUGIN_DIR . 'admin/class-verdant-admin.php';

register_activation_hook(   __FILE__, [ 'VerdantStitch_Activator',   'activate'   ] );
register_deactivation_hook( __FILE__, [ 'VerdantStitch_Deactivator', 'deactivate' ] );

/**
 * Main plugin instance (singleton).
 */
function verdant_stitch(): VerdantStitch_Plugin {
    static $instance = null;
    if ( null === $instance ) {
        $instance = new VerdantStitch_Plugin();
    }
    return $instance;
}
add_action( 'plugins_loaded', 'verdant_stitch' );

/**
 * Core plugin class – wires everything together.
 */
class VerdantStitch_Plugin {

    public VerdantStitch_Database          $db;
    public VerdantStitch_Maker_Profile     $maker_profile;
    public VerdantStitch_Mastery_Engine    $mastery_engine;
    public VerdantStitch_WooCommerce_Bridge $wc_bridge;
    public VerdantStitch_REST_Controller   $rest;
    public VerdantStitch_Auth              $auth;

    public function __construct() {
        $this->db             = new VerdantStitch_Database();
        $this->maker_profile  = new VerdantStitch_Maker_Profile( $this->db );
        $this->mastery_engine = new VerdantStitch_Mastery_Engine( $this->db );
        $this->wc_bridge      = new VerdantStitch_WooCommerce_Bridge( $this->mastery_engine );
        $this->auth           = new VerdantStitch_Auth();
        $this->rest           = new VerdantStitch_REST_Controller(
            $this->maker_profile,
            $this->mastery_engine,
            $this->wc_bridge,
            $this->auth
        );

        add_action( 'rest_api_init', [ $this->rest, 'register_routes' ] );

        if ( is_admin() ) {
            new VerdantStitch_Admin( $this->maker_profile, $this->mastery_engine );
        }

        // Recalculate mastery whenever a project is updated.
        add_action( 'verdant_project_updated', [ $this->mastery_engine, 'recalculate_user_mastery' ] );
    }
}
