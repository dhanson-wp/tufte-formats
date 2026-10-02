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
	if ( preg_match( '/template_redirect|wp_redirect|WP_CLI::add_command|_dhb_|migrate/i', $tufte_formats_scope_src ) ) {
		$tufte_formats_scope_bad[] = basename( $tufte_formats_scope_file );
	}
}
tufte_formats_assert_same( array(), $tufte_formats_scope_bad, 'plugin scope: no migration, redirect, or site-specific code in includes/' );
tufte_formats_assert_same( false, str_contains( (string) file_get_contents( dirname( __DIR__ ) . '/tufte-formats.php' ), 'derekhanson' ), 'plugin scope: main file names no site' );
tufte_formats_assert_same( true, function_exists( 'tufte_formats_is_note' ) && ! function_exists( 'dhb_is_note' ), 'plugin scope: the old dhb_ notes function is gone' );
