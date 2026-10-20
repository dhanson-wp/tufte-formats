<?php
declare(strict_types=1);

tufte_formats_assert_same( 'all', tufte_formats_archive_view_for_uri( 'archive' ), 'view uri: archive is all' );
tufte_formats_assert_same( 'essays', tufte_formats_archive_view_for_uri( 'archive/essays' ), 'view uri: essays' );
tufte_formats_assert_same( 'notes', tufte_formats_archive_view_for_uri( 'archive/notes' ), 'view uri: notes' );
tufte_formats_assert_same( '', tufte_formats_archive_view_for_uri( 'about' ), 'view uri: other pages have no view' );
tufte_formats_assert_same( '', tufte_formats_archive_view_for_uri( 'notes' ), 'view uri: a top-level notes page is not an archive view' );

$base = array( 'post_type' => 'post', 'posts_per_page' => 20 );

$all = tufte_formats_apply_archive_view( $base, 'all' );
tufte_formats_assert_same( $base, $all, 'apply: all leaves the query untouched (notes stay in)' );

$essays = tufte_formats_apply_archive_view( $base, 'essays' );
$last   = end( $essays['tax_query'] );
tufte_formats_assert_same( 'NOT IN', $last['operator'], 'apply: essays excludes notes' );

$notes = tufte_formats_apply_archive_view( $base, 'notes' );
$last  = end( $notes['tax_query'] );
tufte_formats_assert_same( 'IN', $last['operator'], 'apply: notes includes only notes' );

$kept = tufte_formats_apply_archive_view(
	array( 'tax_query' => array( array( 'taxonomy' => 'category', 'terms' => array( 1 ) ) ) ),
	'essays'
);
tufte_formats_assert_same( 'AND', $kept['tax_query']['relation'], 'apply: an existing tax query is combined with AND, not replaced' );

// End to end with real posts.
$essay = wp_insert_post( array( 'post_title' => 'av-essay', 'post_status' => 'publish' ) );
$note  = wp_insert_post( array( 'post_title' => 'av-note', 'post_status' => 'publish' ) );
set_post_format( $note, 'quote' );
$args = array( 'post__in' => array( $essay, $note ), 'orderby' => 'post__in', 'fields' => 'ids', 'posts_per_page' => -1 );

tufte_formats_assert_same( array( $essay, $note ), ( new WP_Query( tufte_formats_apply_archive_view( $args, 'all' ) ) )->posts, 'e2e: all returns both' );
tufte_formats_assert_same( array( $essay ), ( new WP_Query( tufte_formats_apply_archive_view( $args, 'essays' ) ) )->posts, 'e2e: essays returns the essay only' );
tufte_formats_assert_same( array( $note ), ( new WP_Query( tufte_formats_apply_archive_view( $args, 'notes' ) ) )->posts, 'e2e: notes returns the note only' );

wp_delete_post( $essay, true );
wp_delete_post( $note, true );

// Template hierarchy: archive children pick their own templates.
$hier = array( 'page-notes', 'page-5', 'page' );
tufte_formats_assert_same( array( 'page-archive-essays', 'page-notes', 'page-5', 'page' ), tufte_formats_archive_page_templates( $hier, 'essays' ), 'hierarchy: essays view prepends page-archive-essays' );
tufte_formats_assert_same( array( 'page-archive-notes', 'page-notes', 'page-5', 'page' ), tufte_formats_archive_page_templates( $hier, 'notes' ), 'hierarchy: notes view prepends page-archive-notes' );
tufte_formats_assert_same( $hier, tufte_formats_archive_page_templates( $hier, 'all' ), 'hierarchy: all view relies on the page-archive slug' );
tufte_formats_assert_same( $hier, tufte_formats_archive_page_templates( $hier, '' ), 'hierarchy: pages outside the archive are untouched' );

// The view applies only to the archive stream query (queryId 21).
$stream_block = static function ( int $query_id, array $query_settings ) {
	$parsed = array( 'blockName' => 'core/post-template', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
	return new WP_Block( $parsed, array( 'queryId' => $query_id, 'query' => $query_settings ) );
};
$archive_page = get_page_by_path( 'archive' );
$page_id      = $archive_page instanceof WP_Post ? $archive_page->ID : wp_insert_post( array( 'post_title' => 'Archive', 'post_name' => 'archive', 'post_type' => 'page', 'post_status' => 'publish' ) );
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $page_id ) );
$GLOBALS['wp_query']->get_posts();
tufte_formats_assert_same( 'all', tufte_formats_archive_view(), 'queryId: harness is on the archive page' );
$vars = array( 'post_type' => 'post' );
$out  = tufte_formats_notes_query_block( $vars, $stream_block( 21, array( 'postType' => 'post' ) ) );
tufte_formats_assert_same( $vars, $out, 'queryId: the stream on the archive page keeps notes in (all view)' );
$out = tufte_formats_notes_query_block( $vars, $stream_block( 5, array( 'postType' => 'post' ) ) );
tufte_formats_assert_same( 'NOT IN', end( $out['tax_query'] )['operator'], 'queryId: any other query on the page still excludes notes' );
if ( ! $archive_page instanceof WP_Post ) {
	wp_delete_post( $page_id, true );
}
wp_reset_query();

// Configurable views: other sites add URIs and format-based views through filters.
tufte_formats_assert_same( '', tufte_formats_archive_view_for_uri( 'archive/photos' ), 'views: photos is not a view without the filter' );
$tf_add_views = static fn( $v ) => $v + array( 'archive/photos' => 'photos' );
add_filter( 'tufte_formats_archive_views', $tf_add_views );
tufte_formats_assert_same( 'photos', tufte_formats_archive_view_for_uri( 'archive/photos' ), 'views: a filtered URI maps to its view' );
tufte_formats_assert_same( 'notes', tufte_formats_archive_view_for_uri( 'archive/notes' ), 'views: defaults survive the filter' );
remove_filter( 'tufte_formats_archive_views', $tf_add_views );

tufte_formats_assert_same( array(), tufte_formats_archive_view_formats(), 'formats: no format views by default' );
$tf_add_formats = static fn( $m ) => $m + array( 'photos' => array( 'image', 'gallery' ) );
add_filter( 'tufte_formats_archive_view_formats', $tf_add_formats );
$photos = tufte_formats_apply_archive_view( $base, 'photos' );
$last   = end( $photos['tax_query'] );
tufte_formats_assert_same( array( 'post_format', 'IN', array( 'post-format-image', 'post-format-gallery' ) ), array( $last['taxonomy'], $last['operator'], $last['terms'] ), 'apply: photos keeps image and gallery formats' );
tufte_formats_assert_same( $base, tufte_formats_apply_archive_view( $base, 'unknown' ), 'apply: an unknown view leaves the query alone' );

$fv_essay   = wp_insert_post( array( 'post_title' => 'fv-essay', 'post_status' => 'publish' ) );
$fv_gallery = wp_insert_post( array( 'post_title' => 'fv-gallery', 'post_status' => 'publish' ) );
$fv_image   = wp_insert_post( array( 'post_title' => 'fv-image', 'post_status' => 'publish' ) );
$fv_status  = wp_insert_post( array( 'post_title' => 'fv-status', 'post_status' => 'publish' ) );
set_post_format( $fv_gallery, 'gallery' );
set_post_format( $fv_image, 'image' );
set_post_format( $fv_status, 'status' );
$fv_args = array( 'post__in' => array( $fv_essay, $fv_gallery, $fv_image, $fv_status ), 'orderby' => 'post__in', 'fields' => 'ids', 'posts_per_page' => -1 );
tufte_formats_assert_same( array( $fv_gallery, $fv_image ), ( new WP_Query( tufte_formats_apply_archive_view( $fv_args, 'photos' ) ) )->posts, 'e2e: photos returns image and gallery notes only' );
tufte_formats_assert_same( array( $fv_gallery, $fv_image, $fv_status ), ( new WP_Query( tufte_formats_apply_archive_view( $fv_args, 'notes' ) ) )->posts, 'e2e: notes still returns every note' );
tufte_formats_assert_same( array( $fv_essay ), ( new WP_Query( tufte_formats_apply_archive_view( $fv_args, 'essays' ) ) )->posts, 'e2e: essays never returns a photo' );
foreach ( array( $fv_essay, $fv_gallery, $fv_image, $fv_status ) as $fv_id ) {
	wp_delete_post( $fv_id, true );
}

tufte_formats_assert_same( array( 'page-archive-photos', 'page' ), tufte_formats_archive_page_templates( array( 'page' ), 'photos' ), 'hierarchy: any non-all view prepends its template' );
remove_filter( 'tufte_formats_archive_view_formats', $tf_add_formats );
