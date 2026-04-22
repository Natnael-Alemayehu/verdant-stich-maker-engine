<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes all custom tables and options.
 *
 * @package VerdantStitch
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables.
$tables = [
    $wpdb->prefix . 'verdant_kits',
    $wpdb->prefix . 'verdant_progress_history',
    $wpdb->prefix . 'verdant_milestone_images',
    $wpdb->prefix . 'verdant_mastery_scores',
];

foreach ( $tables as $table ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Delete options.
$options = [
    'verdant_db_version',
    'verdant_mastery_thresholds',
    'verdant_coupon_prefix',
    'verdant_coupon_expiry_days',
];

foreach ( $options as $opt ) {
    delete_option( $opt );
}

// Clean up user meta.
$wpdb->query(
    "DELETE FROM {$wpdb->usermeta}
     WHERE meta_key LIKE '_verdant_%'"
);
