<?php
declare(strict_types=1);

// The render_block filter marks only the archive region group.
$region_html = '<div class="wp-block-group alignwide tufte-archive-region"><nav><a href="/archive/">All</a></nav></div>';
$region_blk  = array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'tufte-archive-region' ) );

$out = tufte_formats_archive_region_attributes( $region_html, $region_blk );
tufte_formats_assert_same( true, str_contains( $out, 'data-wp-interactive="tufte-formats/archive"' ), 'region: interactive namespace added' );
tufte_formats_assert_same( true, str_contains( $out, 'data-wp-router-region="tufte-archive"' ), 'region: router region added' );
tufte_formats_assert_same( true, str_contains( $out, 'data-wp-on--click="actions.navigate"' ), 'region: click handler added' );
tufte_formats_assert_same( true, str_contains( $out, 'data-wp-on--mouseover="actions.prefetch"' ), 'region: mouseover prefetch added' );
tufte_formats_assert_same( true, str_contains( $out, 'data-wp-on--focusin="actions.prefetch"' ), 'region: focusin prefetch added' );
tufte_formats_assert_same( true, str_contains( $out, 'data-wp-bind--aria-busy="state.isNavigating"' ), 'region: aria-busy binding added' );
tufte_formats_assert_same( 1, substr_count( $out, 'data-wp-router-region' ), 'region: attributes land on the root only' );
tufte_formats_assert_same( true, str_contains( $out, '<a href="/archive/">All</a>' ), 'region: inner markup is untouched' );

$twice = tufte_formats_archive_region_attributes( $out, $region_blk );
tufte_formats_assert_same( $out, $twice, 'region: running twice does not duplicate attributes' );

$other_html = '<div class="wp-block-group note-meta"></div>';
tufte_formats_assert_same(
	$other_html,
	tufte_formats_archive_region_attributes( $other_html, array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'note-meta' ) ) ),
	'region: other groups are untouched'
);
tufte_formats_assert_same(
	$other_html,
	tufte_formats_archive_region_attributes( $other_html, array( 'blockName' => 'core/group', 'attrs' => array() ) ),
	'region: a group without a class is untouched'
);
tufte_formats_assert_same(
	$other_html,
	tufte_formats_archive_region_attributes( $other_html, array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'not-tufte-archive-region-x' ) ) ),
	'region: a similar class name is not a match'
);

// Through the real filter chain.
$via_filter = apply_filters( 'render_block_core/group', $region_html, $region_blk, null );
tufte_formats_assert_same( $out, $via_filter, 'region: the filter is hooked to render_block_core/group' );

// The module loads on an archive view only.
$mod_wp_query = $GLOBALS['wp_query'];
$mod_post     = $GLOBALS['post'] ?? null;

$mod_page_created = false;
$mod_archive      = get_page_by_path( 'archive' );
if ( ! $mod_archive ) {
	$mod_id           = wp_insert_post( array( 'post_title' => 'archive', 'post_name' => 'archive', 'post_type' => 'page', 'post_status' => 'publish' ) );
	$mod_archive      = get_post( $mod_id );
	$mod_page_created = true;
}
$mod_other = wp_insert_post( array( 'post_title' => 'th-ai-other', 'post_type' => 'page', 'post_status' => 'publish' ) );

$mod_fake = static function ( WP_Post $page ): void {
	$q                    = new WP_Query();
	$q->is_page           = true;
	$q->is_singular       = true;
	$q->queried_object    = $page;
	$q->queried_object_id = $page->ID;
	$GLOBALS['wp_query']  = $q;
	$GLOBALS['post']      = $page;
};

$mod_fake( $mod_archive );
tufte_formats_assert_same( true, tufte_formats_should_load_archive_module(), 'module: loads on the archive page' );
$mod_dep_ids = static function (): array {
	$reg = wp_script_modules()->get_registered( '@tufte-formats/archive' );
	$out = array();
	foreach ( (array) ( $reg['dependencies'] ?? array() ) as $dep ) {
		$out[ $dep['id'] ] = $dep['import'] ?? 'static';
	}
	return $out;
};
$mod_enqueued = static function (): bool {
	return in_array( '@tufte-formats/archive', wp_script_modules()->get_queue(), true );
};
$mod_dequeue = static function (): void {
	wp_dequeue_script_module( '@tufte-formats/archive' );
};

do_action( 'wp_enqueue_scripts' );
$deps = $mod_dep_ids();
tufte_formats_assert_same( 'static', $deps['@wordpress/interactivity'] ?? null, 'module: interactivity is a static dependency' );
tufte_formats_assert_same( 'dynamic', $deps['@wordpress/interactivity-router'] ?? null, 'module: router is a dynamic dependency' );
tufte_formats_assert_same( 'dynamic', $deps['@wordpress/a11y'] ?? null, 'module: a11y is a dynamic dependency' );
tufte_formats_assert_same( true, $mod_enqueued(), 'module: enqueued on an archive request' );
$mod_dequeue();

$mod_fake( get_post( $mod_other ) );
do_action( 'wp_enqueue_scripts' );
tufte_formats_assert_same( false, $mod_enqueued(), 'module: not enqueued on a non-archive request' );
$mod_dequeue();
tufte_formats_assert_same( false, tufte_formats_should_load_archive_module(), 'module: does not load on another page' );
$mod_note = wp_insert_post( array( 'post_title' => 'th-ai-post', 'post_status' => 'publish' ) );
$q                    = new WP_Query();
$q->is_single         = true;
$q->is_singular       = true;
$q->queried_object    = get_post( $mod_note );
$q->queried_object_id = $mod_note;
$GLOBALS['wp_query']  = $q;
tufte_formats_assert_same( false, tufte_formats_should_load_archive_module(), 'module: does not load on a single post' );
$GLOBALS['wp_query'] = new WP_Query();
tufte_formats_assert_same( false, tufte_formats_should_load_archive_module(), 'module: does not load with no queried object' );

$GLOBALS['wp_query'] = $mod_wp_query;
$GLOBALS['post']     = $mod_post;
wp_delete_post( $mod_other, true );
wp_delete_post( $mod_note, true );
if ( $mod_page_created ) {
	wp_delete_post( $mod_archive->ID, true );
}

// A stale click must not navigate after a newer one when the router loads out of order.
$tufte_formats_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/archive.js' );
tufte_formats_assert_same( true, (bool) preg_match( "/yield import\( '@wordpress\/interactivity-router' \);\s*(?:\/\/[^\n]*\n\s*)?if \( id !== navId \) \{\s*return;\s*\}\s*yield actions\.navigate/s", $tufte_formats_js ), 'archive.js: checks for a newer click after the router loads and before navigating' );

// FB-13: the body of a note opens the note, but not over links or a text selection.
tufte_formats_assert_same( true, str_contains( $tufte_formats_js, 'noteOpenTarget' ) && str_contains( $tufte_formats_js, "target.closest( 'a, button, input" ) && str_contains( $tufte_formats_js, 'getSelection()' ), 'archive.js: a note click skips links and active text selections' );
tufte_formats_assert_same( true, str_contains( $tufte_formats_js, 'iframe, .wp-lightbox-container' ), 'archive.js: a note click on a lightbox image opens the lightbox, not the note' );
tufte_formats_assert_same( true, str_contains( $tufte_formats_js, "card.classList.contains( 'format-standard' )" ), 'archive.js: essay cards keep their own stretched link' );

// The router swaps in place on every archive view, including views a site adds through the filter.
$tf_saved_views = $GLOBALS['wp_filter']['tufte_formats_archive_views'] ?? null;
unset( $GLOBALS['wp_filter']['tufte_formats_archive_views'] );
tufte_formats_assert_same( array( '/archive/', '/archive/essays/', '/archive/notes/' ), tufte_formats_archive_paths(), 'paths: the three default views' );
$tf_add_photos = static fn( $v ) => $v + array( 'archive/photos' => 'photos' );
add_filter( 'tufte_formats_archive_views', $tf_add_photos );
tufte_formats_assert_same( array( '/archive/', '/archive/essays/', '/archive/notes/', '/archive/photos/' ), tufte_formats_archive_paths(), 'paths: a filtered view is included' );

$tf_archive_page = get_page_by_path( 'archive' );
$tf_page_id      = $tf_archive_page instanceof WP_Post ? $tf_archive_page->ID : wp_insert_post( array( 'post_title' => 'Archive', 'post_name' => 'archive', 'post_type' => 'page', 'post_status' => 'publish' ) );
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $tf_page_id ) );
$GLOBALS['wp_query']->get_posts();
remove_filter( 'tufte_formats_archive_views', $tf_add_photos );
if ( null !== $tf_saved_views ) {
	$GLOBALS['wp_filter']['tufte_formats_archive_views'] = $tf_saved_views;
}
// Interactivity state merges within a request, so compare against the views as this site really has them.
tufte_formats_enqueue_archive_module();
tufte_formats_assert_same( tufte_formats_archive_paths(), wp_interactivity_state( 'tufte-formats/archive' )['paths'] ?? null, 'paths: handed to the script as interactivity state on an archive page' );
if ( ! $tf_archive_page instanceof WP_Post ) {
	wp_delete_post( $tf_page_id, true );
}
wp_reset_query();

$tufte_formats_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/archive.js' );
tufte_formats_assert_same( 0, preg_match( '/essays\|notes/', $tufte_formats_js ), 'archive.js: no hard-coded list of views' );
tufte_formats_assert_same( true, str_contains( $tufte_formats_js, 'state.paths' ), 'archive.js: eligible links come from state.paths' );
