<?php
/**
 * Gallery notes in a stream: at most six photos in an even grid.
 *
 * Inside a query loop, a note's gallery keeps its first six images and drops
 * the rest before they render, so the stream never loads them. When photos were
 * dropped, the sixth square gets a "+N" overlay that links to the note, where
 * the full gallery shows as it was built. N counts every photo not fully seen,
 * the covered sixth included. Smaller galleries reflow to fill the row. The
 * saved post content is never changed, and single note pages are left alone.
 *
 * @package Tufte_Formats
 */

declare(strict_types=1);

/** Most images a gallery shows in a stream. */
const TUFTE_FORMATS_STREAM_GALLERY_MAX = 6;

/** Parsed-block attribute that carries a trimmed gallery's full image count. */
const TUFTE_FORMATS_STREAM_GALLERY_TOTAL_ATTR = 'tufteFormatsTotalImages';

/**
 * How many query loop post templates are rendering right now.
 *
 * @param int $change Amount to add.
 * @return int The depth after the change.
 */
function tufte_formats_post_template_depth( int $change = 0 ): int {
	static $depth = 0;
	$depth = max( 0, $depth + $change );
	return $depth;
}

/**
 * Count a post template as entered before it renders its posts.
 *
 * @param string|null $pre_render   Short-circuit value; passed through.
 * @param array       $parsed_block Parsed block.
 * @return string|null
 */
function tufte_formats_enter_post_template( $pre_render, array $parsed_block ) {
	if ( null === $pre_render && 'core/post-template' === ( $parsed_block['blockName'] ?? '' ) ) {
		tufte_formats_post_template_depth( 1 );
	}
	return $pre_render;
}
add_filter( 'pre_render_block', 'tufte_formats_enter_post_template', PHP_INT_MAX, 2 );

/**
 * Count a post template as left once it has rendered.
 *
 * @param string $block_content Rendered post template.
 * @return string
 */
function tufte_formats_leave_post_template( string $block_content ): string {
	tufte_formats_post_template_depth( -1 );
	return $block_content;
}
add_filter( 'render_block_core/post-template', 'tufte_formats_leave_post_template' );

/**
 * Whether a gallery rendering now belongs to a note in a stream.
 *
 * @return bool
 */
function tufte_formats_in_note_stream(): bool {
	return tufte_formats_post_template_depth() > 0 && tufte_formats_is_note();
}

/**
 * Keep a gallery's first six images, and record how many it had.
 *
 * Inner blocks are dropped from innerContent as well: each null there stands
 * for one inner block, and the whitespace before a dropped one goes with it.
 *
 * @param array $parsed_block Parsed core/gallery block.
 * @return array
 */
function tufte_formats_trim_gallery_block( array $parsed_block ): array {
	$inner = $parsed_block['innerBlocks'] ?? array();
	$total = count( $inner );

	$parsed_block['attrs'][ TUFTE_FORMATS_STREAM_GALLERY_TOTAL_ATTR ] = $total;
	if ( $total <= TUFTE_FORMATS_STREAM_GALLERY_MAX ) {
		return $parsed_block;
	}

	$parsed_block['innerBlocks'] = array_slice( $inner, 0, TUFTE_FORMATS_STREAM_GALLERY_MAX );

	$content = array();
	$kept    = 0;
	$pending = null;
	foreach ( $parsed_block['innerContent'] ?? array() as $chunk ) {
		if ( null === $chunk ) {
			if ( $kept < TUFTE_FORMATS_STREAM_GALLERY_MAX ) {
				if ( null !== $pending ) {
					$content[] = $pending;
				}
				$content[] = null;
				++$kept;
			}
			$pending = null;
			continue;
		}
		if ( null !== $pending ) {
			$content[] = $pending;
		}
		$pending = $chunk;
	}
	if ( null !== $pending ) {
		$content[] = $pending;
	}
	$parsed_block['innerContent'] = $content;

	return $parsed_block;
}

/**
 * Trim a note's gallery before it renders in a stream.
 *
 * @param array $parsed_block Parsed block.
 * @return array
 */
function tufte_formats_stream_gallery_data( array $parsed_block ): array {
	if ( 'core/gallery' !== ( $parsed_block['blockName'] ?? '' ) || ! tufte_formats_in_note_stream() ) {
		return $parsed_block;
	}
	return tufte_formats_trim_gallery_block( $parsed_block );
}
add_filter( 'render_block_data', 'tufte_formats_stream_gallery_data' );

/**
 * Mark a trimmed gallery for the grid, and lay the "+N" link over the sixth image.
 *
 * @param string $block_content Rendered gallery.
 * @param array  $block         Parsed block, as trimmed.
 * @param string $permalink     Where the "+N" link goes.
 * @return string
 */
function tufte_formats_stream_gallery_markup( string $block_content, array $block, string $permalink ): string {
	if ( ! isset( $block['attrs'][ TUFTE_FORMATS_STREAM_GALLERY_TOTAL_ATTR ] ) ) {
		return $block_content;
	}

	$total = (int) $block['attrs'][ TUFTE_FORMATS_STREAM_GALLERY_TOTAL_ATTR ];
	$shown = min( $total, TUFTE_FORMATS_STREAM_GALLERY_MAX );

	$processor = new WP_HTML_Tag_Processor( $block_content );
	if ( ! $processor->next_tag( 'figure' ) ) {
		return $block_content;
	}
	$processor->add_class( 'tufte-stream-gallery' );
	$processor->add_class( 'tufte-stream-gallery--' . $shown );
	$html = $processor->get_updated_html();

	if ( $total <= TUFTE_FORMATS_STREAM_GALLERY_MAX ) {
		return $html;
	}

	$hidden = $total - ( TUFTE_FORMATS_STREAM_GALLERY_MAX - 1 );
	$label  = sprintf(
		/* translators: %d: number of photos in the gallery. */
		_n( 'See all %d photo', 'See all %d photos', $total, 'tufte-formats' ),
		$total
	);
	$link = sprintf(
		'<a class="tufte-stream-gallery__more" href="%1$s" aria-label="%2$s"><span aria-hidden="true"><svg viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" focusable="false"><path d="M10 3v14M3 10h14"/></svg>%3$d</span></a>',
		esc_url( $permalink ),
		esc_attr( $label ),
		$hidden
	);

	// The link goes just before the sixth image's closing tag, so it sits on that square.
	$processor = new WP_HTML_Tag_Processor( $html );
	$images    = 0;
	while ( $processor->next_tag( array( 'tag_name' => 'figure', 'tag_closers' => 'visit' ) ) ) {
		if ( ! $processor->is_tag_closer() && $processor->has_class( 'wp-block-image' ) ) {
			++$images;
			if ( TUFTE_FORMATS_STREAM_GALLERY_MAX === $images ) {
				$processor->add_class( 'tufte-stream-gallery__last' );
				break;
			}
		}
	}
	$html = $processor->get_updated_html();

	$opener = strpos( $html, 'tufte-stream-gallery__last' );
	if ( false === $opener ) {
		return $html;
	}
	// Images nest no figures, so the next closing figure ends the sixth image.
	$closer = strpos( $html, '</figure>', $opener );
	if ( false === $closer ) {
		return $html;
	}

	return substr( $html, 0, $closer ) . $link . substr( $html, $closer );
}

/**
 * Finish a note's gallery in a stream, and load the grid styles.
 *
 * @param string $block_content Rendered gallery.
 * @param array  $block         Parsed block.
 * @return string
 */
function tufte_formats_render_stream_gallery( string $block_content, array $block ): string {
	if ( ! isset( $block['attrs'][ TUFTE_FORMATS_STREAM_GALLERY_TOTAL_ATTR ] ) ) {
		return $block_content;
	}
	wp_enqueue_style( 'tufte-formats-stream-gallery' );
	return tufte_formats_stream_gallery_markup( $block_content, $block, (string) get_permalink() );
}
add_filter( 'render_block_core/gallery', 'tufte_formats_render_stream_gallery', 10, 2 );

/**
 * Register the stream gallery styles; galleries enqueue them as they render.
 *
 * @return void
 */
function tufte_formats_register_stream_gallery_style(): void {
	wp_register_style(
		'tufte-formats-stream-gallery',
		plugins_url( 'assets/css/stream-gallery.css', TUFTE_FORMATS_PLUGIN_FILE ),
		array(),
		TUFTE_FORMATS_VERSION
	);
}
add_action( 'init', 'tufte_formats_register_stream_gallery_style' );
