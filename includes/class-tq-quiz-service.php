<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TQ_Quiz_Service {
    private $db;

    public function __construct( TQ_DB $db ) {
        $this->db = $db;
    }

    public function get_set_payload( $set_id ) {
        $set          = $this->db->get_set( $set_id );
        $course_title = '';

        if ( $set && ! empty( $set['course_id'] ) ) {
            $course       = $this->db->get_course( (int) $set['course_id'] );
            $course_title = $course ? ( $course['title'] ?? '' ) : '';
        }

        $questions = $this->db->get_set_questions( $set_id );

        foreach ( $questions as &$question ) {
            foreach ( $question['choices'] as &$choice ) {
                unset( $choice['is_correct'] );
            }
        }

        return array(
            'set_id'       => (int) $set_id,
            'set_title'    => $set ? ( $set['title'] ?? '' ) : '',
            'day_label'    => $set ? ( $set['day_label'] ?? '' ) : '',
            'course_title' => $course_title,
            'mode'         => $set ? ( $set['mode'] ?? 'study' ) : 'study',
            'questions'    => $questions,
        );
    }

    public function evaluate_choice( $question_id, $choice_id ) {
        $choice = $this->db->get_choice_by_id( $choice_id );
        if ( empty( $choice ) || (int) $choice['question_id'] !== (int) $question_id ) {
            return array(
                'valid'      => false,
                'is_correct' => false,
                'message'    => 'Invalid choice for question.',
            );
        }

        return array(
            'valid'      => true,
            'is_correct' => (bool) $choice['is_correct'],
            'message'    => ( (int) $choice['is_correct'] === 1 ) ? 'Correct.' : 'Incorrect — please select again.',
            'correct_choice_id' => $this->db->get_correct_choice_id( $question_id ),
        );
    }

    public function calculate_practice_score( $session_id, $set_id ) {
        $questions = $this->db->get_set_questions( $set_id );
        $answers   = $this->db->get_session_answers( $session_id );

        if ( empty( $questions ) ) {
            return array(
                'score_percent' => 0,
                'missed'        => array(),
            );
        }

        $by_question = array();
        foreach ( $answers as $answer ) {
            $by_question[ (int) $answer['question_id'] ] = $answer;
        }

        $correct = 0;
        $missed  = array();

        foreach ( $questions as $question ) {
            $question_id       = (int) $question['id'];
            $correct_choice_id = $this->db->get_correct_choice_id( $question_id );
            $selected          = isset( $by_question[ $question_id ] ) ? (int) $by_question[ $question_id ]['selected_choice_id'] : 0;

            if ( $selected > 0 && $selected === $correct_choice_id ) {
                $correct++;
                continue;
            }

            $review_choices = array();
            foreach ( $question['choices'] as $choice ) {
                $choice_id = (int) $choice['id'];
                $is_correct = (int) $choice['is_correct'] === 1;
                $is_selected = $choice_id === $selected;

                $review_choices[] = array(
                    'id'               => $choice_id,
                    'choice_key'       => $choice['choice_key'],
                    'choice_text'      => $choice['choice_text'],
                    'is_correct'       => $is_correct,
                    'is_selected'      => $is_selected,
                    'is_wrong_selected'=> $is_selected && ! $is_correct,
                );
            }

            $missed[] = array(
                'question_id'        => $question_id,
                'prompt'             => $question['prompt'],
                'question_type'      => $question['question_type'],
                'selected_choice_id' => $selected,
                'correct_choice_id'  => $correct_choice_id,
                'choices'            => $review_choices,
            );
        }

        $score_percent = round( ( $correct / count( $questions ) ) * 100, 2 );

        return array(
            'score_percent' => $score_percent,
            'missed'        => $missed,
        );
    }
}
