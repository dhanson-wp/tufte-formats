<?php
/**
 * Format editing: make sure a note is created as a note.
 *
 * A new post is Standard (an essay) unless someone sets a format. Two ways to start
 * a note without having to remember that:
 *
 * - Untitled posts created through the REST API or code (agents, the WordPress.com
 *   tools) get a format from their first block when they are created.
 * - Posts > New note starts a Status note in the editor.
 *
 * @package Tufte_Formats
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Guess a post format from the first block of some content.
 *
 * @param string $content Post content.
 * @return string Format slug, or '' when the content is empty.
 */
function tufte_formats_detect_format( string $content ): string {
	foreach ( parse_blocks( $content ) as $block ) {
		$name = (string) ( $block['blockName'] ?? '' );
		if ( '' === $name ) {
			continue;
		}

		$map = array(
			'core/image'     => 'image',
			'core/gallery'   => 'gallery',
			'core/video'     => 'video',
			'core/audio'     => 'audio',
			'core/quote'     => 'quote',
			'core/pullquote' => 'quote',
		);
		if ( isset( $map[ $name ] ) ) {
			return $map[ $name ];
		}

		if ( 'core/paragraph' === $name ) {
			$html = trim( (string) ( $block['innerHTML'] ?? '' ) );
			// A paragraph that is only a link (optionally in emphasis) is a link note.
			if ( preg_match( '/^<p[^>]*>\s*(?:<(?:em|i|strong)>)?\s*<a\s[^>]*href=["\']https?:\/\/[^"\']+["\'][^>]*>[^<]*<\/a>\s*(?:<\/(?:em|i|strong)>)?\s*<\/p>$/i', $html ) ) {
				return 'link';
			}
		}

		return 'status';
	}

	return '';
}

/**
 * Give a new untitled post a format, and start notes from the New note link.
 *
 * Runs on creation only, so switching a note back to Standard later sticks.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 * @param bool    $update  Whether this is an update.
 * @return void
 */
function tufte_formats_format_on_create( int $post_id, WP_Post $post, bool $update ): void {
	if ( $update || 'post' !== $post->post_type || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || get_post_format( $post_id ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a flag that only picks a default format.
	if ( 'auto-draft' === $post->post_status && ! empty( $_GET['tufte_note'] ) ) {
		set_post_format( $post_id, 'status' );
		return;
	}

	if ( in_array( $post->post_status, array( 'auto-draft', 'inherit', 'trash' ), true ) || '' !== trim( (string) $post->post_title ) ) {
		return;
	}

	/**
	 * Whether untitled posts get a format from their first block when created.
	 *
	 * @param bool    $enabled Default true.
	 * @param WP_Post $post    The new post.
	 */
	if ( ! apply_filters( 'tufte_formats_auto_format', true, $post ) ) {
		return;
	}

	$format = tufte_formats_detect_format( (string) $post->post_content );
	if ( '' !== $format ) {
		set_post_format( $post_id, $format );
	}
}
add_action( 'wp_after_insert_post', 'tufte_formats_format_on_create', 10, 3 );

/**
 * The address that starts a new note in the editor.
 *
 * @return string
 */
function tufte_formats_new_note_url(): string {
	return admin_url( 'post-new.php?tufte_note=1' );
}

/**
 * Posts > New note, and New > Note in the admin bar.
 *
 * @return void
 */
function tufte_formats_new_note_menu(): void {
	add_submenu_page( 'edit.php', __( 'New note', 'tufte-formats' ), __( 'New note', 'tufte-formats' ), 'edit_posts', 'post-new.php?tufte_note=1' );
}
add_action( 'admin_menu', 'tufte_formats_new_note_menu' );

/**
 * Add the admin bar entry.
 *
 * @param WP_Admin_Bar $bar Admin bar.
 * @return void
 */
function tufte_formats_new_note_bar( WP_Admin_Bar $bar ): void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	$bar->add_node(
		array(
			'id'     => 'tufte-formats-new-note',
			'parent' => 'new-content',
			'title'  => __( 'Note', 'tufte-formats' ),
			'href'   => tufte_formats_new_note_url(),
		)
	);
}
add_action( 'admin_bar_menu', 'tufte_formats_new_note_bar', 80 );
