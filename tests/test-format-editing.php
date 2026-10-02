<?php
/**
 * Format editing: untitled posts become notes when they are created (the agent path), and "New note" starts a note.
 *
 * @package Tufte_Formats
 */

// Detecting a format from the first block.
$tf_detect = static fn( string $c ): string => tufte_formats_detect_format( $c );
tufte_formats_assert_same( 'status', $tf_detect( '<!-- wp:paragraph --><p>A small thing.</p><!-- /wp:paragraph -->' ), 'detect: a text paragraph is a status' );
tufte_formats_assert_same( 'image', $tf_detect( '<!-- wp:image --><figure class="wp-block-image"><img src="https://example.org/a.jpg" alt=""/></figure><!-- /wp:image -->' ), 'detect: an image first is an image note' );
tufte_formats_assert_same( 'gallery', $tf_detect( '<!-- wp:gallery --><figure class="wp-block-gallery"></figure><!-- /wp:gallery -->' ), 'detect: a gallery first is a gallery note' );
tufte_formats_assert_same( 'quote', $tf_detect( '<!-- wp:quote --><blockquote class="wp-block-quote"><p>Words.</p></blockquote><!-- /wp:quote -->' ), 'detect: a quote first is a quote note' );
tufte_formats_assert_same( 'video', $tf_detect( '<!-- wp:video --><figure class="wp-block-video"><video src="https://example.org/a.mp4"></video></figure><!-- /wp:video -->' ), 'detect: a video first is a video note' );
tufte_formats_assert_same( 'link', $tf_detect( '<!-- wp:paragraph --><p><em><a href="https://example.org/x">A title</a></em></p><!-- /wp:paragraph -->' ), 'detect: a paragraph that is just a link is a link note' );
tufte_formats_assert_same( 'status', $tf_detect( '<!-- wp:paragraph --><p>Read <a href="https://example.org/x">this</a> today.</p><!-- /wp:paragraph -->' ), 'detect: a link inside a sentence is still a status' );
tufte_formats_assert_same( '', $tf_detect( '' ), 'detect: empty content has no format' );

// Creation through code or REST, which is what agents do.
$tf_make = static function ( array $args ): int {
	$id = wp_insert_post( array_merge( array( 'post_status' => 'draft', 'post_type' => 'post', 'post_content' => '<!-- wp:paragraph --><p>A small thing.</p><!-- /wp:paragraph -->' ), $args ) );
	return (int) $id;
};
$tf_ids = array();

$tf_ids[] = $a = $tf_make( array( 'post_title' => '' ) );
tufte_formats_assert_same( 'status', get_post_format( $a ), 'agent path: an untitled post is created as a status note' );

$tf_ids[] = $b = $tf_make( array( 'post_title' => '', 'post_content' => '<!-- wp:image --><figure class="wp-block-image"><img src="https://example.org/a.jpg" alt=""/></figure><!-- /wp:image -->' ) );
tufte_formats_assert_same( 'image', get_post_format( $b ), 'agent path: an untitled image post is an image note' );

$tf_ids[] = $c = $tf_make( array( 'post_title' => 'An essay' ) );
tufte_formats_assert_same( false, get_post_format( $c ), 'agent path: a titled post stays an essay' );

$tf_ids[] = $d = $tf_make( array( 'post_title' => '', 'post_status' => 'publish', 'post_content' => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->' ) );
tufte_formats_assert_same( 'status', get_post_format( $d ), 'agent path: publishing straight away also works' );

// Updating never reformats.
wp_update_post( array( 'ID' => $a, 'post_content' => '<!-- wp:image --><figure class="wp-block-image"><img src="https://example.org/a.jpg" alt=""/></figure><!-- /wp:image -->' ) );
tufte_formats_assert_same( 'status', get_post_format( $a ), 'agent path: an update does not change the format again' );
set_post_format( $a, '' );
wp_update_post( array( 'ID' => $a, 'post_title' => '' ) );
tufte_formats_assert_same( false, get_post_format( $a ), 'agent path: switching a note back to standard sticks' );

// Pages and other types are not notes.
$tf_page = wp_insert_post( array( 'post_type' => 'page', 'post_title' => '', 'post_status' => 'draft', 'post_content' => 'x' ) );
tufte_formats_assert_same( false, get_post_format( $tf_page ), 'agent path: pages are never formatted' );
wp_delete_post( $tf_page, true );

// The filter turns it off.
add_filter( 'tufte_formats_auto_format', '__return_false' );
$tf_ids[] = $f = $tf_make( array( 'post_title' => '' ) );
tufte_formats_assert_same( false, get_post_format( $f ), 'agent path: the tufte_formats_auto_format filter turns it off' );
remove_filter( 'tufte_formats_auto_format', '__return_false' );

// New note: an auto-draft started from the New note link is a status note; a normal New post is not.
$_GET['tufte_note'] = '1';
$tf_ids[] = $g = $tf_make( array( 'post_title' => 'Auto Draft', 'post_status' => 'auto-draft', 'post_content' => '' ) );
tufte_formats_assert_same( 'status', get_post_format( $g ), 'new note: the link starts a status note' );
unset( $_GET['tufte_note'] );
$tf_ids[] = $h = $tf_make( array( 'post_title' => 'Auto Draft', 'post_status' => 'auto-draft', 'post_content' => '' ) );
tufte_formats_assert_same( false, get_post_format( $h ), 'new note: a normal new post stays standard' );
tufte_formats_assert_same( true, str_contains( tufte_formats_new_note_url(), 'tufte_note=1' ), 'new note: the link carries the flag' );

foreach ( $tf_ids as $tf_id ) {
	wp_delete_post( $tf_id, true );
}
