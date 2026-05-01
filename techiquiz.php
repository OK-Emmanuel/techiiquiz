<?php
/**
 * Plugin Name: TechiQuiz
 * Description: Custom quiz domain plugin for well-control training.
 * Version: 0.2.0
 * Author: TechiQuiz
 * Update URI: https://github.com/OK-Emmanuel/techiiquiz
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TQ_VERSION', '0.2.0' );
define( 'TQ_PLUGIN_FILE', __FILE__ );
define( 'TQ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TQ_PLUGIN_DIR . 'includes/class-tq-db.php';
require_once TQ_PLUGIN_DIR . 'includes/class-tq-activator.php';
require_once TQ_PLUGIN_DIR . 'includes/class-tq-quiz-service.php';
require_once TQ_PLUGIN_DIR . 'includes/class-tq-session-service.php';
require_once TQ_PLUGIN_DIR . 'includes/class-tq-rest.php';
require_once TQ_PLUGIN_DIR . 'includes/class-tq-import-service.php';
require_once TQ_PLUGIN_DIR . 'includes/class-tq-booking-service.php';
require_once TQ_PLUGIN_DIR . 'includes/class-tq-updater.php';
require_once TQ_PLUGIN_DIR . 'public/class-tq-assets.php';
require_once TQ_PLUGIN_DIR . 'public/class-tq-shortcodes.php';
require_once TQ_PLUGIN_DIR . 'admin/class-tq-admin-menu.php';

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

register_activation_hook( __FILE__, array( 'TQ_Activator', 'activate' ) );

add_action(
    'plugins_loaded',
    static function () {
        TQ_Updater::register();

        $db              = new TQ_DB();
        $db->maybe_upgrade_schema();
        $quiz_service    = new TQ_Quiz_Service( $db );
        $session_service = new TQ_Session_Service( $db, $quiz_service );

        ( new TQ_REST( $quiz_service, $session_service ) )->register();
        ( new TQ_Booking_Service( $db ) )->register();
        ( new TQ_Assets() )->register();
        ( new TQ_Shortcodes( $db ) )->register();
        ( new TQ_Admin_Menu( $db ) )->register();
    }
);
