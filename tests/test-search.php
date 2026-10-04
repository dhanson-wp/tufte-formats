<?php
/**
 * Search: notes appear in results, and a format filter narrows them (FB-20).
 *
 * @package Tufte_Formats
 */

declare(strict_types=1);

$tf_term = 'tfsearchterm';
$tf_mk   = static function ( string $slug, ?string $format, string $type = 'post' ) use ( $tf_term ): int {
	$id = wp_insert_post( array( 'post_title' => "{$tf_term} {$slug}", 'post_status' => 'publish', 'post_type' => $type ) );
	if ( $format ) {
		set_post_format( $id, $format );
	}
	return (int) $id;
};
$tf_essay = $tf_mk( 'essay', null );
$tf_status = $tf_mk( 'status', 'status' );
$tf_link   = $tf_mk( 'link', 'link' );
$tf_quote  = $tf_mk( 'quote', 'quote' );
$tf_page   = $tf_mk( 'page', null, 'page' );
$tf_all    = array( $tf_essay, $tf_status, $tf_link, $tf_quote, $tf_page );

// Jetpack Search can answer the main search query from its own index; keep these cases on WordPress's own query.
add_filter( 'jetpack_search_should_handle_query', '__return_false', 5 );

/**
 * Run a main search query with an optional format request, returning the matching ids of our test posts.
 */
$tf_search = static function ( string $format = '', string $s = '' ) use ( $tf_term, $tf_all ): array {
	if ( '' === $format ) {
		unset( $_GET['format'] );
	} else {
		$_GET['format'] = $format;
	}
	$q                       = new WP_Query();
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = $q;
	$ids                     = wp_list_pluck( $q->query( array( 's' => '' === $s ? $tf_term : $s, 'posts_per_page' => 50 ) ), 'ID' );
	unset( $_GET['format'] );
	$ids = array_values( array_intersect( array_map( 'intval', $ids ), $tf_all ) );
	sort( $ids );
	return $ids;
};
$tf_sorted = static function ( array $ids ): array {
	sort( $ids );
	return $ids;
};

tufte_formats_assert_same( $tf_sorted( $tf_all ), $tf_search(), 'search: with no format, notes and pages appear beside essays' );
tufte_formats_assert_same( $tf_sorted( $tf_all ), $tf_search( 'all' ), 'search: format=all is the same as none' );
tufte_formats_assert_same( $tf_sorted( array( $tf_status, $tf_link, $tf_quote ) ), $tf_search( 'notes' ), 'search: format=notes is every non-standard format, and no pages' );
tufte_formats_assert_same( array( $tf_essay ), $tf_search( 'essays' ), 'search: format=essays is standard posts only' );
tufte_formats_assert_same( array( $tf_link ), $tf_search( 'link' ), 'search: format=link is link notes only' );
tufte_formats_assert_same( array( $tf_quote ), $tf_search( 'QUOTE ' ), 'search: the value is sanitized (case and spaces)' );
tufte_formats_assert_same( $tf_sorted( $tf_all ), $tf_search( 'bogus' ), 'search: an unknown format falls back to all' );
tufte_formats_assert_same( $tf_sorted( $tf_all ), $tf_search( 'standard' ), 'search: standard is not an allowed value, so it falls back to all' );
tufte_formats_assert_same( $tf_sorted( $tf_all ), $tf_search( "'; DROP TABLE" ), 'search: junk falls back to all, never an error' );

// The value helper.
foreach ( array( '' => 'all', 'all' => 'all', 'essays' => 'essays', 'notes' => 'notes', 'link' => 'link', 'image' => 'image', 'nope' => 'all', 'standard' => 'all', 'Link' => 'link' ) as $raw => $want ) {
	tufte_formats_assert_same( $want, tufte_formats_search_filter_value( (string) $raw ), "search value: '{$raw}' is '{$want}'" );
}

// Only search main queries are touched.
unset( $_GET['format'] );
$_GET['format'] = 'link';
$tf_home = new WP_Query();
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = $tf_home;
$tf_home_ids = array_map( 'intval', wp_list_pluck( $tf_home->query( array( 'posts_per_page' => 100 ) ), 'ID' ) );
unset( $_GET['format'] );
tufte_formats_assert_same( array(), array_values( array_intersect( $tf_home_ids, array( $tf_status, $tf_link, $tf_quote ) ) ), 'home: notes stay out of the home page, and a format var does nothing there' );

// Jetpack Search is told to stand aside while a format filter is active, because it ignores the NOT IN clause.
remove_filter( 'jetpack_search_should_handle_query', '__return_false', 5 );
$tf_jq = new WP_Query();
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = $tf_jq;
$tf_jq->query( array( 's' => $tf_term ) );
$_GET['format'] = 'essays';
tufte_formats_assert_same( false, apply_filters( 'jetpack_search_should_handle_query', true, $tf_jq ), 'jetpack: not handled while a format filter is active' );
unset( $_GET['format'] );
tufte_formats_assert_same( true, apply_filters( 'jetpack_search_should_handle_query', true, $tf_jq ), 'jetpack: handled as usual with no format filter' );
add_filter( 'jetpack_search_should_handle_query', '__return_false', 5 );

// The filter row's data.
$tf_items = tufte_formats_search_filter_items( $tf_term, 'link' );
$tf_values = wp_list_pluck( $tf_items, 'value' );
tufte_formats_assert_same( array( 'all', 'essays', 'notes', 'link', 'quote', 'status' ), $tf_values, 'items: All, Essays, Notes, then only the formats with matches, by label' );
tufte_formats_assert_same( array( 'link' ), array_values( wp_list_pluck( array_filter( $tf_items, static fn( $i ) => $i['current'] ), 'value' ) ), 'items: exactly the requested value is current' );
tufte_formats_assert_same( home_url( '/?s=tfsearchterm' ), $tf_items[0]['url'], 'items: All links to the bare search (no format var)' );
tufte_formats_assert_same( home_url( '/?s=tfsearchterm&format=link' ), $tf_items[3]['url'], 'items: a format link keeps the search term and adds format=' );
tufte_formats_assert_same( 'Link', $tf_items[3]['label'], 'items: formats use WordPress\'s own format labels' );
tufte_formats_assert_same( 'All', $tf_items[0]['label'], 'items: label All' );
$tf_items_all = tufte_formats_search_filter_items( $tf_term, 'bogus' );
tufte_formats_assert_same( array( 'all' ), array_values( wp_list_pluck( array_filter( $tf_items_all, static fn( $i ) => $i['current'] ), 'value' ) ), 'items: an unknown current value marks All' );
$tf_odd = tufte_formats_search_filter_items( 'a&b "c" <x>', 'all' );
tufte_formats_assert_same( home_url( '/?s=a%26b%20%22c%22%20%3Cx%3E&format=essays' ), $tf_odd[1]['url'], 'items: the search term is URL-encoded in every link' );
tufte_formats_assert_same( home_url( '/?s=a%26b%20%22c%22%20%3Cx%3E' ), $tf_odd[0]['url'], 'items: the bare search link is encoded too' );
tufte_formats_assert_same( array( 'all', 'essays', 'notes' ), wp_list_pluck( $tf_odd, 'value' ), 'items: a search with no matches still offers All, Essays, Notes' );

remove_filter( 'jetpack_search_should_handle_query', '__return_false', 5 );
foreach ( $tf_all as $tf_id ) {
	wp_delete_post( $tf_id, true );
}
wp_reset_query(); // phpcs:ignore WordPress.WP.DiscouragedFunctions.wp_reset_query_wp_reset_query
