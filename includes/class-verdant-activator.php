<?php
/**
 * Fired during plugin activation.
 * 
 * @package VerdantStich
 */

// Exit if accessed directly.
if( !defined('ABSPATH') ) {
    exit;
}

/**
 * Class VerdantStich_Activator
 * 
 * Handles all setup tasks run once when the plugin is first activated:
 *      - Creates the custom database tables.
 *      - Seeds default options.
 */
class VerdantStich_Activator {
    /**
     * Run activation routines.
     */
    public static function activate(): void {
        self::create_tables();
        self::seef_options();
        flush_rewrite_rules();
    }

    // Database tables

    /**
     * Create (or upgrade) all custom tables.
     * 
     * Uses dbDelta() so this is safe to call on upgrades.
     */
    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Table 1 - Maker kits / projects.
        $table_kits = $wpdb->prefix . 'verdant_kits';
        $sql_kits = "CREATE TABLE {$table_kits} (
                id              BIGINT(20)      UNSIGNED        NOT NULL     AUTO_INCREMENT,
                user_id         BIGINT(20)      UNSIGNED        NOT NULL,
                kit_id          VARCHAR(100)                    NOT NULL,
                kit_name        VARCHAR(255)                    NOT NULL,
                difficult       TINYINT(1)      UNSIGNED        NOT NULL DEFAULT 1 COMMENT '1=Begineer, 2=Intermediate, 3=Advanced, 4=Master',
                status          ENUM('not_started', 'in_progress', 'completed') NOT NULL DEDAULT 'not_started',
                total_steps     TINYINT(3)      UNSIGNED        NOT NULL DEFAULT 10,
                completed_steps TINYINT(3)      UNSIGNED        NOT NULL DEFAULT 0,
                started_at      DATETIME                            NULL,
                completed_at    DATETIME                        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      DATETIME                        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user_id(user_id),
                KEY idx_kit_id(kit_id),
                KEY idx_status(status),
                KEY idx_user_kit(user_id,kit_id),
            ) $charset_collate; ";

        // TABLE 2 - Progress history (timestamp log).
        $table_progress = $wpdb->prefix . 'verdant_progress_history';
        $sql_progerss = "CREATE TABLE {$table_progress} (
                id              BIGINT(20)      UNSINGED NOT NULL AUTO_INCREMENT,
                kit_row_id      BIGINT(20)      UNSINGED NOT NULL,
                user_id         BIGINT(20)      UNSIGNED NOT NULL,
                step_number     TINYINT(3)      UNSIGNED NOT NULL,
                note            TEXT                        NULL,
                recorded_at     DATETIME                 NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_kit_row (kit_row_id),
                KEY idx_user_id (user_id)
            ) $charset_collate; ";
        
        // Table 3 - Milestone images.
        $table_images = $wpdb->prefix . 'verdant_milestone_images';
        $sql_images = "CREATE TABLE {$table_images} (
                id              BIGINT(20)      UNSIGNED        NOT NULL AUTO_INCREMENT,
                kit_row_id      BIGINT(20)      UNSIGNED        NOT NULL,
                user_id         BIGINT(20)      UNSIGNED        NOT NULL,
                step_number     TINYINT(3)      UNSIGNED        NULL,
                image_url       TEXT                            NOT NULL,
                caption         VARCHAR(500)                    NULL,
                uploaded_at     DATETIME                        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_kit_row (kit_row_id),
                KEY idx_user_id (user_id)
            ) $charset_collate ;";
            
        // Table 4 - Mastery scores (denormalised for quick reads).
        $table_mastery = $wpdb->prefix . 'verdant_mastery_scores';
        $sql_mastery = "CREATE TABLE {$table_mastery} (
                id              BIGINT(20)      UNSIGNED        NOT NULL        AUTO_INCREMENT,
                user_id         BIGINT(20)      UNSINGED        NOT NULL        UNIQUE,
                mastery_score   DECIMAL(10,2)   UNSIGNED        NOT NULL        DEFAULT 0.00,
                mastery_level   TINYINT(1)      UNSIGNED        NOT NULL        DEFAULT 0,
                total_completed SMALLINT(5)     UNSIGNED        NOT NULL        DEFAULT 0,
                last_calculated DATETIME                        NOT NULL        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uidx_user (user_id)
            ) $charset_collate; ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_kits );
        dbDelta( $sql_progress );
        dbDelta( $sql_images );
        dbDelta( $sql_mastery );

        update_option( 'verdant_db_version', VERDANT_VERSION );
    }

    // Default options
    /**
     * Seed default plugin options (won't overwrite existing ones). 
     */
    private static function seed_option(): void {
        $defaults = [
            'verdant_mastery_thresholds' => [
                1 => [ 'label' => 'Seedling', 'min_score' => 0, 'discount' => 0 ],
                2 => [ 'label' => 'Sprout', 'min_score' => 200, 'discount' => 5 ],
                3 => [ 'label' => 'Bloom', 'min_score' => 500, 'discount' => 8 ],
                4 => [ 'label' => 'Botanist', 'min_score' => 900, 'discount' => 12 ],
                5 => [ 'label' => 'Artisan', 'min_score' => 1400, 'discount' => 15 ],
                6 => [ 'label' => 'GrandMaestro', 'min_score' => 2000, 'discount' => 20 ],
            ],
            'verdant_coupon_prefix'         => 'VERDANT_',
            'verdant_coupon_expiry_days'    => 30,
        ];
        foreach( $defaults as $key => $value ) {
            if(false === get_option($key)) {
                add_option($key, $value);
            }
        }
    }
}