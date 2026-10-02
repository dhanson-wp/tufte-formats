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
