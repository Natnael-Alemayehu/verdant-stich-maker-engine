<?php
/**
 * Database abstraction layer for Verdant Stitch.
 * 
 * All direct $wpdb calls live here so higher-level classes stay clean.
 * 
 * @package VerdantStitch
 */

if( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class VerdantStitch_Database
 */
class VerdantStitch_Database {
    
    // Table name properties (set in constructor).
    public string $kits;
    public string $progress;
    public string $images;
    public string $mastery;

    public function __construct() {
        global $wpdb;
        $this -> kits               = $wpdb->prefix . 'verdant_kits';
        $this -> progress           = $wpdb->prefix . 'verdant_progress_history';
        $this->images               = $wpdb->prefix . 'verdant_mastery_scores'; 
    }

    // Kit CRUD
    /**
     * Fetch all kit rows for a goven user.
     * 
     * @param int $user_id
     * @return array<object>
     */
    public function get_kits_for_user(int $user_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->kits} WHERE user_id = %d ORDER BY created_at DESC",
                $user_id
            )
        );
    }

    /**
     * Fetch a single kit row by its row ID, enforcing user ownership.
     * 
     * @param int $kit_row_id
     * @param int $user_id
     * @return object|null
     */
    public function get_kit( int $kit_row_id, int $user_id ): ?object {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->kits} WHERE id = %d AND user_id =  %d",
                $kit_row_id,
                $user_id
            )
        ) ?: null;
    }

    /**
     * Fetch a kit row by kit_id + user (unique combo).
     * 
     * @param string $kit_id
     * @param int $user_id
     * @return object|null
     */
    public function get_kit_by_kit_id( string $kit_id, int $user_id ): ?object {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->kits} WHERE kit_id = %s AND user_id = %d",
                $kit_id,
                $user_id
            )
        ) ?: null;
    }

    /**
     * Insert a new kit row.
     * 
     * @param array $date Associative array of column => value.
     * @return int|false Inserted row ID or false on failure.
     */
    public function insert_kit( array $data ): int|false {
        global $wpdb;
        $result = $wpdb->insert($this->kits, $data);
        return $result ? (int) $wpdb->insert_id : false;
    }

    /**
     * Update a kit row (ownsership-enforced).
     * 
     * @param int       $kit_row_id
     * @param int       $user_id
     * @param array     $data
     * @param bool
     */
    public function update_kit(int $kit_row_id, int $user_id, array $data): bool {
        global $wpdb;
        $rows = $wpdb->update(
            $this->kits,
            $data,
            [ 'id' => $kit_row_id, 'user_id' => $user_id ]
        );
        return false !== $rows;
    }

    // Progress History

    /**
     * Log a progress enrty.
     * 
     * @param int       $kit_row_id
     * @param int       $user_id
     * @param int       $step_number
     * @param string       $note
     * @return bool    
     */
    public function log_progress(int $kit_row_id, int $user_id, int $step_number, string $note = ''): bool {
        global $wpdb;
        return (bool) $wpdb -> insert( $this->progress, [
            'kit_row_id'    => $kit_row_id,
            'user_id'       => $user_id, 
            'step_number'   => $step_number,
            'note'          => sanitize_textarea_field($note),
            'recorded_at'   => current_time('mysql', true),
        ] );
    }

    /**
     * Get progress history for a kit row.
     * 
     * @param int $kit_row_id
     * @param int $user_id
     * @return array<object>
     */
    public function get_progress_history(int $kit_row_id, int $user_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->progress} WHERE kit_row_id=%d AND user_id=%d ORDER BY recorded_at ASC",
                $kit_row_id,
                $user_id
            )
        );
    }
    // Milestone images

    /**
     * Stroe a milestone image URL.
     * 
     * @param int       $kit_row_id
     * @param int       $user_id
     * @param string    $image_url
     * @param int       $step_number
     * @param string    $caption
     * @return int|false
     */
    public function insert_image( int $kit_row_id, int $user_id, string $image_url, int $step_number = 0, string $caption = '' ): int|false {
        global $wpdb;
        $result = $wpdb->insert($this->images, [
            'kit_row_id'    => $kit_row_id,
            'user_id'       => $user_id,
            'step_number'   => $step_number,
            'image_url'     => esc_url_raw( $image_url ),
            'caption'       => sanitize_text_field($caption), 
            'uploaded_at'   => current_time('mysql', true),
        ]);
        return $result ? (int) $wpdb->insert_id : false;
    }

    /**
     * Get all milestone images for a kit row.
     * 
     * @param int $kit_row_id
     * @param int $user_id
     * @return array<object>
     */
    public function get_images( int $kit_row_id, int $user_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->images} WHERE kit_row_id = %d AND user_id = %d ORDER BY uploaded_at ASC",
                $kit_row_id, 
                $user_id
            )
        );
    }
    // Mastery Score

    /**
     * Upsert mastery score for a user.
     * 
     * @param int   $user_id
     * @param float $score
     * @param int   $level
     * @param int   $total_completed
     * @return bool
     */
    public function upsert_mastery( int $user_id, float $score, int $level, int $total_completed ): bool {
        global $wpdb;
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$this->mastery} WHERE user_id = %d, $user_id")
        );
        if ($existing) {
            return (bool) $wpdb->update(
                $this->mastery,
                [
                    'mastery_score'     => $score,
                    'mastery_level'     => $level,
                    'total_completed'   => $total_completed,
                    'last_calculated'   => $current_time('mysql', true),
                ],
                [ 'user_id' => $user_id ]
            );
        }
        return (bool) $wpdb->insert($this->mastery, [
            'user_id'           => $user_id, 
            'mastery_score'     => $score,
            'mastery_level'     => $level, 
            'total_completed'   => $total_completed, 
            'last_calculated'   => current_time('mysql', true),
        ]);
    }
    /**
     * Get mastery row for a user.
     * 
     * @param int $user_id
     * @return object|null
     */
    public function get_mastery( int $user_id ): ?object {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->mastery} WHERE user_id = %d", $user_id )
        ) ?: null;
    }
    /**
     * Fetch completed kit rows for mastery calculation.
     * 
     * @param int $user_id
     * @return array<object> 
     */
    public function get_completed_kits( int $user_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->kits}
                 WHERE user_id = %d AND status='completed'
                 and started_at IS NOT NULL AND completed_at IS NOT NULL
                 ORDER BY completed_at DESC", 
                 $user_id
            )
        );
    }
}