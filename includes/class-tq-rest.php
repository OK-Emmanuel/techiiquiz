<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TQ_REST {
    private $quiz_service;
    private $session_service;

    public function __construct( TQ_Quiz_Service $quiz_service, TQ_Session_Service $session_service ) {
        $this->quiz_service    = $quiz_service;
        $this->session_service = $session_service;
    }

    public function register() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route(
            'techiquiz/v1',
            '/set/(?P<set_id>\\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_set' ),
                'permission_callback' => array( $this, 'can_access' ),
            )
        );

        register_rest_route(
            'techiquiz/v1',
            '/session/start',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'start_session' ),
                'permission_callback' => array( $this, 'can_access' ),
            )
        );

        register_rest_route(
            'techiquiz/v1',
            '/session/answer',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'submit_answer' ),
                'permission_callback' => array( $this, 'can_access' ),
            )
        );

        register_rest_route(
            'techiquiz/v1',
            '/session/complete',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'complete_session' ),
                'permission_callback' => array( $this, 'can_access' ),
            )
        );
    }

    public function can_access() {
        return is_user_logged_in();
    }

    public function get_set( WP_REST_Request $request ) {
        $set_id = (int) $request->get_param( 'set_id' );
        return rest_ensure_response( $this->quiz_service->get_set_payload( $set_id ) );
    }

    public function start_session( WP_REST_Request $request ) {
        $set_id = (int) $request->get_param( 'set_id' );
        $mode   = sanitize_key( $request->get_param( 'mode' ) );

        $result = $this->session_service->start_session( get_current_user_id(), $set_id, $mode );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }

    public function submit_answer( WP_REST_Request $request ) {
        $session_id  = (int) $request->get_param( 'session_id' );
        $question_id = (int) $request->get_param( 'question_id' );
        $choice_id   = (int) $request->get_param( 'choice_id' );

        $result = $this->session_service->submit_answer( $session_id, $question_id, $choice_id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }

    public function complete_session( WP_REST_Request $request ) {
        $session_id = (int) $request->get_param( 'session_id' );

        $result = $this->session_service->complete_practice_session( $session_id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }
}
