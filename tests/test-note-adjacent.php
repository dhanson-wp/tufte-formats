<?php
declare(strict_types=1);

// Seeded in 1971 (past, so published) so no real content can interleave.
$mk = static function ( string $title, string $date, ?string $format ) {
	$id = wp_insert_post( array( 'post_title' => $title, 'post_status' => 'publish', 'post_date' => $date ) );
	if ( $format ) {
		set_post_format( $id, $format );
	}

	return $id;
};

$e0 = $mk( 'adj-e0', '1970-12-31 10:00:00', null );
$n1 = $mk( 'adj-n1', '1971-01-01 10:00:00', 'status' );
$e1 = $mk( 'adj-e1', '1971-01-02 10:00:00', null );
$n2 = $mk( 'adj-n2', '1971-01-03 10:00:00', 'image' );
$e2 = $mk( 'adj-e2', '1971-01-04 10:00:00', null );
$n3 = $mk( 'adj-n3', '1971-01-05 10:00:00', 'quote' );
$e3 = $mk( 'adj-e3', '1971-01-06 10:00:00', null );

$GLOBALS['post'] = get_post( $n2 );
$prev            = get_adjacent_post( false, '', true );
$next            = get_adjacent_post( false, '', false );
tufte_formats_assert_same( $n1, $prev->ID ?? 0, 'adjacent: older of a note skips the essay between and crosses formats' );
tufte_formats_assert_same( $n3, $next->ID ?? 0, 'adjacent: newer of a note skips the essay between and crosses formats' );

// Unfiltered core would return the essay $e0 here.
$GLOBALS['post'] = get_post( $n1 );
tufte_formats_assert_same( '', get_adjacent_post( false, '', true ), 'adjacent: the oldest note has no older link (essay before it is skipped)' );

// Real content is all later than 1971, so a strict "no newer note" is not
// possible; unfiltered core would return the essay $e3, so assert that instead.
$GLOBALS['post'] = get_post( $n3 );
$newer           = get_adjacent_post( false, '', false );
tufte_formats_assert_same( true, is_object( $newer ) ? ( $newer->ID !== $e3 && tufte_formats_is_note( $newer ) ) : true, 'adjacent: the newest seeded note does not link to the essay after it' );

$GLOBALS['post'] = get_post( $e1 );
$prev            = get_adjacent_post( false, '', true );
tufte_formats_assert_same( $n1, $prev->ID ?? 0, 'adjacent: an essay still navigates to any post (the filter only applies to notes)' );

foreach ( array( $e0, $n1, $e1, $n2, $e2, $n3, $e3 ) as $id ) {
	wp_delete_post( $id, true );
}
unset( $GLOBALS['post'] );
