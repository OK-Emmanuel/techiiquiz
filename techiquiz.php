<?php
/**
 * Plugin Name: TechiQuiz
 * Description: Custom quiz domain plugin for well-control training.
 * Version: 0.1.0
 * Author: TechiQuiz
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TQ_VERSION', '0.1.0' );
define( 'TQ_PLUGIN_FILE', __FILE__ );
define( 'TQ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TQ_PLUGIN_DIR . 'includes/class-tq-db.php';
require_once TQ_PLUGIN_DIR . 'includes/class-tq-activator.php';
require_once TQ_PLUGIN_DIR . 'includes/class-tq-quiz-service.php';
require_once TQ_PLUGIN_DIR . 'includes/class-tq-session-service.php';
require_once TQ_PLUGIN_DIR . 'includes/class-tq-rest.php';
require_once TQ_PLUGIN_DIR . 'public/class-tq-assets.php';
require_once TQ_PLUGIN_DIR . 'public/class-tq-shortcodes.php';
require_once TQ_PLUGIN_DIR . 'admin/class-tq-admin-menu.php';

register_activation_hook( __FILE__, array( 'TQ_Activator', 'activate' ) );

add_action(
    'plugins_loaded',
    static function () {
        $quiz_service    = new TQ_Quiz_Service( new TQ_DB() );
        $session_service = new TQ_Session_Service( new TQ_DB(), $quiz_service );

        ( new TQ_REST( $quiz_service, $session_service ) )->register();
        ( new TQ_Assets() )->register();
        ( new TQ_Shortcodes() )->register();
        ( new TQ_Admin_Menu( new TQ_DB() ) )->register();
    }
);
