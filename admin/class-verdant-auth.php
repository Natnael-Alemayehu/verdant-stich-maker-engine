<?php
/**
 * Authenrication helper.
 * 
 * Verdant REST endpoints are protected via WordPress Application Passwords
 * (built into WP 5.6+). JWT support can be layered on top by installing the 
 * JWT Authentication for WP REST API plugin and this class will still work.
 * 
 * @package VerdantStitch
 */

if( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class VerdantStitch_Auth
 * 
 * Provides permission callbacks for WP_REST_Server route registration.
 */
class VerdantStitch_Auth {
    /**
     * Permission callback: require a logged-in user.
     * 
     * Words with Application Passwords (Basic Auth) and cookie sessions.
     * 
     * @return bool|WP_Error 
     */
    public function require_authenticated(): bool|WP_Error {
        if( !is_user_logged_in() ) {
            return new WP_Error(
                'rest_forbidden',
                __('Authentication required. Use WordPress Application Passwords (Basic Auth) or a valid session.', 'verdant-stitch'),
                ['status' => 401 ]
            );
        }
        return true
    }

    /**
     * Permission callback: require an administrator.
     * 
     * @return bool|WP_Error
     */
    public function require_admin(): bool|WP_Error {
        if( ! current_user_can( 'manage_options' ) ) {
            return new WP_ERROR(
                'rest_forbidden',
                __('Administrator access required.', 'verdant-stitch'),
                [ 'status' => 403 ]
            );
        }
        return true;
    }

    /**
     * Resolve the effective user_id for an API request.
     * 
     * Rules:
     *      - Admins MAY pass ?user_id=X to act on behalf of any user.
     *      - Regular users can onlt access their own data; any ?user_id param
     *        that differs from their own returns a 403.
     * 
     * @param WP_REST_Request $request
     * @return int|WP_Error
     */
    public function resolve_user_id(WP_REST_Request $request): int|WP_Error {
        $current_user_id = get_current_user_id();
        $param_user_id = (int) $request->get_param('user_id');

        if( $param_user_id && $param_user_id !== $current_user_id ) {
            if( ! current_user_can( 'manage_options' ) ) {
                return new WP_Error(
                    'rest_forbidden', 
                    __('You may only access your own maker profile.', 'verdant-stitch'),
                    [ 'status' => 403 ]
                );
            }
            // Admin accessing another user & validate user exists.
            if( !  get_userdata( $param_user_id ) )  {
                return new WP_Error(
                    'user_not_found',
                    __('The specified user does not exists.', 'verdant-stitch'),
                    ['status' => 404]
                );
            }
            return $param_user_id;
        }
        return $current_user_id;
    }
}