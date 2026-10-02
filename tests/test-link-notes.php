<?php
/**
 * Link-format notes: the first external link in the content is the note's link, marked as external, with a linked source name.
 *
 * @package Tufte_Formats
 */

$tf_host = 'site.example';

// A link in the content: the title link is marked, and a source name is added and linked.
$tf_in  = '<p><em><a href="https://example.org/why-hard">Why programming is difficult</a></em></p>';
$tf_out = tufte_formats_link_note_content( $tf_in, $tf_host );
tufte_formats_assert_same( true, str_contains( $tf_out, '<a class="tufte-external" href="https://example.org/why-hard">Why programming is difficult<span class="screen-reader-text tufte-visually-hidden"> (external link)</span></a>' ), 'link note: the title link is marked external with screen reader text' );
tufte_formats_assert_same( true, str_contains( $tf_out, '<span class="ref-src"><a class="tufte-external" href="https://example.org/why-hard">example.org<span class="screen-reader-text tufte-visually-hidden"> (external link)</span></a></span>' ), 'link note: a missing source name is added from the host and linked' );
tufte_formats_assert_same( false, str_contains( $tf_out, 'target=' ), 'link note: opens in the same tab (no target attribute)' );

// An author-written source name is kept and linked.
$tf_in  = '<p><em><a href="https://example.org/x">A title</a></em> <span class="ref-src">Aeon</span></p>';
$tf_out = tufte_formats_link_note_content( $tf_in, $tf_host );
tufte_formats_assert_same( true, str_contains( $tf_out, '<span class="ref-src"><a class="tufte-external" href="https://example.org/x">Aeon<span class="screen-reader-text tufte-visually-hidden"> (external link)</span></a></span>' ), 'link note: an existing source name is kept and linked to the same URL' );
tufte_formats_assert_same( 1, substr_count( $tf_out, 'class="ref-src"' ), 'link note: still one source name' );

// Running twice changes nothing more.
tufte_formats_assert_same( $tf_out, tufte_formats_link_note_content( $tf_out, $tf_host ), 'link note: idempotent' );

// No link, no URL invented.
$tf_plain = '<p><em>Why programming is difficult</em> <span class="ref-src">example.org</span></p>';
tufte_formats_assert_same( $tf_plain, tufte_formats_link_note_content( $tf_plain, $tf_host ), 'link note: no link in the content leaves it as plain text' );

// Links to this site are not external; an internal first link is skipped for an external later one.
$tf_internal = '<p><a href="https://site.example/about/">About</a></p>';
tufte_formats_assert_same( $tf_internal, tufte_formats_link_note_content( $tf_internal, $tf_host ), 'link note: an internal link is not marked external' );
$tf_mixed = '<p><a href="https://site.example/about/">About</a> and <a href="https://example.org/z">Z</a></p>';
tufte_formats_assert_same( true, str_contains( tufte_formats_link_note_content( $tf_mixed, $tf_host ), '<span class="ref-src"><a class="tufte-external" href="https://example.org/z">' ), 'link note: the first external link is the note link' );

// Relative and mailto links are not external.
tufte_formats_assert_same( '', tufte_formats_link_note_url( '<a href="/about/">x</a> <a href="mailto:a@b.co">m</a>', $tf_host ), 'link note: relative and mailto links are not the note link' );

// The filter only runs for link-format notes.
$tf_id = wp_insert_post( array( 'post_title' => 'tf link', 'post_status' => 'publish', 'post_content' => $tf_in ) );
set_post_format( $tf_id, 'link' );
$GLOBALS['post'] = get_post( $tf_id );
setup_postdata( $GLOBALS['post'] );
tufte_formats_assert_same( true, str_contains( tufte_formats_link_note_filter( $tf_in ), 'tufte-external' ), 'link note filter: runs on a link-format post' );
set_post_format( $tf_id, 'status' );
tufte_formats_assert_same( $tf_in, tufte_formats_link_note_filter( $tf_in ), 'link note filter: leaves other formats alone' );
wp_delete_post( $tf_id, true );
wp_reset_postdata();

// A URL with backreference-looking text stays intact.
$tf_odd = '<p><a href="https://example.org/a$1b\\1c">T</a></p>';
tufte_formats_assert_same( true, str_contains( tufte_formats_link_note_content( $tf_odd, $tf_host ), 'href="https://example.org/a$1b\\1c">T' ), 'link note: "$1" and a backslash in a URL are not backreferences' );

// Feeds and REST get the content untouched (REST_REQUEST is a constant, so this is checked in the source).
$tf_src = (string) file_get_contents( dirname( __DIR__ ) . '/includes/link-notes.php' );
tufte_formats_assert_same( true, str_contains( $tf_src, "is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST )" ), 'link note filter: skipped in feeds and REST responses' );
