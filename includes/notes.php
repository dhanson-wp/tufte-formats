<?php
/**
 * Notes: short posts in any non-standard post format.
 *
 * Notes are regular posts, so they keep one URL scheme, comments, and
 * webmentions. A post is a note when it has any format except standard
 * (aside, gallery, link, image, quote, status, video, audio, chat). Notes stay
 * out of everything that carries the blog and the newsletter: the blog loop,
 * archives, search, and the main feed. They show up in the Archive views, in a
 * query block whose Post format filter asks for a non-standard format, on the
 * post format archives, and in those archives' own feeds (/type/status/feed/).
 *
 * @package Tufte_Formats
 * @since 1.9.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post format term slugs that make a post a note: every format except standard.
 *
 * @return string[]
 */
function tufte_formats_note_term_slugs(): array {
	$slugs = array();
	foreach ( array_keys( get_post_format_slugs() ) as $format ) {
		if ( 'standard' !== $format ) {
			$slugs[] = 'post-format-' . $format;
		}
	}

	return $slugs;
}

/**
 * Tax query clause that matches notes.
 *
 * Standard posts carry no post format term, so NOT IN selects essays.
 *
 * @param string $operator IN to include notes, NOT IN to leave them out.
 * @return array
 */
function tufte_formats_notes_clause( string $operator ): array {
	return array(
		'taxonomy' => 'post_format',
		'field'    => 'slug',
		'terms'    => tufte_formats_note_term_slugs(),
		'operator' => $operator,
	);
}

/**
 * Whether a post is a note: a post with any non-standard format.
 *
 * @param int|WP_Post|null $post Post, or null for the current post.
 * @return bool
 */
function tufte_formats_is_note( $post = null ): bool {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
		return false;
	}

	$format = get_post_format( $post );

	return false !== $format && 'standard' !== $format;
}

/**
 * Declare post format support so aside, link, and quote are selectable.
 *
 * Without this, ActivityPub's fallback offers only gallery, status, image,
 * video, and audio.
 *
 * @return void
 */
function tufte_formats_post_formats(): void {
	add_theme_support( 'post-formats', array( 'aside', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio' ) );
}
add_action( 'after_setup_theme', 'tufte_formats_post_formats' );

/**
 * Add a tax query clause without disturbing existing ones.
 *
 * @param array $tax_query Existing tax query.
 * @param array $clause    Clause to add.
 * @return array
 */
function tufte_formats_notes_and( $tax_query, array $clause ): array {
	$combined = array( 'relation' => 'AND' );
	if ( is_array( $tax_query ) && $tax_query ) {
		$combined[] = $tax_query;
	}
	$combined[] = $clause;

	return $combined;
}

/**
 * Leave notes out of the main blog, archive, search, and feed queries.
 *
 * Tag archives keep them, so a #hashtag in a note leads to everything with that tag.
 *
 * @param WP_Query $query Query.
 * @return void
 */
function tufte_formats_notes_exclude_main( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() || $query->is_singular() || $query->is_tax( 'post_format' ) || $query->is_tag() ) {
		return;
	}

	if ( $query->is_home() || $query->is_feed() || $query->is_archive() || $query->is_search() ) {
		$query->set( 'tax_query', tufte_formats_notes_and( $query->get( 'tax_query' ), tufte_formats_notes_clause( 'NOT IN' ) ) );
	}
}
add_action( 'pre_get_posts', 'tufte_formats_notes_exclude_main' );

/** Query block ID used by patterns/archive-stream.php. Also hard-coded in assets/js/archive.js (query-21-page). */
const TUFTE_FORMATS_ARCHIVE_STREAM_QUERY_ID = 21;

/**
 * Which archive view the current page is: all, essays, notes, or none.
 *
 * @param string $uri Page URI without leading or trailing slashes.
 * @return string
 */
function tufte_formats_archive_view_for_uri( string $uri ): string {
	$views = array(
		'archive'        => 'all',
		'archive/essays' => 'essays',
		'archive/notes'  => 'notes',
	);

	return $views[ $uri ] ?? '';
}

/**
 * The archive view for the page being rendered, or an empty string.
 *
 * @return string
 */
function tufte_formats_archive_view(): string {
	$page = get_queried_object();

	if ( ! $page instanceof WP_Post || 'page' !== $page->post_type ) {
		return '';
	}

	return tufte_formats_archive_view_for_uri( trim( (string) get_page_uri( $page ), '/' ) );
}

/**
 * Shape a query for an archive view.
 *
 * @param array  $query Query vars.
 * @param string $view  all, essays, or notes.
 * @return array
 */
function tufte_formats_apply_archive_view( array $query, string $view ): array {
	if ( 'essays' === $view ) {
		$query['tax_query'] = tufte_formats_notes_and( $query['tax_query'] ?? array(), tufte_formats_notes_clause( 'NOT IN' ) );
	} elseif ( 'notes' === $view ) {
		$query['tax_query'] = tufte_formats_notes_and( $query['tax_query'] ?? array(), tufte_formats_notes_clause( 'IN' ) );
	}

	return $query;
}

/**
 * Query blocks leave notes out unless the page is an archive view or the block asks for a note format.
 *
 * On the three archive pages the view decides: All keeps everything, Essays drops
 * notes, Notes keeps only notes. Elsewhere, a Post format filter set to any
 * non-standard format makes a notes list; otherwise notes are left out. Query
 * blocks that inherit the main query are handled by pre_get_posts.
 *
 * @param array    $query Query vars.
 * @param WP_Block $block Post template block.
 * @return array
 */
function tufte_formats_notes_query_block( array $query, WP_Block $block ): array {
	$settings = (array) ( $block->context['query'] ?? array() );
	$type     = $query['post_type'] ?? 'post';

	if ( ! empty( $settings['inherit'] ) || ( 'post' !== $type && array( 'post' ) !== $type ) ) {
		return $query;
	}

	// The archive view shapes only the archive stream pattern's query (queryId 21).
	$view = TUFTE_FORMATS_ARCHIVE_STREAM_QUERY_ID === (int) ( $block->context['queryId'] ?? 0 ) ? tufte_formats_archive_view() : '';
	if ( '' !== $view ) {
		return tufte_formats_apply_archive_view( $query, $view );
	}

	$asked = array_diff( (array) ( $settings['format'] ?? array() ), array( 'standard' ) );
	if ( $asked ) {
		return $query;
	}

	$query['tax_query'] = tufte_formats_notes_and( $query['tax_query'] ?? array(), tufte_formats_notes_clause( 'NOT IN' ) );

	return $query;
}
add_filter( 'query_loop_block_query_vars', 'tufte_formats_notes_query_block', 10, 2 );

/**
 * Put the archive view's template first for the essays and notes pages.
 *
 * The files are page-archive-essays and page-archive-notes rather than
 * page-essays and page-notes, so an unrelated page with one of those slugs
 * keeps the normal page template. The All view is the page with the slug
 * archive, which page-archive already matches.
 *
 * @param string[] $templates Page template candidates, most specific first.
 * @param string   $view      Archive view for the page, or ''.
 * @return string[]
 */
function tufte_formats_archive_page_templates( array $templates, string $view ): array {
	if ( 'essays' === $view || 'notes' === $view ) {
		array_unshift( $templates, 'page-archive-' . $view );
	}

	return $templates;
}

/**
 * Filter callback for page_template_hierarchy.
 *
 * @param string[] $templates Page template candidates.
 * @return string[]
 */
function tufte_formats_archive_page_template_hierarchy( array $templates ): array {
	return tufte_formats_archive_page_templates( $templates, tufte_formats_archive_view() );
}
add_filter( 'page_template_hierarchy', 'tufte_formats_archive_page_template_hierarchy' );

/**
 * Notes have no visible title. The generated one is for the admin and the feed.
 *
 * @param string $block_content Rendered core/post-title.
 * @return string
 */
function tufte_formats_notes_hide_title( string $block_content ): string {
	return tufte_formats_is_note() ? '' : $block_content;
}
add_filter( 'render_block_core/post-title', 'tufte_formats_notes_hide_title' );

/**
 * Notes (any non-standard format) use their own single template: single-format-status.
 *
 * Block themes have no per-format template in the hierarchy, so add one.
 *
 * @param string[] $templates Single template candidates, most specific first.
 * @return string[]
 */
function tufte_formats_notes_single_template( array $templates ): array {
	$post = get_queried_object();

	if ( $post instanceof WP_Post && 'post' === $post->post_type && tufte_formats_is_note( $post ) ) {
		array_unshift( $templates, 'single-format-status' );
	}

	return $templates;
}
add_filter( 'single_template_hierarchy', 'tufte_formats_notes_single_template' );

/**
 * Keep newer and older navigation among notes, whatever their format.
 *
 * The Post Navigation Link block's taxonomy option only matches the same
 * format, so a quote's neighbour would never be a photo. This restricts the
 * adjacent post to notes whenever the current post is a note.
 *
 * @param string  $where           Adjacent post WHERE clause.
 * @param bool    $in_same_term    Unused.
 * @param mixed   $excluded_terms  Unused.
 * @param string  $taxonomy        Unused.
 * @param WP_Post $post            Current post.
 * @return string
 */
function tufte_formats_notes_adjacent_where( $where, $in_same_term = false, $excluded_terms = '', $taxonomy = 'category', $post = null ) {
	global $wpdb;

	if ( ! $post instanceof WP_Post || ! tufte_formats_is_note( $post ) ) {
		return $where;
	}

	$slugs        = tufte_formats_note_term_slugs();
	$placeholders = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );

	// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$subquery = $wpdb->prepare(
		"AND p.ID IN ( SELECT tr.object_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'post_format' INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE t.slug IN ($placeholders) )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$slugs
	);

	return $where . ' ' . $subquery;
}
add_filter( 'get_previous_post_where', 'tufte_formats_notes_adjacent_where', 10, 5 );
add_filter( 'get_next_post_where', 'tufte_formats_notes_adjacent_where', 10, 5 );
