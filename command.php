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

    public function helloWorld() {
        WP_CLI::success( 'hello world' );
    }

    /**
     * Rebuild the page CSS file for every page in every site on the network
     *
     * @param $args
     * @param $assoc_args
     */
    public function rebuild_css( $args, $assoc_args ) {

        if ( class_exists( '\Elementor\Plugin' ) ) {

            $current_blog_id = get_current_blog_id();

            if ( isset( $assoc_args['network'] ) ) {
                $options = array(
                    'return'     => true,   // Return 'STDOUT'; use 'all' for full object.
                    'parse'      => 'json', // Parse captured STDOUT to JSON array.
                    'launch'     => false,  // Reuse the current process.
                    'exit_error' => true,   // Halt script execution on error.
                );
                $sites = WP_CLI::runcommand('site list --format=json --fields=blog_id,domain', $options );
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
     * Regenerate the Elementor Page Builder CSS.
     *
     * [--network]
     *      Regenerate CSS of for all the sites in the network.
     *
     * ## EXAMPLES
     *
     *  1. wp elementor-commands regenerate-css
     *      - This will regenerate the CSS files for elementor page builder.
     *
     *  2. wp site list --field=url | xargs -n1 -I % wp --url=% elementor-commands regenerate-css
     *    - This will regenerate the CSS files for elementor page builder on all the sites in network.
     *
     * @alias regenerate-css
     */
    public function regenerate_css( $args, $assoc_args ) {

        if ( class_exists( '\Elementor\Plugin' ) ) {
            \Elementor\Plugin::$instance->posts_css_manager->clear_cache();
            WP_CLI::success( 'Regenerated the Elementor CSS' );
        } else {
            WP_CLI::error( 'Elementor is not installed on this site' );
        }
    }

    /**
     * Regenerate the Elementor Page Builder CSS.
     *
     * [--network]
     *      Regenerate CSS of for all the sites in the network.
     *
     * ## EXAMPLES
     *
     *  1. wp elementor-commands search-replace <source-url> <destination-url>
     *      - This will Replace the URLs from <source-url> to <destination-url>.
     *
     *  2. wp site list --field=url | xargs -n1 -I % wp --url=% elementor-commands search-replace <source-url> <destination-url>
     *    - This will Replace the URLs from <source-url> to <destination-url> on all the sites in network.
     *
     * @alias search-replace
     */
    public function search_replace( $args, $assoc_args ) {

        if ( isset( $args[0] ) ) {
            $from = $args[0];
        }
        if ( isset( $args[1] ) ) {
            $to = $args[1];
        }

        $is_valid_urls = ( filter_var( $from, FILTER_VALIDATE_URL ) && filter_var( $to, FILTER_VALIDATE_URL ) );
        if ( ! $is_valid_urls ) {
            WP_CLI::error( __( 'The `from` and `to` URL\'s must be a valid URL', 'elementor' ) );
        }

        if ( $from === $to ) {
            WP_CLI::error( __( 'The `from` and `to` URL\'s must be different', 'elementor' ) );
        }

        global $wpdb;

        // @codingStandardsIgnoreStart cannot use `$wpdb->prepare` because it remove's the backslashes
        $rows_affected = $wpdb->query(
            "UPDATE {$wpdb->postmeta} " .
            "SET `meta_value` = REPLACE(`meta_value`, '" . str_replace( '/', '\\\/', $from ) . "', '" . str_replace( '/', '\\\/', $to ) . "') " .
            "WHERE `meta_key` = '_elementor_data' AND `meta_value` LIKE '[%' ;" ); // meta_value LIKE '[%' are json formatted
        // @codingStandardsIgnoreEnd

        WP_CLI::success( 'Replaced URLs for elementor' );
    }
}

WP_CLI::add_command( 'elementor-commands', 'Elementor_Commands' );
