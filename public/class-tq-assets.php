<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TQ_Assets {
    public function register() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
    }

    public function enqueue() {
        wp_register_style(
            'tq-quiz',
            TQ_PLUGIN_URL . 'public/css/quiz.css',
            array(),
            TQ_VERSION
        );

        wp_register_script(
            'tq-quiz-app',
            TQ_PLUGIN_URL . 'public/js/quiz-app.js',
            array(),
            TQ_VERSION,
            true
        );

        wp_localize_script(
            'tq-quiz-app',
            'TQQuiz',
            array(
                'restBase' => esc_url_raw( rest_url( 'techiquiz/v1/' ) ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
            )
        );
    }

    public static function enqueue_quiz_assets() {
        // Tailwind CDN (play script — scans DOM dynamically, sufficient for plugin UI)
        if ( ! wp_script_is( 'tailwindcss', 'enqueued' ) ) {
            wp_enqueue_script( 'tailwindcss', 'https://cdn.tailwindcss.com', array(), null, false );
        }
        wp_enqueue_style( 'tq-quiz' );
        wp_enqueue_script( 'tq-quiz-app' );
    }
}
