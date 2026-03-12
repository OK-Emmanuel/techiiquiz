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
        add_action( 'admin_post_tq_generate_quiz_page', array( $this, 'generate_quiz_page' ) );
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

        wp_enqueue_script( 'tailwindcss', 'https://cdn.tailwindcss.com', array(), null, false );
        wp_add_inline_script(
            'tailwindcss',
            'tailwind.config={theme:{extend:{colors:{brand:{blue:"#312e81",red:"#dc2626"}}}}};',
            'before'
        );

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
        $modal_open   = $editing_id > 0 ? ' tq-modal-open' : '';

        echo '<div class="wrap tq-admin tq-page-courses">';
        echo '<div class="tq-page-head flex items-center justify-between gap-3 mb-4"><h1 class="tq-title text-slate-900 text-5xl font-extrabold tracking-tight m-0">TechiQuiz Courses</h1>';
        echo '<button type="button" class="button button-primary tq-create-btn !bg-blue-600 hover:!bg-blue-700 !border-blue-700 !text-white !rounded-xl !px-4 !py-2 !inline-flex !items-center !gap-2" data-tq-open-modal="tq-course-modal"><span class="dashicons dashicons-plus-alt2"></span> ' . ( $editing_data ? 'Edit Course' : 'Create New Course' ) . '</button></div>';
        $this->render_notice();

        echo '<div class="tq-card rounded-2xl border border-slate-200 bg-white shadow-sm p-4">';
        echo '<h2>Existing Courses</h2>';
        echo '<table class="widefat striped tq-modern-table"><thead><tr><th>ID</th><th>Slug</th><th>Title</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
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
            echo '<td class="tq-actions">';
            echo '<a class="button button-small tq-icon-btn" title="Edit course" aria-label="Edit course" href="' . esc_url( $edit_link ) . '"><span class="dashicons dashicons-edit"></span></a>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;vertical-align:middle;">';
            wp_nonce_field( 'tq_delete_course_' . (int) $course['id'] );
            echo '<input type="hidden" name="action" value="tq_delete_course" />';
            echo '<input type="hidden" name="course_id" value="' . esc_attr( $course['id'] ) . '" />';
            echo '<button class="button button-small tq-icon-btn tq-icon-danger" title="Delete course" aria-label="Delete course" onclick="return confirm(\'Delete this course?\')"><span class="dashicons dashicons-trash"></span></button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';

        echo '<div id="tq-course-modal" class="tq-modal' . esc_attr( $modal_open ) . '">';
        echo '<div class="tq-modal-backdrop bg-slate-950/45 backdrop-blur-sm" data-tq-close-modal="tq-course-modal"></div>';
        echo '<div class="tq-modal-dialog rounded-3xl border border-slate-200 bg-white shadow-2xl">';
        echo '<div class="tq-modal-head flex items-center justify-between border-b border-slate-200 px-6 py-5"><div><p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">TechiQuiz</p><h2 class="mt-1 text-2xl font-bold text-slate-900">' . ( $editing_data ? 'Edit Course' : 'Create New Course' ) . '</h2></div>';
        echo '<button type="button" class="tq-modal-close inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500 transition hover:bg-slate-200 hover:text-slate-700" aria-label="Close" data-tq-close-modal="tq-course-modal">&times;</button></div>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="tq-modal-form space-y-5 px-6 py-6">';
        wp_nonce_field( 'tq_save_course' );
        echo '<input type="hidden" name="action" value="tq_save_course" />';
        echo '<input type="hidden" name="course_id" value="' . esc_attr( $editing_id ) . '" />';
        echo '<p class="space-y-2"><label class="block text-sm font-semibold text-slate-800">Slug</label><input class="regular-text !h-11 !rounded-xl !border-slate-300 !px-4 !text-sm focus:!border-brand-blue focus:!ring-brand-blue/20" name="slug" required value="' . esc_attr( $editing_data['slug'] ?? '' ) . '" /></p>';
        echo '<p class="space-y-2"><label class="block text-sm font-semibold text-slate-800">Title</label><input class="regular-text !h-11 !rounded-xl !border-slate-300 !px-4 !text-sm focus:!border-brand-blue focus:!ring-brand-blue/20" name="title" required value="' . esc_attr( $editing_data['title'] ?? '' ) . '" /></p>';
        echo '<p><label class="inline-flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700"><input type="checkbox" name="active" value="1" ' . checked( isset( $editing_data['active'] ) ? (int) $editing_data['active'] : 1, 1, false ) . ' /> Active</label></p>';
        submit_button( $editing_data ? 'Update Course' : 'Create Course', 'primary tq-btn-primary !m-0 !h-11 !rounded-xl !border-brand-red !bg-brand-red !px-5 !text-sm !font-semibold !text-white hover:!bg-red-700' );
        echo '</form></div></div>';

        echo '<script>(function(){var b=document.body;document.querySelectorAll("[data-tq-open-modal]").forEach(function(el){el.addEventListener("click",function(){var id=this.getAttribute("data-tq-open-modal");var m=document.getElementById(id);if(m){m.classList.add("tq-modal-open");b.classList.add("tq-no-scroll");}});});document.querySelectorAll("[data-tq-close-modal]").forEach(function(el){el.addEventListener("click",function(){var id=this.getAttribute("data-tq-close-modal");var m=document.getElementById(id);if(m){m.classList.remove("tq-modal-open");b.classList.remove("tq-no-scroll");}});});})();</script>';
        echo '</div>';
    }

    public function render_sets_page() {
        $this->assert_admin();

        $courses      = $this->db->get_courses();
        $sets         = $this->db->get_sets();
        $editing_id   = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
        $editing_data = $editing_id ? $this->db->get_set( $editing_id ) : null;
        $modal_open   = $editing_id > 0 ? ' tq-modal-open' : '';

        echo '<div class="wrap tq-admin tq-page-sets">';
        echo '<div class="tq-page-head flex items-center justify-between gap-3 mb-4"><h1 class="tq-title text-slate-900 text-5xl font-extrabold tracking-tight m-0">TechiQuiz Sets</h1>';
        echo '<button type="button" class="button button-primary tq-create-btn !bg-blue-600 hover:!bg-blue-700 !border-blue-700 !text-white !rounded-xl !px-4 !py-2 !inline-flex !items-center !gap-2" data-tq-open-modal="tq-set-modal"><span class="dashicons dashicons-plus-alt2"></span> ' . ( $editing_data ? 'Edit Set' : 'Create New Set' ) . '</button></div>';
        $this->render_notice();
        echo '<div class="tq-card rounded-2xl border border-slate-200 bg-white shadow-sm p-4" style="overflow-x:auto;">';
        echo '<h2>Existing Sets</h2>';
        echo '<table class="widefat striped tq-modern-table"><thead><tr>';
        echo '<th>ID</th><th>Course</th><th>Title</th><th>Day</th><th>Mode</th><th>Questions</th>';
        echo '<th>Shortcode</th><th>Page</th><th>Actions</th>';
        echo '</tr></thead><tbody>';
        foreach ( $sets as $set ) {
            $sid            = (int) $set['id'];
            $set_mode       = sanitize_key( $set['mode'] ?? 'study' );
            $shortcode      = '[tq_quiz set="' . $sid . '" mode="' . $set_mode . '"]';
            $shortcode_id   = 'tq-sc-' . $sid;

            $edit_link = add_query_arg(
                array(
                    'page' => 'tq-sets',
                    'edit' => $sid,
                ),
                admin_url( 'admin.php' )
            );
            $questions_link = add_query_arg(
                array(
                    'page'   => 'tq-questions',
                    'set_id' => $sid,
                ),
                admin_url( 'admin.php' )
            );

            /* check if a quiz page already exists for this set */
            $page_id     = (int) get_option( 'tq_quiz_page_' . $sid, 0 );
            $page_status = $page_id ? get_post_status( $page_id ) : false;
            $page_cell   = '';
            if ( $page_id && 'publish' === $page_status ) {
                $page_cell = '<a class="button button-small tq-icon-btn" title="View generated page" aria-label="View generated page" href="' . esc_url( get_permalink( $page_id ) ) . '" target="_blank"><span class="dashicons dashicons-external"></span></a>';
            } else {
                $page_cell  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;">';
                $page_cell .= wp_nonce_field( 'tq_generate_page_' . $sid, '_wpnonce', true, false );
                $page_cell .= '<input type="hidden" name="action" value="tq_generate_quiz_page" />';
                $page_cell .= '<input type="hidden" name="set_id" value="' . esc_attr( $sid ) . '" />';
                $page_cell .= '<button class="button button-small tq-icon-btn" title="Generate page" aria-label="Generate page"><span class="dashicons dashicons-admin-site"></span></button>';
                $page_cell .= '</form>';
            }

            echo '<tr>';
            echo '<td>' . esc_html( $set['id'] ) . '</td>';
            echo '<td>' . esc_html( $set['course_title'] ?? 'N/A' ) . '</td>';
            echo '<td>' . esc_html( $set['title'] ) . '</td>';
            echo '<td>' . esc_html( $set['day_label'] ) . '</td>';
            echo '<td>' . esc_html( ucfirst( $set['mode'] ) ) . '</td>';
            echo '<td>' . esc_html( $set['question_count'] ) . '</td>';
            echo '<td style="white-space:nowrap;">';
            echo '<code id="' . esc_attr( $shortcode_id ) . '" style="font-size:11px;">' . esc_html( $shortcode ) . '</code> ';
            echo '<button type="button" class="button button-small tq-icon-btn" title="Copy shortcode" aria-label="Copy shortcode" onclick="tqCopyShortcode(' . "'" . esc_js( $shortcode_id ) . "'" . ')"><span class="dashicons dashicons-admin-page"></span></button>';
            echo '</td>';
            echo '<td>' . $page_cell . '</td>';
            echo '<td class="tq-actions"><a class="button button-small tq-icon-btn" title="Edit set" aria-label="Edit set" href="' . esc_url( $edit_link ) . '"><span class="dashicons dashicons-edit"></span></a> ';
            echo '<a class="button button-small tq-icon-btn" title="Open question bank" aria-label="Open question bank" href="' . esc_url( $questions_link ) . '"><span class="dashicons dashicons-editor-help"></span></a> ';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;vertical-align:middle;">';
            wp_nonce_field( 'tq_delete_set_' . $sid );
            echo '<input type="hidden" name="action" value="tq_delete_set" />';
            echo '<input type="hidden" name="set_id" value="' . esc_attr( $sid ) . '" />';
            echo '<button class="button button-small tq-icon-btn tq-icon-danger" title="Delete set" aria-label="Delete set" onclick="return confirm(\'Delete this set?\')"><span class="dashicons dashicons-trash"></span></button>';
            echo '</form></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';

        echo '<div id="tq-set-modal" class="tq-modal' . esc_attr( $modal_open ) . '">';
        echo '<div class="tq-modal-backdrop bg-slate-950/45 backdrop-blur-sm" data-tq-close-modal="tq-set-modal"></div>';
        echo '<div class="tq-modal-dialog rounded-3xl border border-slate-200 bg-white shadow-2xl">';
        echo '<div class="tq-modal-head flex items-center justify-between border-b border-slate-200 px-6 py-5"><div><p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">TechiQuiz</p><h2 class="mt-1 text-2xl font-bold text-slate-900">' . ( $editing_data ? 'Edit Set' : 'Create New Set' ) . '</h2></div>';
        echo '<button type="button" class="tq-modal-close inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500 transition hover:bg-slate-200 hover:text-slate-700" aria-label="Close" data-tq-close-modal="tq-set-modal">&times;</button></div>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="tq-modal-form space-y-5 px-6 py-6">';
        wp_nonce_field( 'tq_save_set' );
        echo '<input type="hidden" name="action" value="tq_save_set" />';
        echo '<input type="hidden" name="set_id" value="' . esc_attr( $editing_id ) . '" />';
        echo '<p class="space-y-2"><label class="block text-sm font-semibold text-slate-800">Course</label><select class="!h-11 !w-full !max-w-full !rounded-xl !border-slate-300 !px-4 !text-sm focus:!border-brand-blue focus:!ring-brand-blue/20" name="course_id" required>';
        echo '<option value="">Select course</option>';
        foreach ( $courses as $course ) {
            echo '<option value="' . esc_attr( $course['id'] ) . '" ' . selected( (int) ( $editing_data['course_id'] ?? 0 ), (int) $course['id'], false ) . '>' . esc_html( $course['title'] ) . '</option>';
        }
        echo '</select></p>';
        echo '<p class="space-y-2"><label class="block text-sm font-semibold text-slate-800">Title</label><input class="regular-text !h-11 !rounded-xl border !border-slate-300 !px-4 !text-sm focus:!border-brand-blue focus:!ring-brand-blue/20" name="title" required value="' . esc_attr( $editing_data['title'] ?? '' ) . '" /></p>';
        echo '<p class="space-y-2"><label class="block text-sm font-semibold text-slate-800">Day Label</label><input class="regular-text !h-11 !rounded-xl border !border-slate-300 !px-4 !text-sm focus:!border-brand-blue focus:!ring-brand-blue/20" name="day_label" required value="' . esc_attr( $editing_data['day_label'] ?? '' ) . '" /></p>';
        echo '<div class="grid gap-4 md:grid-cols-2">';
        echo '<p class="space-y-2"><label class="block text-sm font-semibold text-slate-800">Mode</label><select class="!h-11 !w-full !max-w-full !rounded-xl !border-slate-300 !px-4 !text-sm focus:!border-brand-blue focus:!ring-brand-blue/20" name="mode"><option value="study" ' . selected( $editing_data['mode'] ?? 'study', 'study', false ) . '>Study</option><option value="practice" ' . selected( $editing_data['mode'] ?? 'study', 'practice', false ) . '>Practice</option></select></p>';
        echo '<p class="space-y-2"><label class="block text-sm font-semibold text-slate-800">Version</label><input class="!h-11 !w-full !max-w-full !rounded-xl !border !border-slate-300 !px-4 !text-sm focus:!border-brand-blue focus:!ring-brand-blue/20" type="number" min="1" name="version" value="' . esc_attr( $editing_data['version'] ?? 1 ) . '" /></p>';
        echo '</div>';
        echo '<p><label class="inline-flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700"><input type="checkbox" name="active" value="1" ' . checked( isset( $editing_data['active'] ) ? (int) $editing_data['active'] : 1, 1, false ) . ' /> Active</label></p>';
        submit_button( $editing_data ? 'Update Set' : 'Create Set', 'primary tq-btn-primary !m-0 !h-11 !rounded-xl !border-brand-red !bg-brand-red !px-5 !text-sm !font-semibold !text-white hover:!bg-red-700' );
        echo '</form></div></div>';

        echo '<script>(function(){var b=document.body;window.tqCopyShortcode=function(id){var el=document.getElementById(id);if(!el)return;navigator.clipboard?navigator.clipboard.writeText(el.textContent).then(function(){}).catch(function(){prompt("Copy this shortcode:",el.textContent);}):prompt("Copy this shortcode:",el.textContent);};document.querySelectorAll("[data-tq-open-modal]").forEach(function(el){el.addEventListener("click",function(){var id=this.getAttribute("data-tq-open-modal");var m=document.getElementById(id);if(m){m.classList.add("tq-modal-open");b.classList.add("tq-no-scroll");}});});document.querySelectorAll("[data-tq-close-modal]").forEach(function(el){el.addEventListener("click",function(){var id=this.getAttribute("data-tq-close-modal");var m=document.getElementById(id);if(m){m.classList.remove("tq-modal-open");b.classList.remove("tq-no-scroll");}});});})();</script>';
        echo '</div>';
    }

    public function render_questions_page() {
        $this->assert_admin();

        $set_id = isset( $_GET['set_id'] ) ? absint( wp_unslash( $_GET['set_id'] ) ) : 0;
        $set    = $set_id ? $this->db->get_set( $set_id ) : null;
        $sets   = $this->db->get_sets();

        $current_page = isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1;
        $current_page = max( 1, $current_page );
        $per_page     = 25;

        if ( ! $set && ! empty( $sets ) ) {
            $set_id = (int) $sets[0]['id'];
            $set    = $this->db->get_set( $set_id );
        }

        $questions        = $set_id ? $this->db->get_questions_for_admin_paginated( $set_id, $current_page, $per_page ) : array();
        $total_questions  = $set_id ? $this->db->count_questions_for_set( $set_id ) : 0;
        $total_pages      = $total_questions > 0 ? (int) ceil( $total_questions / $per_page ) : 1;

        $editing_id      = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
        $editing_data    = $editing_id ? $this->db->get_question_by_id( $editing_id ) : null;
        $editing_choices = $editing_id ? $this->db->get_choices_by_question( $editing_id ) : array();
        $modal_open      = $editing_id > 0 ? ' tq-modal-open' : '';

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

        echo '<div class="wrap tq-admin tq-page-questions">';
        echo '<div class="tq-page-head flex items-center justify-between gap-3 mb-4"><h1 class="tq-title text-slate-900 text-5xl font-extrabold tracking-tight m-0">TechiQuiz Question Bank</h1>';
        echo '<button type="button" class="button button-primary tq-create-btn !bg-blue-600 hover:!bg-blue-700 !border-blue-700 !text-white !rounded-xl !px-4 !py-2 !inline-flex !items-center !gap-2" data-tq-open-modal="tq-question-modal"><span class="dashicons dashicons-plus-alt2"></span> ' . ( $editing_data ? 'Edit Question' : 'Create New Question' ) . '</button></div>';
        $this->render_notice();

        echo '<div class="tq-card tq-toolbar rounded-2xl border border-slate-200 bg-white shadow-sm p-4 mb-4"><p><label>Set:</label> <select class="!min-w-[280px]" onchange="if(this.value){window.location=this.value}">';
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
        echo '</select></p></div>';

        if ( ! $set_id ) {
            echo '<p>Create a set first.</p></div>';
            return;
        }

        echo '<div class="tq-card rounded-2xl border border-slate-200 bg-white shadow-sm p-4">';
        echo '<h2>Questions in Set: ' . esc_html( $set['title'] ) . '</h2>';
        echo '<p><strong>Total:</strong> ' . esc_html( (string) $total_questions ) . ' questions' . ' | <strong>Page:</strong> ' . esc_html( (string) $current_page ) . ' of ' . esc_html( (string) $total_pages ) . '</p>';
        echo '<table class="widefat striped tq-modern-table"><thead><tr><th>Order</th><th>Type</th><th>Prompt</th><th>Actions</th></tr></thead><tbody>';
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
            echo '<td class="tq-actions"><a class="button button-small tq-icon-btn" title="Edit question" aria-label="Edit question" href="' . esc_url( $edit_link ) . '"><span class="dashicons dashicons-edit"></span></a> ';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;vertical-align:middle;">';
            wp_nonce_field( 'tq_delete_question_' . (int) $question['id'] );
            echo '<input type="hidden" name="action" value="tq_delete_question" />';
            echo '<input type="hidden" name="set_id" value="' . esc_attr( $set_id ) . '" />';
            echo '<input type="hidden" name="question_id" value="' . esc_attr( $question['id'] ) . '" />';
            echo '<button class="button button-small tq-icon-btn tq-icon-danger" title="Delete question" aria-label="Delete question" onclick="return confirm(\'Delete this question?\')"><span class="dashicons dashicons-trash"></span></button>';
            echo '</form></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        if ( $total_pages > 1 ) {
            $page_links = paginate_links(
                array(
                    'base'      => add_query_arg(
                        array(
                            'page'   => 'tq-questions',
                            'set_id' => (int) $set_id,
                            'paged'  => '%#%',
                        ),
                        admin_url( 'admin.php' )
                    ),
                    'format'    => '',
                    'current'   => $current_page,
                    'total'     => $total_pages,
                    'type'      => 'array',
                    'prev_text' => '&laquo; Prev',
                    'next_text' => 'Next &raquo;',
                )
            );

            if ( ! empty( $page_links ) && is_array( $page_links ) ) {
                echo '<nav class="mt-4 pt-3 border-t border-slate-200" aria-label="Questions pagination">';
                echo '<div class="flex flex-wrap items-center justify-end gap-2">';
                foreach ( $page_links as $link ) {
                    $item = str_replace( 'page-numbers', 'page-numbers inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50', $link );
                    $item = str_replace( 'current', 'current !bg-blue-600 !border-blue-600 !text-white', $item );
                    echo wp_kses_post( $item );
                }
                echo '</div>';
                echo '</nav>';
            }
        }

        echo '</div>';

        echo '<div id="tq-question-modal" class="tq-modal' . esc_attr( $modal_open ) . '">';
        echo '<div class="tq-modal-backdrop bg-slate-950/45 backdrop-blur-sm" data-tq-close-modal="tq-question-modal"></div>';
        echo '<div class="tq-modal-dialog rounded-3xl border border-slate-200 bg-white shadow-2xl">';
        echo '<div class="tq-modal-head flex items-center justify-between border-b border-slate-200 px-6 py-5"><div><p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">TechiQuiz</p><h2 class="mt-1 text-2xl font-bold text-slate-900">' . ( $editing_data ? 'Edit Question' : 'Create New Question' ) . '</h2></div>';
        echo '<button type="button" class="tq-modal-close inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500 transition hover:bg-slate-200 hover:text-slate-700" aria-label="Close" data-tq-close-modal="tq-question-modal">&times;</button></div>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="tq-modal-form space-y-5 px-6 py-6">';
        wp_nonce_field( 'tq_save_question' );
        echo '<input type="hidden" name="action" value="tq_save_question" />';
        echo '<input type="hidden" name="set_id" value="' . esc_attr( $set_id ) . '" />';
        echo '<input type="hidden" name="question_id" value="' . esc_attr( $editing_id ) . '" />';
        echo '<div class="grid gap-4 md:grid-cols-2">';
        echo '<p class="space-y-2"><label class="block text-sm font-semibold text-slate-800">Question Type</label><select class="!h-11 !w-full !max-w-full !rounded-xl !border-slate-300 !px-4 !text-sm focus:!border-brand-blue focus:!ring-brand-blue/20" name="question_type"><option value="single_choice" ' . selected( $editing_data['question_type'] ?? 'single_choice', 'single_choice', false ) . '>Single Choice</option><option value="objective_math" ' . selected( $editing_data['question_type'] ?? 'single_choice', 'objective_math', false ) . '>Objective Math</option></select></p>';
        echo '<p class="space-y-2"><label class="block text-sm font-semibold text-slate-800">Prompt Format</label><select class="!h-11 !w-full !max-w-full !rounded-xl !border-slate-300 !px-4 !text-sm focus:!border-brand-blue focus:!ring-brand-blue/20" name="prompt_format"><option value="plain" ' . selected( $editing_data['prompt_format'] ?? 'plain', 'plain', false ) . '>Plain</option><option value="mixed" ' . selected( $editing_data['prompt_format'] ?? 'plain', 'mixed', false ) . '>Mixed</option><option value="latex" ' . selected( $editing_data['prompt_format'] ?? 'plain', 'latex', false ) . '>LaTeX</option></select></p>';
        echo '</div>';
        echo '<p class="space-y-2"><label class="block text-sm font-semibold text-slate-800">Prompt</label><textarea name="prompt" rows="5" class="large-text !rounded-2xl !border-slate-300 !px-4 !py-3 !text-sm focus:!border-brand-blue focus:!ring-brand-blue/20" required>' . esc_textarea( $editing_data['prompt'] ?? '' ) . '</textarea></p>';
        echo '<p class="space-y-2"><label class="block text-sm font-semibold text-slate-800">Explanation (optional)</label><textarea name="explanation" rows="3" class="large-text !rounded-2xl !border-slate-300 !px-4 !py-3 !text-sm focus:!border-brand-blue focus:!ring-brand-blue/20">' . esc_textarea( $editing_data['explanation'] ?? '' ) . '</textarea></p>';
        echo '<p class="space-y-2"><label class="block text-sm font-semibold text-slate-800">Display Order</label><input class="!h-11 !w-full !max-w-full !rounded-xl !border !border-slate-300 !px-4 !text-sm focus:!border-brand-blue focus:!ring-brand-blue/20" type="number" min="1" name="display_order" value="' . esc_attr( $editing_data['display_order'] ?? 1 ) . '" /></p>';
        echo '<div class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><h3 class="m-0 mb-4 text-base font-bold text-slate-900">Choices</h3>';
        foreach ( $choice_map as $key => $value ) {
            echo '<p class="space-y-2 mb-3 last:mb-0"><label class="block text-sm font-semibold text-slate-800">Choice ' . esc_html( $key ) . '</label><input class="large-text !h-11 !rounded-xl !border-slate-300 !px-4 !text-sm focus:!border-brand-blue focus:!ring-brand-blue/20" name="choice_' . esc_attr( strtolower( $key ) ) . '" required value="' . esc_attr( $value ) . '" /></p>';
        }
        echo '<p class="space-y-2 mt-4"><label class="block text-sm font-semibold text-slate-800">Correct Choice</label><select class="!h-11 !w-full !max-w-full !rounded-xl !border-slate-300 !px-4 !text-sm focus:!border-brand-blue focus:!ring-brand-blue/20" name="correct_choice">';
        foreach ( array( 'A', 'B', 'C', 'D' ) as $letter ) {
            echo '<option value="' . esc_attr( $letter ) . '" ' . selected( $correct_key, $letter, false ) . '>' . esc_html( $letter ) . '</option>';
        }
        echo '</select></p></div>';
        echo '<p><label class="inline-flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700"><input type="checkbox" name="active" value="1" ' . checked( isset( $editing_data['active'] ) ? (int) $editing_data['active'] : 1, 1, false ) . ' /> Active</label></p>';
        submit_button( $editing_data ? 'Update Question' : 'Create Question', 'primary tq-btn-primary !m-0 !h-11 !rounded-xl !border-brand-red !bg-brand-red !px-5 !text-sm !font-semibold !text-white hover:!bg-red-700' );
        echo '</form></div></div>';

        echo '<script>(function(){var b=document.body;document.querySelectorAll("[data-tq-open-modal]").forEach(function(el){el.addEventListener("click",function(){var id=this.getAttribute("data-tq-open-modal");var m=document.getElementById(id);if(m){m.classList.add("tq-modal-open");b.classList.add("tq-no-scroll");}});});document.querySelectorAll("[data-tq-close-modal]").forEach(function(el){el.addEventListener("click",function(){var id=this.getAttribute("data-tq-close-modal");var m=document.getElementById(id);if(m){m.classList.remove("tq-modal-open");b.classList.remove("tq-no-scroll");}});});})();</script>';
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
            'course_created'      => 'Course created.',
            'course_updated'      => 'Course updated.',
            'course_deleted'      => 'Course deleted.',
            'set_created'         => 'Set created.',
            'set_updated'         => 'Set updated.',
            'set_deleted'         => 'Set deleted.',
            'question_saved'      => 'Question saved.',
            'question_deleted'    => 'Question deleted.',
            'import_finished'     => 'Import completed. See report below.',
            'import_failed'       => 'Import failed. Review report below.',
            'import_missing_file' => 'Please select a file to import.',
            'import_upload_error' => 'Upload failed. Please try again.',
            'page_created'        => 'Quiz page created and published.',
            'page_exists'         => 'A quiz page already exists for this set.',
            'page_error'          => 'Could not create the quiz page — please try again.',
        );

        if ( ! isset( $labels[ $notice ] ) ) {
            return;
        }

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $labels[ $notice ] ) . '</p></div>';
    }

    public function generate_quiz_page() {
        $this->assert_admin();

        $set_id = isset( $_POST['set_id'] ) ? absint( wp_unslash( $_POST['set_id'] ) ) : 0;
        $nonce  = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

        if ( $set_id <= 0 || empty( $nonce ) || ! wp_verify_nonce( $nonce, 'tq_generate_page_' . $set_id ) ) {
            error_log( 'TechiQuiz: generate_quiz_page nonce validation failed for set_id=' . $set_id );
            $this->redirect_with_notice( 'tq-sets', 'page_error' );
        }

        $set = $this->db->get_set( $set_id );
        if ( ! $set || $set_id <= 0 ) {
            error_log( 'TechiQuiz: generate_quiz_page set lookup failed for set_id=' . $set_id );
            wp_die( esc_html__( 'Invalid quiz set.', 'techiquiz' ) );
        }

        /* if a published page already exists, just go back */
        $existing_page_id = (int) get_option( 'tq_quiz_page_' . $set_id, 0 );
        if ( $existing_page_id && 'publish' === get_post_status( $existing_page_id ) ) {
            $this->redirect_with_notice( 'tq-sets', 'page_exists' );
        }

        $set_mode = sanitize_key( $set['mode'] ?? 'study' );
        $content  = '[tq_quiz set="' . $set_id . '" mode="' . $set_mode . '"]';

        $page_id = wp_insert_post(
            array(
                'post_title'     => sanitize_text_field( $set['title'] ),
                'post_content'   => $content,
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            ),
            true
        );

        if ( is_wp_error( $page_id ) ) {
            error_log( 'TechiQuiz: generate_quiz_page wp_insert_post error for set_id=' . $set_id . ' | ' . $page_id->get_error_message() );
            $this->redirect_with_notice( 'tq-sets', 'page_error' );
        }

        update_option( 'tq_quiz_page_' . $set_id, (int) $page_id );
        $this->redirect_with_notice( 'tq-sets', 'page_created' );
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
