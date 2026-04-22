<?php
/**
 * API Tester template – interactive REST endpoint demo within the WP admin.
 *
 * @package VerdantStitch
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$api_base = rest_url( 'verdant/v1' );
?>
<div class="wrap verdant-wrap">
    <h1 class="verdant-title">🔬 Verdant Stitch — API Tester</h1>
    <p class="description">
        <?php
        printf(
            /* translators: %s: REST API base URL */
            esc_html__( 'Base URL: %s', 'verdant-stitch' ),
            '<code>' . esc_html( $api_base ) . '</code>'
        );
        ?>
    </p>
    <p class="description">
        <?php esc_html_e( 'Authentication: WordPress Application Passwords (Basic Auth). Add one at Users → Profile → Application Passwords.', 'verdant-stitch' ); ?>
    </p>

    <div class="verdant-tester-grid">

        <!-- ── GET Profile ─────────────────────────────────── -->
        <div class="verdant-test-card" id="test-get-profile">
            <h3>GET <code>/verdant/v1/progress</code></h3>
            <p><?php esc_html_e( 'Fetch your full maker profile and kit list.', 'verdant-stitch' ); ?></p>
            <label><?php esc_html_e( 'User ID (optional, admin only)', 'verdant-stitch' ); ?>
                <input type="number" id="gp-user-id" class="small-text" placeholder="<?php echo esc_attr( get_current_user_id() ); ?>" />
            </label>
            <button class="button button-primary verdant-run-btn" data-endpoint="get-profile">
                <?php esc_html_e( 'Send GET', 'verdant-stitch' ); ?>
            </button>
            <div class="verdant-response" id="resp-get-profile"></div>
        </div>

        <!-- ── POST Create Kit ─────────────────────────────── -->
        <div class="verdant-test-card" id="test-create-kit">
            <h3>POST <code>/verdant/v1/progress/kit</code></h3>
            <p><?php esc_html_e( 'Register a new embroidery kit.', 'verdant-stitch' ); ?></p>
            <label><?php esc_html_e( 'Kit ID (SKU)', 'verdant-stitch' ); ?>
                <input type="text" id="ck-kit-id" class="regular-text" value="VS-OCT-2024" />
            </label>
            <label><?php esc_html_e( 'Kit Name', 'verdant-stitch' ); ?>
                <input type="text" id="ck-kit-name" class="regular-text" value="October Wildflower Box" />
            </label>
            <label><?php esc_html_e( 'Difficulty (1–4)', 'verdant-stitch' ); ?>
                <select id="ck-difficulty">
                    <option value="1">1 – Beginner</option>
                    <option value="2">2 – Intermediate</option>
                    <option value="3" selected>3 – Advanced</option>
                    <option value="4">4 – Master</option>
                </select>
            </label>
            <label><?php esc_html_e( 'Total Steps', 'verdant-stitch' ); ?>
                <input type="number" id="ck-total-steps" class="small-text" value="10" min="1" max="100" />
            </label>
            <button class="button button-primary verdant-run-btn" data-endpoint="create-kit">
                <?php esc_html_e( 'Send POST', 'verdant-stitch' ); ?>
            </button>
            <div class="verdant-response" id="resp-create-kit"></div>
        </div>

        <!-- ── POST Update Steps ────────────────────────────── -->
        <div class="verdant-test-card" id="test-update-steps">
            <h3>POST <code>/verdant/v1/progress/{id}/steps</code></h3>
            <p><?php esc_html_e( 'Update step completion for a kit.', 'verdant-stitch' ); ?></p>
            <label><?php esc_html_e( 'Kit Row ID', 'verdant-stitch' ); ?>
                <input type="number" id="us-kit-id" class="small-text" value="1" min="1" />
            </label>
            <label><?php esc_html_e( 'Completed Steps', 'verdant-stitch' ); ?>
                <input type="number" id="us-steps" class="small-text" value="4" min="0" />
            </label>
            <label><?php esc_html_e( 'Note', 'verdant-stitch' ); ?>
                <input type="text" id="us-note" class="regular-text" value="Finished the stem work!" />
            </label>
            <button class="button button-primary verdant-run-btn" data-endpoint="update-steps">
                <?php esc_html_e( 'Send POST', 'verdant-stitch' ); ?>
            </button>
            <div class="verdant-response" id="resp-update-steps"></div>
        </div>

        <!-- ── POST Milestone Image ──────────────────────────── -->
        <div class="verdant-test-card" id="test-add-image">
            <h3>POST <code>/verdant/v1/progress/{id}/images</code></h3>
            <p><?php esc_html_e( 'Submit a milestone photo URL.', 'verdant-stitch' ); ?></p>
            <label><?php esc_html_e( 'Kit Row ID', 'verdant-stitch' ); ?>
                <input type="number" id="ai-kit-id" class="small-text" value="1" min="1" />
            </label>
            <label><?php esc_html_e( 'Image URL', 'verdant-stitch' ); ?>
                <input type="url" id="ai-url" class="large-text" value="https://example.com/my-progress.jpg" />
            </label>
            <label><?php esc_html_e( 'Step Number (0 = general)', 'verdant-stitch' ); ?>
                <input type="number" id="ai-step" class="small-text" value="4" min="0" />
            </label>
            <label><?php esc_html_e( 'Caption', 'verdant-stitch' ); ?>
                <input type="text" id="ai-caption" class="regular-text" value="Almost there!" />
            </label>
            <button class="button button-primary verdant-run-btn" data-endpoint="add-image">
                <?php esc_html_e( 'Send POST', 'verdant-stitch' ); ?>
            </button>
            <div class="verdant-response" id="resp-add-image"></div>
        </div>

        <!-- ── GET Mastery ──────────────────────────────────── -->
        <div class="verdant-test-card" id="test-get-mastery">
            <h3>GET <code>/verdant/v1/mastery</code></h3>
            <p><?php esc_html_e( 'Retrieve mastery score, level, and active discount coupon.', 'verdant-stitch' ); ?></p>
            <button class="button button-primary verdant-run-btn" data-endpoint="get-mastery">
                <?php esc_html_e( 'Send GET', 'verdant-stitch' ); ?>
            </button>
            <div class="verdant-response" id="resp-get-mastery"></div>
        </div>

        <!-- ── POST Recalculate ─────────────────────────────── -->
        <div class="verdant-test-card" id="test-recalculate">
            <h3>POST <code>/verdant/v1/mastery/recalculate</code></h3>
            <p><?php esc_html_e( 'Force a fresh mastery score calculation.', 'verdant-stitch' ); ?></p>
            <button class="button button-secondary verdant-run-btn" data-endpoint="recalculate">
                <?php esc_html_e( 'Recalculate', 'verdant-stitch' ); ?>
            </button>
            <div class="verdant-response" id="resp-recalculate"></div>
        </div>

        <!-- ── GET Levels ───────────────────────────────────── -->
        <div class="verdant-test-card" id="test-get-levels">
            <h3>GET <code>/verdant/v1/levels</code> <span class="verdant-public-badge">Public</span></h3>
            <p><?php esc_html_e( 'Retrieve the mastery tier table (no auth required).', 'verdant-stitch' ); ?></p>
            <button class="button verdant-run-btn" data-endpoint="get-levels">
                <?php esc_html_e( 'Send GET', 'verdant-stitch' ); ?>
            </button>
            <div class="verdant-response" id="resp-get-levels"></div>
        </div>

    </div><!-- .verdant-tester-grid -->
</div>
