<?php
declare(strict_types=1);

/**
 * Gallery markup with $count images, the way the editor saves it.
 *
 * @param int    $count   Images.
 * @param string $caption Per-image caption, if any.
 * @return string
 */
function tufte_formats_test_gallery( int $count, string $caption = '' ): string {
	$images = '';
	for ( $i = 1; $i <= $count; $i++ ) {
		$figcaption = '' === $caption ? '' : "<figcaption class=\"wp-element-caption\">{$caption} {$i}</figcaption>";
		$images    .= "\n\n<!-- wp:image -->\n<figure class=\"wp-block-image\"><img src=\"https://example.com/p{$i}.jpg\" alt=\"Photo {$i}\"/>{$figcaption}</figure>\n<!-- /wp:image -->";
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

foreach ( array( $note, $small_note, $exact_note, $essay ) as $tufte_formats_test_id ) {
	wp_delete_post( $tufte_formats_test_id, true );
}
