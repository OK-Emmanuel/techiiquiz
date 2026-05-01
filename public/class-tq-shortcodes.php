<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TQ_Shortcodes {
    private $db;

    public function __construct( TQ_DB $db = null ) {
        $this->db = $db ? $db : new TQ_DB();
    }

    public function register() {
        add_shortcode( 'tq_quiz', array( $this, 'render_quiz' ) );
        add_shortcode( 'tq_workbook', array( $this, 'render_workbook' ) );
        add_shortcode( 'tq_landing_video', array( $this, 'render_landing_video' ) );
        add_shortcode( 'tq_class_details', array( $this, 'render_class_details' ) );
        add_shortcode( 'tq_booking_calendar', array( $this, 'render_booking_calendar' ) );
        add_shortcode( 'tq_my_bookings', array( $this, 'render_my_bookings' ) );
    }

    public function render_quiz( $atts ) {
        $atts = shortcode_atts(
            array(
                'set'      => 0,
                'mode'     => 'study',
                'class_id' => 0,
            ),
            $atts,
            'tq_quiz'
        );

        $set_id   = (int) $atts['set'];
        $mode     = sanitize_key( $atts['mode'] );
        $class_id = (int) $atts['class_id'];

        if ( $set_id <= 0 ) {
            return '<div class="tq-error">Invalid quiz set.</div>';
        }

        if ( ! in_array( $mode, array( 'study', 'practice' ), true ) ) {
            return '<div class="tq-error">Invalid quiz mode.</div>';
        }

        if ( ! is_user_logged_in() ) {
            return '<div class="tq-error">Please log in to access this quiz.</div>';
        }

        if ( ! current_user_can( 'manage_options' ) && ! $this->user_has_active_entitlement( get_current_user_id(), $class_id, 'quiz' ) ) {
            return '<div class="tq-error">Your access is not yet available or has expired.</div>';
        }

        TQ_Assets::enqueue_quiz_assets();

        ob_start();
        include TQ_PLUGIN_DIR . 'templates/quiz-shell.php';
        return ob_get_clean();
    }

    public function render_workbook( $atts ) {
        $atts = shortcode_atts(
            array(
                'class'    => '',
                'class_id' => 0,
                'label'    => 'Download Workbook',
            ),
            $atts,
            'tq_workbook'
        );

        if ( ! is_user_logged_in() ) {
            return '<div class="tq-error">Please log in to access this workbook.</div>';
        }

        $class_id = (int) $atts['class_id'];
        $class    = null;

        if ( $class_id > 0 ) {
            $class = $this->db->get_booking_class( $class_id );
        } elseif ( ! empty( $atts['class'] ) ) {
            $class = $this->db->get_booking_class_by_code( sanitize_text_field( $atts['class'] ) );
        }

        if ( ! is_array( $class ) || empty( $class['id'] ) ) {
            return '<div class="tq-error">Invalid class reference for workbook.</div>';
        }

        if ( ! current_user_can( 'manage_options' ) && ! $this->user_has_active_entitlement( get_current_user_id(), (int) $class['id'], 'workbook' ) ) {
            return '<div class="tq-error">Your access is not yet available or has expired.</div>';
        }

        $url = isset( $class['workbook_url'] ) ? esc_url( $class['workbook_url'] ) : '';
        if ( '' === $url ) {
            return '<div class="tq-error">Workbook is not available for this class yet.</div>';
        }

        $label = esc_html( $atts['label'] );
        return '<a class="button button-primary tq-workbook-link" href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
    }

    private function user_has_active_entitlement( $user_id, $class_id, $resource_type ) {
        return $this->db->user_has_active_entitlement( (int) $user_id, sanitize_key( $resource_type ), (int) $class_id );
    }

    private function parse_class_rules( $raw_rules ) {
        $rules = preg_split( '/\r\n|\r|\n/', (string) $raw_rules );

        if ( ! is_array( $rules ) ) {
            return array();
        }

        $rules = array_map( 'trim', $rules );
        $rules = array_filter(
            $rules,
            static function ( $rule ) {
                return '' !== $rule;
            }
        );

        return array_values( $rules );
    }

    public function render_landing_video( $atts ) {
        $atts = shortcode_atts(
            array(
                'video_url'       => '',
                'poster_url'      => '',
                'headline'        => 'Train with confidence before you go offshore.',
                'subheadline'     => 'Structured lessons, realistic drills, and guided review to keep your crew sharp.',
                'primary_label'   => 'Start Training',
                'primary_url'     => '',
                'secondary_label' => 'View Schedule',
                'secondary_url'   => '',
                'height'          => '72vh',
            ),
            $atts,
            'tq_landing_video'
        );

        TQ_Assets::enqueue_public_style();

        $video_url       = esc_url( $atts['video_url'] );
        $poster_url      = esc_url( $atts['poster_url'] );
        $headline        = esc_html( $atts['headline'] );
        $subheadline     = esc_html( $atts['subheadline'] );
        $primary_label   = esc_html( $atts['primary_label'] );
        $primary_url     = esc_url( $atts['primary_url'] );
        $secondary_label = esc_html( $atts['secondary_label'] );
        $secondary_url   = esc_url( $atts['secondary_url'] );
        $height          = sanitize_text_field( $atts['height'] );

        if ( '' === $video_url ) {
            return '<div class="tq-error">Landing video requires a valid video_url attribute.</div>';
        }

        $style = '';
        if ( preg_match( '/^[0-9.]+(vh|vw|px|rem|%)$/', $height ) ) {
            $style = ' style="--tq-hero-height:' . esc_attr( $height ) . ';"';
        }

        ob_start();
        ?>
        <section class="tq-hero-video"<?php echo $style; ?>>
            <video class="tq-hero-video__media" autoplay muted loop playsinline preload="metadata"<?php echo $poster_url ? ' poster="' . esc_attr( $poster_url ) . '"' : ''; ?>>
                <source src="<?php echo esc_url( $video_url ); ?>" type="video/mp4" />
            </video>
            <div class="tq-hero-video__overlay" aria-hidden="true"></div>
            <div class="tq-hero-video__grain" aria-hidden="true"></div>

            <div class="tq-hero-video__content">
                <p class="tq-hero-video__eyebrow">TechiQuiz Training</p>
                <h2 class="tq-hero-video__headline"><?php echo $headline; ?></h2>
                <p class="tq-hero-video__subheadline"><?php echo $subheadline; ?></p>
                <?php if ( '' !== $primary_url || '' !== $secondary_url ) : ?>
                    <div class="tq-hero-video__actions">
                        <?php if ( '' !== $primary_url ) : ?>
                            <a class="tq-hero-video__button tq-hero-video__button--primary" href="<?php echo esc_url( $primary_url ); ?>"><?php echo $primary_label; ?></a>
                        <?php endif; ?>
                        <?php if ( '' !== $secondary_url ) : ?>
                            <a class="tq-hero-video__button tq-hero-video__button--secondary" href="<?php echo esc_url( $secondary_url ); ?>"><?php echo $secondary_label; ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

        public function render_class_details( $atts ) {
            $atts = shortcode_atts(
                array(
                    'instance_id' => 0,
                    'class_id'    => 0,
                ),
                $atts,
                'tq_class_details'
            );

            $instance_id = (int) $atts['instance_id'];
            $class_id    = (int) $atts['class_id'];

            if ( $instance_id <= 0 && $class_id <= 0 ) {
                return '<div class="bg-red-50 border-l-4 border-brand-red p-4 text-red-900">Class reference missing. Use instance_id or class_id attribute.</div>';
            }

            $instance = null;
            $class    = null;

            if ( $instance_id > 0 ) {
                $instance = $this->db->get_class_instance( $instance_id );
                if ( $instance ) {
                    $class_id = (int) ( $instance['class_id'] ?? 0 );
                    $class    = $this->db->get_booking_class( $class_id );
                }
            } elseif ( $class_id > 0 ) {
                $class = $this->db->get_booking_class( $class_id );
            }

            if ( ! $instance || ! $class ) {
                return '<div class="bg-red-50 border-l-4 border-brand-red p-4 text-red-900">Class not found.</div>';
            }

            $max_capacity    = max( 1, (int) ( $instance['max_capacity'] ?? 12 ) );
            $enrolled_count  = max( 0, (int) $this->db->count_enrollments_for_instance( $instance_id ) );
            $available_seats = max( 0, $max_capacity - $enrolled_count );
            $access_end      = gmdate( 'Y-m-d', strtotime( (string) $instance['end_date'] . ' +45 days' ) );
            $product_id      = (int) ( $instance['woocommerce_product_id'] ?? 0 );

            TQ_Assets::enqueue_booking_assets();

            $default_rules = array(
                'Well Control is a TEAM Effort',
                'Do Not Dominate',
                'Respect Opinions',
                'Respect Time',
                'Must Not Be Absent More Than 4 Hours',
                'No Phone Usage During Lectures (must be on silent/vibrate; calls returned outside lecture hall)',
                'No Electronic Recording of Lectures',
            );
            $custom_rules  = $this->parse_class_rules( $class['class_rules'] ?? '' );

            ob_start();
            ?>
            <article class="bg-white rounded-2xl shadow-lg overflow-hidden max-w-3xl mx-auto">
                <!-- Header -->
                <div class="bg-gradient-to-r from-brand-blue to-blue-600 text-white p-8">
                    <h2 class="text-3xl font-bold mb-2"><?php echo esc_html( $class['name'] ?? 'Class' ); ?></h2>
                    <p class="text-blue-100">Course Code: <strong><?php echo esc_html( $class['course_code'] ?? 'N/A' ); ?></strong></p>
                </div>

                <div class="p-8 space-y-8">
                    <!-- About Section -->
                    <?php if ( ! empty( $class['description'] ) ) : ?>
                        <section>
                            <h3 class="text-xl font-bold text-slate-900 mb-4 pb-2 border-b-2 border-brand-blue">About This Class</h3>
                            <p class="text-slate-700 leading-relaxed"><?php echo wp_kses_post( $class['description'] ?? '' ); ?></p>
                        </section>
                    <?php endif; ?>

                    <!-- Key Details Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-blue-50 border-2 border-brand-blue rounded-lg p-4">
                            <p class="text-xs uppercase tracking-widest text-slate-600 font-bold">Start Date</p>
                            <p class="text-2xl font-bold text-brand-blue mt-2"><?php echo esc_html( gmdate( 'M j, Y', strtotime( (string) $instance['start_date'] ) ) ); ?></p>
                        </div>
                        <div class="bg-blue-50 border-2 border-brand-blue rounded-lg p-4">
                            <p class="text-xs uppercase tracking-widest text-slate-600 font-bold">End Date</p>
                            <p class="text-2xl font-bold text-brand-blue mt-2"><?php echo esc_html( gmdate( 'M j, Y', strtotime( (string) $instance['end_date'] ) ) ); ?></p>
                        </div>
                        <div class="bg-red-50 border-2 border-brand-red rounded-lg p-4">
                            <p class="text-xs uppercase tracking-widest text-slate-600 font-bold">Access Through</p>
                            <p class="text-2xl font-bold text-brand-red mt-2"><?php echo esc_html( gmdate( 'M j, Y', strtotime( $access_end ) ) ); ?></p>
                            <p class="text-xs text-slate-600 mt-1">(45 days after class ends)</p>
                        </div>
                        <div class="bg-slate-50 border-2 border-slate-300 rounded-lg p-4">
                            <p class="text-xs uppercase tracking-widest text-slate-600 font-bold">Seats Available</p>
                            <p class="text-2xl font-bold text-slate-900 mt-2"><?php echo esc_html( $available_seats . ' / ' . $max_capacity ); ?></p>
                        </div>
                    </div>

                    <!-- Class Rules Section -->
                    <section>
                        <h3 class="text-xl font-bold text-slate-900 mb-4 pb-2 border-b-2 border-brand-blue">Class Rules</h3>
                        <ul class="space-y-3">
                            <?php foreach ( $default_rules as $rule ) : ?>
                                <li class="flex items-start">
                                    <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-brand-blue text-white flex-shrink-0 font-bold text-sm">✓</span>
                                    <span class="ml-3 text-slate-700"><?php echo esc_html( $rule ); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ( ! empty( $custom_rules ) ) : ?>
                            <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Additional Rules for This Class</p>
                                <ul class="mt-3 space-y-3">
                                    <?php foreach ( $custom_rules as $rule ) : ?>
                                        <li class="flex items-start">
                                            <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-brand-red text-white flex-shrink-0 font-bold text-sm">+</span>
                                            <span class="ml-3 text-slate-700"><?php echo esc_html( $rule ); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </section>

                    <!-- Pre-Class Resources -->
                    <section class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded">
                        <h4 class="font-bold text-amber-900 mb-2">Before Class Starts</h4>
                        <p class="text-sm text-amber-800">Please review the following materials to prepare for class:</p>
                        <ul class="text-sm text-amber-800 list-disc list-inside mt-2 space-y-1">
                            <li>Workbook</li>
                            <li>Pocket Pro Guide</li>
                            <li>Killsheets</li>
                            <li>Formula Sheets</li>
                        </ul>
                    </section>

                    <!-- Price Section -->
                    <?php if ( $product_id > 0 && function_exists( 'wc_get_product' ) ) : ?>
                        <?php $product = wc_get_product( $product_id ); ?>
                        <?php if ( $product ) : ?>
                            <div class="bg-slate-100 p-6 rounded-lg flex items-baseline justify-between">
                                <span class="text-slate-600 font-semibold text-lg">Total Price</span>
                                <span class="text-4xl font-bold text-brand-blue"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="flex gap-3 pt-4">
                        <?php if ( 0 === $available_seats ) : ?>
                            <button disabled class="flex-1 bg-slate-400 text-white font-bold py-4 px-6 rounded-lg cursor-not-allowed text-lg">
                                Class Full
                            </button>
                        <?php elseif ( $product_id > 0 ) : ?>
                            <button onclick="window.location.href='<?php echo esc_url( add_query_arg( array( 'add-to-cart' => $product_id, 'quantity' => '1' ), function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ) ) ); ?>'" class="flex-1 bg-brand-blue hover:bg-blue-700 text-white font-bold py-4 px-6 rounded-lg transition text-lg">
                                Reserve My Seat
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
            <?php
            return (string) ob_get_clean();
        }

    public function render_booking_calendar( $atts ) {
        $atts = shortcode_atts(
            array(
                'title'       => 'Book a class',
                'subtitle'    => 'Browse upcoming classes, check seats, and move to checkout in one step.',
                'cta_label'   => 'Book now',
                'empty_label' => 'No classes are scheduled in this window yet.',
            ),
            $atts,
            'tq_booking_calendar'
        );

        $classes   = $this->db->get_booking_classes();
        $instances = $this->db->get_class_instances();

        $booking_data = $this->build_booking_calendar_payload( $atts, $classes, $instances );

        TQ_Assets::enqueue_booking_assets();

        wp_localize_script(
            'tq-booking-calendar',
            'TQBookingCalendar',
            array(
                'cartUrl'     => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
                'checkoutUrl' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' ),
                'labels'      => array(
                    'allClasses'  => 'All classes',
                    'selectSchool' => 'Select school',
                    'availability' => 'seats available',
                    'full'         => 'Full',
                    'bookNow'      => $atts['cta_label'],
                    'previousMonth'=> 'Previous month',
                    'nextMonth'    => 'Next month',
                    'empty'        => $atts['empty_label'],
                    'loading'      => 'Loading schedule...',
                    'error'        => 'Unable to load the schedule.',
                ),
            )
        );

        ob_start();
        include TQ_PLUGIN_DIR . 'templates/booking-calendar.php';
        return ob_get_clean();
    }

    private function build_booking_calendar_payload( $atts, $classes, $instances ) {
        $class_map = array();

        foreach ( (array) $classes as $class ) {
            $class_id = (int) ( $class['id'] ?? 0 );
            if ( $class_id <= 0 ) {
                continue;
            }

            $class_map[ $class_id ] = array(
                'id'            => $class_id,
                'name'          => sanitize_text_field( $class['name'] ?? '' ),
                'course_code'   => sanitize_text_field( $class['course_code'] ?? '' ),
                'description'   => wp_strip_all_tags( (string) ( $class['description'] ?? '' ) ),
                'class_rules'   => $this->parse_class_rules( $class['class_rules'] ?? '' ),
                'workbook_url'  => esc_url_raw( $class['workbook_url'] ?? '' ),
                'instance_count' => (int) ( $class['instance_count'] ?? 0 ),
            );
        }

        $calendar_instances = array();

        foreach ( (array) $instances as $instance ) {
            $instance_id = (int) ( $instance['id'] ?? 0 );
            $class_id    = (int) ( $instance['class_id'] ?? 0 );
            $start_date  = sanitize_text_field( $instance['start_date'] ?? '' );
            $end_date    = sanitize_text_field( $instance['end_date'] ?? '' );

            if ( $instance_id <= 0 || $class_id <= 0 || '' === $start_date ) {
                continue;
            }

            $class_info = $class_map[ $class_id ] ?? array(
                'id'            => $class_id,
                'name'          => sanitize_text_field( $instance['class_name'] ?? '' ),
                'course_code'   => '',
                'description'   => '',
                'class_rules'   => array(),
                'workbook_url'  => '',
                'instance_count' => 0,
            );

            $max_capacity    = max( 1, (int) ( $instance['max_capacity'] ?? 12 ) );
            $enrolled_count  = max( 0, (int) $this->db->count_enrollments_for_instance( $instance_id ) );
            $available_seats = max( 0, $max_capacity - $enrolled_count );
            $product_id      = (int) ( $instance['woocommerce_product_id'] ?? 0 );

            $calendar_instances[] = array(
                'id'              => $instance_id,
                'class_id'        => $class_id,
                'class_name'      => $class_info['name'],
                'course_code'     => $class_info['course_code'],
                'description'     => $class_info['description'],
                'class_rules'     => $class_info['class_rules'],
                'workbook_url'    => $class_info['workbook_url'],
                'product_id'      => $product_id,
                'start_date'      => $start_date,
                'end_date'        => $end_date,
                'max_capacity'    => $max_capacity,
                'enrolled_count'  => $enrolled_count,
                'available_seats' => $available_seats,
                'is_full'         => 0 === $available_seats,
                'display_label'   => $this->build_calendar_label( $class_info['name'], $start_date, $end_date ),
            );
        }

        usort(
            $calendar_instances,
            static function ( $left, $right ) {
                return strcmp( (string) ( $left['start_date'] ?? '' ), (string) ( $right['start_date'] ?? '' ) );
            }
        );

        return array(
            'title'     => sanitize_text_field( $atts['title'] ),
            'subtitle'  => sanitize_text_field( $atts['subtitle'] ),
            'cta_label' => sanitize_text_field( $atts['cta_label'] ),
            'classes'   => array_values( $class_map ),
            'instances' => array_values( $calendar_instances ),
        );
    }

    private function build_calendar_label( $class_name, $start_date, $end_date ) {
        $label_parts = array_filter(
            array(
                sanitize_text_field( $class_name ),
                $start_date ? gmdate( 'M j', strtotime( $start_date ) ) : '',
                $end_date ? gmdate( 'M j, Y', strtotime( $end_date ) ) : '',
            )
        );

        return implode( ' · ', $label_parts );
    }

    public function render_my_bookings( $atts ) {
        $atts = shortcode_atts(
            array(
                'title'        => 'My Bookings',
                'calendar_url' => '',
            ),
            $atts,
            'tq_my_bookings'
        );

        if ( ! is_user_logged_in() ) {
            return '<div class="tq-error">Please log in to manage your bookings.</div>';
        }

        $calendar_url  = esc_url( $atts['calendar_url'] );
        $flash_message = $this->handle_my_booking_actions( $calendar_url );
        $rows          = $this->db->get_user_enrollments( get_current_user_id() );

        TQ_Assets::enqueue_booking_assets();

        ob_start();
        echo '<section class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-900 shadow-sm sm:p-5">';
        echo '<header>';
        echo '<h2 class="text-2xl font-black tracking-tight text-slate-900">' . esc_html( $atts['title'] ) . '</h2>';
        echo '<p class="mt-2 text-sm text-slate-600">Manage your current enrollments, or change to a new date by booking another class.</p>';
        if ( '' !== $calendar_url ) {
            echo '<a class="mt-3 inline-flex rounded-full bg-brand-blue px-4 py-2 text-sm font-bold text-white hover:bg-brand-red" href="' . $calendar_url . '">Browse class calendar</a>';
        }
        echo '</header>';

        if ( '' !== $flash_message ) {
            echo '<div class="mt-3 rounded-xl border border-brand-blue/25 bg-brand-blue/5 p-3 text-sm text-brand-blue">' . esc_html( $flash_message ) . '</div>';
        }

        if ( empty( $rows ) ) {
            echo '<div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">No bookings yet. Use the booking calendar to reserve your seat.</div>';
            echo '</section>';
            return (string) ob_get_clean();
        }

        echo '<div class="mt-4 grid gap-3">';
        foreach ( $rows as $row ) {
            $enrollment_id = (int) ( $row['enrollment_id'] ?? 0 );
            $status        = sanitize_key( $row['enrollment_status'] ?? 'active' );
            $status_label  = ucfirst( str_replace( '_', ' ', $status ) );
            $can_cancel    = 'active' === $status;
            $status_class  = 'bg-slate-100 text-slate-700';
            if ( 'active' === $status ) {
                $status_class = 'bg-brand-blue/10 text-brand-blue';
            } elseif ( 'cancelled' === $status ) {
                $status_class = 'bg-brand-red/10 text-brand-red';
            }

            echo '<article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">';
            echo '<div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">';
            echo '<h3 class="text-lg font-bold text-white">' . esc_html( $row['class_name'] ?? 'Class' ) . '</h3>';
            echo '<span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span>';
            echo '</div>';

            echo '<p class="mt-2 text-sm text-slate-700"><strong>Course:</strong> ' . esc_html( $row['course_code'] ?? 'N/A' ) . '</p>';
            echo '<p class="mt-1 text-sm text-slate-700"><strong>Dates:</strong> ' . esc_html( $row['start_date'] ?? '' ) . ' to ' . esc_html( $row['end_date'] ?? '' ) . '</p>';
            echo '<p class="mt-1 text-sm text-slate-600"><strong>Access:</strong> ' . esc_html( $row['access_start'] ?? '' ) . ' to ' . esc_html( $row['access_end'] ?? '' ) . '</p>';
            echo '<p class="mt-1 text-sm text-slate-600"><strong>Order:</strong> #' . esc_html( (string) ( $row['woocommerce_order_id'] ?? '' ) ) . '</p>';

            echo '<div class="mt-3 flex flex-wrap gap-2">';

            if ( $can_cancel ) {
                echo '<form method="post">';
                wp_nonce_field( 'tq_cancel_enrollment_' . $enrollment_id );
                echo '<input type="hidden" name="tq_booking_action" value="cancel_enrollment" />';
                echo '<input type="hidden" name="enrollment_id" value="' . esc_attr( $enrollment_id ) . '" />';
                echo '<button type="submit" class="inline-flex items-center justify-center rounded-full border border-brand-red/40 bg-brand-red/10 px-3 py-2 text-sm font-semibold text-brand-red hover:bg-brand-red/20" onclick="return confirm(\'Cancel this booking?\')">Cancel booking</button>';
                echo '</form>';

                if ( '' !== $calendar_url ) {
                    echo '<form method="post">';
                    wp_nonce_field( 'tq_cancel_enrollment_' . $enrollment_id );
                    echo '<input type="hidden" name="tq_booking_action" value="change_enrollment" />';
                    echo '<input type="hidden" name="enrollment_id" value="' . esc_attr( $enrollment_id ) . '" />';
                    echo '<button type="submit" class="inline-flex items-center justify-center rounded-full border border-brand-blue/40 bg-brand-blue/10 px-3 py-2 text-sm font-semibold text-brand-blue hover:bg-brand-blue/20" onclick="return confirm(\'Cancel this booking and choose a new date?\')">Change booking</button>';
                    echo '</form>';
                }
            }

            if ( ! $can_cancel && '' !== $calendar_url ) {
                echo '<a class="inline-flex items-center justify-center rounded-full border border-brand-blue/40 bg-brand-blue/10 px-3 py-2 text-sm font-semibold text-brand-blue hover:bg-brand-blue/20" href="' . $calendar_url . '">Book another class</a>';
            }

            if ( ! empty( $row['workbook_url'] ) ) {
                echo '<a class="inline-flex items-center justify-center rounded-full border border-brand-blue/40 bg-white px-3 py-2 text-sm font-semibold text-brand-blue hover:bg-brand-blue/5" href="' . esc_url( $row['workbook_url'] ) . '" target="_blank" rel="noopener noreferrer">Workbook</a>';
            }

            echo '</div>';
            echo '</article>';
        }
        echo '</div>';
        echo '</section>';

        return (string) ob_get_clean();
    }

    private function handle_my_booking_actions( $calendar_url = '' ) {
        if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            return '';
        }

        $action = isset( $_POST['tq_booking_action'] ) ? sanitize_key( wp_unslash( $_POST['tq_booking_action'] ) ) : '';
        if ( ! in_array( $action, array( 'cancel_enrollment', 'change_enrollment' ), true ) ) {
            return '';
        }

        $enrollment_id = isset( $_POST['enrollment_id'] ) ? (int) wp_unslash( $_POST['enrollment_id'] ) : 0;
        if ( $enrollment_id <= 0 ) {
            return 'Invalid booking reference.';
        }

        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'tq_cancel_enrollment_' . $enrollment_id ) ) {
            return 'Security check failed. Please try again.';
        }

        $cancelled = $this->db->cancel_enrollment_for_user( $enrollment_id, get_current_user_id() );
        if ( ! $cancelled ) {
            return 'We could not cancel this booking right now. Please contact support.';
        }

        if ( 'change_enrollment' === $action ) {
            if ( ! empty( $calendar_url ) ) {
                return 'Booking cancelled. Next step: choose your new date from the booking calendar above.';
            }

            return 'Booking cancelled. You can now choose a new date from the booking calendar.';
        }

        return 'Booking cancelled. If payment refunds apply, they can be processed in your Shop order.';
    }
}
