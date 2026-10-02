<?php
/**
 * Note meta: reply count for the margin date, and source lines for comments.
 *
 * @package Tufte_Formats
 * @since 1.9.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Approved replies on a post. Only the plain comment type counts: site
 * comments, ActivityPub replies, and Webmention replies. Likes, reposts,
 * quotes, and other mention types are reactions, not replies.
 *
 * @param int $post_id Post ID.
 * @return int
 */
function tufte_formats_reply_count( int $post_id ): int {
	return (int) get_comments(
		array(
			'post_id' => $post_id,
			'status'  => 'approve',
			'type'    => 'comment',
			'count'   => true,
		)
	);
}

/**
 * Turn a fediverse actor URL into a handle.
 *
 * @param string $actor_url Actor or profile URL.
 * @return string Handle such as @name@host, the bare host when the path shape is unknown, or ''.
 */
function tufte_formats_fediverse_handle( string $actor_url ): string {
	$parts = wp_parse_url( $actor_url );
	$host  = is_array( $parts ) ? (string) ( $parts['host'] ?? '' ) : '';
	if ( '' === $host ) {
		return '';
	}

	$path = trim( (string) ( $parts['path'] ?? '' ), '/' );
	if ( preg_match( '#^@([^/@]+)$#', $path, $m ) || preg_match( '#^users/([^/]+)$#', $path, $m ) ) {
		return '@' . $m[1] . '@' . $host;
	}

	return $host;
}

/**
 * Host of an http(s) URL, or '' for anything else (including junk such as an escaped, dotless string).
 *
 * @param string $url URL.
 * @return string
 */
function tufte_formats_http_host( string $url ): string {
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
		return '';
	}

	$host = (string) ( $parts['host'] ?? '' );

	return preg_match( '/^[a-z0-9-]+(\.[a-z0-9-]+)+$/i', $host ) ? $host : '';
}

/**
 * The source line for a comment: a fediverse handle or "via domain".
 *
 * @param WP_Comment $comment Comment.
 * @return array{type:string,text:string}
 */
function tufte_formats_comment_source( WP_Comment $comment ): array {
	$none = array( 'type' => '', 'text' => '' );
	$url  = (string) $comment->comment_author_url;

	if ( 'activitypub' === get_comment_meta( (int) $comment->comment_ID, 'protocol', true ) ) {
		$handle = tufte_formats_fediverse_handle( $url );

		return '' === $handle ? $none : array( 'type' => 'fediverse', 'text' => $handle );
	}

	if ( 'webmention' === get_comment_meta( (int) $comment->comment_ID, 'protocol', true ) ) {
		$source = (string) get_comment_meta( (int) $comment->comment_ID, 'webmention_source_url', true );
		$host   = tufte_formats_http_host( $source );
		if ( '' === $host ) {
			$host = tufte_formats_http_host( $url );
		}

		/* translators: %s: domain of the site that sent the webmention. */
		return '' === $host ? $none : array( 'type' => 'webmention', 'text' => sprintf( __( 'via %s', 'tufte-formats' ), $host ) );
	}

	return $none;
}

/**
 * Mark fediverse replies so the theme can style them.
 *
 * @param string[]        $classes    Comment classes.
 * @param string|string[] $css_class  Extra classes.
 * @param string|int      $comment_id Comment ID.
 * @param WP_Comment      $comment    Comment.
 * @return string[]
 */
function tufte_formats_comment_class( array $classes, $css_class, $comment_id, $comment ): array {
	if ( $comment instanceof WP_Comment && tufte_formats_is_note( (int) $comment->comment_post_ID ) && 'activitypub' === get_comment_meta( (int) $comment->comment_ID, 'protocol', true ) ) {
		$classes[] = 'is-fediverse';
	}

	return $classes;
}
add_filter( 'comment_class', 'tufte_formats_comment_class', 10, 4 );

/**
 * Print the source line under a comment's date.
 *
 * @param string   $block_content Rendered core/comment-date.
 * @param array    $block         Parsed block.
 * @param WP_Block $instance      Block instance.
 * @return string
 */
function tufte_formats_comment_source_line( string $block_content, array $block, WP_Block $instance ): string {
	$comment_id = (int) ( $instance->context['commentId'] ?? 0 );
	$comment    = $comment_id ? get_comment( $comment_id ) : null;
	if ( ! $comment instanceof WP_Comment ) {
		return $block_content;
	}

	if ( ! tufte_formats_is_note( (int) $comment->comment_post_ID ) ) {
		return $block_content;
	}

	$source = tufte_formats_comment_source( $comment );
	if ( '' === $source['text'] ) {
		return $block_content;
	}

	return $block_content . sprintf( '<span class="c-src c-src--%1$s">%2$s</span>', esc_attr( $source['type'] ), esc_html( $source['text'] ) );
}
add_filter( 'render_block_core/comment-date', 'tufte_formats_comment_source_line', 10, 3 );

/**
 * Add the reply link and the View note link after a note's date.
 *
 * Applies to a Post Date block with the class tufte-note-date, on notes. The reply link reads "N replies" with replies, or "Reply" with none.
 *
 * @param string   $block_content Rendered core/post-date.
 * @param array    $block         Parsed block.
 * @param WP_Block $instance      Block instance.
 * @return string
 */
function tufte_formats_note_reply_link( string $block_content, array $block, WP_Block $instance ): string {
	if ( false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'tufte-note-date' ) ) {
		return $block_content;
	}

	$post_id = (int) ( $instance->context['postId'] ?? 0 );
	if ( ! $post_id || ! tufte_formats_is_note( $post_id ) ) {
		return $block_content;
	}

	$count = tufte_formats_reply_count( $post_id );

	$date_id = 'note-date-' . $post_id;
	$tags    = new WP_HTML_Tag_Processor( $block_content );
	if ( $tags->next_tag( 'time' ) ) {
		$existing = $tags->get_attribute( 'id' );
		if ( is_string( $existing ) && '' !== $existing ) {
			$date_id = $existing;
		} else {
			$tags->set_attribute( 'id', $date_id );
		}
		$block_content = $tags->get_updated_html();
	}

	// With replies, link to the thread; with none, invite one by linking to the comment form.
	$reply_href = get_permalink( $post_id ) . ( $count < 1 ? '#respond' : '#responses' );
	$reply_text = $count < 1
		? __( 'Reply', 'tufte-formats' )
		: sprintf( _n( '%d reply', '%d replies', $count, 'tufte-formats' ), $count );

	$out = $block_content . sprintf(
		'<p class="note-replies"><a href="%1$s" aria-describedby="%3$s">%2$s</a></p>',
		esc_url( $reply_href ),
		esc_html( $reply_text ),
		esc_attr( $date_id )
	);

	/**
	 * Whether to add the "View note" link under a note's date. Off on the note's own page.
	 * The date already links to the note, so this link is visual only.
	 */
	if ( apply_filters( 'tufte_formats_show_view_note', ! tufte_formats_is_single_note() ) ) {
		$out .= sprintf(
			'<p class="note-view"><a href="%1$s" aria-hidden="true" tabindex="-1">%2$s</a></p>',
			esc_url( get_permalink( $post_id ) ),
			esc_html__( 'View note', 'tufte-formats' )
		);
	}

	return $out;
}
add_filter( 'render_block_core/post-date', 'tufte_formats_note_reply_link', 10, 3 );

/**
 * Whether a single note is being displayed.
 *
 * @return bool
 */
function tufte_formats_is_single_note(): bool {
	if ( ! is_singular( 'post' ) ) {
		return false;
	}

	return tufte_formats_is_note( get_queried_object() );
}

/**
 * Notes thread two levels deep.
 *
 * @param mixed $value Option value.
 * @return mixed
 */
function tufte_formats_note_thread_depth( $value ) {
	return tufte_formats_is_single_note() ? 2 : $value;
}
add_filter( 'option_thread_comments_depth', 'tufte_formats_note_thread_depth' );

/**
 * Notes show one unpaginated thread.
 *
 * @param mixed $value Option value.
 * @return mixed
 */
function tufte_formats_note_page_comments( $value ) {
	return tufte_formats_is_single_note() ? 0 : $value;
}
add_filter( 'option_page_comments', 'tufte_formats_note_page_comments' );

/**
 * Essays show a title and excerpt in the archive, not their body.
 *
 * Only posts are blanked; pages and blocks with no post ID are left alone.
 *
 * @param string $content Rendered post content.
 * @param int    $post_id Post ID.
 * @param string $view    Archive view, or '' outside the archive pages.
 * @return string
 */
function tufte_formats_archive_essay_body( string $content, int $post_id, string $view ): string {
	if ( '' === $view || $post_id <= 0 ) {
		return $content;
	}

	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || tufte_formats_is_note( $post_id ) ) {
		return $content;
	}

	return '';
}

/**
 * Block filter for core/post-content inside the archive views.
 *
 * @param string   $block_content Rendered block.
 * @param array    $block         Parsed block.
 * @param WP_Block $instance      Block instance.
 * @return string
 */
function tufte_formats_archive_post_content( string $block_content, array $block, WP_Block $instance ): string {
	return tufte_formats_archive_essay_body( $block_content, (int) ( $instance->context['postId'] ?? 0 ), tufte_formats_archive_view() );
}
add_filter( 'render_block_core/post-content', 'tufte_formats_archive_post_content', 10, 3 );
