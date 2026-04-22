<?php
/**
 * REST API Controller.
 *
 * Registers all /wp-json/verdant/v1/ endpoints.
 *
 * ────────────────────────────────────────────────────────────────
 * Endpoint map:
 *
 *  GET    /verdant/v1/progress/              → profile summary + kit list
 *  GET    /verdant/v1/progress/(?P<id>[\d]+) → single kit detail + history + images
 *  POST   /verdant/v1/progress/kit           → create / register a kit
 *  POST   /verdant/v1/progress/(?P<id>[\d]+)/steps  → update step progress
 *  POST   /verdant/v1/progress/(?P<id>[\d]+)/images → add milestone image
 *  GET    /verdant/v1/mastery                → mastery score + level + coupon info
 *  POST   /verdant/v1/mastery/recalculate    → force recalculation (admin)
 *  GET    /verdant/v1/levels                 → public level / threshold table
 *
 * @package VerdantStitch
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class VerdantStitch_REST_Controller
 */
class VerdantStitch_REST_Controller {

    public const NAMESPACE = 'verdant/v1';

    public function __construct(
        private VerdantStitch_Maker_Profile      $maker_profile,
        private VerdantStitch_Mastery_Engine     $mastery_engine,
        private VerdantStitch_WooCommerce_Bridge $wc_bridge,
        private VerdantStitch_Auth               $auth
    ) {}

    // ─────────────────────────────────────────────────────────────
    // Route registration
    // ─────────────────────────────────────────────────────────────

    public function register_routes(): void {
        $ns = self::NAMESPACE;

        // ── Profile / kit list ──────────────────────────────────────
        register_rest_route( $ns, '/progress', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_profile' ],
                'permission_callback' => [ $this->auth, 'require_authenticated' ],
                'args'                => $this->user_id_arg(),
            ],
        ] );

        // ── Single kit detail ───────────────────────────────────────
        register_rest_route( $ns, '/progress/(?P<id>[\d]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_kit' ],
                'permission_callback' => [ $this->auth, 'require_authenticated' ],
                'args'                => array_merge(
                    $this->user_id_arg(),
                    [ 'id' => [ 'type' => 'integer', 'required' => true, 'minimum' => 1 ] ]
                ),
            ],
        ] );

        // ── Create kit ─────────────────────────────────────────────
        register_rest_route( $ns, '/progress/kit', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_kit' ],
                'permission_callback' => [ $this->auth, 'require_authenticated' ],
                'args'                => $this->create_kit_args(),
            ],
        ] );

        // ── Update steps ────────────────────────────────────────────
        register_rest_route( $ns, '/progress/(?P<id>[\d]+)/steps', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'update_steps' ],
                'permission_callback' => [ $this->auth, 'require_authenticated' ],
                'args'                => $this->update_steps_args(),
            ],
        ] );

        // ── Add milestone image ─────────────────────────────────────
        register_rest_route( $ns, '/progress/(?P<id>[\d]+)/images', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'add_image' ],
                'permission_callback' => [ $this->auth, 'require_authenticated' ],
                'args'                => $this->add_image_args(),
            ],
        ] );

        // ── Mastery summary ─────────────────────────────────────────
        register_rest_route( $ns, '/mastery', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_mastery' ],
                'permission_callback' => [ $this->auth, 'require_authenticated' ],
                'args'                => $this->user_id_arg(),
            ],
        ] );

        // ── Force recalculate (admin) ────────────────────────────────
        register_rest_route( $ns, '/mastery/recalculate', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'recalculate_mastery' ],
                'permission_callback' => [ $this->auth, 'require_authenticated' ],
                'args'                => $this->user_id_arg(),
            ],
        ] );

        // ── Public level table ──────────────────────────────────────
        register_rest_route( $ns, '/levels', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_levels' ],
                'permission_callback' => '__return_true',   // public read
            ],
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // Handlers
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /verdant/v1/progress
     */
    public function get_profile( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $user_id = $this->auth->resolve_user_id( $request );
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $data = $this->maker_profile->get_profile_summary( $user_id );
        return rest_ensure_response( [ 'success' => true, 'data' => $data ] );
    }

    /**
     * GET /verdant/v1/progress/{id}
     */
    public function get_kit( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $user_id = $this->auth->resolve_user_id( $request );
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $result = $this->maker_profile->get_kit_detail( (int) $request['id'], $user_id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( [ 'success' => true, 'data' => $result ] );
    }

    /**
     * POST /verdant/v1/progress/kit
     */
    public function create_kit( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $user_id = $this->auth->resolve_user_id( $request );
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $result = $this->maker_profile->get_or_create_kit(
            $user_id,
            (string) $request->get_param( 'kit_id' ),
            (string) $request->get_param( 'kit_name' ),
            (int)    $request->get_param( 'difficulty' ),
            (int)    $request->get_param( 'total_steps' )
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response(
            [ 'success' => true, 'data' => $this->maker_profile->format_kit( $result ) ],
            201
        );
    }

    /**
     * POST /verdant/v1/progress/{id}/steps
     */
    public function update_steps( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $user_id = $this->auth->resolve_user_id( $request );
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $result = $this->maker_profile->update_progress(
            (int)    $request['id'],
            $user_id,
            (int)    $request->get_param( 'completed_steps' ),
            (string) ( $request->get_param( 'note' ) ?? '' )
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( [
            'success' => true,
            'message' => __( 'Progress updated.', 'verdant-stitch' ),
            'data'    => $this->maker_profile->format_kit( $result ),
        ] );
    }

    /**
     * POST /verdant/v1/progress/{id}/images
     */
    public function add_image( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $user_id = $this->auth->resolve_user_id( $request );
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $image_id = $this->maker_profile->add_milestone_image(
            (int)    $request['id'],
            $user_id,
            (string) $request->get_param( 'image_url' ),
            (int)    ( $request->get_param( 'step_number' ) ?? 0 ),
            (string) ( $request->get_param( 'caption' ) ?? '' )
        );

        if ( is_wp_error( $image_id ) ) {
            return $image_id;
        }

        return new WP_REST_Response(
            [
                'success'  => true,
                'message'  => __( 'Milestone image stored.', 'verdant-stitch' ),
                'image_id' => $image_id,
            ],
            201
        );
    }

    /**
     * GET /verdant/v1/mastery
     */
    public function get_mastery( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $user_id = $this->auth->resolve_user_id( $request );
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $mastery = $this->mastery_engine->get_cached_mastery( $user_id );
        $coupon  = $this->wc_bridge->get_user_coupon_info( $user_id );

        return rest_ensure_response( [
            'success' => true,
            'data'    => array_merge( $mastery, [ 'coupon' => $coupon ] ),
        ] );
    }

    /**
     * POST /verdant/v1/mastery/recalculate
     */
    public function recalculate_mastery( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $user_id = $this->auth->resolve_user_id( $request );
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $result = $this->mastery_engine->recalculate_user_mastery( $user_id );

        return rest_ensure_response( [
            'success' => true,
            'message' => __( 'Mastery score recalculated.', 'verdant-stitch' ),
            'data'    => $result,
        ] );
    }

    /**
     * GET /verdant/v1/levels  (public)
     */
    public function get_levels( WP_REST_Request $request ): WP_REST_Response {
        return rest_ensure_response( [
            'success' => true,
            'data'    => $this->mastery_engine->get_level_table(),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // Argument schemas
    // ─────────────────────────────────────────────────────────────

    private function user_id_arg(): array {
        return [
            'user_id' => [
                'type'              => 'integer',
                'minimum'           => 1,
                'sanitize_callback' => 'absint',
                'required'          => false,
                'description'       => __( 'Target user ID. Defaults to authenticated user. Admin-only override.', 'verdant-stitch' ),
            ],
        ];
    }

    private function create_kit_args(): array {
        return array_merge( $this->user_id_arg(), [
            'kit_id' => [
                'type'              => 'string',
                'required'          => true,
                'minLength'         => 1,
                'maxLength'         => 100,
                'sanitize_callback' => 'sanitize_text_field',
                'description'       => __( 'Unique kit/product identifier (e.g. SKU).', 'verdant-stitch' ),
            ],
            'kit_name' => [
                'type'              => 'string',
                'required'          => true,
                'minLength'         => 1,
                'maxLength'         => 255,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'difficulty' => [
                'type'              => 'integer',
                'required'          => false,
                'default'           => 1,
                'minimum'           => 1,
                'maximum'           => 4,
                'sanitize_callback' => 'absint',
                'description'       => __( '1=Beginner, 2=Intermediate, 3=Advanced, 4=Master', 'verdant-stitch' ),
            ],
            'total_steps' => [
                'type'              => 'integer',
                'required'          => false,
                'default'           => 10,
                'minimum'           => 1,
                'maximum'           => 100,
                'sanitize_callback' => 'absint',
            ],
        ] );
    }

    private function update_steps_args(): array {
        return array_merge( $this->user_id_arg(), [
            'id' => [
                'type'    => 'integer',
                'required'=> true,
                'minimum' => 1,
            ],
            'completed_steps' => [
                'type'              => 'integer',
                'required'          => true,
                'minimum'           => 0,
                'sanitize_callback' => 'absint',
                'description'       => __( 'Total steps completed so far (not a delta).', 'verdant-stitch' ),
            ],
            'note' => [
                'type'              => 'string',
                'required'          => false,
                'default'           => '',
                'maxLength'         => 1000,
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
        ] );
    }

    private function add_image_args(): array {
        return array_merge( $this->user_id_arg(), [
            'id' => [
                'type'    => 'integer',
                'required'=> true,
                'minimum' => 1,
            ],
            'image_url' => [
                'type'              => 'string',
                'required'          => true,
                'format'            => 'uri',
                'sanitize_callback' => 'esc_url_raw',
            ],
            'step_number' => [
                'type'              => 'integer',
                'required'          => false,
                'default'           => 0,
                'minimum'           => 0,
                'sanitize_callback' => 'absint',
            ],
            'caption' => [
                'type'              => 'string',
                'required'          => false,
                'default'           => '',
                'maxLength'         => 500,
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ] );
    }
}
