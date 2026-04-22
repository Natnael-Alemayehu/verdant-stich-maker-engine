<?php
/**
 * Admin Settings template.
 *
 * @package VerdantStitch
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap verdant-wrap">
    <h1 class="verdant-title">🌿 Verdant Stitch — Settings</h1>
    <form method="post" action="options.php">
        <?php
        settings_fields( 'verdant_settings_group' );
        do_settings_sections( 'verdant-settings' );
        submit_button();
        ?>
    </form>

    <hr />

    <h2><?php esc_html_e( 'Mastery Level Thresholds', 'verdant-stitch' ); ?></h2>
    <p><?php esc_html_e( 'Edit the verdant_mastery_thresholds option directly via the Options table or a custom UI to adjust score requirements and discount percentages per tier.', 'verdant-stitch' ); ?></p>
    <?php
    $thresholds = get_option( 'verdant_mastery_thresholds', [] );
    ?>
    <table class="wp-list-table widefat fixed striped" style="max-width:600px">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Level', 'verdant-stitch' ); ?></th>
                <th><?php esc_html_e( 'Label', 'verdant-stitch' ); ?></th>
                <th><?php esc_html_e( 'Min Score', 'verdant-stitch' ); ?></th>
                <th><?php esc_html_e( 'Discount %', 'verdant-stitch' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $thresholds as $lvl => $data ) : ?>
            <tr>
                <td><?php echo esc_html( $lvl ); ?></td>
                <td><?php echo esc_html( $data['label'] ); ?></td>
                <td><?php echo esc_html( $data['min_score'] ); ?></td>
                <td><?php echo esc_html( $data['discount'] ); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <hr />
    <h2><?php esc_html_e( 'Plugin Info', 'verdant-stitch' ); ?></h2>
    <p><?php printf( esc_html__( 'Version: %s', 'verdant-stitch' ), esc_html( VERDANT_VERSION ) ); ?></p>
    <p><?php printf( esc_html__( 'DB Version: %s', 'verdant-stitch' ), esc_html( get_option( 'verdant_db_version', '—' ) ) ); ?></p>
    <p><?php printf( esc_html__( 'API Base: %s', 'verdant-stitch' ), '<code>' . esc_html( rest_url( 'verdant/v1' ) ) . '</code>' ); ?></p>
</div>
