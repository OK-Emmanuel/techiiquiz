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

        $sql[] = "CREATE TABLE {$this->table( 'import_logs' )} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            source_filename VARCHAR(255) NOT NULL,
            source_group VARCHAR(120) NULL,
            file_extension VARCHAR(10) NOT NULL,
            dry_run TINYINT(1) NOT NULL DEFAULT 1,
            upsert TINYINT(1) NOT NULL DEFAULT 0,
            created_count INT UNSIGNED NOT NULL DEFAULT 0,
            updated_count INT UNSIGNED NOT NULL DEFAULT 0,
            failed_count INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'success',
            error_count INT UNSIGNED NOT NULL DEFAULT 0,
            error_excerpt LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$this->table( 'classes' )} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            course_code VARCHAR(50) NOT NULL,
            description LONGTEXT NULL,
            workbook_url VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY course_code (course_code)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$this->table( 'class_instances' )} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            class_id BIGINT UNSIGNED NOT NULL,
            woocommerce_product_id BIGINT UNSIGNED NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            max_capacity INT UNSIGNED NOT NULL DEFAULT 12,
            current_enrollment INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY class_id (class_id),
            KEY woocommerce_product_id (woocommerce_product_id),
            KEY start_end (start_date, end_date)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$this->table( 'enrollments' )} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            class_instance_id BIGINT UNSIGNED NOT NULL,
            woocommerce_order_id BIGINT UNSIGNED NOT NULL,
            enrollment_date DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY uq_user_class_instance (user_id, class_instance_id),
            KEY user_id (user_id),
            KEY class_instance_id (class_instance_id),
            KEY woocommerce_order_id (woocommerce_order_id),
            KEY status (status)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$this->table( 'entitlements' )} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            enrollment_id BIGINT UNSIGNED NOT NULL,
            resource_type VARCHAR(20) NOT NULL,
            access_start DATE NOT NULL,
            access_end DATE NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY enrollment_id (enrollment_id),
            KEY resource_type (resource_type),
            KEY access_window (access_start, access_end),
            KEY enrollment_resource (enrollment_id, resource_type)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$this->table( 'provisioning_logs' )} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            woocommerce_order_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            class_instance_id BIGINT UNSIGNED NULL,
            action VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'success',
            message LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY woocommerce_order_id (woocommerce_order_id),
            KEY user_id (user_id),
            KEY class_instance_id (class_instance_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        foreach ( $sql as $statement ) {
            dbDelta( $statement );
        }
    }

    public function get_courses() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$this->table( 'courses' )} ORDER BY title ASC",
            ARRAY_A
        );
    }

    public function get_course( $course_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'courses' )} WHERE id = %d",
                $course_id
            ),
            ARRAY_A
        );
    }

    public function get_course_by_slug( $slug ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'courses' )} WHERE slug = %s",
                sanitize_title( $slug )
            ),
            ARRAY_A
        );
    }

    public function create_course( $slug, $title, $active ) {
        global $wpdb;

        return $wpdb->insert(
            $this->table( 'courses' ),
            array(
                'slug'   => sanitize_title( $slug ),
                'title'  => sanitize_text_field( $title ),
                'active' => $active ? 1 : 0,
            ),
            array( '%s', '%s', '%d' )
        );
    }

    public function update_course( $course_id, $slug, $title, $active ) {
        global $wpdb;

        return $wpdb->update(
            $this->table( 'courses' ),
            array(
                'slug'   => sanitize_title( $slug ),
                'title'  => sanitize_text_field( $title ),
                'active' => $active ? 1 : 0,
            ),
            array( 'id' => (int) $course_id ),
            array( '%s', '%s', '%d' ),
            array( '%d' )
        );
    }

    public function delete_course( $course_id ) {
        global $wpdb;
        return $wpdb->delete(
            $this->table( 'courses' ),
            array( 'id' => (int) $course_id ),
            array( '%d' )
        );
    }

    public function get_sets() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT s.*, c.title AS course_title
             FROM {$this->table( 'sets' )} s
             LEFT JOIN {$this->table( 'courses' )} c ON c.id = s.course_id
             ORDER BY s.id DESC",
            ARRAY_A
        );
    }

    public function get_set( $set_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'sets' )} WHERE id = %d",
                $set_id
            ),
            ARRAY_A
        );
    }

    public function get_set_by_identity( $course_id, $title, $day_label, $mode ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'sets' )}
                 WHERE course_id = %d AND title = %s AND day_label = %s AND mode = %s
                 LIMIT 1",
                $course_id,
                sanitize_text_field( $title ),
                sanitize_text_field( $day_label ),
                sanitize_key( $mode )
            ),
            ARRAY_A
        );
    }

    public function create_set( $data ) {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->table( 'sets' ),
            array(
                'course_id'      => (int) $data['course_id'],
                'day_label'      => sanitize_text_field( $data['day_label'] ),
                'mode'           => sanitize_key( $data['mode'] ),
                'title'          => sanitize_text_field( $data['title'] ),
                'question_count' => (int) $data['question_count'],
                'version'        => (int) $data['version'],
                'active'         => ! empty( $data['active'] ) ? 1 : 0,
            ),
            array( '%d', '%s', '%s', '%s', '%d', '%d', '%d' )
        );

        if ( ! $inserted ) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public function update_set( $set_id, $data ) {
        global $wpdb;

        return $wpdb->update(
            $this->table( 'sets' ),
            array(
                'course_id'      => (int) $data['course_id'],
                'day_label'      => sanitize_text_field( $data['day_label'] ),
                'mode'           => sanitize_key( $data['mode'] ),
                'title'          => sanitize_text_field( $data['title'] ),
                'question_count' => (int) $data['question_count'],
                'version'        => (int) $data['version'],
                'active'         => ! empty( $data['active'] ) ? 1 : 0,
            ),
            array( 'id' => (int) $set_id ),
            array( '%d', '%s', '%s', '%s', '%d', '%d', '%d' ),
            array( '%d' )
        );
    }

    public function delete_set( $set_id ) {
        global $wpdb;
        return $wpdb->delete(
            $this->table( 'sets' ),
            array( 'id' => (int) $set_id ),
            array( '%d' )
        );
    }

    public function get_questions_for_admin( $set_id ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'questions' )} WHERE set_id = %d ORDER BY display_order ASC",
                $set_id
            ),
            ARRAY_A
        );
    }

    public function count_questions_for_set( $set_id ) {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table( 'questions' )} WHERE set_id = %d",
                $set_id
            )
        );
    }

    public function get_questions_for_admin_paginated( $set_id, $page = 1, $per_page = 25 ) {
        global $wpdb;

        $page     = max( 1, (int) $page );
        $per_page = max( 1, (int) $per_page );
        $offset   = ( $page - 1 ) * $per_page;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'questions' )}
                 WHERE set_id = %d
                 ORDER BY display_order ASC
                 LIMIT %d OFFSET %d",
                $set_id,
                $per_page,
                $offset
            ),
            ARRAY_A
        );
    }

    public function get_choices_by_question( $question_id ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'choices' )} WHERE question_id = %d ORDER BY id ASC",
                $question_id
            ),
            ARRAY_A
        );
    }

    public function create_question( $data ) {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->table( 'questions' ),
            array(
                'set_id'        => (int) $data['set_id'],
                'question_type' => sanitize_key( $data['question_type'] ),
                'prompt'        => wp_kses_post( $data['prompt'] ),
                'prompt_format' => sanitize_key( $data['prompt_format'] ),
                'prompt_meta'   => isset( $data['prompt_meta'] ) ? wp_json_encode( $data['prompt_meta'] ) : null,
                'explanation'   => wp_kses_post( $data['explanation'] ),
                'display_order' => (int) $data['display_order'],
                'active'        => ! empty( $data['active'] ) ? 1 : 0,
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
        );

        if ( ! $inserted ) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public function update_question( $question_id, $data ) {
        global $wpdb;

        return $wpdb->update(
            $this->table( 'questions' ),
            array(
                'question_type' => sanitize_key( $data['question_type'] ),
                'prompt'        => wp_kses_post( $data['prompt'] ),
                'prompt_format' => sanitize_key( $data['prompt_format'] ),
                'explanation'   => wp_kses_post( $data['explanation'] ),
                'display_order' => (int) $data['display_order'],
                'active'        => ! empty( $data['active'] ) ? 1 : 0,
            ),
            array( 'id' => (int) $question_id ),
            array( '%s', '%s', '%s', '%s', '%d', '%d' ),
            array( '%d' )
        );
    }

    public function delete_question( $question_id ) {
        global $wpdb;
        $wpdb->delete(
            $this->table( 'choices' ),
            array( 'question_id' => (int) $question_id ),
            array( '%d' )
        );

        return $wpdb->delete(
            $this->table( 'questions' ),
            array( 'id' => (int) $question_id ),
            array( '%d' )
        );
    }

    public function replace_question_choices( $question_id, $choices, $correct_choice_key ) {
        global $wpdb;

        $wpdb->delete(
            $this->table( 'choices' ),
            array( 'question_id' => (int) $question_id ),
            array( '%d' )
        );

        foreach ( $choices as $key => $text ) {
            if ( '' === trim( (string) $text ) ) {
                continue;
            }

            $wpdb->insert(
                $this->table( 'choices' ),
                array(
                    'question_id' => (int) $question_id,
                    'choice_key'  => sanitize_text_field( $key ),
                    'choice_text' => wp_kses_post( $text ),
                    'is_correct'  => $key === $correct_choice_key ? 1 : 0,
                ),
                array( '%d', '%s', '%s', '%d' )
            );
        }
    }

    public function sync_set_question_count( $set_id ) {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table( 'questions' )} WHERE set_id = %d",
                $set_id
            )
        );

        $wpdb->update(
            $this->table( 'sets' ),
            array( 'question_count' => $count ),
            array( 'id' => (int) $set_id ),
            array( '%d' ),
            array( '%d' )
        );

        return $count;
    }

    public function get_set_questions( $set_id, $limit = 0 ) {
        global $wpdb;

        $set_id = (int) $set_id;
        $limit  = (int) $limit;

        $query = "SELECT * FROM {$this->table( 'questions' )} WHERE set_id = %d AND active = 1 ORDER BY display_order ASC";

        if ( $limit > 0 ) {
            $questions = $wpdb->get_results(
                $wpdb->prepare( $query . ' LIMIT %d', $set_id, $limit ),
                ARRAY_A
            );
        } else {
            $questions = $wpdb->get_results(
                $wpdb->prepare( $query, $set_id ),
                ARRAY_A
            );
        }

        if ( empty( $questions ) ) {
            return array();
        }

        $question_ids = array_map(
            static function ( $question ) {
                return (int) $question['id'];
            },
            $questions
        );

        $placeholders = implode( ',', array_fill( 0, count( $question_ids ), '%d' ) );
        $choices_sql  = "SELECT id, question_id, choice_key, choice_text, is_correct
                         FROM {$this->table( 'choices' )}
                         WHERE question_id IN ({$placeholders})
                         ORDER BY question_id ASC, id ASC";

        $choices = $wpdb->get_results(
            $wpdb->prepare( $choices_sql, ...$question_ids ),
            ARRAY_A
        );

        $choices_by_question = array();
        foreach ( $choices as $choice ) {
            $question_id = (int) $choice['question_id'];
            if ( ! isset( $choices_by_question[ $question_id ] ) ) {
                $choices_by_question[ $question_id ] = array();
            }
            $choices_by_question[ $question_id ][] = $choice;
        }

        foreach ( $questions as &$question ) {
            $question_id          = (int) $question['id'];
            $question['choices']  = isset( $choices_by_question[ $question_id ] ) ? $choices_by_question[ $question_id ] : array();
        }
        unset( $question );

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

    public function get_question_by_set_and_order( $set_id, $display_order ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'questions' )} WHERE set_id = %d AND display_order = %d LIMIT 1",
                $set_id,
                $display_order
            ),
            ARRAY_A
        );
    }

    public function get_next_display_order_for_set( $set_id ) {
        global $wpdb;

        $max_display_order = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(display_order) FROM {$this->table( 'questions' )} WHERE set_id = %d",
                $set_id
            )
        );

        return $max_display_order + 1;
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

    public function get_active_session( $user_id, $set_id, $mode ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'sessions' )}
                 WHERE user_id = %d AND set_id = %d AND mode = %s AND status = 'in_progress'
                 ORDER BY id DESC
                 LIMIT 1",
                (int) $user_id,
                (int) $set_id,
                sanitize_key( $mode )
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

    public function create_import_log( $data ) {
        global $wpdb;

        return $wpdb->insert(
            $this->table( 'import_logs' ),
            array(
                'user_id'         => (int) $data['user_id'],
                'source_filename' => sanitize_file_name( $data['source_filename'] ),
                'source_group'    => sanitize_text_field( $data['source_group'] ),
                'file_extension'  => sanitize_text_field( $data['file_extension'] ),
                'dry_run'         => ! empty( $data['dry_run'] ) ? 1 : 0,
                'upsert'          => ! empty( $data['upsert'] ) ? 1 : 0,
                'created_count'   => (int) $data['created_count'],
                'updated_count'   => (int) $data['updated_count'],
                'failed_count'    => (int) $data['failed_count'],
                'status'          => sanitize_key( $data['status'] ),
                'error_count'     => (int) $data['error_count'],
                'error_excerpt'   => isset( $data['error_excerpt'] ) ? wp_kses_post( $data['error_excerpt'] ) : '',
                'created_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s' )
        );
    }

    public function get_import_logs( $limit = 20 ) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'import_logs' )} ORDER BY id DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    public function get_class_instance_by_product_id( $product_id ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'class_instances' )} WHERE woocommerce_product_id = %d LIMIT 1",
                (int) $product_id
            ),
            ARRAY_A
        );
    }

    public function get_enrollment_by_user_and_instance( $user_id, $class_instance_id ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'enrollments' )} WHERE user_id = %d AND class_instance_id = %d LIMIT 1",
                (int) $user_id,
                (int) $class_instance_id
            ),
            ARRAY_A
        );
    }

    public function create_enrollment( $user_id, $class_instance_id, $woocommerce_order_id, $status = 'active' ) {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->table( 'enrollments' ),
            array(
                'user_id'              => (int) $user_id,
                'class_instance_id'    => (int) $class_instance_id,
                'woocommerce_order_id' => (int) $woocommerce_order_id,
                'enrollment_date'      => current_time( 'mysql' ),
                'status'               => sanitize_key( $status ),
            ),
            array( '%d', '%d', '%d', '%s', '%s' )
        );

        if ( ! $inserted ) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public function create_entitlement( $enrollment_id, $resource_type, $access_start, $access_end, $is_active = 1 ) {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->table( 'entitlements' ),
            array(
                'enrollment_id' => (int) $enrollment_id,
                'resource_type' => sanitize_key( $resource_type ),
                'access_start'  => sanitize_text_field( $access_start ),
                'access_end'    => sanitize_text_field( $access_end ),
                'is_active'     => (int) $is_active,
                'created_at'    => current_time( 'mysql' ),
                'updated_at'    => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
        );

        if ( ! $inserted ) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public function create_provisioning_log( $data ) {
        global $wpdb;

        return $wpdb->insert(
            $this->table( 'provisioning_logs' ),
            array(
                'woocommerce_order_id' => isset( $data['woocommerce_order_id'] ) ? (int) $data['woocommerce_order_id'] : 0,
                'user_id'              => isset( $data['user_id'] ) ? (int) $data['user_id'] : null,
                'class_instance_id'    => isset( $data['class_instance_id'] ) ? (int) $data['class_instance_id'] : null,
                'action'               => isset( $data['action'] ) ? sanitize_key( $data['action'] ) : 'provisioning',
                'status'               => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'success',
                'message'              => isset( $data['message'] ) ? sanitize_textarea_field( $data['message'] ) : '',
                'created_at'           => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
        );
    }

    public function get_booking_classes() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT c.*, COUNT(ci.id) AS instance_count
             FROM {$this->table( 'classes' )} c
             LEFT JOIN {$this->table( 'class_instances' )} ci ON ci.class_id = c.id
             GROUP BY c.id
             ORDER BY c.id DESC",
            ARRAY_A
        );
    }

    public function get_booking_class( $class_id ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'classes' )} WHERE id = %d",
                (int) $class_id
            ),
            ARRAY_A
        );
    }

    public function create_booking_class( $data ) {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->table( 'classes' ),
            array(
                'name'        => sanitize_text_field( $data['name'] ?? '' ),
                'course_code' => sanitize_text_field( $data['course_code'] ?? '' ),
                'description' => wp_kses_post( $data['description'] ?? '' ),
                'workbook_url'=> esc_url_raw( $data['workbook_url'] ?? '' ),
                'created_at'  => current_time( 'mysql' ),
                'updated_at'  => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( ! $inserted ) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public function update_booking_class( $class_id, $data ) {
        global $wpdb;

        return $wpdb->update(
            $this->table( 'classes' ),
            array(
                'name'         => sanitize_text_field( $data['name'] ?? '' ),
                'course_code'  => sanitize_text_field( $data['course_code'] ?? '' ),
                'description'  => wp_kses_post( $data['description'] ?? '' ),
                'workbook_url' => esc_url_raw( $data['workbook_url'] ?? '' ),
                'updated_at'   => current_time( 'mysql' ),
            ),
            array( 'id' => (int) $class_id ),
            array( '%s', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    public function delete_booking_class( $class_id ) {
        global $wpdb;

        return $wpdb->delete(
            $this->table( 'classes' ),
            array( 'id' => (int) $class_id ),
            array( '%d' )
        );
    }

    public function get_class_instances() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT ci.*, c.name AS class_name
             FROM {$this->table( 'class_instances' )} ci
             LEFT JOIN {$this->table( 'classes' )} c ON c.id = ci.class_id
             ORDER BY ci.start_date DESC, ci.id DESC",
            ARRAY_A
        );
    }

    public function get_class_instance( $instance_id ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'class_instances' )} WHERE id = %d",
                (int) $instance_id
            ),
            ARRAY_A
        );
    }

    public function create_class_instance( $data ) {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->table( 'class_instances' ),
            array(
                'class_id'               => (int) ( $data['class_id'] ?? 0 ),
                'woocommerce_product_id' => (int) ( $data['woocommerce_product_id'] ?? 0 ),
                'start_date'             => sanitize_text_field( $data['start_date'] ?? '' ),
                'end_date'               => sanitize_text_field( $data['end_date'] ?? '' ),
                'max_capacity'           => (int) ( $data['max_capacity'] ?? 12 ),
                'current_enrollment'     => (int) ( $data['current_enrollment'] ?? 0 ),
                'created_at'             => current_time( 'mysql' ),
                'updated_at'             => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
        );

        if ( ! $inserted ) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public function update_class_instance( $instance_id, $data ) {
        global $wpdb;

        return $wpdb->update(
            $this->table( 'class_instances' ),
            array(
                'class_id'               => (int) ( $data['class_id'] ?? 0 ),
                'woocommerce_product_id' => (int) ( $data['woocommerce_product_id'] ?? 0 ),
                'start_date'             => sanitize_text_field( $data['start_date'] ?? '' ),
                'end_date'               => sanitize_text_field( $data['end_date'] ?? '' ),
                'max_capacity'           => (int) ( $data['max_capacity'] ?? 12 ),
                'current_enrollment'     => (int) ( $data['current_enrollment'] ?? 0 ),
                'updated_at'             => current_time( 'mysql' ),
            ),
            array( 'id' => (int) $instance_id ),
            array( '%d', '%d', '%s', '%s', '%d', '%d', '%s' ),
            array( '%d' )
        );
    }

    public function delete_class_instance( $instance_id ) {
        global $wpdb;

        return $wpdb->delete(
            $this->table( 'class_instances' ),
            array( 'id' => (int) $instance_id ),
            array( '%d' )
        );
    }

    public function count_enrollments_for_instance( $instance_id ) {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table( 'enrollments' )} WHERE class_instance_id = %d",
                (int) $instance_id
            )
        );
    }

    public function get_provisioning_logs( $limit = 50 ) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pl.*, ci.start_date, ci.end_date
                 FROM {$this->table( 'provisioning_logs' )} pl
                 LEFT JOIN {$this->table( 'class_instances' )} ci ON ci.id = pl.class_instance_id
                 ORDER BY pl.id DESC
                 LIMIT %d",
                (int) $limit
            ),
            ARRAY_A
        );
    }

    public function get_booking_class_by_code( $course_code ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table( 'classes' )} WHERE course_code = %s LIMIT 1",
                sanitize_text_field( $course_code )
            ),
            ARRAY_A
        );
    }

    public function user_has_active_entitlement( $user_id, $resource_type, $class_id = 0 ) {
        global $wpdb;

        $user_id       = (int) $user_id;
        $resource_type = sanitize_key( $resource_type );
        $class_id      = (int) $class_id;

        if ( $user_id <= 0 || '' === $resource_type ) {
            return false;
        }

        $sql = "SELECT e.id
                FROM {$this->table( 'entitlements' )} e
                INNER JOIN {$this->table( 'enrollments' )} enr ON enr.id = e.enrollment_id
                INNER JOIN {$this->table( 'class_instances' )} ci ON ci.id = enr.class_instance_id
                WHERE enr.user_id = %d
                  AND e.resource_type = %s
                  AND e.is_active = 1
                  AND CURDATE() >= e.access_start
                  AND CURDATE() <= e.access_end";

        if ( $class_id > 0 ) {
            $sql .= ' AND ci.class_id = %d';
            $found_id = $wpdb->get_var( $wpdb->prepare( $sql . ' LIMIT 1', $user_id, $resource_type, $class_id ) );
        } else {
            $found_id = $wpdb->get_var( $wpdb->prepare( $sql . ' LIMIT 1', $user_id, $resource_type ) );
        }

        return ! empty( $found_id );
    }

    public function get_enrollment_report_rows( $class_instance_id = 0 ) {
        global $wpdb;

        $class_instance_id = (int) $class_instance_id;
        $users_table       = $wpdb->users;

        $sql = "SELECT
                    enr.id AS enrollment_id,
                    enr.user_id,
                    enr.class_instance_id,
                    enr.enrollment_date,
                    enr.status AS enrollment_status,
                    ci.start_date,
                    ci.end_date,
                    c.name AS class_name,
                    c.course_code,
                    u.user_email,
                    u.display_name,
                    MIN(e.access_start) AS access_start,
                    MAX(e.access_end) AS access_end
                FROM {$this->table( 'enrollments' )} enr
                INNER JOIN {$this->table( 'class_instances' )} ci ON ci.id = enr.class_instance_id
                INNER JOIN {$this->table( 'classes' )} c ON c.id = ci.class_id
                LEFT JOIN {$this->table( 'entitlements' )} e ON e.enrollment_id = enr.id
                LEFT JOIN {$users_table} u ON u.ID = enr.user_id";

        $args = array();
        if ( $class_instance_id > 0 ) {
            $sql    .= ' WHERE enr.class_instance_id = %d';
            $args[] = $class_instance_id;
        }

        $sql .= ' GROUP BY enr.id ORDER BY enr.enrollment_date DESC';

        if ( ! empty( $args ) ) {
            return $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );
        }

        return $wpdb->get_results( $sql, ARRAY_A );
    }

    public function get_user_enrollments( $user_id ) {
        global $wpdb;

        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            return array();
        }

        $sql = "SELECT
                    enr.id AS enrollment_id,
                    enr.status AS enrollment_status,
                    enr.enrollment_date,
                    enr.woocommerce_order_id,
                    ci.id AS class_instance_id,
                    ci.start_date,
                    ci.end_date,
                    ci.max_capacity,
                    ci.woocommerce_product_id,
                    c.id AS class_id,
                    c.name AS class_name,
                    c.course_code,
                    c.workbook_url,
                    MIN(e.access_start) AS access_start,
                    MAX(e.access_end) AS access_end,
                    MAX(e.is_active) AS has_active_entitlement
                FROM {$this->table( 'enrollments' )} enr
                INNER JOIN {$this->table( 'class_instances' )} ci ON ci.id = enr.class_instance_id
                INNER JOIN {$this->table( 'classes' )} c ON c.id = ci.class_id
                LEFT JOIN {$this->table( 'entitlements' )} e ON e.enrollment_id = enr.id
                WHERE enr.user_id = %d
                GROUP BY enr.id
                ORDER BY ci.start_date DESC, enr.enrollment_date DESC";

        return $wpdb->get_results( $wpdb->prepare( $sql, $user_id ), ARRAY_A );
    }

    public function cancel_enrollment_for_user( $enrollment_id, $user_id ) {
        global $wpdb;

        $enrollment_id = (int) $enrollment_id;
        $user_id       = (int) $user_id;

        if ( $enrollment_id <= 0 || $user_id <= 0 ) {
            return false;
        }

        $updated_enrollment = $wpdb->update(
            $this->table( 'enrollments' ),
            array( 'status' => 'cancelled' ),
            array(
                'id'      => $enrollment_id,
                'user_id' => $user_id,
            ),
            array( '%s' ),
            array( '%d', '%d' )
        );

        if ( false === $updated_enrollment ) {
            return false;
        }

        $updated_entitlements = $wpdb->update(
            $this->table( 'entitlements' ),
            array(
                'is_active'  => 0,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'enrollment_id' => $enrollment_id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        if ( false === $updated_entitlements ) {
            return false;
        }

        return true;
    }
}
