<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TQ_Import_Service {
    private $db;

    public function __construct( TQ_DB $db ) {
        $this->db = $db;
    }

    public function process_file( $file_path, $extension, $dry_run = true, $upsert = false, $context = array() ) {
        $sheets = $this->load_rows_by_sheet( $file_path, $extension );

        $source_filename = isset( $context['original_filename'] ) ? (string) $context['original_filename'] : basename( (string) $file_path );
        $source_group    = isset( $context['source_group'] ) ? sanitize_title( $context['source_group'] ) : '';

        $summary = array(
            'dry_run'         => $dry_run,
            'created'         => 0,
            'updated'         => 0,
            'failed'          => 0,
            'errors'          => array(),
            'source_filename' => $source_filename,
            'source_group'    => $source_group,
        );

        $touched_set_ids = array();

        foreach ( $sheets as $sheet_name => $rows ) {
            foreach ( $rows as $index => $row ) {
                if ( $this->is_placeholder_row( $row ) ) {
                    continue;
                }

                $line_number = $index + 2;
                $normalized  = $this->normalize_row(
                    $row,
                    $sheet_name,
                    $source_filename,
                    $source_group,
                    $index
                );
                $errors      = $this->validate_row( $normalized );

                if ( ! empty( $errors ) ) {
                    $summary['failed']++;
                    $summary['errors'][] = sprintf(
                        '%s row %d: %s',
                        $sheet_name,
                        $line_number,
                        implode( ' | ', $errors )
                    );
                    continue;
                }

                if ( $dry_run ) {
                    $summary['created']++;
                    continue;
                }

                try {
                    $result = $this->persist_row( $normalized, $upsert );
                    $summary[ $result['action'] ]++;
                    $touched_set_ids[] = (int) $result['set_id'];
                } catch ( Exception $exception ) {
                    $summary['failed']++;
                    $summary['errors'][] = sprintf(
                        '%s row %d: %s',
                        $sheet_name,
                        $line_number,
                        $exception->getMessage()
                    );
                }
            }
        }

        if ( ! $dry_run ) {
            $touched_set_ids = array_unique( $touched_set_ids );
            foreach ( $touched_set_ids as $set_id ) {
                $this->db->sync_set_question_count( $set_id );
            }
        }

        return $summary;
    }

    private function load_rows_by_sheet( $file_path, $extension ) {
        if ( 'csv' === $extension ) {
            return array( 'CSV' => $this->read_csv( $file_path ) );
        }

        if ( in_array( $extension, array( 'xlsx', 'xls' ), true ) ) {
            if ( ! class_exists( '\\PhpOffice\\PhpSpreadsheet\\IOFactory' ) ) {
                throw new RuntimeException( 'XLSX/XLS import requires PhpSpreadsheet. Install it to enable Excel import, or upload CSV.' );
            }

            return $this->read_excel( $file_path );
        }

        throw new RuntimeException( 'Unsupported file format. Use CSV, XLSX, or XLS.' );
    }

    private function read_csv( $file_path ) {
        $rows    = array();
        $handle  = fopen( $file_path, 'r' );

        if ( false === $handle ) {
            throw new RuntimeException( 'Unable to read CSV file.' );
        }

        $headers = fgetcsv( $handle );
        if ( empty( $headers ) ) {
            fclose( $handle );
            return $rows;
        }

        $headers = array_map( array( $this, 'normalize_header' ), $headers );

        while ( ( $line = fgetcsv( $handle ) ) !== false ) {
            $assoc = array();
            foreach ( $headers as $index => $header ) {
                $assoc[ $header ] = isset( $line[ $index ] ) ? trim( (string) $line[ $index ] ) : '';
            }
            $rows[] = $assoc;
        }

        fclose( $handle );
        return $rows;
    }

    private function read_excel( $file_path ) {
        $reader       = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile( $file_path );
        $spreadsheet  = $reader->load( $file_path );
        $sheets_rows  = array();

        foreach ( $spreadsheet->getWorksheetIterator() as $sheet ) {
            $raw_rows = $sheet->toArray( '', true, true, false );
            if ( empty( $raw_rows ) ) {
                continue;
            }

            $headers = array_map( array( $this, 'normalize_header' ), array_shift( $raw_rows ) );
            $rows    = array();

            foreach ( $raw_rows as $line ) {
                $assoc = array();
                foreach ( $headers as $index => $header ) {
                    $assoc[ $header ] = isset( $line[ $index ] ) ? trim( (string) $line[ $index ] ) : '';
                }
                if ( $this->is_empty_row( $assoc ) ) {
                    continue;
                }
                $rows[] = $assoc;
            }

            $sheets_rows[ $sheet->getTitle() ] = $rows;
        }

        return $sheets_rows;
    }

    private function is_empty_row( $row ) {
        foreach ( $row as $value ) {
            if ( '' !== (string) $value ) {
                return false;
            }
        }
        return true;
    }

    private function is_placeholder_row( $row ) {
        $has_value = false;

        foreach ( $row as $value ) {
            $trimmed = trim( (string) $value );
            if ( '' === $trimmed ) {
                continue;
            }

            $has_value = true;
            if ( '...' !== $trimmed ) {
                return false;
            }
        }

        return $has_value;
    }

    private function normalize_header( $header ) {
        $header = strtolower( trim( (string) $header ) );
        $header = preg_replace( '/[^a-z0-9]+/', '_', $header );
        return trim( (string) $header, '_' );
    }

    private function normalize_row( $row, $sheet_name, $source_filename, $source_group, $row_index ) {
        $question_type = sanitize_key( (string) $this->pick_value( $row, array( 'question_type' ) ) );

        if ( '' === $question_type ) {
            $sheet_lower = strtolower( (string) $sheet_name );
            $question_type = preg_match( '/math|calculation|formula/', $sheet_lower ) ? 'objective_math' : 'single_choice';
        }

        $question_column = $this->find_question_column_key( $row );
        $question_text   = '';
        if ( '' !== $question_column && isset( $row[ $question_column ] ) ) {
            $question_text = (string) $row[ $question_column ];
        }

        if ( '' === $question_text ) {
            $question_text = (string) $this->pick_value( $row, array( 'question_text', 'question', 'questions' ) );
        }

        if ( '' === trim( $question_text ) ) {
            $question_text = $this->infer_question_text_from_row( $row );
        }

        $display_order = (int) $this->pick_value( $row, array( 'display_order', 'ct', 'serial_number', 'serial', 'no', 'number' ) );
        if ( $display_order <= 0 ) {
            $display_order = (int) $row_index + 1;
        }

        $day_label = sanitize_text_field( (string) $this->pick_value( $row, array( 'day_label', 'day' ) ) );
        if ( '' === $day_label ) {
            $day_label = $this->derive_day_label_from_filename( $source_filename );
        } elseif ( is_numeric( $day_label ) ) {
            $day_label = 'Day ' . (int) $day_label;
        }

        $course_slug = sanitize_title( (string) $this->pick_value( $row, array( 'course_slug' ) ) );
        if ( '' === $course_slug ) {
            $course_slug = $this->infer_course_slug( $source_filename, $source_group );
        }

        $mode = sanitize_key( (string) $this->pick_value( $row, array( 'mode' ) ) );
        if ( ! in_array( $mode, array( 'study', 'practice' ), true ) ) {
            $mode = $this->infer_mode( $source_filename );
        }

        $set_title = sanitize_text_field( (string) $this->pick_value( $row, array( 'set_title' ) ) );
        if ( '' === $set_title ) {
            $set_title = $this->derive_set_title( $question_column, $source_filename, $day_label, $mode, $course_slug );
        }

        $correct_choice = strtoupper( sanitize_text_field( (string) $this->pick_value( $row, array( 'correct_choice', 'ans', 'answer' ) ) ) );

        $normalized = array(
            'course_slug'   => $course_slug,
            'set_title'     => $set_title,
            'day_label'     => $day_label,
            'mode'          => $mode,
            'question_text' => wp_kses_post( $question_text ),
            'choice_a'      => sanitize_text_field( (string) $this->pick_value( $row, array( 'choice_a', 'a', 'option_a' ) ) ),
            'choice_b'      => sanitize_text_field( (string) $this->pick_value( $row, array( 'choice_b', 'b', 'option_b' ) ) ),
            'choice_c'      => sanitize_text_field( (string) $this->pick_value( $row, array( 'choice_c', 'c', 'option_c' ) ) ),
            'choice_d'      => sanitize_text_field( (string) $this->pick_value( $row, array( 'choice_d', 'd', 'option_d' ) ) ),
            'correct_choice'=> $correct_choice,
            'display_order' => $display_order,
            'question_type' => in_array( $question_type, array( 'single_choice', 'objective_math' ), true ) ? $question_type : 'single_choice',
            'prompt_format' => sanitize_key( (string) $this->pick_value( $row, array( 'prompt_format' ) ) ) ?: 'plain',
            'explanation'   => wp_kses_post( (string) $this->pick_value( $row, array( 'explanation' ) ) ),
        );

        if ( ! in_array( $normalized['correct_choice'], array( 'A', 'B', 'C', 'D' ), true ) ) {
            $normalized['correct_choice'] = $this->resolve_correct_choice_from_value( $normalized );
        }

        return $normalized;
    }

    private function pick_value( $row, $aliases ) {
        foreach ( $aliases as $alias ) {
            $key = $this->normalize_header( $alias );
            if ( isset( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) {
                return $row[ $key ];
            }
        }

        return '';
    }

    private function find_question_column_key( $row ) {
        $blocked = array(
            'ct',
            'day',
            'topic',
            'spec',
            'ident',
            'a',
            'b',
            'c',
            'd',
            'ans',
            'answer',
            'form',
            'question_type',
            'prompt_format',
            'explanation',
            'course_slug',
            'set_title',
            'day_label',
            'mode',
            'display_order',
        );

        foreach ( array_keys( $row ) as $key ) {
            if ( in_array( $key, $blocked, true ) ) {
                continue;
            }

            if ( preg_match( '/question/', $key ) ) {
                return $key;
            }
        }

        return '';
    }

    private function infer_question_text_from_row( $row ) {
        $skip_keys = array(
            'ct',
            'day',
            'topic',
            'spec',
            'ident',
            'a',
            'b',
            'c',
            'd',
            'ans',
            'answer',
            'form',
            'form_answer',
            'course_slug',
            'set_title',
            'day_label',
            'mode',
            'display_order',
            'question_type',
            'prompt_format',
            'explanation',
        );

        $best_value = '';
        $best_score = 0;

        foreach ( $row as $key => $value ) {
            if ( in_array( $key, $skip_keys, true ) ) {
                continue;
            }

            $text = trim( (string) $value );
            if ( '' === $text ) {
                continue;
            }

            $score = strlen( $text );
            if ( preg_match( '/\?|\./', $text ) ) {
                $score += 10;
            }

            if ( $score > $best_score ) {
                $best_score = $score;
                $best_value = $text;
            }
        }

        return $best_value;
    }

    private function resolve_correct_choice_from_value( $row ) {
        $answer_value = strtoupper( trim( (string) $row['correct_choice'] ) );
        if ( '' === $answer_value ) {
            return '';
        }

        if ( preg_match( '/^\(?\s*([A-D])\s*\)?[\.)]?$/', $answer_value, $match ) ) {
            return $match[1];
        }

        if ( in_array( $answer_value, array( '1', '2', '3', '4' ), true ) ) {
            $map = array(
                '1' => 'A',
                '2' => 'B',
                '3' => 'C',
                '4' => 'D',
            );

            return $map[ $answer_value ];
        }

        $choices = array(
            'A' => strtoupper( trim( (string) $row['choice_a'] ) ),
            'B' => strtoupper( trim( (string) $row['choice_b'] ) ),
            'C' => strtoupper( trim( (string) $row['choice_c'] ) ),
            'D' => strtoupper( trim( (string) $row['choice_d'] ) ),
        );

        foreach ( $choices as $key => $choice_text ) {
            if ( '' === $choice_text ) {
                continue;
            }

            if ( $answer_value === $choice_text ) {
                return $key;
            }
        }

        return '';
    }

    private function extract_available_choices( $row ) {
        $choices = array(
            'A' => trim( (string) $row['choice_a'] ),
            'B' => trim( (string) $row['choice_b'] ),
            'C' => trim( (string) $row['choice_c'] ),
            'D' => trim( (string) $row['choice_d'] ),
        );

        return array_filter(
            $choices,
            static function ( $value ) {
                return '' !== $value;
            }
        );
    }

    private function infer_course_slug( $source_filename, $source_group ) {
        if ( in_array( $source_group, array( 'subsea-questions', 'old-quiz', 'updated-drill-questions' ), true ) ) {
            return $source_group;
        }

        $stem = strtoupper( pathinfo( $source_filename, PATHINFO_FILENAME ) );
        $stem = preg_replace( '/-SAMPLE$/', '', $stem );

        if ( preg_match( '/^SS|^SST/', $stem ) ) {
            return 'subsea-questions';
        }

        if ( preg_match( '/^DR|^DRT/', $stem ) ) {
            return 'updated-drill-questions';
        }

        if ( preg_match( '/^DS|^DST|^OWO|^OWOT|^OWS|^OWST/', $stem ) ) {
            return 'old-quiz';
        }

        return 'quiz-import';
    }

    private function infer_mode( $source_filename ) {
        $stem = strtoupper( pathinfo( $source_filename, PATHINFO_FILENAME ) );
        $stem = preg_replace( '/-SAMPLE$/', '', $stem );

        if ( preg_match( '/(SST|DST|DRT|OWOT|OWST|[A-Z]{2,}T\d+)/', $stem ) ) {
            return 'practice';
        }

        return 'study';
    }

    private function derive_day_label_from_filename( $source_filename ) {
        $stem = strtoupper( pathinfo( $source_filename, PATHINFO_FILENAME ) );
        $stem = preg_replace( '/-SAMPLE$/', '', $stem );

        if ( preg_match( '/(\d+)/', $stem, $matches ) ) {
            return 'Day ' . (int) $matches[1];
        }

        return '';
    }

    private function derive_set_title( $question_column, $source_filename, $day_label, $mode, $course_slug ) {
        if ( '' !== $question_column ) {
            $title = ucwords( str_replace( '_', ' ', $question_column ) );
            if ( '' !== trim( $title ) ) {
                return trim( $title );
            }
        }

        $base_name = strtoupper( pathinfo( $source_filename, PATHINFO_FILENAME ) );
        $base_name = preg_replace( '/-SAMPLE$/', '', $base_name );
        $mode_label = 'practice' === $mode ? 'Practice Test' : 'Study Guide';
        $course     = ucwords( str_replace( '-', ' ', $course_slug ) );

        if ( '' !== $day_label ) {
            return sprintf( '%s %s %s', $course, $day_label, $mode_label );
        }

        return sprintf( '%s %s %s', $course, $base_name, $mode_label );
    }

    private function validate_row( $row ) {
        $errors = array();

        $required_fields = array(
            'course_slug',
            'set_title',
            'day_label',
            'mode',
            'question_text',
            'choice_a',
            'choice_b',
        );

        foreach ( $required_fields as $field ) {
            if ( empty( $row[ $field ] ) ) {
                $errors[] = sprintf( '%s is required', $field );
            }
        }

        if ( ! in_array( $row['mode'], array( 'study', 'practice' ), true ) ) {
            $errors[] = 'mode must be study or practice';
        }

        $available_choices = $this->extract_available_choices( $row );

        if ( count( $available_choices ) < 2 ) {
            $errors[] = 'at least two choices are required';
        }

        if ( '' === $row['correct_choice'] ) {
            $errors[] = 'correct_choice is required';
        } elseif ( ! isset( $available_choices[ $row['correct_choice'] ] ) ) {
            $errors[] = 'correct_choice must match one of the available choices';
        }

        if ( (int) $row['display_order'] <= 0 ) {
            $errors[] = 'display_order must be a positive integer';
        }

        return $errors;
    }

    private function persist_row( $row, $upsert ) {
        $course = $this->db->get_course_by_slug( $row['course_slug'] );
        if ( empty( $course ) ) {
            $this->db->create_course( $row['course_slug'], ucwords( str_replace( '-', ' ', $row['course_slug'] ) ), 1 );
            $course = $this->db->get_course_by_slug( $row['course_slug'] );
        }

        if ( empty( $course ) ) {
            throw new RuntimeException( 'Unable to create or find course.' );
        }

        $set = $this->db->get_set_by_identity(
            (int) $course['id'],
            $row['set_title'],
            $row['day_label'],
            $row['mode']
        );

        if ( empty( $set ) ) {
            $set_id = $this->db->create_set(
                array(
                    'course_id'      => (int) $course['id'],
                    'day_label'      => $row['day_label'],
                    'mode'           => $row['mode'],
                    'title'          => $row['set_title'],
                    'question_count' => 0,
                    'version'        => 1,
                    'active'         => 1,
                )
            );
            $set = $this->db->get_set( $set_id );
        }

        if ( empty( $set ) ) {
            throw new RuntimeException( 'Unable to create or find set.' );
        }

        $existing = $this->db->get_question_by_set_and_order( (int) $set['id'], (int) $row['display_order'] );

        if ( ! empty( $existing ) && ! $upsert ) {
            $incoming_prompt = strtolower( trim( wp_strip_all_tags( (string) $row['question_text'] ) ) );
            $existing_prompt = strtolower( trim( wp_strip_all_tags( (string) $existing['prompt'] ) ) );

            if ( $incoming_prompt === $existing_prompt ) {
                throw new RuntimeException( 'Duplicate display_order in set. Enable upsert to update existing rows.' );
            }

            $row['display_order'] = $this->db->get_next_display_order_for_set( (int) $set['id'] );
            $existing = array();
        }

        $payload = array(
            'set_id'        => (int) $set['id'],
            'question_type' => $row['question_type'],
            'prompt'        => $row['question_text'],
            'prompt_format' => $row['prompt_format'],
            'explanation'   => $row['explanation'],
            'display_order' => (int) $row['display_order'],
            'active'        => 1,
        );

        $choices = array(
            'A' => $row['choice_a'],
            'B' => $row['choice_b'],
            'C' => $row['choice_c'],
            'D' => $row['choice_d'],
        );

        $choices = array_filter(
            $choices,
            static function ( $value ) {
                return '' !== trim( (string) $value );
            }
        );

        if ( ! empty( $existing ) ) {
            $question_id = (int) $existing['id'];
            $this->db->update_question( $question_id, $payload );
            $this->db->replace_question_choices( $question_id, $choices, $row['correct_choice'] );

            return array(
                'action' => 'updated',
                'set_id' => (int) $set['id'],
            );
        }

        $question_id = $this->db->create_question( $payload );
        if ( $question_id <= 0 ) {
            throw new RuntimeException( 'Failed to create question.' );
        }

        $this->db->replace_question_choices( $question_id, $choices, $row['correct_choice'] );

        return array(
            'action' => 'created',
            'set_id' => (int) $set['id'],
        );
    }
}
