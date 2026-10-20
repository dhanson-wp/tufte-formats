<?php
/**
 * Gallery notes in a stream: at most six photos per note, in even grids.
 *
 * Inside a query loop, a note gets a budget of six photos across all of its
 * galleries, in order. A gallery keeps what still fits and drops the rest
 * before they render, so the stream never loads them; galleries after the
 * budget runs out render nothing. The note's text stays as written. When photos
 * were dropped, the last photo shown gets a "+N" overlay that links to the note,
 * where every gallery shows as it was built. N counts every photo in the note
 * not fully seen, the covered one included. Each shown gallery reflows to the
 * photos it keeps. The saved post content is never changed, and single note
 * pages are left alone.
 *
 * @package Tufte_Formats
 */

declare(strict_types=1);

/** Most photos a note shows in a stream, across all of its galleries. */
const TUFTE_FORMATS_STREAM_GALLERY_MAX = 6;

/** Parsed-block attribute that carries a gallery's full image count. */
const TUFTE_FORMATS_STREAM_GALLERY_TOTAL_ATTR = 'tufteFormatsTotalImages';

/** Parsed-block attribute: how many of the gallery's images the stream shows. */
const TUFTE_FORMATS_STREAM_GALLERY_SHOWN_ATTR = 'tufteFormatsShownImages';

/** Parsed-block attribute: the "+N" count when this gallery holds the note's last shown photo, else 0. */
const TUFTE_FORMATS_STREAM_GALLERY_MORE_ATTR = 'tufteFormatsMoreImages';

/** Parsed-block attribute: every gallery photo in the note, for the link's label. */
const TUFTE_FORMATS_STREAM_GALLERY_NOTE_TOTAL_ATTR = 'tufteFormatsNoteImages';

/**
 * How many query loop post templates are rendering right now.
 *
 * Entering the outermost loop starts every note's photo budget over.
 *
 * @param int $change Amount to add.
 * @return int The depth after the change.
 */
function tufte_formats_post_template_depth( int $change = 0 ): int {
	static $depth = 0;
	if ( $change > 0 && 0 === $depth ) {
		tufte_formats_note_photo_budget_reset();
	}
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
 * Photos each note has already shown in the current stream.
 *
 * @param int|null $post_id Post to read or update; null with $reset clears every note.
 * @param int      $add     Photos to add to the post's count.
 * @param bool     $reset   Clear every note's count.
 * @return int The post's count after the change, or 0 after a reset.
 */
function tufte_formats_note_photo_budget( ?int $post_id = null, int $add = 0, bool $reset = false ): int {
	static $used = array();
	if ( $reset ) {
		$used = array();
		return 0;
	}
	if ( null === $post_id ) {
		return 0;
	}
	$used[ $post_id ] = ( $used[ $post_id ] ?? 0 ) + $add;
	return $used[ $post_id ];
}

/**
 * Start every note's photo budget over.
 *
 * @return void
 */
function tufte_formats_note_photo_budget_reset(): void {
	tufte_formats_note_photo_budget( null, 0, true );
}

/**
 * Count the images inside galleries in a list of parsed blocks, at any depth.
 *
 * @param array $blocks Parsed blocks.
 * @return int
 */
function tufte_formats_count_gallery_images( array $blocks ): int {
	$count = 0;
	foreach ( $blocks as $block ) {
		if ( 'core/gallery' === ( $block['blockName'] ?? '' ) ) {
			$count += count( $block['innerBlocks'] ?? array() );
			continue;
		}
		$count += tufte_formats_count_gallery_images( $block['innerBlocks'] ?? array() );
	}
	return $count;
}

/**
 * Every gallery photo in a post, across all of its galleries.
 *
 * @param int $post_id Post.
 * @return int
 */
function tufte_formats_note_photo_count( int $post_id ): int {
	static $counts = array();
	if ( ! isset( $counts[ $post_id ] ) ) {
		$counts[ $post_id ] = tufte_formats_count_gallery_images( parse_blocks( (string) get_post_field( 'post_content', $post_id ) ) );
	}
	return $counts[ $post_id ];
}

/**
 * Keep a gallery's first images, and record how many it had.
 *
 * Inner blocks are dropped from innerContent as well: each null there stands
 * for one inner block, and the whitespace before a dropped one goes with it.
 *
 * @param array $parsed_block Parsed core/gallery block.
 * @param int   $keep         Most images to keep.
 * @return array
 */
function tufte_formats_trim_gallery_block( array $parsed_block, int $keep = TUFTE_FORMATS_STREAM_GALLERY_MAX ): array {
	$inner = $parsed_block['innerBlocks'] ?? array();
	$total = count( $inner );
	$keep  = max( 0, $keep );

	$parsed_block['attrs'][ TUFTE_FORMATS_STREAM_GALLERY_TOTAL_ATTR ] = $total;
	if ( $total <= $keep ) {
		return $parsed_block;
	}

	$parsed_block['innerBlocks'] = array_slice( $inner, 0, $keep );

	$content = array();
	$kept    = 0;
	$pending = null;
	foreach ( $parsed_block['innerContent'] ?? array() as $chunk ) {
		if ( null === $chunk ) {
			if ( $kept < $keep ) {
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
 * Trim a note's gallery to what's left of the note's photo budget, before it renders in a stream.
 *
 * @param array $parsed_block Parsed block.
 * @return array
 */
function tufte_formats_stream_gallery_data( array $parsed_block ): array {
	if ( 'core/gallery' !== ( $parsed_block['blockName'] ?? '' ) || ! tufte_formats_in_note_stream() ) {
		return $parsed_block;
	}

	$post_id    = (int) get_the_ID();
	$note_total = tufte_formats_note_photo_count( $post_id );
	$used       = tufte_formats_note_photo_budget( $post_id );
	$available  = max( 0, TUFTE_FORMATS_STREAM_GALLERY_MAX - $used );
	$count      = count( $parsed_block['innerBlocks'] ?? array() );
	$shown      = min( $count, $available );

	$parsed_block = tufte_formats_trim_gallery_block( $parsed_block, $shown );
	$used         = tufte_formats_note_photo_budget( $post_id, $shown );

	$more = 0;
	if ( $shown > 0 && $used >= TUFTE_FORMATS_STREAM_GALLERY_MAX && $note_total > $used ) {
		// The covered photo counts as not fully seen.
		$more = $note_total - ( $used - 1 );
	}

	$parsed_block['attrs'][ TUFTE_FORMATS_STREAM_GALLERY_SHOWN_ATTR ]      = $shown;
	$parsed_block['attrs'][ TUFTE_FORMATS_STREAM_GALLERY_MORE_ATTR ]       = $more;
	$parsed_block['attrs'][ TUFTE_FORMATS_STREAM_GALLERY_NOTE_TOTAL_ATTR ] = $note_total;

	return $parsed_block;
}
add_filter( 'render_block_data', 'tufte_formats_stream_gallery_data' );

/**
 * Mark a trimmed gallery for the grid, and lay the "+N" link over its last shown image.
 *
 * @param string $block_content Rendered gallery.
 * @param array  $block         Parsed block, as trimmed.
 * @param string $permalink     Where the "+N" link goes.
 * @return string
 */
function tufte_formats_stream_gallery_markup( string $block_content, array $block, string $permalink ): string {
	$attrs = $block['attrs'] ?? array();
	if ( ! isset( $attrs[ TUFTE_FORMATS_STREAM_GALLERY_TOTAL_ATTR ] ) ) {
		return $block_content;
	}

	$total      = (int) $attrs[ TUFTE_FORMATS_STREAM_GALLERY_TOTAL_ATTR ];
	$shown      = (int) ( $attrs[ TUFTE_FORMATS_STREAM_GALLERY_SHOWN_ATTR ] ?? min( $total, TUFTE_FORMATS_STREAM_GALLERY_MAX ) );
	$more       = (int) ( $attrs[ TUFTE_FORMATS_STREAM_GALLERY_MORE_ATTR ] ?? ( $total > TUFTE_FORMATS_STREAM_GALLERY_MAX ? $total - ( TUFTE_FORMATS_STREAM_GALLERY_MAX - 1 ) : 0 ) );
	$note_total = (int) ( $attrs[ TUFTE_FORMATS_STREAM_GALLERY_NOTE_TOTAL_ATTR ] ?? $total );

	if ( $shown <= 0 ) {
		return '';
	}

	$processor = new WP_HTML_Tag_Processor( $block_content );
	if ( ! $processor->next_tag( 'figure' ) ) {
		return $block_content;
	}
	$processor->add_class( 'tufte-stream-gallery' );
	$processor->add_class( 'tufte-stream-gallery--' . $shown );
	$html = $processor->get_updated_html();

	if ( $more <= 0 ) {
		return $html;
	}

	$label = sprintf(
		/* translators: %d: number of photos in the note. */
		_n( 'See all %d photo', 'See all %d photos', $note_total, 'tufte-formats' ),
		$note_total
	);
	$link  = sprintf(
		'<a class="tufte-stream-gallery__more" href="%1$s" aria-label="%2$s"><span aria-hidden="true"><svg viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" focusable="false"><path d="M10 3v14M3 10h14"/></svg>%3$d</span></a>',
		esc_url( $permalink ),
		esc_attr( $label ),
		$more
	);

	// The link goes just before the last shown image's closing tag, so it sits on that square.
	$processor = new WP_HTML_Tag_Processor( $html );
	$images    = 0;
	while ( $processor->next_tag( array( 'tag_name' => 'figure', 'tag_closers' => 'visit' ) ) ) {
		if ( ! $processor->is_tag_closer() && $processor->has_class( 'wp-block-image' ) ) {
			++$images;
			if ( $shown === $images ) {
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
	// Images nest no figures, so the next closing figure ends the last shown image.
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
