<?php
/**
 * Admin Dashboard template.
 *
 * @package VerdantStitch
 * @var array  $top_users   Top 20 makers by mastery score.
 * @var array  $thresholds  Mastery level threshold config.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap verdant-wrap">
    <h1 class="verdant-title">🌿 Verdant Stitch — Maker Dashboard</h1>

    <div class="verdant-cards">
        <div class="verdant-card">
            <h3><?php esc_html_e( 'Mastery Levels', 'verdant-stitch' ); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Level', 'verdant-stitch' ); ?></th>
                        <th><?php esc_html_e( 'Title', 'verdant-stitch' ); ?></th>
                        <th><?php esc_html_e( 'Min Score', 'verdant-stitch' ); ?></th>
                        <th><?php esc_html_e( 'Discount', 'verdant-stitch' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $thresholds as $lvl => $data ) : ?>
                    <tr>
                        <td><?php echo esc_html( $lvl ); ?></td>
                        <td><?php echo esc_html( $data['label'] ); ?></td>
                        <td><?php echo esc_html( number_format( $data['min_score'] ) ); ?></td>
                        <td><?php echo esc_html( $data['discount'] ); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="verdant-card">
            <h3><?php esc_html_e( 'Top Makers', 'verdant-stitch' ); ?></h3>
            <?php if ( empty( $top_users ) ) : ?>
                <p><?php esc_html_e( 'No maker data yet. Start completing kits!', 'verdant-stitch' ); ?></p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Name', 'verdant-stitch' ); ?></th>
                        <th><?php esc_html_e( 'Email', 'verdant-stitch' ); ?></th>
                        <th><?php esc_html_e( 'Score', 'verdant-stitch' ); ?></th>
                        <th><?php esc_html_e( 'Level', 'verdant-stitch' ); ?></th>
                        <th><?php esc_html_e( 'Completed', 'verdant-stitch' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $top_users as $u ) :
                        $lvl   = (int) $u->mastery_level;
                        $label = $thresholds[ $lvl ]['label'] ?? 'Seedling';
                    ?>
                    <tr>
                        <td><?php echo esc_html( $u->display_name ); ?></td>
                        <td><?php echo esc_html( $u->user_email ); ?></td>
                        <td><strong><?php echo esc_html( number_format( (float) $u->mastery_score, 1 ) ); ?></strong></td>
                        <td><span class="verdant-badge level-<?php echo esc_attr( $lvl ); ?>"><?php echo esc_html( $label ); ?></span></td>
                        <td><?php echo esc_html( $u->total_completed ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
