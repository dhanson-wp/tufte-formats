<?php
/**
 * Search: notes appear in results, and a format filter narrows them.
 *
 * The filter is a plain query var, ?s=term&format=<value>, so every link works
 * without JavaScript. Values: all (the default), essays (standard posts), notes
 * (every non-standard format), or one format slug such as link or quote. Anything
 * else falls back to all, never an error. Any value but all narrows the results
 * to posts, so pages show under All only.
 *
 * @package Tufte_Formats
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The most posts the filter row looks at when it counts which formats have matches.
 */
const TUFTE_FORMATS_SEARCH_COUNT_LIMIT = 500;

/**
 * The format filter value for a raw request value.
 *
 * @param string|null $raw Raw value, or null to read ?format= from the request.
 * @return string all, essays, notes, or a note format slug.
 */
function tufte_formats_search_filter_value( ?string $raw = null ): string {
	if ( null === $raw ) {
		// A read-only filter on a public page, so there's nothing to nonce.
		$raw = isset( $_GET['format'] ) && is_string( $_GET['format'] ) ? wp_unslash( $_GET['format'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	$value   = sanitize_key( $raw );
	$formats = array_diff( array_keys( get_post_format_slugs() ), array( 'standard' ) );

	if ( in_array( $value, array( 'essays', 'notes' ), true ) || in_array( $value, $formats, true ) ) {
		return $value;
	}

	return 'all';
}

/**
 * Narrow the main search query to the requested format.
 *
 * @param WP_Query $query Query.
 * @return void
 */
function tufte_formats_search_filter_query( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}

	$value = tufte_formats_search_filter_value();
	if ( 'all' === $value ) {
		return;
	}

	if ( 'essays' === $value ) {
		$clause = tufte_formats_notes_clause( 'NOT IN' );
	} elseif ( 'notes' === $value ) {
		$clause = tufte_formats_notes_clause( 'IN' );
	} else {
		$clause = array(
			'taxonomy' => 'post_format',
			'field'    => 'slug',
			'terms'    => array( 'post-format-' . $value ),
			'operator' => 'IN',
		);
	}

	$query->set( 'post_type', 'post' );
	$query->set( 'tax_query', tufte_formats_notes_and( $query->get( 'tax_query' ), $clause ) );
}
add_action( 'pre_get_posts', 'tufte_formats_search_filter_query' );

/**
 * Keep Jetpack Search out of a filtered search.
 *
 * Jetpack Search answers the main search query from its own index and only reads the
 * IN terms of a tax query, so it would ignore the Essays filter's NOT IN clause. A
 * filtered search goes to WordPress's own query instead. Unfiltered searches stay with
 * Jetpack, and they include notes.
 *
 * @param bool     $handle Whether Jetpack Search handles the query.
 * @param WP_Query $query  Query.
 * @return bool
 */
function tufte_formats_search_filter_jetpack( $handle, $query ) {
	if ( $query instanceof WP_Query && 'all' !== tufte_formats_search_filter_value() ) {
		return false;
	}

	return $handle;
}
add_filter( 'jetpack_search_should_handle_query', 'tufte_formats_search_filter_jetpack', 10, 2 );

/**
 * The URL for one filter value, keeping the search term.
 *
 * @param string $search Search term.
 * @param string $value  Filter value.
 * @return string
 */
function tufte_formats_search_filter_url( string $search, string $value ): string {
	$url = home_url( '/' ) . '?s=' . rawurlencode( $search );

	return 'all' === $value ? $url : $url . '&format=' . rawurlencode( $value );
}

/**
 * The filter row for a search: All, Essays, Notes, then each note format that has matches.
 *
 * Counts the posts that match the term, up to TUFTE_FORMATS_SEARCH_COUNT_LIMIT. Each item is
 * array( value, label, url, current ). Labels are plain text and urls are unescaped, so the
 * caller escapes them.
 *
 * @param string      $search  Search term.
 * @param string|null $current Current filter value, or null to read the request.
 * @return array<int, array{value: string, label: string, url: string, current: bool}>
 */
function tufte_formats_search_filter_items( string $search, ?string $current = null ): array {
	$current = null === $current ? tufte_formats_search_filter_value() : tufte_formats_search_filter_value( $current );

	$present = array();
	if ( '' !== $search ) {
		$ids = get_posts(
			array(
				's'                => $search,
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'fields'           => 'ids',
				'posts_per_page'   => TUFTE_FORMATS_SEARCH_COUNT_LIMIT,
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
		if ( $ids ) {
			$terms = wp_get_object_terms( $ids, 'post_format', array( 'fields' => 'slugs' ) );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $slug ) {
					$present[ str_replace( 'post-format-', '', (string) $slug ) ] = true;
				}
			}
		}
	}

	$items = array(
		array( 'value' => 'all', 'label' => __( 'All', 'tufte-formats' ) ),
		array( 'value' => 'essays', 'label' => __( 'Essays', 'tufte-formats' ) ),
		array( 'value' => 'notes', 'label' => __( 'Notes', 'tufte-formats' ) ),
	);

	$formats = array();
	foreach ( array_diff( array_keys( get_post_format_slugs() ), array( 'standard' ) ) as $slug ) {
		if ( isset( $present[ $slug ] ) || $slug === $current ) {
			$formats[ $slug ] = (string) get_post_format_string( $slug );
		}
	}
	asort( $formats );
	foreach ( $formats as $slug => $label ) {
		$items[] = array( 'value' => $slug, 'label' => $label );
	}

	foreach ( $items as $i => $item ) {
		$items[ $i ]['url']     = tufte_formats_search_filter_url( $search, $item['value'] );
		$items[ $i ]['current'] = $item['value'] === $current;
	}

	return $items;
}
