<?php
declare(strict_types=1);

// Tag and place archives list notes; category archives and the blog loop don't.
// Registers a stand-in place taxonomy when Tufte Places isn't active.
$tf_place_registered_here = false;
if ( ! taxonomy_exists( 'place' ) ) {
	register_taxonomy( 'place', 'post', array( 'public' => true, 'rewrite' => false ) );
	$tf_place_registered_here = true;
}

tufte_formats_assert_same( array( 'post_tag', 'place' ), tufte_formats_note_archive_taxonomies(), 'note archive taxonomies: tags and places by default' );

$tf_place = wp_insert_term( 't-note-archive-place', 'place' );
$tf_tag   = wp_insert_term( 't-note-archive-tag', 'post_tag' );
$tf_cat   = wp_insert_term( 't-note-archive-cat', 'category' );
$tf_essay = wp_insert_post( array( 'post_title' => 't-na-essay', 'post_status' => 'publish', 'post_type' => 'post' ) );
$tf_photo = wp_insert_post( array( 'post_title' => 't-na-photo', 'post_status' => 'publish', 'post_type' => 'post' ) );
set_post_format( $tf_photo, 'gallery' );
foreach ( array( $tf_essay, $tf_photo ) as $tf_id ) {
	wp_set_object_terms( $tf_id, array( (int) $tf_place['term_id'] ), 'place' );
	wp_set_object_terms( $tf_id, array( (int) $tf_tag['term_id'] ), 'post_tag' );
	wp_set_object_terms( $tf_id, array( (int) $tf_cat['term_id'] ), 'category' );
}
$tf_both = array( $tf_essay, $tf_photo );
sort( $tf_both );

/**
 * Run a main query, returning the ids of our two test posts it matched.
 */
$tf_main = static function ( array $vars ) use ( $tf_both ): array {
	$q                       = new WP_Query();
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = $q;
	$ids                     = array_values( array_intersect( array_map( 'intval', wp_list_pluck( $q->query( $vars + array( 'posts_per_page' => 50 ) ), 'ID' ) ), $tf_both ) );
	sort( $ids );
	return $ids;
};

$tf_place_slug = get_term( (int) $tf_place['term_id'], 'place' )->slug;
$tf_tag_slug   = get_term( (int) $tf_tag['term_id'], 'post_tag' )->slug;
$tf_cat_slug   = get_term( (int) $tf_cat['term_id'], 'category' )->slug;

tufte_formats_assert_same( $tf_both, $tf_main( array( 'place' => $tf_place_slug ) ), 'place archive: lists the gallery note and the essay' );
tufte_formats_assert_same( $tf_both, $tf_main( array( 'tag' => $tf_tag_slug ) ), 'tag archive: lists the gallery note and the essay' );
tufte_formats_assert_same( array( $tf_essay ), $tf_main( array( 'category_name' => $tf_cat_slug ) ), 'category archive: leaves the note out' );
tufte_formats_assert_same( array( $tf_essay ), $tf_main( array() ), 'blog loop: leaves the note out' );

// The filter decides: dropping place sends notes back out of place archives, adding category lets them in.
$tf_only_tags = static function (): array {
	return array( 'post_tag' );
};
add_filter( 'tufte_formats_note_archive_taxonomies', $tf_only_tags );
tufte_formats_assert_same( array( $tf_essay ), $tf_main( array( 'place' => $tf_place_slug ) ), 'filter: a place archive without place in the list leaves the note out' );
remove_filter( 'tufte_formats_note_archive_taxonomies', $tf_only_tags );

$tf_add_cat = static function ( array $taxonomies ): array {
	$taxonomies[] = 'category';
	return $taxonomies;
};
add_filter( 'tufte_formats_note_archive_taxonomies', $tf_add_cat );
tufte_formats_assert_same( $tf_both, $tf_main( array( 'category_name' => $tf_cat_slug ) ), 'filter: adding category lists notes on category archives' );
remove_filter( 'tufte_formats_note_archive_taxonomies', $tf_add_cat );

$tf_junk = static function (): array {
	return array( 'post_tag', 42, null );
};
add_filter( 'tufte_formats_note_archive_taxonomies', $tf_junk );
tufte_formats_assert_same( array( 'post_tag' ), tufte_formats_note_archive_taxonomies(), 'filter: non-string entries are dropped' );
remove_filter( 'tufte_formats_note_archive_taxonomies', $tf_junk );

$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new WP_Query();
wp_delete_post( $tf_essay, true );
wp_delete_post( $tf_photo, true );
wp_delete_term( (int) $tf_place['term_id'], 'place' );
wp_delete_term( (int) $tf_tag['term_id'], 'post_tag' );
wp_delete_term( (int) $tf_cat['term_id'], 'category' );
if ( $tf_place_registered_here ) {
	unregister_taxonomy( 'place' );
}
