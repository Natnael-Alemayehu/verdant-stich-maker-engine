<?php
/**
 * WooCommerce Bridge.
 *
 * Listens for mastery level changes and automatically creates / updates
 * a personal discount coupon for the user.
 *
 * Coupon code pattern:  VERDANT_{USER_ID}_{LEVEL}
 * Coupon type:          Percentage discount
 *
 * @package VerdantStitch
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class VerdantStitch_WooCommerce_Bridge
 */
class VerdantStitch_WooCommerce_Bridge {

    public function __construct(
        private VerdantStitch_Mastery_Engine $mastery_engine
    ) {
        add_action( 'verdant_mastery_updated', [ $this, 'handle_mastery_updated' ], 10, 3 );
    }

    // ─────────────────────────────────────────────────────────────
    // Event handler
    // ─────────────────────────────────────────────────────────────

    /**
     * React to a mastery level change.
     *
     * @param int   $user_id
     * @param int   $new_level
     * @param float $new_score
     */
    public function handle_mastery_updated( int $user_id, int $new_level, float $new_score ): void {
        if ( ! $this->woocommerce_active() ) {
            return;
        }

        $discount_pct = $this->mastery_engine->get_discount_for_level( $new_level );

        // Update user meta so themes / shortcodes can read it cheaply.
        update_user_meta( $user_id, '_verdant_mastery_level', $new_level );
        update_user_meta( $user_id, '_verdant_mastery_score', $new_score );
        update_user_meta( $user_id, '_verdant_discount_pct',  $discount_pct );

        if ( $discount_pct > 0 ) {
            $this->upsert_artisan_coupon( $user_id, $new_level, $discount_pct );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Coupon management
    // ─────────────────────────────────────────────────────────────

    /**
     * Create or update the Artisan Discount coupon for a user.
     *
     * @param int $user_id
     * @param int $level
     * @param int $discount_pct
     * @return int|false  Coupon post ID or false on failure.
     */
    public function upsert_artisan_coupon( int $user_id, int $level, int $discount_pct ): int|false {
        $prefix      = get_option( 'verdant_coupon_prefix', 'VERDANT_' );
        $expiry_days = (int) get_option( 'verdant_coupon_expiry_days', 30 );
        $coupon_code = strtoupper( $prefix . $user_id . '_L' . $level );

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        // Look up existing coupon.
        $existing_id = $this->get_coupon_post_id( $coupon_code );

        $coupon_data = [
            'post_title'   => $coupon_code,
            'post_content' => '',
            'post_status'  => 'publish',
            'post_author'  => 1,
            'post_type'    => 'shop_coupon',
        ];

        if ( $existing_id ) {
            $coupon_data['ID'] = $existing_id;
            $coupon_id = wp_update_post( $coupon_data );
        } else {
            $coupon_id = wp_insert_post( $coupon_data );
        }

        if ( is_wp_error( $coupon_id ) || ! $coupon_id ) {
            return false;
        }

        $expiry_date = date( 'Y-m-d', strtotime( "+{$expiry_days} days" ) );
        $thresholds  = get_option( 'verdant_mastery_thresholds', [] );
        $label       = $thresholds[ $level ]['label'] ?? 'Artisan';

        // WooCommerce coupon meta.
        $meta = [
            'discount_type'              => 'percent',
            'coupon_amount'              => (string) $discount_pct,
            'individual_use'             => 'yes',
            'usage_limit'                => '0',         // unlimited
            'usage_limit_per_user'       => '1',
            'expiry_date'                => $expiry_date,
            'free_shipping'              => 'no',
            'exclude_sale_items'         => 'no',
            'customer_email'             => [ $user->user_email ],
            'verdant_level'              => $level,
            'verdant_level_label'        => $label,
            'verdant_generated_for_user' => $user_id,
        ];

        foreach ( $meta as $key => $value ) {
            update_post_meta( $coupon_id, $key, $value );
        }

        // Store coupon code on user for easy retrieval.
        update_user_meta( $user_id, '_verdant_coupon_code', $coupon_code );
        update_user_meta( $user_id, '_verdant_coupon_id',   $coupon_id );

        return $coupon_id;
    }

    /**
     * Get the coupon data for a user.
     *
     * @param int $user_id
     * @return array
     */
    public function get_user_coupon_info( int $user_id ): array {
        $coupon_code  = get_user_meta( $user_id, '_verdant_coupon_code', true );
        $discount_pct = (int) get_user_meta( $user_id, '_verdant_discount_pct', true );
        $level        = (int) get_user_meta( $user_id, '_verdant_mastery_level', true );

        if ( ! $coupon_code ) {
            return [
                'coupon_code'   => null,
                'discount_pct'  => 0,
                'level'         => $level,
                'wc_available'  => $this->woocommerce_active(),
            ];
        }

        $coupon_id = $this->get_coupon_post_id( $coupon_code );
        $expiry    = $coupon_id ? get_post_meta( $coupon_id, 'expiry_date', true ) : null;

        return [
            'coupon_code'   => $coupon_code,
            'discount_pct'  => $discount_pct,
            'level'         => $level,
            'expiry_date'   => $expiry,
            'wc_available'  => $this->woocommerce_active(),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Check whether WooCommerce is active.
     *
     * @return bool
     */
    private function woocommerce_active(): bool {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Find the post ID of a coupon by its code.
     *
     * @param string $code
     * @return int|null
     */
    private function get_coupon_post_id( string $code ): ?int {
        global $wpdb;
        $post_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_title = %s AND post_type = 'shop_coupon' AND post_status = 'publish'
                 LIMIT 1",
                $code
            )
        );
        return $post_id ? (int) $post_id : null;
    }
}
