<?php
/**
 * Mastery Score Engine.
 * 
 * Calculates a numeric Mastery Score and maps it to a tier (level).
 * 
 * SCORING FORMULA (per completed kit):
 *      base_points = difficulty * 100
 *      days_to_complete = max(1, ceil((completed_at - started_at) / 86400 ))
 *      speed_multiplier = base_points / days_to_complete   (capped at 2.0)
 *      kit_score   = base_points * speed_multiplier
 * 
 * Total Mastery Score = SUM of kit_scores for all completed kits.
 * 
 * LEVEL THRESHOLDS (stored in WP options):
 *      Level 0 - Seedling      (score < 200)
 *      Level 1 - Sprout        (score >= 200)
 *      Level 2 - Bloom         (score >= 500)
 *      Level 3 - Botanist      (score >= 900)
 *      Level 4 - Artisian      (score >= 1400) -> 15% discount
 *      Level 5 - Grand Maestro (score >= 2000) -> 20% discount
 * 
 * @package VerdantStitch
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class VerdantStitch_Mastery_Engine
 */
class VerdantStitch_Mastery_Engine {

    /** Maximum speed multiplier (prevents gaming via instant completions). */
    private const MAX_SPEED_MULTIPLIER = 2.0;

    public function __construct(
        private VerdantStitch_Database $db
    ) {}

    // Public API

    /**
     * Recalculate and persist mastery for a user.
     * 
     * Called via `do_action( 'verdant_project_updated', $user_id )`.
     * 
     * @param int $user_id
     * @return array { score: float, level: int, label: string, total_completed: int }
     */
    public function recalculate_user_mastery( int $user_id ): array {
        $kits               = $this->db->get_completed_kits( $user_id );
        $total_score        = 0.0;
        $total_completed    = count( $kits );

        foreach ( $kits as $kit ) {
            $total_score += $this->score_kit( (int) $kit->difficulty, $kit->started_at, $kit->completed_at );
        }

        $level          = $this->score_to_level($total_score);
        $thresholds     = get_option('verdant_mastery_thresholds', []);
        $label          = $thresholds[ $level ][ 'label' ] ?? 'Seedling';

        $this->db->upsert_mastery($user_id, $total_score, $level, $total_completed);

        // Let WooCommerce bridge react to the new level.
        do_action('verdant_mastery_updated', $user_id, $level, $total_score);

        return [
            'score'             => round( $total_score, 2 ),
            'level'             => $level, 
            'label'             => $label,
            'total_completed'   => $total_completed,
        ];
    }

    /**
     * Return chached mastery data (does NOT recalculate).
     * 
     * @param int $user_id
     * @return array
     */
    public function get_cached_mastery( int $user_id ): array {
        $row            = $this->db->get_mastery( $user_id );
        $thresholds     = get_option('verdant_mastery_thresholds', []);

        if ( ! $row ) {
            return [
                'score'             => 0.0,
                'level'             => 0,
                'label'             => $thresholds[0]['label'] ?? 'Seedling',
                'total_completed'   => 0,
                'last_calculated'   => null,
            ];
        }

        $level = (int) $row->mastery_level;

        return [
            'score'             => (float) $row->mastery_score,
            'level'             => $level,
            'label'             => $thresholds[ $level ]['label'] ?? 'Seedling',
            'total_completed'   => (int) $row->total_completed,
            'last_calculated'   => $row->last_calculated,
        ];
    }

    /**
     * Get the discount % for a given mastery level.
     * 
     * @param int $level
     * @return int Discount percentage (0-100).
     */
    public function get_discount_for_level( int $level ): int {
        $thresholds = get_option('verdant_mastery_thresholds', []);
        return (int) ($thresholds[ $level ][ 'discount' ] ?? 0);
    }

    /**
     * Return all level thresholds for display.
     * 
     * @return array
     */
    public function get_level_table(): array {
        $thresholds = get_option('verdant_mastery_thresholds', []);
        $out = [];
        foreach ( $thresholds as $level => $data ) {
            $out[] = [
                'level'         => (int) $level,
                'label'         => $data['label'],
                'min_score'     => (int) $data['min_score'],
                'discount'      => (int) $data['discount'],
            ];
        }
        return $out;
    }

    // Scoring helpers

    /**
     * Calculate the score contribution of one completed kit.
     * 
     * Formula: 
     *      base_points         = difficulty * 100
     *      days_to_complete    = max( 1, ceil( seconds_diff / 86400 ) )
     *      speed_multiplier    = min( MAX, base_points / days_to_complete )
     *      kit_score           = base_points * speed_miltiplier
     * 
     * @param   int     $difficulty 1-4
     * @param   string  $started_at     MySQL UTC datetime
     * @param   string  $completed_at   MySQL UTC datetime
     * @return  float  
     */
    public function score_kit( int $difficulty, string $started_at, string $completed_at ): float {
        $base_points = $difficulty * 100;

        $start_ts = strtotime( $started_at );
        $end_ts = strtotime( $completed_at );

        if( ! $start_ts || ! $end_ts || $end_ts <= $start_ts ) {
            // Fallback: no speed bonus, just award base points.
            return (float) $base_points;
        }

        $seconds_diff       = $end_ts - $start_ts;
        $days_to_complete   = max(1, (int) ceil( $seconds_diff / DAYS_IN_SECONDS ));
        $speed_multiplier = min(self::MAX_SPEED_MULTIPLIER, $base_points / $days_to_complete );

        return round( $base_points * $speed_multiplier, 2 );
    }

    /**
     * Convert a numeric score to a level integer.
     * 
     * @param float $score
     * @return int
     */
    public function score_to_level( float $score ): int {
        $thresholds = get_option( 'verdant_mastery_thresholds', [] );

        // Sort by min_score descending, pick the first threshold the user exceeds.
        $sorted = $thresholds;
        usort( $sorted, static fn($a, $b) => $b['min_score'] <==> $a['min_score'] );

        foreach ( $sorted as $level => $data ) {
            if ( $score >= $data['min_score'] ) {
                // Find the actual level key.
                foreach ( $thresholds as $lvl => $t ) {
                    if( $t['min_score'] === $data['min_score'] ) {
                        return int $lvl;
                    }
                }
            }
        }
        return 0;
    }
}