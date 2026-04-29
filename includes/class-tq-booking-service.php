<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TQ_Booking_Service {
    private $db;

    public function __construct( TQ_DB $db ) {
        $this->db = $db;
    }

    public function register() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_action( 'woocommerce_order_status_completed', array( $this, 'handle_order_status_completed' ) );
    }

    public function register_routes() {
        register_rest_route(
            'tq/v1',
            '/webhook/order-completed',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_order_completed_webhook' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function handle_order_status_completed( $order_id ) {
        $order_id = (int) $order_id;
        if ( $order_id <= 0 ) {
            return;
        }

        $result = $this->provision_from_order( $order_id );
        if ( is_wp_error( $result ) ) {
            $this->log_event( $order_id, 0, 0, 'order_status_completed', 'error', $result->get_error_message() );
        }
    }

    public function handle_order_completed_webhook( WP_REST_Request $request ) {
        $order_id = (int) $request->get_param( 'order_id' );

        if ( $order_id <= 0 ) {
            return new WP_Error( 'invalid_order_id', 'Missing or invalid order_id.', array( 'status' => 400 ) );
        }

        $result = $this->provision_from_order( $order_id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }

    public function provision_from_order( $order_id ) {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return new WP_Error( 'woocommerce_unavailable', 'WooCommerce is not available.', array( 'status' => 500 ) );
        }

        $order = wc_get_order( (int) $order_id );
        if ( ! $order ) {
            return new WP_Error( 'order_not_found', 'Order not found.', array( 'status' => 404 ) );
        }

        $email = sanitize_email( $order->get_billing_email() );
        if ( ! $email ) {
            return new WP_Error( 'missing_customer_email', 'Customer email is missing from order.', array( 'status' => 400 ) );
        }

        $user_id = $this->find_or_create_user( $email );
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $created_count      = 0;
        $provisioned_rows   = array();
        foreach ( $order->get_items() as $item ) {
            $product_id = (int) $item->get_product_id();
            if ( $product_id <= 0 ) {
                continue;
            }

            $class_instance = $this->db->get_class_instance_by_product_id( $product_id );
            if ( ! $class_instance ) {
                $this->log_event( $order_id, $user_id, 0, 'mapping_lookup', 'error', 'No class instance mapped to product ' . $product_id );
                continue;
            }

            $booking_class = $this->db->get_booking_class( (int) ( $class_instance['class_id'] ?? 0 ) );

            $enrollment = $this->db->get_enrollment_by_user_and_instance( $user_id, (int) $class_instance['id'] );
            if ( $enrollment ) {
                $this->log_event( $order_id, $user_id, (int) $class_instance['id'], 'enrollment_exists', 'success', 'Enrollment already exists; skipping duplicate.' );
                continue;
            }

            $enrollment_id = $this->db->create_enrollment( $user_id, (int) $class_instance['id'], $order_id, 'active' );
            if ( $enrollment_id <= 0 ) {
                $this->log_event( $order_id, $user_id, (int) $class_instance['id'], 'enrollment_create', 'error', 'Failed to create enrollment row.' );
                continue;
            }

            $access_start = (string) $class_instance['start_date'];
            $access_end   = gmdate( 'Y-m-d', strtotime( (string) $class_instance['end_date'] . ' +45 days' ) );

            $quiz_entitlement_id = $this->db->create_entitlement( $enrollment_id, 'quiz', $access_start, $access_end, 1 );
            $workbook_entitlement_id = $this->db->create_entitlement( $enrollment_id, 'workbook', $access_start, $access_end, 1 );

            if ( $quiz_entitlement_id <= 0 || $workbook_entitlement_id <= 0 ) {
                $this->log_event( $order_id, $user_id, (int) $class_instance['id'], 'entitlements_create', 'error', 'Failed to create one or more entitlement rows.' );
                continue;
            }

            $this->log_event( $order_id, $user_id, (int) $class_instance['id'], 'provisioning_complete', 'success', 'Enrollment and entitlements created.' );

            $provisioned_rows[] = array(
                'class_name'   => isset( $booking_class['name'] ) ? (string) $booking_class['name'] : 'Class #' . (int) ( $class_instance['class_id'] ?? 0 ),
                'course_code'  => isset( $booking_class['course_code'] ) ? (string) $booking_class['course_code'] : '',
                'start_date'   => (string) $class_instance['start_date'],
                'end_date'     => (string) $class_instance['end_date'],
                'access_end'   => (string) $access_end,
                'workbook_url' => isset( $booking_class['workbook_url'] ) ? (string) $booking_class['workbook_url'] : '',
            );
            $created_count++;
        }

        if ( $created_count > 0 ) {
            $this->send_booking_confirmation_email( $order, $user_id, $provisioned_rows );
        }

        return array(
            'ok'                    => true,
            'order_id'              => (int) $order_id,
            'user_id'               => (int) $user_id,
            'provisioned_instances' => (int) $created_count,
        );
    }

    private function send_booking_confirmation_email( $order, $user_id, $provisioned_rows ) {
        $email = sanitize_email( $order->get_billing_email() );
        if ( ! $email || empty( $provisioned_rows ) ) {
            return;
        }

        $user      = get_user_by( 'id', (int) $user_id );
        $username  = $user && isset( $user->user_login ) ? (string) $user->user_login : $email;
        $login_url = wp_login_url();

        $subject = 'Booking confirmed - TechiQuiz';
        $lines   = array();

        $lines[] = 'Hello,';
        $lines[] = '';
        $lines[] = 'Your booking is confirmed. Here are your class details:';
        $lines[] = '';

        foreach ( $provisioned_rows as $row ) {
            $title = (string) ( $row['class_name'] ?? 'Class' );
            if ( ! empty( $row['course_code'] ) ) {
                $title .= ' (' . (string) $row['course_code'] . ')';
            }
            $lines[] = '- ' . $title;
            $lines[] = '  Dates: ' . (string) ( $row['start_date'] ?? '' ) . ' to ' . (string) ( $row['end_date'] ?? '' );
            $lines[] = '  Access until: ' . (string) ( $row['access_end'] ?? '' );
            if ( ! empty( $row['workbook_url'] ) ) {
                $lines[] = '  Workbook: ' . esc_url_raw( (string) $row['workbook_url'] );
            }
            $lines[] = '';
        }

        $lines[] = 'Account login: ' . $login_url;
        $lines[] = 'Username: ' . $username;
        $lines[] = '';
        $lines[] = 'If you need to change your booking, visit your My Bookings page.';

        wp_mail( $email, $subject, implode( "\n", $lines ) );
    }

    private function find_or_create_user( $email ) {
        $user = get_user_by( 'email', $email );
        if ( $user && ! empty( $user->ID ) ) {
            return (int) $user->ID;
        }

        $username  = $this->build_unique_username( $email );
        $password  = wp_generate_password( 16, true, true );
        $user_id   = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $login_url = wp_login_url();
        $subject   = 'Your TechiQuiz account is ready';
        $message   = "Hello,\n\nYour account has been created after your order.\n\nUsername: {$username}\nTemporary Password: {$password}\nLogin: {$login_url}\n\nPlease log in and change your password.\n";

        wp_mail( $email, $subject, $message );

        return (int) $user_id;
    }

    private function build_unique_username( $email ) {
        $base = sanitize_user( current( explode( '@', (string) $email ) ), true );
        if ( ! $base ) {
            $base = 'student';
        }

        $candidate = $base;
        $suffix    = 1;

        while ( username_exists( $candidate ) ) {
            $candidate = $base . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function log_event( $order_id, $user_id, $class_instance_id, $action, $status, $message ) {
        $this->db->create_provisioning_log(
            array(
                'woocommerce_order_id' => (int) $order_id,
                'user_id'              => (int) $user_id,
                'class_instance_id'    => (int) $class_instance_id,
                'action'               => sanitize_key( $action ),
                'status'               => sanitize_key( $status ),
                'message'              => (string) $message,
            )
        );
    }
}
