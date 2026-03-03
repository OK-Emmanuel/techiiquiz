<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TQ_Activator {
    public static function activate() {
        $db = new TQ_DB();
        $db->create_tables();
    }
}
