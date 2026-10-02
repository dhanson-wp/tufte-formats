<?php
/**
 * Tufte Formats stays a generic companion plugin: no migrations, no redirects, nothing about one site.
 *
 * @package Tufte_Formats
 */

$tufte_formats_scope_files = glob( dirname( __DIR__ ) . '/includes/*.php' );
$tufte_formats_scope_bad   = array();
foreach ( $tufte_formats_scope_files as $tufte_formats_scope_file ) {
	$tufte_formats_scope_src = (string) file_get_contents( $tufte_formats_scope_file );
	if ( preg_match( '/\\bdhb_|\\bDHB_|derekhanson|template_redirect|wp_redirect|WP_CLI::add_command|migrat/i', $tufte_formats_scope_src ) ) {
		$tufte_formats_scope_bad[] = basename( $tufte_formats_scope_file );
	}
}
tufte_formats_assert_same( array(), $tufte_formats_scope_bad, 'plugin scope: no migration, redirect, or site-specific code in includes/' );
tufte_formats_assert_same( false, str_contains( (string) file_get_contents( dirname( __DIR__ ) . '/tufte-formats.php' ), 'derekhanson' ), 'plugin scope: main file names no site' );
tufte_formats_assert_same( true, function_exists( 'tufte_formats_is_note' ), 'plugin scope: the notes functions load without the rollout plugin' );
