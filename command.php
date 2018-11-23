<?php

use Elementor\Global_CSS_File;
use Elementor\Post_CSS_File;

if ( ! class_exists( 'WP_CLI' ) ) {
    return;
}

/**
 * WP ClI Commands for Elementor
 */
class Elementor_Commands extends WP_CLI_Command {

    /**
     * Just a test to check if the package is installed and callable
     */
    public function hello() {
        WP_CLI::success( 'Hello elementor-commands' );
    }

    /**
     * Rebuild the page CSS file for every page in every site on the network
     *
     * [--network]
     *      Regenerate CSS of for all the sites in the network.
     *
     * @param $args
     * @param $assoc_args
     */
    public function rebuild_css( $args, $assoc_args ) {

        if ( class_exists( '\Elementor\Plugin' ) ) {

            $current_blog_id = get_current_blog_id();

            if ( isset( $assoc_args['network'] ) ) {
                $options = [
                    'return'     => true,   // Return 'STDOUT'; use 'all' for full object.
                    'parse'      => 'json', // Parse captured STDOUT to JSON array.
                    'launch'     => false,  // Reuse the current process.
                    'exit_error' => true,   // Halt script execution on error.
                ];
                $sites   = WP_CLI::runcommand( 'site list --format=json --fields=blog_id,domain', $options );
                WP_CLI::line( WP_CLI::colorize( '%c[NETWORK] Number of sites%n ' . count( $sites ) ) );
            } else {
                // running for first site in the network
                $sites = [ (array) get_blog_details( $current_blog_id, false ) ];
            }

            if ( $sites ) {

                foreach ( $sites as $site ) {

                    if ( isset( $assoc_args['network'] ) ) {
                        WP_CLI::line( WP_CLI::colorize( '%3%k[NETWORK] Switching to site%n ' . $site['domain'] . ' (' . $site['blog_id'] . ')' ) );
                    }
                    switch_to_blog( $site['blog_id'] );

                    WP_CLI::line( WP_CLI::colorize( '%cRebuilding global CSS file%n' ) );
                    $global_css_file = new Global_CSS_File();
                    $global_css_file->update();

                    $pages = get_posts( [
                        'post_type'      => 'page',
                        'post_status'    => 'any',
                        'posts_per_page' => - 1,
                        'offset'         => 0,
                    ] );

                    WP_CLI::line( WP_CLI::colorize( '%mNumber of pages%n ' . count( $pages ) ) );

                    foreach ( $pages as $page ) {
                        WP_CLI::line( WP_CLI::colorize( '%yRebuilding CSS for page %n' . $page->ID ) );
                        $css_file = new Post_CSS_File( $page->ID );
                        $css_file->update();
                    }
                }

            }

            $global_css_file = new Global_CSS_File();
            $global_css_file->update();

            if ( isset( $assoc_args['network'] ) ) {
                WP_CLI::line( WP_CLI::colorize( '%3%k[NETWORK] Switching back to site%n ' . $current_blog_id ) );
                switch_to_blog( $current_blog_id );
            }

            WP_CLI::success( 'CSS has been rebuilt for every page' );

        } else {
            WP_CLI::error( 'Elementor is not installed on this site' );
        }

    }

    /**
     * Clear the CSS cache for every page on the current site (delete files and remove meta from database)
     * TODO : handle --network argument
     *
     * @param $args
     * @param $assoc_args
     */
    public function clear_css_cache( $args, $assoc_args ) {

        if ( class_exists( '\Elementor\Plugin' ) ) {
            \Elementor\Plugin::$instance->posts_css_manager->clear_cache();
            WP_CLI::success( 'Regenerated the Elementor CSS' );
        } else {
            WP_CLI::error( 'Elementor is not installed on this site' );
        }
    }

}

WP_CLI::add_command( 'elementor-commands', 'Elementor_Commands' );
