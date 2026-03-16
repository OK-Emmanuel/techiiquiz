<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TQ_Quiz_Service {
    private $db;

    public function __construct( TQ_DB $db ) {
        $this->db = $db;
    }

    public function get_question_limit_for_mode( $mode ) {
        return 'practice' === sanitize_key( $mode ) ? 100 : 35;
    }

    public function get_set_payload( $set_id ) {
        $set          = $this->db->get_set( $set_id );
        $course_title = '';
        $set_mode     = $set ? sanitize_key( $set['mode'] ?? 'study' ) : 'study';

        if ( $set && ! empty( $set['course_id'] ) ) {
            $course       = $this->db->get_course( (int) $set['course_id'] );
            $course_title = $course ? ( $course['title'] ?? '' ) : '';
        }

        $questions = $this->db->get_set_questions( $set_id, $this->get_question_limit_for_mode( $set_mode ) );

        foreach ( $questions as &$question ) {
            $question['question_identifier'] = $this->build_question_identifier( $question, $set, $course_title, $set_mode );
            foreach ( $question['choices'] as &$choice ) {
                unset( $choice['is_correct'] );
            }
            unset( $choice );
        }
        unset( $question );

        return array(
            'set_id'       => (int) $set_id,
            'set_title'    => $set ? ( $set['title'] ?? '' ) : '',
            'day_label'    => $set ? ( $set['day_label'] ?? '' ) : '',
            'course_title' => $course_title,
            'mode'         => $set_mode,
            'quiz_length'  => count( $questions ),
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
        );
    }

    public function calculate_practice_score( $session_id, $set_id, $mode = 'practice' ) {
        $questions = $this->db->get_set_questions( $set_id, $this->get_question_limit_for_mode( $mode ) );
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
            $selected          = isset( $by_question[ $question_id ] ) ? (int) $by_question[ $question_id ]['selected_choice_id'] : 0;
            $correct_choice_id = 0;

            $review_choices = array();
            foreach ( $question['choices'] as $choice ) {
                $choice_id = (int) $choice['id'];
                $is_correct = (int) $choice['is_correct'] === 1;
                $is_selected = $choice_id === $selected;

                if ( $is_correct ) {
                    $correct_choice_id = $choice_id;
                }

                $review_choices[] = array(
                    'id'               => $choice_id,
                    'choice_key'       => $choice['choice_key'],
                    'choice_text'      => $choice['choice_text'],
                    'is_correct'       => $is_correct,
                    'is_selected'      => $is_selected,
                    'is_wrong_selected'=> $is_selected && ! $is_correct,
                );
            }

            if ( $selected > 0 && $selected === $correct_choice_id ) {
                $correct++;
                continue;
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

    private function build_question_identifier( $question, $set, $course_title, $mode ) {
        $meta_identifier = '';

        if ( ! empty( $question['prompt_meta'] ) ) {
            $meta = json_decode( (string) $question['prompt_meta'], true );
            if ( is_array( $meta ) ) {
                $meta_identifier = (string) ( $meta['identifier'] ?? $meta['question_identifier'] ?? $meta['internal_id'] ?? $meta['ident'] ?? '' );
            }
        }

        if ( '' !== trim( $meta_identifier ) ) {
            return trim( $meta_identifier );
        }

        $display_order = (int) ( $question['display_order'] ?? 0 );
        $set_code      = $this->derive_set_code( $set, $course_title, $mode );

        if ( '' !== $set_code && $display_order > 0 ) {
            return $set_code . '.' . $display_order;
        }

        return 'Q-' . (int) ( $question['id'] ?? 0 );
    }

    private function derive_set_code( $set, $course_title, $mode ) {
        $title = strtoupper( (string) ( $set['title'] ?? '' ) );
        if ( preg_match( '/\b([A-Z]{2,}T?\d+)\b/', $title, $match ) ) {
            return $match[1];
        }

        $day_label = (string) ( $set['day_label'] ?? '' );
        $day       = 0;
        if ( preg_match( '/(\d+)/', $day_label, $match ) ) {
            $day = (int) $match[1];
        }

        if ( $day <= 0 ) {
            return '';
        }

        $course = strtolower( $course_title );
        $mode   = sanitize_key( $mode );

        if ( false !== strpos( $course, 'drill' ) ) {
            return ( 'practice' === $mode ? 'DRT' : 'DR' ) . $day;
        }

        if ( false !== strpos( $course, 'subsea' ) ) {
            return ( 'practice' === $mode ? 'SST' : 'SS' ) . $day;
        }

        if ( false !== strpos( $course, 'old' ) ) {
            return ( 'practice' === $mode ? 'DST' : 'DS' ) . $day;
        }

        return ( 'practice' === $mode ? 'PT' : 'ST' ) . $day;
    }
}
