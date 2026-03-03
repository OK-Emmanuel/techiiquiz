<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TQ_Import_Service {
    private $db;

    public function __construct( TQ_DB $db ) {
        $this->db = $db;
    }

    public function process_file( $file_path, $extension, $dry_run = true, $upsert = false ) {
        $sheets = $this->load_rows_by_sheet( $file_path, $extension );

        $summary = array(
            'dry_run' => $dry_run,
            'created' => 0,
            'updated' => 0,
            'failed'  => 0,
            'errors'  => array(),
        );

        $touched_set_ids = array();

        foreach ( $sheets as $sheet_name => $rows ) {
            foreach ( $rows as $index => $row ) {
                $line_number = $index + 2;
                $normalized  = $this->normalize_row( $row, $sheet_name );
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

    private function normalize_header( $header ) {
        $header = strtolower( trim( (string) $header ) );
        return str_replace( array( ' ', '-' ), '_', $header );
    }

    private function normalize_row( $row, $sheet_name ) {
        $question_type = isset( $row['question_type'] ) ? sanitize_key( $row['question_type'] ) : '';

        if ( '' === $question_type ) {
            $sheet_lower = strtolower( (string) $sheet_name );
            $question_type = preg_match( '/math|calculation|formula/', $sheet_lower ) ? 'objective_math' : 'single_choice';
        }

        return array(
            'course_slug'   => sanitize_title( $row['course_slug'] ?? '' ),
            'set_title'     => sanitize_text_field( $row['set_title'] ?? '' ),
            'day_label'     => sanitize_text_field( $row['day_label'] ?? '' ),
            'mode'          => sanitize_key( $row['mode'] ?? '' ),
            'question_text' => wp_kses_post( $row['question_text'] ?? '' ),
            'choice_a'      => sanitize_text_field( $row['choice_a'] ?? '' ),
            'choice_b'      => sanitize_text_field( $row['choice_b'] ?? '' ),
            'choice_c'      => sanitize_text_field( $row['choice_c'] ?? '' ),
            'choice_d'      => sanitize_text_field( $row['choice_d'] ?? '' ),
            'correct_choice'=> strtoupper( sanitize_text_field( $row['correct_choice'] ?? '' ) ),
            'display_order' => absint( $row['display_order'] ?? 0 ),
            'question_type' => in_array( $question_type, array( 'single_choice', 'objective_math' ), true ) ? $question_type : 'single_choice',
            'prompt_format' => sanitize_key( $row['prompt_format'] ?? 'plain' ),
            'explanation'   => wp_kses_post( $row['explanation'] ?? '' ),
        );
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
            'choice_c',
            'choice_d',
            'correct_choice',
            'display_order',
        );

        foreach ( $required_fields as $field ) {
            if ( empty( $row[ $field ] ) ) {
                $errors[] = sprintf( '%s is required', $field );
            }
        }

        if ( ! in_array( $row['mode'], array( 'study', 'practice' ), true ) ) {
            $errors[] = 'mode must be study or practice';
        }

        if ( ! in_array( $row['correct_choice'], array( 'A', 'B', 'C', 'D' ), true ) ) {
            $errors[] = 'correct_choice must be one of A, B, C, D';
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
            throw new RuntimeException( 'Duplicate display_order in set. Enable upsert to update existing rows.' );
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
