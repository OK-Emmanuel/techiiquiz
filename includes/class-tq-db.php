<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TQ_DB {
    public function table( $suffix ) {
        global $wpdb;
        return $wpdb->prefix . 'tq_' . $suffix;
    }

    public function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = array();

        $sql[] = "CREATE TABLE {$this->table( 'courses' )} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(100) NOT NULL,
            title VARCHAR(200) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$this->table( 'sets' )} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id BIGINT UNSIGNED NOT NULL,
            day_label VARCHAR(80) NOT NULL,
            mode VARCHAR(30) NOT NULL,
            title VARCHAR(200) NOT NULL,
            question_count INT UNSIGNED NOT NULL DEFAULT 0,
            version INT UNSIGNED NOT NULL DEFAULT 1,
            active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY course_id (course_id),
            KEY mode (mode)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$this->table( 'questions' )} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            set_id BIGINT UNSIGNED NOT NULL,
            question_type VARCHAR(30) NOT NULL DEFAULT 'single_choice',
            prompt LONGTEXT NOT NULL,
            prompt_format VARCHAR(20) NOT NULL DEFAULT 'plain',
            prompt_meta LONGTEXT NULL,
            explanation LONGTEXT NULL,
            display_order INT UNSIGNED NOT NULL DEFAULT 1,
            active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY set_id (set_id),
            KEY display_order (display_order)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$this->table( 'choices' )} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id BIGINT UNSIGNED NOT NULL,
            choice_key VARCHAR(10) NOT NULL,
            choice_text LONGTEXT NOT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY question_id (question_id)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$this->table( 'sessions' )} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            set_id BIGINT UNSIGNED NOT NULL,
            mode VARCHAR(30) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'in_progress',
            started_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            score_percent DECIMAL(5,2) NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY set_id (set_id),
            KEY status (status)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$this->table( 'session_answers' )} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT UNSIGNED NOT NULL,
            question_id BIGINT UNSIGNED NOT NULL,
            selected_choice_id BIGINT UNSIGNED NOT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            attempt_no INT UNSIGNED NOT NULL DEFAULT 1,
            answered_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY question_id (question_id)
        ) {$charset_collate};";

        foreach ( $sql as $statement ) {
            dbDelta( $statement );
        }
    }

    public function get_set_questions( $set_id ) {
        global $wpdb;

        $questions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'questions' )} WHERE set_id = %d AND active = 1 ORDER BY display_order ASC",
                $set_id
            ),
            ARRAY_A
        );

        foreach ( $questions as &$question ) {
            $question['choices'] = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, choice_key, choice_text, is_correct FROM {$this->table( 'choices' )} WHERE question_id = %d ORDER BY id ASC",
                    (int) $question['id']
                ),
                ARRAY_A
            );
        }

        return $questions;
    }

    public function get_question_by_id( $question_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'questions' )} WHERE id = %d",
                $question_id
            ),
            ARRAY_A
        );
    }

    public function get_choice_by_id( $choice_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'choices' )} WHERE id = %d",
                $choice_id
            ),
            ARRAY_A
        );
    }

    public function get_correct_choice_id( $question_id ) {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table( 'choices' )} WHERE question_id = %d AND is_correct = 1 LIMIT 1",
                $question_id
            )
        );
    }

    public function create_session( $user_id, $set_id, $mode ) {
        global $wpdb;

        $wpdb->insert(
            $this->table( 'sessions' ),
            array(
                'user_id'    => (int) $user_id,
                'set_id'     => (int) $set_id,
                'mode'       => sanitize_text_field( $mode ),
                'status'     => 'in_progress',
                'started_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s' )
        );

        return (int) $wpdb->insert_id;
    }

    public function get_session( $session_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'sessions' )} WHERE id = %d",
                $session_id
            ),
            ARRAY_A
        );
    }

    public function get_last_attempt_number( $session_id, $question_id ) {
        global $wpdb;

        $attempt = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(attempt_no) FROM {$this->table( 'session_answers' )} WHERE session_id = %d AND question_id = %d",
                $session_id,
                $question_id
            )
        );

        return $attempt ? (int) $attempt : 0;
    }

    public function store_answer( $session_id, $question_id, $choice_id, $is_correct, $attempt_no ) {
        global $wpdb;

        $wpdb->insert(
            $this->table( 'session_answers' ),
            array(
                'session_id'         => (int) $session_id,
                'question_id'        => (int) $question_id,
                'selected_choice_id' => (int) $choice_id,
                'is_correct'         => (int) $is_correct,
                'attempt_no'         => (int) $attempt_no,
                'answered_at'        => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%d', '%d', '%s' )
        );
    }

    public function upsert_practice_answer( $session_id, $question_id, $choice_id, $is_correct ) {
        global $wpdb;

        $existing_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table( 'session_answers' )} WHERE session_id = %d AND question_id = %d LIMIT 1",
                $session_id,
                $question_id
            )
        );

        if ( $existing_id > 0 ) {
            $wpdb->update(
                $this->table( 'session_answers' ),
                array(
                    'selected_choice_id' => (int) $choice_id,
                    'is_correct'         => (int) $is_correct,
                    'answered_at'        => current_time( 'mysql' ),
                ),
                array( 'id' => $existing_id ),
                array( '%d', '%d', '%s' ),
                array( '%d' )
            );
            return;
        }

        $this->store_answer( $session_id, $question_id, $choice_id, $is_correct, 1 );
    }

    public function complete_session( $session_id, $score_percent ) {
        global $wpdb;

        $wpdb->update(
            $this->table( 'sessions' ),
            array(
                'status'       => 'completed',
                'completed_at' => current_time( 'mysql' ),
                'score_percent'=> $score_percent,
            ),
            array( 'id' => (int) $session_id ),
            array( '%s', '%s', '%f' ),
            array( '%d' )
        );
    }

    public function get_session_answers( $session_id ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'session_answers' )} WHERE session_id = %d",
                $session_id
            ),
            ARRAY_A
        );
    }
}
