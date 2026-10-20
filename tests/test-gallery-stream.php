<?php
declare(strict_types=1);

/**
 * Gallery markup with $count images, the way the editor saves it.
 *
 * @param int    $count   Images.
 * @param string $caption Per-image caption, if any.
 * @return string
 */
function tufte_formats_test_gallery( int $count, string $caption = '', string $prefix = 'p' ): string {
	$images = '';
	for ( $i = 1; $i <= $count; $i++ ) {
		$figcaption = '' === $caption ? '' : "<figcaption class=\"wp-element-caption\">{$caption} {$i}</figcaption>";
		$images    .= "\n\n<!-- wp:image -->\n<figure class=\"wp-block-image\"><img src=\"https://example.com/{$prefix}{$i}.jpg\" alt=\"Photo {$i}\"/>{$figcaption}</figure>\n<!-- /wp:image -->";
	}
	return "<!-- wp:gallery {\"linkTo\":\"none\"} -->\n<figure class=\"wp-block-gallery has-nested-images columns-default is-cropped\">{$images}\n\n<figcaption class=\"blocks-gallery-caption wp-element-caption\">The whole trip</figcaption></figure>\n<!-- /wp:gallery -->";
}

/**
 * Render a post's content as a stream would, inside a post template.
 *
 * @param int $post_id Post.
 * @return string
 */
function tufte_formats_test_render_in_stream( int $post_id ): string {
	$GLOBALS['post'] = get_post( $post_id );
	setup_postdata( $GLOBALS['post'] );
	tufte_formats_post_template_depth( 1 );
	$html = do_blocks( get_post_field( 'post_content', $post_id ) );
	tufte_formats_post_template_depth( -1 );
	wp_reset_postdata();
	return $html;
}

// Trimming the parsed block.
$parsed  = parse_blocks( tufte_formats_test_gallery( 18 ) )[0];
$trimmed = tufte_formats_trim_gallery_block( $parsed );
tufte_formats_assert_same( 6, count( $trimmed['innerBlocks'] ), 'trim: keeps six images' );
tufte_formats_assert_same( 6, count( array_filter( $trimmed['innerContent'], 'is_null' ) ), 'trim: innerContent keeps six placeholders' );
tufte_formats_assert_same( 18, $trimmed['attrs'][ TUFTE_FORMATS_STREAM_GALLERY_TOTAL_ATTR ], 'trim: records the full count' );
tufte_formats_assert_same( true, str_contains( (string) end( $trimmed['innerContent'] ), 'blocks-gallery-caption' ), 'trim: the gallery caption and closing tag stay' );

$small = tufte_formats_trim_gallery_block( parse_blocks( tufte_formats_test_gallery( 4 ) )[0] );
tufte_formats_assert_same( 4, count( $small['innerBlocks'] ), 'trim: a small gallery keeps every image' );
tufte_formats_assert_same( 4, $small['attrs'][ TUFTE_FORMATS_STREAM_GALLERY_TOTAL_ATTR ], 'trim: a small gallery records its count' );

// Posts to render.
$note = wp_insert_post( array( 'post_title' => '', 'post_content' => tufte_formats_test_gallery( 18, 'Fog' ), 'post_status' => 'publish', 'post_type' => 'post' ) );
set_post_format( $note, 'gallery' );
$small_note = wp_insert_post( array( 'post_title' => '', 'post_content' => tufte_formats_test_gallery( 5 ), 'post_status' => 'publish', 'post_type' => 'post' ) );
set_post_format( $small_note, 'gallery' );
$exact_note = wp_insert_post( array( 'post_title' => '', 'post_content' => tufte_formats_test_gallery( 6 ), 'post_status' => 'publish', 'post_type' => 'post' ) );
set_post_format( $exact_note, 'gallery' );
$essay = wp_insert_post( array( 'post_title' => 'An essay', 'post_content' => tufte_formats_test_gallery( 18 ), 'post_status' => 'publish', 'post_type' => 'post' ) );

// A big gallery note in a stream.
$html = tufte_formats_test_render_in_stream( $note );
tufte_formats_assert_same( 6, substr_count( $html, '<img ' ), 'stream: a big gallery renders six images' );
tufte_formats_assert_same( false, str_contains( $html, 'p7.jpg' ), 'stream: the seventh image never renders' );
tufte_formats_assert_same( true, str_contains( $html, 'tufte-stream-gallery tufte-stream-gallery--6' ), 'stream: the gallery is marked for the six-photo grid' );
tufte_formats_assert_same( 1, substr_count( $html, 'tufte-stream-gallery__more' ), 'stream: one "+N" link' );
tufte_formats_assert_same( true, str_contains( $html, '</svg>13</span></a></figure>' ), 'stream: "+N" counts the covered sixth, and closes the sixth image' );
tufte_formats_assert_same( true, str_contains( $html, 'aria-label="See all 18 photos"' ), 'stream: the link names the full count' );
tufte_formats_assert_same( true, str_contains( $html, 'href="' . esc_url( get_permalink( $note ) ) . '"' ), 'stream: the link goes to the note' );
tufte_formats_assert_same( 1, substr_count( $html, 'tufte-stream-gallery__last' ), 'stream: only the sixth image is marked' );
tufte_formats_assert_same( true, str_contains( $html, 'The whole trip' ), 'stream: the gallery caption stays in the markup' );
tufte_formats_assert_same( true, wp_style_is( 'tufte-formats-stream-gallery', 'enqueued' ), 'stream: the grid styles load' );

// Small and exactly-six galleries.
$small_html = tufte_formats_test_render_in_stream( $small_note );
tufte_formats_assert_same( 5, substr_count( $small_html, '<img ' ), 'stream: five photos all render' );
tufte_formats_assert_same( true, str_contains( $small_html, 'tufte-stream-gallery--5' ), 'stream: five photos get the five-photo reflow' );
tufte_formats_assert_same( false, str_contains( $small_html, 'tufte-stream-gallery__more' ), 'stream: five photos get no "+N"' );

$exact_html = tufte_formats_test_render_in_stream( $exact_note );
tufte_formats_assert_same( 6, substr_count( $exact_html, '<img ' ), 'stream: six photos all render' );
tufte_formats_assert_same( false, str_contains( $exact_html, 'tufte-stream-gallery__more' ), 'stream: exactly six photos get no "+N"' );

// Left alone: essays in a stream, and notes outside one.
$essay_html = tufte_formats_test_render_in_stream( $essay );
tufte_formats_assert_same( 18, substr_count( $essay_html, '<img ' ), 'essay: a gallery in an essay keeps every image' );
tufte_formats_assert_same( false, str_contains( $essay_html, 'tufte-stream-gallery' ), 'essay: no stream grid' );

$GLOBALS['post'] = get_post( $note );
setup_postdata( $GLOBALS['post'] );
$single_html = do_blocks( get_post_field( 'post_content', $note ) );
wp_reset_postdata();
tufte_formats_assert_same( 18, substr_count( $single_html, '<img ' ), 'single: outside a stream the note keeps every image' );
tufte_formats_assert_same( false, str_contains( $single_html, 'tufte-stream-gallery' ), 'single: no stream grid' );

// Through a real query loop.
$only_note = static function ( array $query ) use ( $note ): array {
	$query['post__in']  = array( $note );
	$query['tax_query'] = array();
	return $query;
};
add_filter( 'query_loop_block_query_vars', $only_note, 99 );
$loop_html = do_blocks( '<!-- wp:query {"queryId":99,"query":{"perPage":1,"postType":"post","inherit":false}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-content /--><!-- /wp:post-template --></div><!-- /wp:query -->' );
remove_filter( 'query_loop_block_query_vars', $only_note, 99 );
tufte_formats_assert_same( 6, substr_count( $loop_html, '<img ' ), 'loop: a gallery note in a query loop shows six images' );
tufte_formats_assert_same( 0, tufte_formats_post_template_depth(), 'loop: the stream depth is back to zero after the loop' );

tufte_formats_assert_same( get_post_field( 'post_content', $note ), tufte_formats_test_gallery( 18, 'Fog' ), 'content: the saved post is unchanged' );

// One six-photo budget per note, across all of its galleries.
/**
 * A paragraph block.
 *
 * @param string $text Text.
 * @return string
 */
function tufte_formats_test_paragraph( string $text ): string {
	return "<!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->";
}

$multi_content = implode(
	"\n\n",
	array(
		tufte_formats_test_paragraph( 'Breakfast first.' ),
		tufte_formats_test_gallery( 2, '', 'a' ),
		tufte_formats_test_paragraph( 'Then the cowork.' ),
		tufte_formats_test_gallery( 3, '', 'b' ),
		tufte_formats_test_paragraph( 'Lunch walk.' ),
		tufte_formats_test_gallery( 6, '', 'c' ),
		tufte_formats_test_paragraph( 'Late afternoon.' ),
		tufte_formats_test_gallery( 1, '', 'd' ),
	)
);
$multi_note = wp_insert_post( array( 'post_title' => '', 'post_content' => $multi_content, 'post_status' => 'publish', 'post_type' => 'post' ) );
set_post_format( $multi_note, 'gallery' );

tufte_formats_assert_same( 12, tufte_formats_note_photo_count( $multi_note ), 'budget: counts photos across every gallery in the note' );

$multi_html = tufte_formats_test_render_in_stream( $multi_note );
tufte_formats_assert_same( 6, substr_count( $multi_html, '<img ' ), 'budget: a note with several galleries shows six photos in all' );
tufte_formats_assert_same( true, str_contains( $multi_html, 'a2.jpg' ) && str_contains( $multi_html, 'b3.jpg' ) && str_contains( $multi_html, 'c1.jpg' ), 'budget: photos fill the budget in order' );
tufte_formats_assert_same( false, str_contains( $multi_html, 'c2.jpg' ), 'budget: the gallery that runs out keeps only what fits' );
tufte_formats_assert_same( false, str_contains( $multi_html, 'd1.jpg' ), 'budget: galleries after the budget runs out are dropped' );
tufte_formats_assert_same( 3, preg_match_all( '#<figure[^>]*class="wp-block-gallery[ "]#', $multi_html ), 'budget: a dropped gallery leaves no empty figure' );
tufte_formats_assert_same( true, str_contains( $multi_html, 'Breakfast first.' ) && str_contains( $multi_html, 'Then the cowork.' ) && str_contains( $multi_html, 'Lunch walk.' ) && str_contains( $multi_html, 'Late afternoon.' ), 'budget: the note text is kept' );
tufte_formats_assert_same( 1, substr_count( $multi_html, 'tufte-stream-gallery__more' ), 'budget: one "+N" link for the whole note' );
tufte_formats_assert_same( true, str_contains( $multi_html, '</svg>7</span></a></figure>' ), 'budget: "+N" counts every photo in the note not fully seen' );
tufte_formats_assert_same( true, str_contains( $multi_html, 'aria-label="See all 12 photos"' ), 'budget: the link names the note total' );
$multi_last = (int) strpos( $multi_html, 'c1.jpg' );
$multi_more = (int) strpos( $multi_html, 'tufte-stream-gallery__more' );
tufte_formats_assert_same( true, $multi_last > 0 && $multi_more > $multi_last && ! str_contains( substr( $multi_html, $multi_last, $multi_more - $multi_last ), '</figure>' ), 'budget: "+N" sits on the last photo shown' );
tufte_formats_assert_same( true, str_contains( $multi_html, 'tufte-stream-gallery--2' ) && str_contains( $multi_html, 'tufte-stream-gallery--3' ) && str_contains( $multi_html, 'tufte-stream-gallery--1' ), 'budget: each shown gallery reflows to the photos it keeps' );

$again_html = tufte_formats_test_render_in_stream( $multi_note );
tufte_formats_assert_same( 6, substr_count( $again_html, '<img ' ), 'budget: a new stream starts the note with a fresh budget' );

$split_six = wp_insert_post( array( 'post_title' => '', 'post_content' => tufte_formats_test_gallery( 3, '', 'e' ) . "\n\n" . tufte_formats_test_gallery( 3, '', 'f' ), 'post_status' => 'publish', 'post_type' => 'post' ) );
set_post_format( $split_six, 'gallery' );
$split_html = tufte_formats_test_render_in_stream( $split_six );
tufte_formats_assert_same( 6, substr_count( $split_html, '<img ' ), 'budget: two galleries of three show all six' );
tufte_formats_assert_same( false, str_contains( $split_html, 'tufte-stream-gallery__more' ), 'budget: exactly six across galleries gets no "+N"' );

$full_first = wp_insert_post( array( 'post_title' => '', 'post_content' => tufte_formats_test_gallery( 6, '', 'g' ) . "\n\n" . tufte_formats_test_paragraph( 'More later.' ) . "\n\n" . tufte_formats_test_gallery( 4, '', 'h' ), 'post_status' => 'publish', 'post_type' => 'post' ) );
set_post_format( $full_first, 'gallery' );
$full_html = tufte_formats_test_render_in_stream( $full_first );
tufte_formats_assert_same( false, str_contains( $full_html, 'h1.jpg' ), 'budget: a later gallery is dropped when the first uses the budget' );
tufte_formats_assert_same( true, str_contains( $full_html, '</svg>5</span></a></figure>' ), 'budget: "+N" lands on the first gallery when it uses the budget' );
tufte_formats_assert_same( true, str_contains( $full_html, 'More later.' ), 'budget: text after the last shown gallery is kept' );

foreach ( array( $note, $small_note, $exact_note, $essay, $multi_note, $split_six, $full_first ) as $tufte_formats_test_id ) {
	wp_delete_post( $tufte_formats_test_id, true );
}
