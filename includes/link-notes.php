<?php
/**
 * Link-format notes: the first external link in the content is the note's
 * link. It is marked as external, and the source name beside it links to
 * the same address. Notes without a link stay plain text.
 *
 * @package Tufte_Formats
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The first external http(s) link in some HTML.
 *
 * @param string $html      Content.
 * @param string $home_host This site's host, so its own links are not external.
 * @return string URL, or an empty string.
 */
function tufte_formats_link_note_url( string $html, string $home_host ): string {
	if ( ! preg_match_all( '/<a\s[^>]*href=(["\'])(https?:\/\/[^"\']+)\1/i', $html, $matches ) ) {
		return '';
	}

	foreach ( $matches[2] as $url ) {
		$host = (string) wp_parse_url( html_entity_decode( $url ), PHP_URL_HOST );
		if ( '' !== $host && 0 !== strcasecmp( $host, $home_host ) ) {
			return $url;
		}
	}

	return '';
}

/**
 * Mark a link note's link and source name as external.
 *
 * Each marked link gets aria-describedby pointing at a hidden element that lives
 * outside the content (see tufte_formats_link_note_render_block), so microformats and
 * webmention parsers reading the content never see the screen reader text.
 *
 * @param string $content     Note content.
 * @param string $home_host   This site's host.
 * @param string $describedby Id of the hidden "External link" element.
 * @return string
 */
function tufte_formats_link_note_content( string $content, string $home_host, string $describedby = 'tufte-external-description' ): string {
	$url = tufte_formats_link_note_url( $content, $home_host );
	if ( '' === $url || str_contains( $content, 'class="tufte-external"' ) ) {
		return $content;
	}

	$attrs  = ' aria-describedby="' . esc_attr( $describedby ) . '"';
	$quoted = preg_quote( $url, '/' );

	// The title link: keep its text, add the class and the description pointer. A callback, so a "$1" or "\1" in a URL is not read as a backreference.
	$content = (string) preg_replace_callback(
		'/<a\s[^>]*href=(["\'])' . $quoted . '\1[^>]*>(.*?)<\/a>/is',
		static fn( array $m ): string => '<a class="tufte-external" href="' . $url . '"' . $attrs . '>' . $m[2] . '</a>',
		$content,
		1
	);

	// The source name: link an author-written one, or add one from the host.
	if ( preg_match( '/<span class="ref-src">(.*?)<\/span>/is', $content, $src ) ) {
		$content = str_replace(
			$src[0],
			'<span class="ref-src"><a class="tufte-external" href="' . $url . '"' . $attrs . '>' . $src[1] . '</a></span>',
			$content
		);
	} else {
		$host   = (string) preg_replace( '/^www\./i', '', (string) wp_parse_url( html_entity_decode( $url ), PHP_URL_HOST ) );
		$source = ' <span class="ref-src"><a class="tufte-external" href="' . $url . '"' . $attrs . '>' . esc_html( $host ) . '</a></span>';
		$pos    = strpos( $content, '</p>' );
		$content = false === $pos ? $content . $source : substr_replace( $content, $source, $pos, 0 );
	}

	return $content;
}

/**
 * Mark a link note's link when the post content block renders it for a page.
 *
 * The whole treatment is added here, on the finished block output, and never in
 * the_content. That keeps it out of everything that reads the content for another
 * audience: ActivityPub objects, excerpts, the REST API, feeds, and newsletter or email
 * renders. Only the post content block of a link-format post on a front-end HTML page
 * gets it.
 *
 * The hidden "External link" text is one element appended after the block's output, a
 * sibling of the content wrapper and not inside it. A single note marks that wrapper
 * as e-content, so microformats and webmention parsers skip the text; screen readers
 * still announce it through aria-describedby. Its id carries the post id, so a stream
 * of notes has no duplicate ids.
 *
 * @param string         $html     Rendered block HTML.
 * @param array          $block    Parsed block.
 * @param WP_Block|null  $instance Block instance, which carries the post context.
 * @return string
 */
function tufte_formats_link_note_render_block( string $html, array $block, $instance = null ): string {
	if ( 'core/post-content' !== ( $block['blockName'] ?? '' ) || is_feed() || is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $html;
	}

	$post_id = $instance instanceof WP_Block ? (int) ( $instance->context['postId'] ?? 0 ) : 0;
	if ( $post_id < 1 || 'link' !== get_post_format( $post_id ) ) {
		return $html;
	}

	$id     = 'tufte-external-' . $post_id;
	$marked = tufte_formats_link_note_content( $html, (string) wp_parse_url( home_url(), PHP_URL_HOST ), $id );
	if ( $marked === $html ) {
		return $html;
	}

	return $marked . '<span id="' . esc_attr( $id ) . '" class="screen-reader-text tufte-visually-hidden">' . esc_html__( 'External link', 'tufte-formats' ) . '</span>';
}
add_filter( 'render_block', 'tufte_formats_link_note_render_block', 10, 3 );
