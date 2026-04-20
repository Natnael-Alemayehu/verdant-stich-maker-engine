<?php
/**
 * WordPress admin integration.
 * 
 * Adds a "Verdant stitch" top-level menu with:
 *      - Dashboard(user mastery overview)
 *      - API Tester (interactive GET/POST demo with the admin)
 *      - Settings
 * 
 * @package Verdantstitch
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Verdantstitch_Admin
 */
class Verdantstitch_Admin {
    public function __construct(
        private Verdantstitch_Maker_Profile $maker_profile,
        private Verdantstitch_Maker_Engine $mastery_engine
    ) {
        add_action( 'admin_menu', [$this, 'register_menus'] );
        add_action( 'admin_enqueue_scripts', [$this, 'enqueue_assets'] );
        add_action( 'admin_init', [$this, 'register_settings'] );
    }

    // Menus
    public function register_menus(): void {
        add_menu_page(
            __('Verdant stitch', 'verdant-stitch'),
            __('Verdant stitch', 'verdant-stitch'),
            'manage_options',
            'verdant-stitch',
            [ $this, 'render_dashboard' ],
            'dashicons-admin-customizer',
            56
        );
        
        add_submenu_page(
            'verdant-stitch',
            __( 'Dashboard', 'verdant-stitch' ),
            __( 'Dashboard', 'verdant-stitch' ),
            'manage_options',
            'verdant-stitch',
            [ $this, 'render_dashboard' ]
        );

        add_submenu_page(
            'verdant-stitch',
            __( 'API Tester', 'verdant-stitch' ),
            __( 'API Tester', 'verdant-stitch' ),
            'manage_options',
            'verdant-api-tester',
            [ $this, 'render_api_tester' ]
        );

        add_submenu_page(
            'verdant-stitch',
            __('Settings', 'verdant-stitch'),
            __('settings', 'verdant-stitch'),
            'manage_options',
            'verdant-settings',
            [ $this, 'render_settings' ]
        );
    }

    // Assets
    public function enqueue_assets( string $hook ): void {
        if( !str_contains( $hook, 'verdant' ) ) {
            return;
        }

        wp_enqueue_style(
            'verdant-admin',
            VERDANT_PLUGIN_URL . 'assets/css/admin.css',
            [],
            VERDANT_VERSION
        );

        wp_enqueue_script(
            'verdant-api-tester',
            VERDANT_PLUGIN_URL . 'asets/js/api-tester.js',
            [ 'wp-api', 'jquery' ],
            VERDANT_VERSION,
            true
        );

        wp_localize_script( 'verdant-api-tester', 'verdantAdmin', [
            'apiBase'   => rest_url('verdant/v1'),
            'nonce'     => wp_create_nonce('wp_rest'),
            'currentUser' => get_current_user_id(),
        ] );
    }

    // Page renders
    public function render_dashboard(): void {
        global $wpdb;
        $table_mastery = $wpdb->prefix . 'verdant_mastery_score';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $top_users = $wpdb -> get_results(
            "SELECT m.*, u.display_name, u.user_email
            FROM {$table_mastery} m
            JOIN {$wpdb->users} u on u.ID = m.user_id
            ORDER BY m.mastery_score DESC
            LIMIT 20"
        );

        $thresholds = get_option( 'verdant_mastery_thresholds', [] );
        require_once VERDANT_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }

    public function render_api_tester(): void {
        require_once VERDANT_PLUGIN_DIR . 'templates/admin-api-tester.php';
    }

    public function render_settings(): void {
        require_once VERDANT_PLUGIN_DIR . 'templates/admin-settings.php';
    }

    // Settings API
    public function register_settings(): void {
        register_setting( 'verdant_settings_group', 'verdant_coupon_prefix', ['sanitize_callback' => 'sanitize_text_field'] );
        register_setting( 'verdant_settings_group', 'verdant_coupon_expiry_days', ['sanitize_callback' => 'absint'] );

        add_settings_section( 'verdant_coupon_section', __('Coupon Settings', 'verdant-stitch'), '__return_false', 'verdant-settings' );
        add_settings_field('verdant_coupon_prefix', __('Coupon Code Prefix', 'verdant-stitch'), 
            function() {
                $value = esc_attr(get_option('verdant_coupon_prefix', 'VERDANT_'));
                echo "<input type='text' name='verdant_coupon_prefix' value='{$value}' class='regular-text' />";
            },
            'verdant-settings', 'verdant_coupon_section'
        );
        add_settings_field('verdant_coupon_exiry_days', __('Coupon Expiry (days)', 'verdant-stitch'),
            function() {
                $value = absint(get_option('verdant_coupon_expiry_days', 30));
                echo "<input type='number' name='verdant_coupon_expiry_days' value='{$value}' class='small-text' min='1'/>";
            },
            'verdant-settings', 'verdant_coupon_section'
        );
    }
}