<?php
/**
 * In-place navigation between the archive views.
 *
 * The filter row and the stream sit in a group with the tufte-archive-region
 * class. This file marks that group as an Interactivity API router region and
 * loads the script module that swaps it in place. Anything a theme puts inside
 * the region (a per-view header, say) is swapped with it. Links stay real links: without
 * JavaScript, or on a modified click, the browser navigates as usual.
 *
 * @package Tufte_Formats
 */

declare(strict_types=1);

/** Class that marks the router region group in the archive templates. */
const TUFTE_FORMATS_ARCHIVE_REGION_CLASS = 'tufte-archive-region';

/**
 * Add the router region and event directives to the archive region group.
 *
 * @param string $block_content Rendered group block.
 * @param array  $block         Parsed block.
 * @return string
 */
function tufte_formats_archive_region_attributes( string $block_content, array $block ): string {
	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	if ( ! in_array( TUFTE_FORMATS_ARCHIVE_REGION_CLASS, explode( ' ', $class_name ), true ) ) {
		return $block_content;
	}

	$processor = new WP_HTML_Tag_Processor( $block_content );
	if ( ! $processor->next_tag() ) {
		return $block_content;
	}

	$processor->set_attribute( 'data-wp-interactive', 'tufte-formats/archive' );
	$processor->set_attribute( 'data-wp-router-region', 'tufte-archive' );
	$processor->set_attribute( 'data-wp-on--click', 'actions.navigate' );
	$processor->set_attribute( 'data-wp-on--mouseover', 'actions.prefetch' );
	$processor->set_attribute( 'data-wp-on--focusin', 'actions.prefetch' );
	$processor->set_attribute( 'data-wp-bind--aria-busy', 'state.isNavigating' );

	return $processor->get_updated_html();
}
add_filter( 'render_block_core/group', 'tufte_formats_archive_region_attributes', 10, 2 );

/**
 * Whether the archive script module is needed on this request.
 *
 * @return bool
 */
function tufte_formats_should_load_archive_module(): bool {
	return '' !== tufte_formats_archive_view();
}

/**
 * Register the archive script module, and enqueue it on the archive views only.
 *
 * The router loads on the first interaction (a dynamic import), not with the page.
 * The views' paths reach the script as interactivity state; the query-21
 * pagination parameter in assets/js/archive.js mirrors
 * TUFTE_FORMATS_ARCHIVE_STREAM_QUERY_ID in includes/notes.php.
 *
 * @return void
 */
function tufte_formats_enqueue_archive_module(): void {
	wp_register_script_module(
		'@tufte-formats/archive',
		plugins_url( 'assets/js/archive.js', TUFTE_FORMATS_PLUGIN_FILE ),
		array(
			'@wordpress/interactivity',
			array(
				'id'     => '@wordpress/interactivity-router',
				'import' => 'dynamic',
			),
			array(
				'id'     => '@wordpress/a11y',
				'import' => 'dynamic',
			),
		),
		TUFTE_FORMATS_VERSION
	);

	if ( tufte_formats_should_load_archive_module() ) {
		wp_interactivity_state( 'tufte-formats/archive', array( 'paths' => tufte_formats_archive_paths() ) );
		wp_enqueue_script_module( '@tufte-formats/archive' );
	}
}
add_action( 'wp_enqueue_scripts', 'tufte_formats_enqueue_archive_module' );
