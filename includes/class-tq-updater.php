<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TQ_Updater {

    /**
     * Register plugin update checker against a GitHub repository.
     */
    public static function register(): void {
        if ( ! class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
            return;
        }

        $repo_url = (string) apply_filters( 'tq_updater_repo_url', 'https://github.com/OK-Emmanuel/techiiquiz' );
        $branch   = (string) apply_filters( 'tq_updater_branch', 'main' );
        $slug     = (string) apply_filters( 'tq_updater_slug', 'techiiquiz' );

        if ( '' === $repo_url ) {
            return;
        }

        $update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            $repo_url,
            TQ_PLUGIN_FILE,
            $slug
        );

        $update_checker->setBranch( $branch );

        $github_token = (string) apply_filters( 'tq_updater_github_token', '' );
        if ( '' !== $github_token ) {
            $update_checker->setAuthentication( $github_token );
        }

        if ( method_exists( $update_checker->getVcsApi(), 'enableReleaseAssets' ) ) {
            $update_checker->getVcsApi()->enableReleaseAssets();
        }
    }
}
