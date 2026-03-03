<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TQ_Session_Service {
    private $db;
    private $quiz_service;

    public function __construct( TQ_DB $db, TQ_Quiz_Service $quiz_service ) {
        $this->db           = $db;
        $this->quiz_service = $quiz_service;
    }

    public function start_session( $user_id, $set_id, $mode ) {
        $mode = sanitize_key( $mode );
        if ( ! in_array( $mode, array( 'study', 'practice' ), true ) ) {
            return new WP_Error( 'invalid_mode', 'Mode must be study or practice.' );
        }

        $session_id = $this->db->create_session( $user_id, $set_id, $mode );

        do_action(
            'tq/quiz_session_started',
            array(
                'session_id' => $session_id,
                'user_id'    => (int) $user_id,
                'set_id'     => (int) $set_id,
                'mode'       => $mode,
            )
        );

        return array(
            'session_id' => $session_id,
            'mode'       => $mode,
        );
    }

    public function submit_answer( $session_id, $question_id, $choice_id ) {
        $session = $this->db->get_session( $session_id );
        if ( empty( $session ) ) {
            return new WP_Error( 'session_not_found', 'Session not found.' );
        }

        if ( 'completed' === $session['status'] ) {
            return new WP_Error( 'session_completed', 'This session is already completed.' );
        }

        $evaluation = $this->quiz_service->evaluate_choice( $question_id, $choice_id );
        if ( ! $evaluation['valid'] ) {
            return new WP_Error( 'invalid_answer', $evaluation['message'] );
        }

        if ( 'study' === $session['mode'] ) {
            $attempt_no = $this->db->get_last_attempt_number( $session_id, $question_id ) + 1;
            $this->db->store_answer( $session_id, $question_id, $choice_id, $evaluation['is_correct'], $attempt_no );

            return array(
                'mode'              => 'study',
                'is_correct'        => (bool) $evaluation['is_correct'],
                'can_advance'       => (bool) $evaluation['is_correct'],
                'message'           => $evaluation['message'],
                'correct_choice_id' => (int) $evaluation['correct_choice_id'],
            );
        }

        $this->db->upsert_practice_answer( $session_id, $question_id, $choice_id, $evaluation['is_correct'] );

        return array(
            'mode'        => 'practice',
            'saved'       => true,
            'message'     => 'Answer recorded.',
            'is_correct'  => null,
            'can_advance' => true,
        );
    }

    public function complete_practice_session( $session_id ) {
        $session = $this->db->get_session( $session_id );
        if ( empty( $session ) ) {
            return new WP_Error( 'session_not_found', 'Session not found.' );
        }

        if ( 'practice' !== $session['mode'] ) {
            return new WP_Error( 'invalid_mode', 'Only practice sessions can be completed with scoring.' );
        }

        $result = $this->quiz_service->calculate_practice_score( $session_id, (int) $session['set_id'] );

        $this->db->complete_session( $session_id, $result['score_percent'] );

        do_action(
            'tq/quiz_session_completed',
            array(
                'session_id'    => (int) $session_id,
                'user_id'       => (int) $session['user_id'],
                'set_id'        => (int) $session['set_id'],
                'score_percent' => (float) $result['score_percent'],
            )
        );

        return $result;
    }
}
