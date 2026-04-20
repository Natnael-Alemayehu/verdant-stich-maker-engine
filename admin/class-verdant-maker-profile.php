<?php
/**
 * Maker Profile: manages a customer's kit portfolio.
 * 
 * @package VerdantStitch
 */

if( !defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class VerdantStich_Maker_Profile
 * 
 * Handles:
 *      - Retriving / creating kits for a user.
 *      - Recording step-level progress updates.
 *      - Storing milestone image URLS.
 *      - Presenting a clean "profile" summary for the REST layer.
 */
class VerdantStitch_Maker_Profile {
    /**Valid status strings. */
    public const STATUSES = ['not_started', 'in_progress', 'completed'];

    /** Difficulty levels. */
    public const DIFFICULTIES = [
        1 => 'Beginner',
        2 => 'Intermediate', 
        3 => 'Advanced',
        4 => 'Master',
    ];
    public function __construct(
        private VerdantStitch_Database $db
    ){}

    // Profile Summary

    /**
     * Build a full summary for the REST GET endpoint.
     * 
     * @param int $user_id
     * @return array
     */
    public function get_profile_summary( int $user_id ): array {
        $user       = get_userdata($user_id);
        $mastery    = $this->db->get_mastery($user_id);
        $kits       = $this->db->get_kits_for_user($user_id);

        return [
            'user_id'       => $user_id,
            'display_name'  => $user ? $user->display_name : '',
            'email'         => $user ? $user->user_email   : '',
            'mastery'       => [
                'score'             => $mastery ? (float) $mastery->mastery_score: 0.0,
                'level'             => $mastery ? (int) $mastery->mastery_level: 0,
                'level_label'       => $this->get_level_label( $mastery ? (int) $mastery->mastery_level : 0 ),
                'total_completed'   => $mastery ? (int) $mastery->total_completed: 0,
                'last_completed'    => $mastery ? $mastery->last_calculated : null,
            ],
            'kits'  => array_map([$this, 'format_kit'], $kits),
        ];
    }
    // Kit Management
    /**
     * Get or create a kit row fro a user.
     * 
     * @param int       $user_id
     * @param string    $kit_id Unique product / subscription kit identifier.
     * @param string    $kit_name
     * @param int       $difficulty 1-4
     * @param object|WP_Error The kit row object or a WP_Error.
     */
    public function get_or_create_kit( int $user_id, string $kit_id, string $kit_name, int $difficulty = 1, int $total_steps=10 ): ojbect|WP_Error {
        $existing = $this->db->get_kit_by_kit_id($kit_id, $user_id);
        if ($existing) {
            return $existing;
        }

        if (! array_key_exists($difficulty, self::DIFFICULTIES)) {
            return new WP_Error('invalid_difficulty', __('Difficulty must be 1-4.', 'verdant-stitch'), ['status'=>400]);
        }

        $row_id = $this->db->insert_kit( [
            'user_id'           => $user_id,
            'kit_id'            => sanitize_text_field($kit_id),
            'kit_name'          => sanitize_text_field($kit_name),
            'difficulty'        => $difficulty,
            'total_steps'       => max(1, (int) $total_steps),
            'status'            => 'not_started',
            'completed_steps'   => 0,
            'created_at'        => current_time('mysql', true),
        ]);

        if( ! $row_id ) {
            return new WP_Error('db_insert_failed', __('Could not create kit entry.', 'verdant-stitch'), ['status'=> 500]);
        }
        
        return $this->db->get_kits($row_id, $user_id);
    }

    /**
     * Update step progress for a kit.
     * 
     * Validates ownership, step bounds, and status transisions.
     * 
     * @param int       $kit_row_id
     * @param int       $user_id
     * @param int       $completed_steps
     * @param string    $note
     * @return object|WP_Error  Updated kit row or error.
     */
    public function update_progress(int $kit_row_id, int $user_id, int $completed_steps, string $note=''): object|WP_Error{
        $kit = $this->db->get_kit($kit_row_id, $user_id);

        if (! $kit ) {
            return new WP_Error('kit_not_found', __('Kit not found or access denied.', 'verdant-stitch'), ['status' => 404]);
        }

        if ('completed' == $kit->status) {
            return new WP_Error('already_completed', __('This kit is already marked as completed.', 'verdant-stitch'),['status'=>409]);
        }

        $completed_steps = max( 0, min((int) $kit->total_steps, $completed_steps));

        $update_data = [
            'completed_steps' => $completed_steps,
            'updated_at'    => current_time('mysql', true),
        ];

        // Transition: not_started -> in_progress.
        if( 'not_started' === $kit->status && $completed_steps > 0 ) {
            $update_data['status']  = 'in_progress';
            $update_data['started_at'] = current_time('mysql', true);
        }
    }
}