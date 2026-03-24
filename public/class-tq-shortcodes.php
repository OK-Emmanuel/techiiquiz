<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TQ_Shortcodes {
    private $db;

    public function __construct( TQ_DB $db = null ) {
        $this->db = $db ? $db : new TQ_DB();
    }

    public function register() {
        add_shortcode( 'tq_quiz', array( $this, 'render_quiz' ) );
        add_shortcode( 'tq_workbook', array( $this, 'render_workbook' ) );
        add_shortcode( 'tq_landing_video', array( $this, 'render_landing_video' ) );
    }

    public function render_quiz( $atts ) {
        $atts = shortcode_atts(
            array(
                'set'      => 0,
                'mode'     => 'study',
                'class_id' => 0,
            ),
            $atts,
            'tq_quiz'
        );

        $set_id   = (int) $atts['set'];
        $mode     = sanitize_key( $atts['mode'] );
        $class_id = (int) $atts['class_id'];

        if ( $set_id <= 0 ) {
            return '<div class="tq-error">Invalid quiz set.</div>';
        }

        if ( ! in_array( $mode, array( 'study', 'practice' ), true ) ) {
            return '<div class="tq-error">Invalid quiz mode.</div>';
        }

        if ( ! is_user_logged_in() ) {
            return '<div class="tq-error">Please log in to access this quiz.</div>';
        }

        if ( ! current_user_can( 'manage_options' ) && ! $this->user_has_active_entitlement( get_current_user_id(), $class_id, 'quiz' ) ) {
            return '<div class="tq-error">Your access is not yet available or has expired.</div>';
        }

        TQ_Assets::enqueue_quiz_assets();

        ob_start();
        include TQ_PLUGIN_DIR . 'templates/quiz-shell.php';
        return ob_get_clean();
    }

    public function render_workbook( $atts ) {
        $atts = shortcode_atts(
            array(
                'class'    => '',
                'class_id' => 0,
                'label'    => 'Download Workbook',
            ),
            $atts,
            'tq_workbook'
        );

        if ( ! is_user_logged_in() ) {
            return '<div class="tq-error">Please log in to access this workbook.</div>';
        }

        $class_id = (int) $atts['class_id'];
        $class    = null;

        if ( $class_id > 0 ) {
            $class = $this->db->get_booking_class( $class_id );
        } elseif ( ! empty( $atts['class'] ) ) {
            $class = $this->db->get_booking_class_by_code( sanitize_text_field( $atts['class'] ) );
        }

        if ( ! is_array( $class ) || empty( $class['id'] ) ) {
            return '<div class="tq-error">Invalid class reference for workbook.</div>';
        }

        if ( ! current_user_can( 'manage_options' ) && ! $this->user_has_active_entitlement( get_current_user_id(), (int) $class['id'], 'workbook' ) ) {
            return '<div class="tq-error">Your access is not yet available or has expired.</div>';
        }

        $url = isset( $class['workbook_url'] ) ? esc_url( $class['workbook_url'] ) : '';
        if ( '' === $url ) {
            return '<div class="tq-error">Workbook is not available for this class yet.</div>';
        }

        $label = esc_html( $atts['label'] );
        return '<a class="button button-primary tq-workbook-link" href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
    }

    private function user_has_active_entitlement( $user_id, $class_id, $resource_type ) {
        return $this->db->user_has_active_entitlement( (int) $user_id, sanitize_key( $resource_type ), (int) $class_id );
    }

    public function render_landing_video( $atts ) {
        $atts = shortcode_atts(
            array(
                'video_url'       => '',
                'poster_url'      => '',
                'headline'        => 'Train with confidence before you go offshore.',
                'subheadline'     => 'Structured lessons, realistic drills, and guided review to keep your crew sharp.',
                'primary_label'   => 'Start Training',
                'primary_url'     => '',
                'secondary_label' => 'View Schedule',
                'secondary_url'   => '',
                'height'          => '72vh',
            ),
            $atts,
            'tq_landing_video'
        );

        TQ_Assets::enqueue_public_style();

        $video_url       = esc_url( $atts['video_url'] );
        $poster_url      = esc_url( $atts['poster_url'] );
        $headline        = esc_html( $atts['headline'] );
        $subheadline     = esc_html( $atts['subheadline'] );
        $primary_label   = esc_html( $atts['primary_label'] );
        $primary_url     = esc_url( $atts['primary_url'] );
        $secondary_label = esc_html( $atts['secondary_label'] );
        $secondary_url   = esc_url( $atts['secondary_url'] );
        $height          = sanitize_text_field( $atts['height'] );

        if ( '' === $video_url ) {
            return '<div class="tq-error">Landing video requires a valid video_url attribute.</div>';
        }

        $style = '';
        if ( preg_match( '/^[0-9.]+(vh|vw|px|rem|%)$/', $height ) ) {
            $style = ' style="--tq-hero-height:' . esc_attr( $height ) . ';"';
        }

        ob_start();
        ?>
        <section class="tq-hero-video"<?php echo $style; ?>>
            <video class="tq-hero-video__media" autoplay muted loop playsinline preload="metadata"<?php echo $poster_url ? ' poster="' . esc_attr( $poster_url ) . '"' : ''; ?>>
                <source src="<?php echo esc_url( $video_url ); ?>" type="video/mp4" />
            </video>
            <div class="tq-hero-video__overlay" aria-hidden="true"></div>
            <div class="tq-hero-video__grain" aria-hidden="true"></div>

            <div class="tq-hero-video__content">
                <p class="tq-hero-video__eyebrow">TechiQuiz Training</p>
                <h2 class="tq-hero-video__headline"><?php echo $headline; ?></h2>
                <p class="tq-hero-video__subheadline"><?php echo $subheadline; ?></p>
                <?php if ( '' !== $primary_url || '' !== $secondary_url ) : ?>
                    <div class="tq-hero-video__actions">
                        <?php if ( '' !== $primary_url ) : ?>
                            <a class="tq-hero-video__button tq-hero-video__button--primary" href="<?php echo esc_url( $primary_url ); ?>"><?php echo $primary_label; ?></a>
                        <?php endif; ?>
                        <?php if ( '' !== $secondary_url ) : ?>
                            <a class="tq-hero-video__button tq-hero-video__button--secondary" href="<?php echo esc_url( $secondary_url ); ?>"><?php echo $secondary_label; ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
