<?php
declare(strict_types=1);

$slugs = tufte_formats_note_term_slugs();
tufte_formats_assert_same( true, in_array( 'post-format-status', $slugs, true ), 'note slugs: status included' );
tufte_formats_assert_same( true, in_array( 'post-format-image', $slugs, true ), 'note slugs: image included' );
tufte_formats_assert_same( true, in_array( 'post-format-quote', $slugs, true ), 'note slugs: quote included' );
tufte_formats_assert_same( false, in_array( 'post-format-standard', $slugs, true ), 'note slugs: standard is not a note format' );

$clause = tufte_formats_notes_clause( 'NOT IN' );
tufte_formats_assert_same( 'NOT IN', $clause['operator'], 'clause: operator passes through' );
tufte_formats_assert_same( 'post_format', $clause['taxonomy'], 'clause: post_format taxonomy' );
tufte_formats_assert_same( $slugs, $clause['terms'], 'clause: terms are every note format' );

$ids = array();
foreach ( array( 'standard-none' => null, 'standard-explicit' => 'standard', 'status' => 'status', 'image' => 'image', 'link' => 'link' ) as $label => $format ) {
	$id = wp_insert_post( array( 'post_title' => "t-{$label}", 'post_status' => 'publish', 'post_type' => 'post' ) );
	if ( $format ) {
		set_post_format( $id, $format );
	}
	$ids[ $label ] = $id;
}

tufte_formats_assert_same( false, tufte_formats_is_note( $ids['standard-none'] ), 'is_note: no format is an essay' );
tufte_formats_assert_same( false, tufte_formats_is_note( $ids['standard-explicit'] ), 'is_note: explicit standard is an essay' );
tufte_formats_assert_same( true, tufte_formats_is_note( $ids['status'] ), 'is_note: status is a note' );
tufte_formats_assert_same( true, tufte_formats_is_note( $ids['image'] ), 'is_note: image is a note' );
tufte_formats_assert_same( true, tufte_formats_is_note( $ids['link'] ), 'is_note: link is a note' );

$supported = (array) get_theme_support( 'post-formats' );
$supported = (array) ( $supported[0] ?? array() );
foreach ( array( 'aside', 'link', 'quote', 'status', 'image', 'gallery' ) as $f ) {
	tufte_formats_assert_same( true, in_array( $f, $supported, true ), "theme supports the {$f} format" );
}

$page = wp_insert_post( array( 'post_title' => 't-page', 'post_status' => 'publish', 'post_type' => 'page' ) );
tufte_formats_assert_same( false, tufte_formats_is_note( $page ), 'is_note: a page is never a note' );

$q = new WP_Query( array( 'post__in' => array_values( $ids ), 'orderby' => 'post__in', 'posts_per_page' => -1, 'fields' => 'ids', 'tax_query' => array( tufte_formats_notes_clause( 'NOT IN' ) ) ) );
tufte_formats_assert_same( array( $ids['standard-none'], $ids['standard-explicit'] ), $q->posts, 'query NOT IN: only the two essays' );

$q = new WP_Query( array( 'post__in' => array_values( $ids ), 'orderby' => 'post__in', 'posts_per_page' => -1, 'fields' => 'ids', 'tax_query' => array( tufte_formats_notes_clause( 'IN' ) ) ) );
tufte_formats_assert_same( array( $ids['status'], $ids['image'], $ids['link'] ), $q->posts, 'query IN: only the three notes' );

foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}
wp_delete_post( $page, true );
