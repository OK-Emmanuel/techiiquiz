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
        add_action( 'admin_post_tq_save_booking_class', array( $this, 'save_booking_class' ) );
        add_action( 'admin_post_tq_delete_booking_class', array( $this, 'delete_booking_class' ) );
        add_action( 'admin_post_tq_save_class_instance', array( $this, 'save_class_instance' ) );
        add_action( 'admin_post_tq_delete_class_instance', array( $this, 'delete_class_instance' ) );
        add_action( 'admin_post_tq_simulate_provisioning', array( $this, 'simulate_provisioning' ) );
        add_action( 'admin_post_tq_export_enrollment_report', array( $this, 'export_enrollment_report' ) );
        add_action( 'admin_post_tq_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_tq_save_integration_checks', array( $this, 'save_integration_checks' ) );
        add_action( 'admin_post_tq_save_launch_readiness', array( $this, 'save_launch_readiness' ) );
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

        add_submenu_page(
            'tq-courses',
            'Booking Classes',
            'Booking Classes',
            'manage_options',
            'tq-booking-classes',
            array( $this, 'render_booking_classes_page' )
        );

        add_submenu_page(
            'tq-courses',
            'Class Instances',
            'Class Instances',
            'manage_options',
            'tq-class-instances',
            array( $this, 'render_class_instances_page' )
        );

        add_submenu_page(
            'tq-courses',
            'Provisioning Logs',
            'Provisioning Logs',
            'manage_options',
            'tq-provisioning-logs',
            array( $this, 'render_provisioning_logs_page' )
        );

        add_submenu_page(
            'tq-courses',
            'Enrollment Reports',
            'Enrollment Reports',
            'manage_options',
            'tq-enrollment-reports',
            array( $this, 'render_enrollment_reports_page' )
        );

        add_submenu_page(
            'tq-courses',
            'Settings',
            'Settings',
            'manage_options',
            'tq-settings',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            'tq-courses',
            'Integration QA',
            'Integration QA',
            'manage_options',
            'tq-integration-qa',
            array( $this, 'render_integration_qa_page' )
        );

        add_submenu_page(
            'tq-courses',
            'Launch Readiness',
            'Launch Readiness',
            'manage_options',
            'tq-launch-readiness',
            array( $this, 'render_launch_readiness_page' )
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

    public function render_booking_classes_page() {
        $this->assert_admin();

        $classes = $this->db->get_booking_classes();
        $edit_id = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
        $editing = $edit_id > 0 ? $this->db->get_booking_class( $edit_id ) : null;

        echo '<div class="wrap tq-admin">';
        echo '<h1 class="tq-title">Booking Classes</h1>';
        $this->render_notice();

        echo '<div class="tq-card" style="max-width:900px;">';
        echo '<h2>' . ( $editing ? 'Edit Class' : 'Add New Class' ) . '</h2>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'tq_save_booking_class' );
        echo '<input type="hidden" name="action" value="tq_save_booking_class" />';
        echo '<input type="hidden" name="class_id" value="' . esc_attr( $editing['id'] ?? 0 ) . '" />';
        echo '<p><label>Name</label><br /><input class="regular-text" name="name" required value="' . esc_attr( $editing['name'] ?? '' ) . '" /></p>';
        echo '<p><label>Course Code</label><br /><input class="regular-text" name="course_code" required value="' . esc_attr( $editing['course_code'] ?? '' ) . '" /></p>';
        echo '<p><label>Workbook URL</label><br /><input class="regular-text" type="url" name="workbook_url" value="' . esc_attr( $editing['workbook_url'] ?? '' ) . '" /></p>';
        echo '<p><label>Description</label><br /><textarea class="large-text" rows="4" name="description">' . esc_textarea( $editing['description'] ?? '' ) . '</textarea></p>';
        submit_button( $editing ? 'Update Class' : 'Create Class', 'primary tq-btn-primary' );
        echo '</form>';
        echo '</div>';

        echo '<div class="tq-card" style="max-width:1080px; margin-top:16px;">';
        echo '<h2>Existing Booking Classes</h2>';
        echo '<table class="widefat striped tq-modern-table"><thead><tr><th>ID</th><th>Name</th><th>Code</th><th>Workbook</th><th>Instances</th><th>Actions</th></tr></thead><tbody>';
        if ( empty( $classes ) ) {
            echo '<tr><td colspan="6">No booking classes yet.</td></tr>';
        } else {
            foreach ( $classes as $class ) {
                $edit_link = add_query_arg(
                    array(
                        'page' => 'tq-booking-classes',
                        'edit' => (int) $class['id'],
                    ),
                    admin_url( 'admin.php' )
                );
                echo '<tr>';
                echo '<td>' . esc_html( $class['id'] ) . '</td>';
                echo '<td>' . esc_html( $class['name'] ) . '</td>';
                echo '<td>' . esc_html( $class['course_code'] ) . '</td>';
                echo '<td>' . ( ! empty( $class['workbook_url'] ) ? '<a href="' . esc_url( $class['workbook_url'] ) . '" target="_blank">Open</a>' : 'N/A' ) . '</td>';
                echo '<td>' . esc_html( $class['instance_count'] ) . '</td>';
                echo '<td class="tq-actions">';
                echo '<a class="button button-small tq-icon-btn" href="' . esc_url( $edit_link ) . '" title="Edit class"><span class="dashicons dashicons-edit"></span></a>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;vertical-align:middle;">';
                wp_nonce_field( 'tq_delete_booking_class_' . (int) $class['id'] );
                echo '<input type="hidden" name="action" value="tq_delete_booking_class" />';
                echo '<input type="hidden" name="class_id" value="' . esc_attr( $class['id'] ) . '" />';
                echo '<button class="button button-small tq-icon-btn tq-icon-danger" onclick="return confirm(\'Delete this booking class?\')"><span class="dashicons dashicons-trash"></span></button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';
    }

    public function render_class_instances_page() {
        $this->assert_admin();

        $classes   = $this->db->get_booking_classes();
        $instances = $this->db->get_class_instances();
        $edit_id   = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
        $editing   = $edit_id > 0 ? $this->db->get_class_instance( $edit_id ) : null;
        $products  = function_exists( 'wc_get_products' )
            ? wc_get_products(
                array(
                    'status' => 'publish',
                    'limit'  => 200,
                    'return' => 'ids',
                )
            )
            : array();

        echo '<div class="wrap tq-admin">';
        echo '<h1 class="tq-title">Class Instances</h1>';
        $this->render_notice();

        echo '<div class="tq-card" style="max-width:960px;">';
        echo '<h2>' . ( $editing ? 'Edit Instance' : 'Add New Instance' ) . '</h2>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'tq_save_class_instance' );
        echo '<input type="hidden" name="action" value="tq_save_class_instance" />';
        echo '<input type="hidden" name="instance_id" value="' . esc_attr( $editing['id'] ?? 0 ) . '" />';

        echo '<p><label>Class</label><br /><select name="class_id" required>';
        echo '<option value="">Select class</option>';
        foreach ( $classes as $class ) {
            echo '<option value="' . esc_attr( $class['id'] ) . '" ' . selected( (int) ( $editing['class_id'] ?? 0 ), (int) $class['id'], false ) . '>' . esc_html( $class['name'] . ' (' . $class['course_code'] . ')' ) . '</option>';
        }
        echo '</select></p>';

        echo '<p><label>WooCommerce Product</label><br /><select name="woocommerce_product_id" required>';
        echo '<option value="">Select product</option>';
        foreach ( $products as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }
            echo '<option value="' . esc_attr( $product_id ) . '" ' . selected( (int) ( $editing['woocommerce_product_id'] ?? 0 ), (int) $product_id, false ) . '>' . esc_html( '#' . $product_id . ' - ' . $product->get_name() ) . '</option>';
        }
        echo '</select></p>';

        echo '<p><label>Start Date</label><br /><input type="date" name="start_date" required value="' . esc_attr( $editing['start_date'] ?? '' ) . '" /></p>';
        echo '<p><label>End Date</label><br /><input type="date" name="end_date" required value="' . esc_attr( $editing['end_date'] ?? '' ) . '" /></p>';
        echo '<p><label>Max Capacity</label><br /><input type="number" min="1" name="max_capacity" required value="' . esc_attr( $editing['max_capacity'] ?? 12 ) . '" /></p>';

        submit_button( $editing ? 'Update Instance' : 'Create Instance', 'primary tq-btn-primary' );
        echo '</form>';
        echo '</div>';

        echo '<div class="tq-card" style="max-width:1200px; margin-top:16px;">';
        echo '<h2>Existing Class Instances</h2>';
        echo '<table class="widefat striped tq-modern-table"><thead><tr><th>ID</th><th>Class</th><th>Product</th><th>Start</th><th>End</th><th>Access End (+45d)</th><th>Capacity</th><th>Enrollments</th><th>Actions</th></tr></thead><tbody>';
        if ( empty( $instances ) ) {
            echo '<tr><td colspan="9">No class instances yet.</td></tr>';
        } else {
            foreach ( $instances as $instance ) {
                $edit_link    = add_query_arg(
                    array(
                        'page' => 'tq-class-instances',
                        'edit' => (int) $instance['id'],
                    ),
                    admin_url( 'admin.php' )
                );
                $access_end   = gmdate( 'Y-m-d', strtotime( (string) $instance['end_date'] . ' +45 days' ) );
                $enroll_count = $this->db->count_enrollments_for_instance( (int) $instance['id'] );

                echo '<tr>';
                echo '<td>' . esc_html( $instance['id'] ) . '</td>';
                echo '<td>' . esc_html( $instance['class_name'] ?: 'N/A' ) . '</td>';
                echo '<td>#' . esc_html( $instance['woocommerce_product_id'] ) . '</td>';
                echo '<td>' . esc_html( $instance['start_date'] ) . '</td>';
                echo '<td>' . esc_html( $instance['end_date'] ) . '</td>';
                echo '<td>' . esc_html( $access_end ) . '</td>';
                echo '<td>' . esc_html( $instance['max_capacity'] ) . '</td>';
                echo '<td>' . esc_html( $enroll_count ) . '</td>';
                echo '<td class="tq-actions">';
                echo '<a class="button button-small tq-icon-btn" href="' . esc_url( $edit_link ) . '" title="Edit instance"><span class="dashicons dashicons-edit"></span></a>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;vertical-align:middle;">';
                wp_nonce_field( 'tq_delete_class_instance_' . (int) $instance['id'] );
                echo '<input type="hidden" name="action" value="tq_delete_class_instance" />';
                echo '<input type="hidden" name="instance_id" value="' . esc_attr( $instance['id'] ) . '" />';
                echo '<button class="button button-small tq-icon-btn tq-icon-danger" onclick="return confirm(\'Delete this instance? This may impact enrolled students.\')"><span class="dashicons dashicons-trash"></span></button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';
    }

    public function render_provisioning_logs_page() {
        $this->assert_admin();

        $logs = $this->db->get_provisioning_logs( 100 );
        $simulation_report = get_transient( 'tq_provisioning_simulation_' . get_current_user_id() );

        echo '<div class="wrap tq-admin">';
        echo '<h1 class="tq-title">Provisioning Logs</h1>';
        $this->render_notice();

        echo '<div class="tq-card" style="max-width:800px; margin-bottom:16px;">';
        echo '<h2>Simulate Provisioning by Order ID</h2>';
        echo '<p>Use this to test provisioning logic for an existing WooCommerce order without waiting for a new checkout.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'tq_simulate_provisioning' );
        echo '<input type="hidden" name="action" value="tq_simulate_provisioning" />';
        echo '<p><label>WooCommerce Order ID</label><br /><input type="number" min="1" name="order_id" required class="small-text" /></p>';
        submit_button( 'Run Provisioning Simulation', 'primary tq-btn-primary' );
        echo '</form>';
        echo '</div>';

        if ( is_array( $simulation_report ) ) {
            echo '<div class="tq-card" style="max-width:1000px; margin-bottom:16px;">';
            echo '<h2>Last Simulation Result</h2>';
            echo '<p><strong>Order ID:</strong> ' . esc_html( $simulation_report['order_id'] ?? '' ) . '</p>';
            if ( ! empty( $simulation_report['error'] ) ) {
                echo '<p style="color:#991b1b;"><strong>Error:</strong> ' . esc_html( $simulation_report['error'] ) . '</p>';
            } else {
                echo '<p><strong>User ID:</strong> ' . esc_html( $simulation_report['user_id'] ?? '' ) . '</p>';
                echo '<p><strong>Provisioned Instances:</strong> ' . esc_html( $simulation_report['provisioned_instances'] ?? 0 ) . '</p>';
            }
            echo '</div>';
            delete_transient( 'tq_provisioning_simulation_' . get_current_user_id() );
        }

        echo '<div class="tq-card" style="max-width:1200px;">';
        echo '<h2>Latest Provisioning Events</h2>';
        echo '<table class="widefat striped tq-modern-table"><thead><tr><th>Date</th><th>Order</th><th>User</th><th>Instance</th><th>Action</th><th>Status</th><th>Message</th></tr></thead><tbody>';

        if ( empty( $logs ) ) {
            echo '<tr><td colspan="7">No provisioning logs yet.</td></tr>';
        } else {
            foreach ( $logs as $log ) {
                echo '<tr>';
                echo '<td>' . esc_html( $log['created_at'] ) . '</td>';
                echo '<td>#' . esc_html( $log['woocommerce_order_id'] ) . '</td>';
                echo '<td>' . ( ! empty( $log['user_id'] ) ? esc_html( $log['user_id'] ) : 'N/A' ) . '</td>';
                echo '<td>' . ( ! empty( $log['class_instance_id'] ) ? esc_html( $log['class_instance_id'] ) : 'N/A' ) . '</td>';
                echo '<td>' . esc_html( $log['action'] ) . '</td>';
                echo '<td>' . esc_html( ucfirst( $log['status'] ) ) . '</td>';
                echo '<td>' . esc_html( $log['message'] ) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';
    }

    public function render_enrollment_reports_page() {
        $this->assert_admin();

        $selected_instance_id = isset( $_GET['class_instance_id'] ) ? absint( wp_unslash( $_GET['class_instance_id'] ) ) : 0;
        $instances            = $this->db->get_class_instances();
        $rows                 = $this->db->get_enrollment_report_rows( $selected_instance_id );

        echo '<div class="wrap tq-admin">';
        echo '<h1 class="tq-title">Enrollment Reports</h1>';
        $this->render_notice();

        echo '<div class="tq-card" style="max-width:1100px; margin-bottom:16px;">';
        echo '<h2>Filter</h2>';
        echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
        echo '<input type="hidden" name="page" value="tq-enrollment-reports" />';
        echo '<p><label>Class Instance</label><br /><select name="class_instance_id">';
        echo '<option value="0">All instances</option>';
        foreach ( $instances as $instance ) {
            $label = '#' . (int) $instance['id'] . ' - ' . ( $instance['class_name'] ?: 'N/A' ) . ' (' . $instance['start_date'] . ' to ' . $instance['end_date'] . ')';
            echo '<option value="' . esc_attr( $instance['id'] ) . '" ' . selected( $selected_instance_id, (int) $instance['id'], false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></p>';
        submit_button( 'Apply Filter', 'secondary', '', false );
        echo '</form>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:12px;">';
        wp_nonce_field( 'tq_export_enrollment_report' );
        echo '<input type="hidden" name="action" value="tq_export_enrollment_report" />';
        echo '<input type="hidden" name="class_instance_id" value="' . esc_attr( $selected_instance_id ) . '" />';
        submit_button( 'Export CSV', 'primary tq-btn-primary', '', false );
        echo '</form>';
        echo '</div>';

        echo '<div class="tq-card" style="max-width:1200px;">';
        echo '<h2>Roster</h2>';
        echo '<table class="widefat striped tq-modern-table"><thead><tr><th>User</th><th>Email</th><th>Class</th><th>Instance Dates</th><th>Enrollment Date</th><th>Access End</th><th>Status</th></tr></thead><tbody>';
        if ( empty( $rows ) ) {
            echo '<tr><td colspan="7">No enrollments found for selected filter.</td></tr>';
        } else {
            $today = current_time( 'Y-m-d' );
            foreach ( $rows as $row ) {
                $access_end    = ! empty( $row['access_end'] ) ? (string) $row['access_end'] : '';
                $access_start  = ! empty( $row['access_start'] ) ? (string) $row['access_start'] : '';
                $access_status = 'inactive';
                if ( '' !== $access_end ) {
                    if ( $today < $access_start ) {
                        $access_status = 'not_yet_available';
                    } elseif ( $today > $access_end ) {
                        $access_status = 'expired';
                    } else {
                        $access_status = 'active';
                    }
                }

                echo '<tr>';
                echo '<td>' . esc_html( $row['display_name'] ?: 'User #' . (int) $row['user_id'] ) . '</td>';
                echo '<td>' . esc_html( $row['user_email'] ?: 'N/A' ) . '</td>';
                echo '<td>' . esc_html( $row['class_name'] . ' (' . $row['course_code'] . ')' ) . '</td>';
                echo '<td>' . esc_html( $row['start_date'] . ' to ' . $row['end_date'] ) . '</td>';
                echo '<td>' . esc_html( $row['enrollment_date'] ) . '</td>';
                echo '<td>' . esc_html( $access_end ?: 'N/A' ) . '</td>';
                echo '<td>' . esc_html( $access_status ) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';
    }

    public function render_settings_page() {
        $this->assert_admin();

        $study_limit    = (int) get_option( 'tq_study_limit', 100 );
        $practice_limit = (int) get_option( 'tq_practice_limit', 35 );

        if ( $study_limit <= 0 ) {
            $study_limit = 100;
        }

        if ( $practice_limit <= 0 ) {
            $practice_limit = 35;
        }

        echo '<div class="wrap tq-admin">';
        echo '<h1 class="tq-title">TechiQuiz Settings</h1>';
        $this->render_notice();

        echo '<div class="tq-card" style="max-width:700px;">';
        echo '<h2>Question Limits</h2>';
        echo '<p>Configure how many questions are loaded per session mode.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'tq_save_settings' );
        echo '<input type="hidden" name="action" value="tq_save_settings" />';
        echo '<p><label for="tq-study-limit">Study mode question limit</label><br />';
        echo '<input id="tq-study-limit" type="number" min="1" max="500" step="1" class="small-text" name="study_limit" value="' . esc_attr( $study_limit ) . '" /></p>';
        echo '<p><label for="tq-practice-limit">Practice mode question limit</label><br />';
        echo '<input id="tq-practice-limit" type="number" min="1" max="500" step="1" class="small-text" name="practice_limit" value="' . esc_attr( $practice_limit ) . '" /></p>';
        submit_button( 'Save Settings', 'primary tq-btn-primary' );
        echo '</form>';
        echo '</div>';
        echo '</div>';
    }

    public function render_integration_qa_page() {
        $this->assert_admin();

        $instances = $this->db->get_class_instances();
        $logs      = $this->db->get_provisioning_logs( 200 );
        $rows      = $this->db->get_enrollment_report_rows();

        $mapped_instances       = 0;
        $unmapped_instances     = 0;
        $error_logs             = 0;
        $success_logs           = 0;
        $active_access          = 0;
        $expired_access         = 0;
        $future_access          = 0;
        $today                  = current_time( 'Y-m-d' );
        $active_enrollment_rows = 0;

        foreach ( $instances as $instance ) {
            if ( ! empty( $instance['woocommerce_product_id'] ) ) {
                $mapped_instances++;
            } else {
                $unmapped_instances++;
            }
        }

        foreach ( $logs as $log ) {
            if ( 'error' === ( $log['status'] ?? '' ) ) {
                $error_logs++;
            }
            if ( 'success' === ( $log['status'] ?? '' ) ) {
                $success_logs++;
            }
        }

        foreach ( $rows as $row ) {
            if ( 'active' === ( $row['enrollment_status'] ?? '' ) ) {
                $active_enrollment_rows++;
            }

            $access_start = ! empty( $row['access_start'] ) ? (string) $row['access_start'] : '';
            $access_end   = ! empty( $row['access_end'] ) ? (string) $row['access_end'] : '';

            if ( '' === $access_start || '' === $access_end ) {
                continue;
            }

            if ( $today < $access_start ) {
                $future_access++;
            } elseif ( $today > $access_end ) {
                $expired_access++;
            } else {
                $active_access++;
            }
        }

        $check_keys = array(
            'admin_flow',
            'customer_purchase',
            'webhook_entitlements',
            'quiz_access',
            'workbook_access',
            'expiry_enforced',
            'roster_export',
            'duplicate_purchase_blocked',
            'unmapped_product_error_logged',
            'existing_user_reused',
        );

        $saved_checks = get_option( 'tq_integration_checks', array() );
        if ( ! is_array( $saved_checks ) ) {
            $saved_checks = array();
        }

        $check_labels = array(
            'admin_flow'                   => 'Admin creates class, instance, and maps product',
            'customer_purchase'            => 'Customer purchase completed in WooCommerce',
            'webhook_entitlements'         => 'Webhook created user/enrollment/entitlements',
            'quiz_access'                  => 'Customer can access quiz with entitlement',
            'workbook_access'              => 'Customer can download workbook',
            'expiry_enforced'              => 'Access denied after expiry window',
            'roster_export'                => 'Roster visible and CSV export works',
            'duplicate_purchase_blocked'   => 'Duplicate purchase does not duplicate enrollment',
            'unmapped_product_error_logged'=> 'Unmapped product logs provisioning error',
            'existing_user_reused'         => 'Existing user is reused on repurchase',
        );

        echo '<div class="wrap tq-admin">';
        echo '<h1 class="tq-title">Integration QA (Phase 5e)</h1>';
        $this->render_notice();

        echo '<div class="tq-card" style="max-width:1200px; margin-bottom:16px;">';
        echo '<h2>Live Snapshot</h2>';
        echo '<table class="widefat striped tq-modern-table"><tbody>';
        echo '<tr><th>Total Class Instances</th><td>' . esc_html( count( $instances ) ) . '</td><th>Mapped Instances</th><td>' . esc_html( $mapped_instances ) . '</td></tr>';
        echo '<tr><th>Unmapped Instances</th><td>' . esc_html( $unmapped_instances ) . '</td><th>Total Enrollments</th><td>' . esc_html( count( $rows ) ) . '</td></tr>';
        echo '<tr><th>Active Enrollments</th><td>' . esc_html( $active_enrollment_rows ) . '</td><th>Provisioning Success Logs</th><td>' . esc_html( $success_logs ) . '</td></tr>';
        echo '<tr><th>Provisioning Error Logs</th><td>' . esc_html( $error_logs ) . '</td><th>Active Access Windows</th><td>' . esc_html( $active_access ) . '</td></tr>';
        echo '<tr><th>Future Access Windows</th><td>' . esc_html( $future_access ) . '</td><th>Expired Access Windows</th><td>' . esc_html( $expired_access ) . '</td></tr>';
        echo '</tbody></table>';
        echo '</div>';

        echo '<div class="tq-card" style="max-width:1200px; margin-bottom:16px;">';
        echo '<h2>Phase 5e Checklist Runner</h2>';
        echo '<p>Use this to track completion of the integration scenarios while testing.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'tq_save_integration_checks' );
        echo '<input type="hidden" name="action" value="tq_save_integration_checks" />';
        echo '<table class="widefat striped tq-modern-table"><tbody>';
        foreach ( $check_keys as $key ) {
            $checked = ! empty( $saved_checks[ $key ] );
            echo '<tr>';
            echo '<td style="width:32px;"><input type="checkbox" name="checks[]" value="' . esc_attr( $key ) . '" ' . checked( $checked, true, false ) . ' /></td>';
            echo '<td>' . esc_html( $check_labels[ $key ] ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        submit_button( 'Save QA Progress', 'primary tq-btn-primary' );
        echo '</form>';
        echo '</div>';

        echo '<div class="tq-card" style="max-width:1200px;">';
        echo '<h2>Quick Links</h2>';
        echo '<ul style="list-style:disc; padding-left:18px;">';
        echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=tq-booking-classes' ) ) . '">Booking Classes</a></li>';
        echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=tq-class-instances' ) ) . '">Class Instances</a></li>';
        echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=tq-provisioning-logs' ) ) . '">Provisioning Logs</a></li>';
        echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=tq-enrollment-reports' ) ) . '">Enrollment Reports</a></li>';
        echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=tq-settings' ) ) . '">TechiQuiz Settings</a></li>';
        echo '</ul>';
        echo '</div>';

        echo '</div>';
    }

    public function save_booking_class() {
        $this->assert_admin();
        check_admin_referer( 'tq_save_booking_class' );

        $class_id = isset( $_POST['class_id'] ) ? absint( wp_unslash( $_POST['class_id'] ) ) : 0;
        $data     = array(
            'name'         => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
            'course_code'  => isset( $_POST['course_code'] ) ? sanitize_text_field( wp_unslash( $_POST['course_code'] ) ) : '',
            'workbook_url' => isset( $_POST['workbook_url'] ) ? esc_url_raw( wp_unslash( $_POST['workbook_url'] ) ) : '',
            'description'  => isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '',
        );

        if ( $class_id > 0 ) {
            $this->db->update_booking_class( $class_id, $data );
            $this->redirect_with_notice( 'tq-booking-classes', 'booking_class_updated' );
        }

        $this->db->create_booking_class( $data );
        $this->redirect_with_notice( 'tq-booking-classes', 'booking_class_created' );
    }

    public function delete_booking_class() {
        $this->assert_admin();

        $class_id = isset( $_POST['class_id'] ) ? absint( wp_unslash( $_POST['class_id'] ) ) : 0;
        check_admin_referer( 'tq_delete_booking_class_' . $class_id );

        if ( $class_id > 0 ) {
            $this->db->delete_booking_class( $class_id );
        }

        $this->redirect_with_notice( 'tq-booking-classes', 'booking_class_deleted' );
    }

    public function save_class_instance() {
        $this->assert_admin();
        check_admin_referer( 'tq_save_class_instance' );

        $instance_id = isset( $_POST['instance_id'] ) ? absint( wp_unslash( $_POST['instance_id'] ) ) : 0;
        $data        = array(
            'class_id'               => isset( $_POST['class_id'] ) ? absint( wp_unslash( $_POST['class_id'] ) ) : 0,
            'woocommerce_product_id' => isset( $_POST['woocommerce_product_id'] ) ? absint( wp_unslash( $_POST['woocommerce_product_id'] ) ) : 0,
            'start_date'             => isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '',
            'end_date'               => isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '',
            'max_capacity'           => isset( $_POST['max_capacity'] ) ? absint( wp_unslash( $_POST['max_capacity'] ) ) : 12,
            'current_enrollment'     => 0,
        );

        if ( $instance_id > 0 ) {
            $existing = $this->db->get_class_instance( $instance_id );
            if ( $existing ) {
                $data['current_enrollment'] = (int) $existing['current_enrollment'];
            }
            $this->db->update_class_instance( $instance_id, $data );
            $this->redirect_with_notice( 'tq-class-instances', 'class_instance_updated' );
        }

        $this->db->create_class_instance( $data );
        $this->redirect_with_notice( 'tq-class-instances', 'class_instance_created' );
    }

    public function delete_class_instance() {
        $this->assert_admin();

        $instance_id = isset( $_POST['instance_id'] ) ? absint( wp_unslash( $_POST['instance_id'] ) ) : 0;
        check_admin_referer( 'tq_delete_class_instance_' . $instance_id );

        if ( $instance_id > 0 ) {
            $this->db->delete_class_instance( $instance_id );
        }

        $this->redirect_with_notice( 'tq-class-instances', 'class_instance_deleted' );
    }

    public function simulate_provisioning() {
        $this->assert_admin();
        check_admin_referer( 'tq_simulate_provisioning' );

        $order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
        if ( $order_id <= 0 ) {
            $this->redirect_with_notice( 'tq-provisioning-logs', 'provisioning_simulation_invalid_order' );
        }

        $booking_service = new TQ_Booking_Service( $this->db );
        $result          = $booking_service->provision_from_order( $order_id );

        if ( is_wp_error( $result ) ) {
            set_transient(
                'tq_provisioning_simulation_' . get_current_user_id(),
                array(
                    'order_id' => $order_id,
                    'error'    => $result->get_error_message(),
                ),
                10 * MINUTE_IN_SECONDS
            );
            $this->redirect_with_notice( 'tq-provisioning-logs', 'provisioning_simulation_error' );
        }

        set_transient(
            'tq_provisioning_simulation_' . get_current_user_id(),
            array(
                'order_id'              => (int) ( $result['order_id'] ?? $order_id ),
                'user_id'               => (int) ( $result['user_id'] ?? 0 ),
                'provisioned_instances' => (int) ( $result['provisioned_instances'] ?? 0 ),
            ),
            10 * MINUTE_IN_SECONDS
        );

        $this->redirect_with_notice( 'tq-provisioning-logs', 'provisioning_simulation_success' );
    }

    public function export_enrollment_report() {
        $this->assert_admin();
        check_admin_referer( 'tq_export_enrollment_report' );

        $selected_instance_id = isset( $_POST['class_instance_id'] ) ? absint( wp_unslash( $_POST['class_instance_id'] ) ) : 0;
        $rows                 = $this->db->get_enrollment_report_rows( $selected_instance_id );

        $filename = 'tq-enrollment-report-' . gmdate( 'Ymd-His' ) . '.csv';
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );

        $output = fopen( 'php://output', 'w' );
        if ( false === $output ) {
            exit;
        }

        fputcsv( $output, array( 'user_email', 'user_name', 'class_name', 'course_code', 'class_instance_id', 'start_date', 'end_date', 'access_start', 'access_end', 'enrollment_date', 'status' ) );

        $today = current_time( 'Y-m-d' );
        foreach ( $rows as $row ) {
            $access_end    = ! empty( $row['access_end'] ) ? (string) $row['access_end'] : '';
            $access_start  = ! empty( $row['access_start'] ) ? (string) $row['access_start'] : '';
            $access_status = 'inactive';
            if ( '' !== $access_end ) {
                if ( $today < $access_start ) {
                    $access_status = 'not_yet_available';
                } elseif ( $today > $access_end ) {
                    $access_status = 'expired';
                } else {
                    $access_status = 'active';
                }
            }

            fputcsv(
                $output,
                array(
                    (string) ( $row['user_email'] ?? '' ),
                    (string) ( $row['display_name'] ?? '' ),
                    (string) ( $row['class_name'] ?? '' ),
                    (string) ( $row['course_code'] ?? '' ),
                    (string) ( $row['class_instance_id'] ?? '' ),
                    (string) ( $row['start_date'] ?? '' ),
                    (string) ( $row['end_date'] ?? '' ),
                    (string) ( $row['access_start'] ?? '' ),
                    (string) ( $row['access_end'] ?? '' ),
                    (string) ( $row['enrollment_date'] ?? '' ),
                    (string) $access_status,
                )
            );
        }

        fclose( $output );
        exit;
    }

    public function save_settings() {
        $this->assert_admin();
        check_admin_referer( 'tq_save_settings' );

        $study_limit    = isset( $_POST['study_limit'] ) ? absint( wp_unslash( $_POST['study_limit'] ) ) : 100;
        $practice_limit = isset( $_POST['practice_limit'] ) ? absint( wp_unslash( $_POST['practice_limit'] ) ) : 35;

        if ( $study_limit <= 0 ) {
            $study_limit = 100;
        }

        if ( $practice_limit <= 0 ) {
            $practice_limit = 35;
        }

        update_option( 'tq_study_limit', $study_limit );
        update_option( 'tq_practice_limit', $practice_limit );

        $this->redirect_with_notice( 'tq-settings', 'settings_saved' );
    }

    public function save_integration_checks() {
        $this->assert_admin();
        check_admin_referer( 'tq_save_integration_checks' );

        $allowed = array(
            'admin_flow',
            'customer_purchase',
            'webhook_entitlements',
            'quiz_access',
            'workbook_access',
            'expiry_enforced',
            'roster_export',
            'duplicate_purchase_blocked',
            'unmapped_product_error_logged',
            'existing_user_reused',
        );

        $selected = isset( $_POST['checks'] ) ? (array) wp_unslash( $_POST['checks'] ) : array();
        $selected = array_map( 'sanitize_key', $selected );
        $selected = array_values( array_intersect( $selected, $allowed ) );

        $payload = array();
        foreach ( $allowed as $key ) {
            $payload[ $key ] = in_array( $key, $selected, true ) ? 1 : 0;
        }

        update_option( 'tq_integration_checks', $payload );
        $this->redirect_with_notice( 'tq-integration-qa', 'integration_checks_saved' );
    }

    public function render_launch_readiness_page() {
        $this->assert_admin();

        $keys = array(
            'func_study_retry',
            'func_practice_scoring',
            'access_unauthorized_blocked',
            'access_entitled_allowed',
            'sec_nonce_capability',
            'sec_sanitize_escape',
            'sec_rate_limit_reviewed',
            'perf_question_load',
            'perf_db_indexes',
            'ops_backup_rollback',
            'ops_monitoring_logging',
            'uat_signoff',
        );

        $labels = array(
            'func_study_retry'            => 'Functional QA: Study mode retry behavior',
            'func_practice_scoring'       => 'Functional QA: Practice scoring and review correctness',
            'access_unauthorized_blocked' => 'Access QA: unauthorized users blocked',
            'access_entitled_allowed'     => 'Access QA: entitled users allowed',
            'sec_nonce_capability'        => 'Security QA: nonce and capability enforcement',
            'sec_sanitize_escape'         => 'Security QA: sanitize input and escape output',
            'sec_rate_limit_reviewed'     => 'Security QA: endpoint rate limiting reviewed',
            'perf_question_load'          => 'Performance QA: question load strategy and pagination',
            'perf_db_indexes'             => 'Performance QA: database index review and tuning',
            'ops_backup_rollback'         => 'Operations: backup and rollback plan prepared',
            'ops_monitoring_logging'      => 'Operations: monitoring and logging checklist prepared',
            'uat_signoff'                 => 'UAT signoff completed',
        );

        $state = get_option( 'tq_launch_readiness', array() );
        if ( ! is_array( $state ) ) {
            $state = array();
        }

        $notes             = isset( $state['notes'] ) ? (string) $state['notes'] : '';
        $target_launch_date = isset( $state['target_launch_date'] ) ? (string) $state['target_launch_date'] : '';

        $checked_total = 0;
        foreach ( $keys as $key ) {
            if ( ! empty( $state[ $key ] ) ) {
                $checked_total++;
            }
        }

        echo '<div class="wrap tq-admin">';
        echo '<h1 class="tq-title">Launch Readiness (Phase 7)</h1>';
        $this->render_notice();

        echo '<div class="tq-card" style="max-width:1200px; margin-bottom:16px;">';
        echo '<h2>Progress Snapshot</h2>';
        echo '<p><strong>Completed items:</strong> ' . esc_html( $checked_total ) . ' / ' . esc_html( count( $keys ) ) . '</p>';
        echo '<p><strong>Target launch date:</strong> ' . ( '' !== $target_launch_date ? esc_html( $target_launch_date ) : 'Not set' ) . '</p>';
        echo '</div>';

        echo '<div class="tq-card" style="max-width:1200px; margin-bottom:16px;">';
        echo '<h2>QA, Security, and Operations Checklist</h2>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'tq_save_launch_readiness' );
        echo '<input type="hidden" name="action" value="tq_save_launch_readiness" />';
        echo '<table class="widefat striped tq-modern-table"><tbody>';
        foreach ( $keys as $key ) {
            $is_checked = ! empty( $state[ $key ] );
            echo '<tr>';
            echo '<td style="width:32px;"><input type="checkbox" name="checks[]" value="' . esc_attr( $key ) . '" ' . checked( $is_checked, true, false ) . ' /></td>';
            echo '<td>' . esc_html( $labels[ $key ] ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p style="margin-top:12px;"><label for="tq-target-launch-date"><strong>Target launch date</strong></label><br />';
        echo '<input id="tq-target-launch-date" type="date" name="target_launch_date" value="' . esc_attr( $target_launch_date ) . '" /></p>';
        echo '<p><label for="tq-launch-notes"><strong>Notes / blockers</strong></label><br />';
        echo '<textarea id="tq-launch-notes" name="notes" rows="6" class="large-text" placeholder="Record remaining blockers, risk decisions, and who signed off what.">' . esc_textarea( $notes ) . '</textarea></p>';
        submit_button( 'Save Launch Readiness', 'primary tq-btn-primary' );
        echo '</form>';
        echo '</div>';

        echo '<div class="tq-card" style="max-width:1200px;">';
        echo '<h2>Useful Review Pages</h2>';
        echo '<ul style="list-style:disc; padding-left:18px;">';
        echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=tq-integration-qa' ) ) . '">Integration QA</a></li>';
        echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=tq-provisioning-logs' ) ) . '">Provisioning Logs</a></li>';
        echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=tq-enrollment-reports' ) ) . '">Enrollment Reports</a></li>';
        echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=tq-settings' ) ) . '">Settings</a></li>';
        echo '</ul>';
        echo '</div>';

        echo '</div>';
    }

    public function save_launch_readiness() {
        $this->assert_admin();
        check_admin_referer( 'tq_save_launch_readiness' );

        $allowed = array(
            'func_study_retry',
            'func_practice_scoring',
            'access_unauthorized_blocked',
            'access_entitled_allowed',
            'sec_nonce_capability',
            'sec_sanitize_escape',
            'sec_rate_limit_reviewed',
            'perf_question_load',
            'perf_db_indexes',
            'ops_backup_rollback',
            'ops_monitoring_logging',
            'uat_signoff',
        );

        $selected = isset( $_POST['checks'] ) ? (array) wp_unslash( $_POST['checks'] ) : array();
        $selected = array_map( 'sanitize_key', $selected );
        $selected = array_values( array_intersect( $selected, $allowed ) );

        $payload = array();
        foreach ( $allowed as $key ) {
            $payload[ $key ] = in_array( $key, $selected, true ) ? 1 : 0;
        }

        $payload['target_launch_date'] = isset( $_POST['target_launch_date'] ) ? sanitize_text_field( wp_unslash( $_POST['target_launch_date'] ) ) : '';
        $payload['notes']              = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

        update_option( 'tq_launch_readiness', $payload );
        $this->redirect_with_notice( 'tq-launch-readiness', 'launch_readiness_saved' );
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
            'booking_class_created' => 'Booking class created.',
            'booking_class_updated' => 'Booking class updated.',
            'booking_class_deleted' => 'Booking class deleted.',
            'class_instance_created' => 'Class instance created.',
            'class_instance_updated' => 'Class instance updated.',
            'class_instance_deleted' => 'Class instance deleted.',
            'provisioning_simulation_invalid_order' => 'Please provide a valid WooCommerce order ID.',
            'provisioning_simulation_success' => 'Provisioning simulation completed. Check result details below.',
            'provisioning_simulation_error' => 'Provisioning simulation failed. Check error details below.',
            'enrollment_report_exported' => 'Enrollment report exported.',
            'settings_saved' => 'Settings saved.',
            'integration_checks_saved' => 'Integration QA checklist progress saved.',
            'launch_readiness_saved' => 'Launch readiness progress saved.',
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
