<?php
declare(strict_types=1);

$note = wp_insert_post( array( 'post_title' => 'th-note', 'post_status' => 'publish' ) );
set_post_format( $note, 'status' );
$essay = wp_insert_post( array( 'post_title' => 'th-essay', 'post_status' => 'publish' ) );

$orig_depth    = get_option( 'thread_comments_depth', null );
$orig_page     = get_option( 'page_comments', null );
$orig_wp_query = $GLOBALS['wp_query'];
$orig_post     = $GLOBALS['post'] ?? null;

update_option( 'thread_comments_depth', 5 );
update_option( 'page_comments', 1 );

tufte_formats_assert_same( 5, (int) get_option( 'thread_comments_depth' ), 'thread: outside a note, depth is the site setting' );

$fake_single = static function ( int $post_id ): void {
	$q                      = new WP_Query();
	$q->is_single           = true;
	$q->is_singular         = true;
	$q->queried_object      = get_post( $post_id );
	$q->queried_object_id   = $post_id;
	$GLOBALS['wp_query']    = $q;
	$GLOBALS['post']        = get_post( $post_id );
};

$fake_single( $note );
tufte_formats_assert_same( 2, (int) get_option( 'thread_comments_depth' ), 'thread: depth is 2 on a single note' );
tufte_formats_assert_same( 0, (int) get_option( 'page_comments' ), 'thread: comment pagination is off on a single note' );

$fake_single( $essay );
tufte_formats_assert_same( 5, (int) get_option( 'thread_comments_depth' ), 'thread: an essay keeps the site depth' );
tufte_formats_assert_same( 1, (int) get_option( 'page_comments' ), 'thread: an essay keeps comment pagination' );

$GLOBALS['wp_query'] = $orig_wp_query;
$GLOBALS['post']     = $orig_post;
if ( null === $orig_depth ) {
	delete_option( 'thread_comments_depth' );
} else {
	update_option( 'thread_comments_depth', $orig_depth );
}
if ( null === $orig_page ) {
	delete_option( 'page_comments' );
} else {
	update_option( 'page_comments', $orig_page );
}

// Essay body is not shipped inside the archive: the filter blanks it only when an archive view is active.
tufte_formats_assert_same( '<p>body</p>', tufte_formats_archive_essay_body( '<p>body</p>', $essay, '' ), 'essay body: kept outside archive views' );
tufte_formats_assert_same( '', tufte_formats_archive_essay_body( '<p>body</p>', $essay, 'all' ), 'essay body: dropped inside an archive view' );
tufte_formats_assert_same( '<p>body</p>', tufte_formats_archive_essay_body( '<p>body</p>', $note, 'all' ), 'essay body: a note keeps its body in an archive view' );

// The render_block wrapper, driven inside a faked archive page.
$page_created = false;
$archive_page = get_page_by_path( 'archive' );
if ( ! $archive_page ) {
	$archive_id   = wp_insert_post( array( 'post_title' => 'archive', 'post_name' => 'archive', 'post_type' => 'page', 'post_status' => 'publish' ) );
	$archive_page = get_post( $archive_id );
	$page_created = true;
}
$other_page = wp_insert_post( array( 'post_title' => 'th-other-page', 'post_type' => 'page', 'post_status' => 'publish' ) );

$wrap_wp_query = $GLOBALS['wp_query'];
$wrap_post     = $GLOBALS['post'] ?? null;
$q                    = new WP_Query();
$q->is_page           = true;
$q->is_singular       = true;
$q->queried_object    = $archive_page;
$q->queried_object_id = $archive_page->ID;
$GLOBALS['wp_query']  = $q;
$GLOBALS['post']      = $archive_page;

$wrap = static function ( int $id ): string {
	$block = new WP_Block( array( 'blockName' => 'core/post-content', 'attrs' => array() ), array( 'postId' => $id ) );
	return tufte_formats_archive_post_content( '<p>body</p>', array(), $block );
};

tufte_formats_assert_same( 'all', tufte_formats_archive_view(), 'wrapper setup: archive view is active' );
tufte_formats_assert_same( '', $wrap( $essay ), 'wrapper: an essay body is blanked in an archive view' );
tufte_formats_assert_same( '<p>body</p>', $wrap( $note ), 'wrapper: a note body is kept in an archive view' );
tufte_formats_assert_same( '<p>body</p>', $wrap( $other_page ), 'wrapper: a page body is kept in an archive view' );
tufte_formats_assert_same( '<p>body</p>', $wrap( (int) $archive_page->ID ), 'wrapper: the archive page own body is kept' );
tufte_formats_assert_same( '<p>body</p>', $wrap( 0 ), 'wrapper: a missing postId keeps the content' );

$GLOBALS['wp_query'] = $wrap_wp_query;
$GLOBALS['post']     = $wrap_post;
wp_delete_post( $other_page, true );
if ( $page_created ) {
	wp_delete_post( $archive_page->ID, true );
}

wp_delete_post( $note, true );
wp_delete_post( $essay, true );
