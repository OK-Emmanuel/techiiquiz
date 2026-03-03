<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TQ_Shortcodes {
    public function register() {
        add_shortcode( 'tq_quiz', array( $this, 'render_quiz' ) );
    }

    public function render_quiz( $atts ) {
        $atts = shortcode_atts(
            array(
                'set'  => 0,
                'mode' => 'study',
            ),
            $atts,
            'tq_quiz'
        );

        $set_id = (int) $atts['set'];
        $mode   = sanitize_key( $atts['mode'] );

        if ( $set_id <= 0 ) {
            return '<div class="tq-error">Invalid quiz set.</div>';
        }

        if ( ! in_array( $mode, array( 'study', 'practice' ), true ) ) {
            return '<div class="tq-error">Invalid quiz mode.</div>';
        }

        TQ_Assets::enqueue_quiz_assets();

        ob_start();
        include TQ_PLUGIN_DIR . 'templates/quiz-shell.php';
        return ob_get_clean();
    }
}
