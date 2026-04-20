<?php
/**
 * Fired during plugin deactication.
 * 
 * @package VerdantStitch
 */

if ( !defined('ABSPATH') ) {
    exit;
}

/**
 * Class VerdantStitch_Deactivator
 * 
 * NOTE: Tables and data are intentionally preserved on deactivation.
 * Use uninstall.php (or a "Delete Data" setting) for full removal.
 */
class VerdantStitch_Deactivator {
    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}