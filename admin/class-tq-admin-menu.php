<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TQ_Admin_Menu {
    private $db;
    private $import_service;

    public function __construct( TQ_DB $db ) {
        $this->db             = $db;
        $this->import_service = new TQ_Import_Service( $db );
    }

    public function register() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_post_tq_save_course', array( $this, 'save_course' ) );
        add_action( 'admin_post_tq_delete_course', array( $this, 'delete_course' ) );
        add_action( 'admin_post_tq_save_set', array( $this, 'save_set' ) );
        add_action( 'admin_post_tq_delete_set', array( $this, 'delete_set' ) );
        add_action( 'admin_post_tq_save_question', array( $this, 'save_question' ) );
        add_action( 'admin_post_tq_delete_question', array( $this, 'delete_question' ) );
        add_action( 'admin_post_tq_run_import', array( $this, 'run_import' ) );
    }

    public function add_menu() {
        add_menu_page(
            'TechiQuiz',
            'TechiQuiz',
            'manage_options',
            'tq-courses',
            array( $this, 'render_courses_page' ),
            'dashicons-welcome-learn-more',
            26
        );

        add_submenu_page(
            'tq-courses',
            'Courses',
            'Courses',
            'manage_options',
            'tq-courses',
            array( $this, 'render_courses_page' )
        );

        add_submenu_page(
            'tq-courses',
            'Sets',
            'Sets',
            'manage_options',
            'tq-sets',
            array( $this, 'render_sets_page' )
        );

        add_submenu_page(
            'tq-courses',
            'Question Bank',
            'Question Bank',
            'manage_options',
            'tq-questions',
            array( $this, 'render_questions_page' )
        );

        add_submenu_page(
            'tq-courses',
            'Importer',
            'Importer',
            'manage_options',
            'tq-importer',
            array( $this, 'render_importer_page' )
        );
    }

    public function enqueue_assets( $hook ) {
        if ( false === strpos( $hook, 'tq-' ) ) {
            return;
        }

        wp_enqueue_style(
            'tq-admin',
            TQ_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            TQ_VERSION
        );
    }

    public function render_courses_page() {
        $this->assert_admin();

        $courses      = $this->db->get_courses();
        $editing_id   = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
        $editing_data = $editing_id ? $this->db->get_course( $editing_id ) : null;

        echo '<div class="wrap tq-admin">';
        echo '<h1 class="tq-title">TechiQuiz Courses</h1>';
        $this->render_notice();

        echo '<div class="tq-grid">';
        echo '<div class="tq-card">';
        echo '<h2>' . ( $editing_data ? 'Edit Course' : 'Add Course' ) . '</h2>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'tq_save_course' );
        echo '<input type="hidden" name="action" value="tq_save_course" />';
        echo '<input type="hidden" name="course_id" value="' . esc_attr( $editing_id ) . '" />';
        echo '<p><label>Slug</label><input class="regular-text" name="slug" required value="' . esc_attr( $editing_data['slug'] ?? '' ) . '" /></p>';
        echo '<p><label>Title</label><input class="regular-text" name="title" required value="' . esc_attr( $editing_data['title'] ?? '' ) . '" /></p>';
        echo '<p><label><input type="checkbox" name="active" value="1" ' . checked( isset( $editing_data['active'] ) ? (int) $editing_data['active'] : 1, 1, false ) . ' /> Active</label></p>';
        submit_button( $editing_data ? 'Update Course' : 'Create Course', 'primary tq-btn-primary' );
        echo '</form>';
        echo '</div>';

        echo '<div class="tq-card">';
        echo '<h2>Existing Courses</h2>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Slug</th><th>Title</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        foreach ( $courses as $course ) {
            $edit_link = add_query_arg(
                array(
                    'page' => 'tq-courses',
                    'edit' => (int) $course['id'],
                ),
                admin_url( 'admin.php' )
            );

            echo '<tr>';
            echo '<td>' . esc_html( $course['id'] ) . '</td>';
            echo '<td>' . esc_html( $course['slug'] ) . '</td>';
            echo '<td>' . esc_html( $course['title'] ) . '</td>';
            echo '<td>' . ( (int) $course['active'] === 1 ? 'Active' : 'Inactive' ) . '</td>';
            echo '<td><a class="button button-small" href="' . esc_url( $edit_link ) . '">Edit</a> ';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;">';
            wp_nonce_field( 'tq_delete_course_' . (int) $course['id'] );
            echo '<input type="hidden" name="action" value="tq_delete_course" />';
            echo '<input type="hidden" name="course_id" value="' . esc_attr( $course['id'] ) . '" />';
            echo '<button class="button button-small" onclick="return confirm(\'Delete this course?\')">Delete</button>';
            echo '</form></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public function render_sets_page() {
        $this->assert_admin();

        $courses      = $this->db->get_courses();
        $sets         = $this->db->get_sets();
        $editing_id   = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
        $editing_data = $editing_id ? $this->db->get_set( $editing_id ) : null;

        echo '<div class="wrap tq-admin">';
        echo '<h1 class="tq-title">TechiQuiz Sets</h1>';
        $this->render_notice();

        echo '<div class="tq-grid">';
        echo '<div class="tq-card">';
        echo '<h2>' . ( $editing_data ? 'Edit Set' : 'Add Set' ) . '</h2>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'tq_save_set' );
        echo '<input type="hidden" name="action" value="tq_save_set" />';
        echo '<input type="hidden" name="set_id" value="' . esc_attr( $editing_id ) . '" />';
        echo '<p><label>Course</label><select name="course_id" required>';
        echo '<option value="">Select course</option>';
        foreach ( $courses as $course ) {
            echo '<option value="' . esc_attr( $course['id'] ) . '" ' . selected( (int) ( $editing_data['course_id'] ?? 0 ), (int) $course['id'], false ) . '>' . esc_html( $course['title'] ) . '</option>';
        }
        echo '</select></p>';
        echo '<p><label>Title</label><input class="regular-text" name="title" required value="' . esc_attr( $editing_data['title'] ?? '' ) . '" /></p>';
        echo '<p><label>Day Label</label><input class="regular-text" name="day_label" required value="' . esc_attr( $editing_data['day_label'] ?? '' ) . '" /></p>';
        echo '<p><label>Mode</label><select name="mode"><option value="study" ' . selected( $editing_data['mode'] ?? 'study', 'study', false ) . '>Study</option><option value="practice" ' . selected( $editing_data['mode'] ?? 'study', 'practice', false ) . '>Practice</option></select></p>';
        echo '<p><label>Version</label><input type="number" min="1" name="version" value="' . esc_attr( $editing_data['version'] ?? 1 ) . '" /></p>';
        echo '<p><label><input type="checkbox" name="active" value="1" ' . checked( isset( $editing_data['active'] ) ? (int) $editing_data['active'] : 1, 1, false ) . ' /> Active</label></p>';
        submit_button( $editing_data ? 'Update Set' : 'Create Set', 'primary tq-btn-primary' );
        echo '</form>';
        echo '</div>';

        echo '<div class="tq-card">';
        echo '<h2>Existing Sets</h2>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Course</th><th>Title</th><th>Day</th><th>Mode</th><th>Questions</th><th>Actions</th></tr></thead><tbody>';
        foreach ( $sets as $set ) {
            $edit_link = add_query_arg(
                array(
                    'page' => 'tq-sets',
                    'edit' => (int) $set['id'],
                ),
                admin_url( 'admin.php' )
            );
            $questions_link = add_query_arg(
                array(
                    'page'   => 'tq-questions',
                    'set_id' => (int) $set['id'],
                ),
                admin_url( 'admin.php' )
            );

            echo '<tr>';
            echo '<td>' . esc_html( $set['id'] ) . '</td>';
            echo '<td>' . esc_html( $set['course_title'] ?? 'N/A' ) . '</td>';
            echo '<td>' . esc_html( $set['title'] ) . '</td>';
            echo '<td>' . esc_html( $set['day_label'] ) . '</td>';
            echo '<td>' . esc_html( ucfirst( $set['mode'] ) ) . '</td>';
            echo '<td>' . esc_html( $set['question_count'] ) . '</td>';
            echo '<td><a class="button button-small" href="' . esc_url( $edit_link ) . '">Edit</a> ';
            echo '<a class="button button-small" href="' . esc_url( $questions_link ) . '">Questions</a> ';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;">';
            wp_nonce_field( 'tq_delete_set_' . (int) $set['id'] );
            echo '<input type="hidden" name="action" value="tq_delete_set" />';
            echo '<input type="hidden" name="set_id" value="' . esc_attr( $set['id'] ) . '" />';
            echo '<button class="button button-small" onclick="return confirm(\'Delete this set?\')">Delete</button>';
            echo '</form></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public function render_questions_page() {
        $this->assert_admin();

        $set_id = isset( $_GET['set_id'] ) ? absint( wp_unslash( $_GET['set_id'] ) ) : 0;
        $set    = $set_id ? $this->db->get_set( $set_id ) : null;
        $sets   = $this->db->get_sets();

        if ( ! $set && ! empty( $sets ) ) {
            $set_id = (int) $sets[0]['id'];
            $set    = $this->db->get_set( $set_id );
        }

        $questions = $set_id ? $this->db->get_questions_for_admin( $set_id ) : array();

        $editing_id      = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
        $editing_data    = $editing_id ? $this->db->get_question_by_id( $editing_id ) : null;
        $editing_choices = $editing_id ? $this->db->get_choices_by_question( $editing_id ) : array();

        $choice_map = array(
            'A' => '',
            'B' => '',
            'C' => '',
            'D' => '',
        );
        $correct_key = 'A';

        foreach ( $editing_choices as $choice ) {
            $choice_map[ $choice['choice_key'] ] = $choice['choice_text'];
            if ( (int) $choice['is_correct'] === 1 ) {
                $correct_key = $choice['choice_key'];
            }
        }

        echo '<div class="wrap tq-admin">';
        echo '<h1 class="tq-title">TechiQuiz Question Bank</h1>';
        $this->render_notice();

        echo '<p><label>Set:</label> <select onchange="if(this.value){window.location=this.value}">';
        foreach ( $sets as $entry ) {
            $target = add_query_arg(
                array(
                    'page'   => 'tq-questions',
                    'set_id' => (int) $entry['id'],
                ),
                admin_url( 'admin.php' )
            );
            echo '<option value="' . esc_url( $target ) . '" ' . selected( (int) $entry['id'], (int) $set_id, false ) . '>' . esc_html( $entry['title'] . ' (' . ucfirst( $entry['mode'] ) . ')' ) . '</option>';
        }
        echo '</select></p>';

        if ( ! $set_id ) {
            echo '<p>Create a set first.</p></div>';
            return;
        }

        echo '<div class="tq-grid">';
        echo '<div class="tq-card">';
        echo '<h2>' . ( $editing_data ? 'Edit Question' : 'Add Question' ) . '</h2>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'tq_save_question' );
        echo '<input type="hidden" name="action" value="tq_save_question" />';
        echo '<input type="hidden" name="set_id" value="' . esc_attr( $set_id ) . '" />';
        echo '<input type="hidden" name="question_id" value="' . esc_attr( $editing_id ) . '" />';

        echo '<p><label>Question Type</label><select name="question_type"><option value="single_choice" ' . selected( $editing_data['question_type'] ?? 'single_choice', 'single_choice', false ) . '>Single Choice</option><option value="objective_math" ' . selected( $editing_data['question_type'] ?? 'single_choice', 'objective_math', false ) . '>Objective Math</option></select></p>';
        echo '<p><label>Prompt Format</label><select name="prompt_format"><option value="plain" ' . selected( $editing_data['prompt_format'] ?? 'plain', 'plain', false ) . '>Plain</option><option value="mixed" ' . selected( $editing_data['prompt_format'] ?? 'plain', 'mixed', false ) . '>Mixed</option><option value="latex" ' . selected( $editing_data['prompt_format'] ?? 'plain', 'latex', false ) . '>LaTeX</option></select></p>';
        echo '<p><label>Prompt</label><textarea name="prompt" rows="5" class="large-text" required>' . esc_textarea( $editing_data['prompt'] ?? '' ) . '</textarea></p>';
        echo '<p><label>Explanation (optional)</label><textarea name="explanation" rows="3" class="large-text">' . esc_textarea( $editing_data['explanation'] ?? '' ) . '</textarea></p>';
        echo '<p><label>Display Order</label><input type="number" min="1" name="display_order" value="' . esc_attr( $editing_data['display_order'] ?? 1 ) . '" /></p>';

        echo '<h3>Choices</h3>';
        foreach ( $choice_map as $key => $value ) {
            echo '<p><label>Choice ' . esc_html( $key ) . '</label><input class="large-text" name="choice_' . esc_attr( strtolower( $key ) ) . '" required value="' . esc_attr( $value ) . '" /></p>';
        }
        echo '<p><label>Correct Choice</label><select name="correct_choice">';
        foreach ( array( 'A', 'B', 'C', 'D' ) as $letter ) {
            echo '<option value="' . esc_attr( $letter ) . '" ' . selected( $correct_key, $letter, false ) . '>' . esc_html( $letter ) . '</option>';
        }
        echo '</select></p>';
        echo '<p><label><input type="checkbox" name="active" value="1" ' . checked( isset( $editing_data['active'] ) ? (int) $editing_data['active'] : 1, 1, false ) . ' /> Active</label></p>';

        submit_button( $editing_data ? 'Update Question' : 'Create Question', 'primary tq-btn-primary' );
        echo '</form>';
        echo '</div>';

        echo '<div class="tq-card">';
        echo '<h2>Questions in Set: ' . esc_html( $set['title'] ) . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Order</th><th>Type</th><th>Prompt</th><th>Actions</th></tr></thead><tbody>';
        foreach ( $questions as $question ) {
            $edit_link = add_query_arg(
                array(
                    'page'   => 'tq-questions',
                    'set_id' => (int) $set_id,
                    'edit'   => (int) $question['id'],
                ),
                admin_url( 'admin.php' )
            );
            echo '<tr>';
            echo '<td>' . esc_html( $question['display_order'] ) . '</td>';
            echo '<td>' . esc_html( $question['question_type'] ) . '</td>';
            echo '<td>' . esc_html( wp_trim_words( wp_strip_all_tags( $question['prompt'] ), 14, '…' ) ) . '</td>';
            echo '<td><a class="button button-small" href="' . esc_url( $edit_link ) . '">Edit</a> ';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;">';
            wp_nonce_field( 'tq_delete_question_' . (int) $question['id'] );
            echo '<input type="hidden" name="action" value="tq_delete_question" />';
            echo '<input type="hidden" name="set_id" value="' . esc_attr( $set_id ) . '" />';
            echo '<input type="hidden" name="question_id" value="' . esc_attr( $question['id'] ) . '" />';
            echo '<button class="button button-small" onclick="return confirm(\'Delete this question?\')">Delete</button>';
            echo '</form></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public function render_importer_page() {
        $this->assert_admin();

        $report_key = 'tq_import_report_' . get_current_user_id();
        $report     = get_transient( $report_key );
        $logs       = $this->db->get_import_logs( 20 );

        echo '<div class="wrap tq-admin">';
        echo '<h1 class="tq-title">TechiQuiz Importer</h1>';
        $this->render_notice();

        echo '<div class="tq-card" style="max-width: 860px;">';
        echo '<h2>Import Questions (CSV / XLSX / XLS)</h2>';
        echo '<p>Use dry-run first to validate rows before writing to the database.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
        wp_nonce_field( 'tq_run_import' );
        echo '<input type="hidden" name="action" value="tq_run_import" />';
        echo '<p><label>File</label><input type="file" name="quiz_file" accept=".csv,.xlsx,.xls" required /></p>';
        echo '<p><label>Source Group</label><select name="source_group">';
        echo '<option value="">Auto detect</option>';
        echo '<option value="subsea-questions">subsea-questions</option>';
        echo '<option value="old-quiz">old-quiz</option>';
        echo '<option value="updated-drill-questions">updated-drill-questions</option>';
        echo '</select></p>';
        echo '<p><label><input type="checkbox" name="dry_run" value="1" checked /> Dry run (no database changes)</label></p>';
        echo '<p><label><input type="checkbox" name="upsert" value="1" /> Upsert existing question rows by (set + display_order)</label></p>';
        submit_button( 'Run Import', 'primary tq-btn-primary' );
        echo '</form>';
        echo '</div>';

        if ( ! empty( $report ) && is_array( $report ) ) {
            echo '<div class="tq-card" style="max-width: 860px; margin-top: 16px;">';
            echo '<h2>Last Import Report</h2>';
            echo '<ul>';
            echo '<li><strong>Mode:</strong> ' . esc_html( ! empty( $report['dry_run'] ) ? 'Dry Run' : 'Write' ) . '</li>';
            echo '<li><strong>Created:</strong> ' . esc_html( (string) ( $report['created'] ?? 0 ) ) . '</li>';
            echo '<li><strong>Updated:</strong> ' . esc_html( (string) ( $report['updated'] ?? 0 ) ) . '</li>';
            echo '<li><strong>Failed:</strong> ' . esc_html( (string) ( $report['failed'] ?? 0 ) ) . '</li>';
            echo '</ul>';

            if ( ! empty( $report['errors'] ) ) {
                echo '<h3>Errors</h3>';
                echo '<div style="max-height:240px;overflow:auto;border:1px solid #ddd;padding:8px;background:#fff;">';
                foreach ( $report['errors'] as $error ) {
                    echo '<p style="margin:6px 0;color:#b91c1c;">' . esc_html( $error ) . '</p>';
                }
                echo '</div>';
            }

            echo '</div>';
            delete_transient( $report_key );
        }

        echo '<div class="tq-card" style="max-width: 1080px; margin-top: 16px;">';
        echo '<h2>Import History</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Date</th><th>File</th><th>Group</th><th>Mode</th><th>Upsert</th><th>Status</th><th>Created</th><th>Updated</th><th>Failed</th><th>Errors</th></tr></thead><tbody>';
        if ( empty( $logs ) ) {
            echo '<tr><td colspan="10">No import history yet.</td></tr>';
        } else {
            foreach ( $logs as $log ) {
                echo '<tr>';
                echo '<td>' . esc_html( $log['created_at'] ) . '</td>';
                echo '<td>' . esc_html( $log['source_filename'] ) . '</td>';
                echo '<td>' . esc_html( $log['source_group'] ?: 'auto' ) . '</td>';
                echo '<td>' . esc_html( (int) $log['dry_run'] === 1 ? 'Dry Run' : 'Write' ) . '</td>';
                echo '<td>' . esc_html( (int) $log['upsert'] === 1 ? 'Yes' : 'No' ) . '</td>';
                echo '<td>' . esc_html( ucfirst( $log['status'] ) ) . '</td>';
                echo '<td>' . esc_html( $log['created_count'] ) . '</td>';
                echo '<td>' . esc_html( $log['updated_count'] ) . '</td>';
                echo '<td>' . esc_html( $log['failed_count'] ) . '</td>';
                echo '<td>' . esc_html( $log['error_count'] ) . '</td>';
                echo '</tr>';

                if ( ! empty( $log['error_excerpt'] ) ) {
                    echo '<tr><td colspan="10" style="background:#fff8f8;color:#991b1b;">' . esc_html( $log['error_excerpt'] ) . '</td></tr>';
                }
            }
        }
        echo '</tbody></table>';
        echo '</div>';

        echo '</div>';
    }

    public function save_course() {
        $this->assert_admin();
        check_admin_referer( 'tq_save_course' );

        $course_id = isset( $_POST['course_id'] ) ? absint( wp_unslash( $_POST['course_id'] ) ) : 0;
        $slug      = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
        $title     = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $active    = isset( $_POST['active'] ) ? 1 : 0;

        if ( $course_id > 0 ) {
            $this->db->update_course( $course_id, $slug, $title, $active );
            $this->redirect_with_notice( 'tq-courses', 'course_updated' );
        }

        $this->db->create_course( $slug, $title, $active );
        $this->redirect_with_notice( 'tq-courses', 'course_created' );
    }

    public function delete_course() {
        $this->assert_admin();

        $course_id = isset( $_POST['course_id'] ) ? absint( wp_unslash( $_POST['course_id'] ) ) : 0;
        check_admin_referer( 'tq_delete_course_' . $course_id );

        if ( $course_id > 0 ) {
            $this->db->delete_course( $course_id );
        }

        $this->redirect_with_notice( 'tq-courses', 'course_deleted' );
    }

    public function save_set() {
        $this->assert_admin();
        check_admin_referer( 'tq_save_set' );

        $set_id = isset( $_POST['set_id'] ) ? absint( wp_unslash( $_POST['set_id'] ) ) : 0;
        $data   = array(
            'course_id'      => isset( $_POST['course_id'] ) ? absint( wp_unslash( $_POST['course_id'] ) ) : 0,
            'title'          => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
            'day_label'      => isset( $_POST['day_label'] ) ? sanitize_text_field( wp_unslash( $_POST['day_label'] ) ) : '',
            'mode'           => isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'study',
            'question_count' => 0,
            'version'        => isset( $_POST['version'] ) ? absint( wp_unslash( $_POST['version'] ) ) : 1,
            'active'         => isset( $_POST['active'] ) ? 1 : 0,
        );

        if ( $set_id > 0 ) {
            $this->db->update_set( $set_id, $data );
            $this->db->sync_set_question_count( $set_id );
            $this->redirect_with_notice( 'tq-sets', 'set_updated' );
        }

        $created_id = $this->db->create_set( $data );
        if ( $created_id > 0 ) {
            $this->db->sync_set_question_count( $created_id );
        }

        $this->redirect_with_notice( 'tq-sets', 'set_created' );
    }

    public function delete_set() {
        $this->assert_admin();

        $set_id = isset( $_POST['set_id'] ) ? absint( wp_unslash( $_POST['set_id'] ) ) : 0;
        check_admin_referer( 'tq_delete_set_' . $set_id );

        if ( $set_id > 0 ) {
            $this->db->delete_set( $set_id );
        }

        $this->redirect_with_notice( 'tq-sets', 'set_deleted' );
    }

    public function save_question() {
        $this->assert_admin();
        check_admin_referer( 'tq_save_question' );

        $set_id      = isset( $_POST['set_id'] ) ? absint( wp_unslash( $_POST['set_id'] ) ) : 0;
        $question_id = isset( $_POST['question_id'] ) ? absint( wp_unslash( $_POST['question_id'] ) ) : 0;
        $data        = array(
            'set_id'        => $set_id,
            'question_type' => isset( $_POST['question_type'] ) ? sanitize_key( wp_unslash( $_POST['question_type'] ) ) : 'single_choice',
            'prompt'        => isset( $_POST['prompt'] ) ? wp_kses_post( wp_unslash( $_POST['prompt'] ) ) : '',
            'prompt_format' => isset( $_POST['prompt_format'] ) ? sanitize_key( wp_unslash( $_POST['prompt_format'] ) ) : 'plain',
            'explanation'   => isset( $_POST['explanation'] ) ? wp_kses_post( wp_unslash( $_POST['explanation'] ) ) : '',
            'display_order' => isset( $_POST['display_order'] ) ? absint( wp_unslash( $_POST['display_order'] ) ) : 1,
            'active'        => isset( $_POST['active'] ) ? 1 : 0,
        );

        $choices = array(
            'A' => isset( $_POST['choice_a'] ) ? sanitize_text_field( wp_unslash( $_POST['choice_a'] ) ) : '',
            'B' => isset( $_POST['choice_b'] ) ? sanitize_text_field( wp_unslash( $_POST['choice_b'] ) ) : '',
            'C' => isset( $_POST['choice_c'] ) ? sanitize_text_field( wp_unslash( $_POST['choice_c'] ) ) : '',
            'D' => isset( $_POST['choice_d'] ) ? sanitize_text_field( wp_unslash( $_POST['choice_d'] ) ) : '',
        );

        $correct_choice = isset( $_POST['correct_choice'] ) ? sanitize_text_field( wp_unslash( $_POST['correct_choice'] ) ) : 'A';

        if ( $question_id > 0 ) {
            $this->db->update_question( $question_id, $data );
            $this->db->replace_question_choices( $question_id, $choices, $correct_choice );
        } else {
            $question_id = $this->db->create_question( $data );
            if ( $question_id > 0 ) {
                $this->db->replace_question_choices( $question_id, $choices, $correct_choice );
            }
        }

        $this->db->sync_set_question_count( $set_id );
        $url = add_query_arg(
            array(
                'page'   => 'tq-questions',
                'set_id' => $set_id,
                'notice' => 'question_saved',
            ),
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $url );
        exit;
    }

    public function delete_question() {
        $this->assert_admin();

        $set_id      = isset( $_POST['set_id'] ) ? absint( wp_unslash( $_POST['set_id'] ) ) : 0;
        $question_id = isset( $_POST['question_id'] ) ? absint( wp_unslash( $_POST['question_id'] ) ) : 0;
        check_admin_referer( 'tq_delete_question_' . $question_id );

        if ( $question_id > 0 ) {
            $this->db->delete_question( $question_id );
        }

        if ( $set_id > 0 ) {
            $this->db->sync_set_question_count( $set_id );
        }

        $url = add_query_arg(
            array(
                'page'   => 'tq-questions',
                'set_id' => $set_id,
                'notice' => 'question_deleted',
            ),
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $url );
        exit;
    }

    public function run_import() {
        $this->assert_admin();
        check_admin_referer( 'tq_run_import' );

        if ( empty( $_FILES['quiz_file']['name'] ) ) {
            $this->redirect_with_notice( 'tq-importer', 'import_missing_file' );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $uploaded = wp_handle_upload(
            $_FILES['quiz_file'],
            array(
                'test_form' => false,
                'mimes'     => array(
                    'csv'  => 'text/csv',
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'xls'  => 'application/vnd.ms-excel',
                ),
            )
        );

        if ( isset( $uploaded['error'] ) ) {
            $this->redirect_with_notice( 'tq-importer', 'import_upload_error' );
        }

        $file_path = $uploaded['file'];
        $extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
        $dry_run   = isset( $_POST['dry_run'] );
        $upsert    = isset( $_POST['upsert'] );
        $source_group = isset( $_POST['source_group'] ) ? sanitize_title( wp_unslash( $_POST['source_group'] ) ) : '';
        $original_filename = isset( $_FILES['quiz_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['quiz_file']['name'] ) ) : basename( $file_path );

        try {
            $report = $this->import_service->process_file(
                $file_path,
                $extension,
                $dry_run,
                $upsert,
                array(
                    'source_group'      => $source_group,
                    'original_filename' => $original_filename,
                )
            );

            $this->db->create_import_log(
                array(
                    'user_id'         => get_current_user_id(),
                    'source_filename' => $original_filename,
                    'source_group'    => $source_group,
                    'file_extension'  => $extension,
                    'dry_run'         => $dry_run,
                    'upsert'          => $upsert,
                    'created_count'   => (int) ( $report['created'] ?? 0 ),
                    'updated_count'   => (int) ( $report['updated'] ?? 0 ),
                    'failed_count'    => (int) ( $report['failed'] ?? 0 ),
                    'status'          => ( (int) ( $report['failed'] ?? 0 ) > 0 ) ? 'warning' : 'success',
                    'error_count'     => ! empty( $report['errors'] ) ? count( $report['errors'] ) : 0,
                    'error_excerpt'   => ! empty( $report['errors'] ) ? implode( ' | ', array_slice( $report['errors'], 0, 3 ) ) : '',
                )
            );

            set_transient( 'tq_import_report_' . get_current_user_id(), $report, 10 * MINUTE_IN_SECONDS );
            $this->redirect_with_notice( 'tq-importer', 'import_finished' );
        } catch ( Exception $exception ) {
            $this->db->create_import_log(
                array(
                    'user_id'         => get_current_user_id(),
                    'source_filename' => $original_filename,
                    'source_group'    => $source_group,
                    'file_extension'  => $extension,
                    'dry_run'         => $dry_run,
                    'upsert'          => $upsert,
                    'created_count'   => 0,
                    'updated_count'   => 0,
                    'failed_count'    => 1,
                    'status'          => 'failed',
                    'error_count'     => 1,
                    'error_excerpt'   => $exception->getMessage(),
                )
            );

            set_transient(
                'tq_import_report_' . get_current_user_id(),
                array(
                    'dry_run' => $dry_run,
                    'created' => 0,
                    'updated' => 0,
                    'failed'  => 1,
                    'errors'  => array( $exception->getMessage() ),
                ),
                10 * MINUTE_IN_SECONDS
            );
            $this->redirect_with_notice( 'tq-importer', 'import_failed' );
        }
    }

    private function render_notice() {
        if ( empty( $_GET['notice'] ) ) {
            return;
        }

        $notice = sanitize_text_field( wp_unslash( $_GET['notice'] ) );
        $labels = array(
            'course_created'   => 'Course created.',
            'course_updated'   => 'Course updated.',
            'course_deleted'   => 'Course deleted.',
            'set_created'      => 'Set created.',
            'set_updated'      => 'Set updated.',
            'set_deleted'      => 'Set deleted.',
            'question_saved'   => 'Question saved.',
            'question_deleted' => 'Question deleted.',
            'import_finished'  => 'Import completed. See report below.',
            'import_failed'    => 'Import failed. Review report below.',
            'import_missing_file' => 'Please select a file to import.',
            'import_upload_error' => 'Upload failed. Please try again.',
        );

        if ( ! isset( $labels[ $notice ] ) ) {
            return;
        }

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $labels[ $notice ] ) . '</p></div>';
    }

    private function redirect_with_notice( $page, $notice ) {
        $url = add_query_arg(
            array(
                'page'   => $page,
                'notice' => $notice,
            ),
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $url );
        exit;
    }

    private function assert_admin() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage TechiQuiz.', 'techiquiz' ) );
        }
    }
}
