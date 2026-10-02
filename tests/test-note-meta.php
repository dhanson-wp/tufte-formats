<?php
declare(strict_types=1);

tufte_formats_assert_same( '@priya@mastodon.social', tufte_formats_fediverse_handle( 'https://mastodon.social/@priya' ), 'handle: /@name shape' );
tufte_formats_assert_same( '@sam@example.org', tufte_formats_fediverse_handle( 'https://example.org/users/sam' ), 'handle: /users/name shape' );
tufte_formats_assert_same( 'weird.example', tufte_formats_fediverse_handle( 'https://weird.example/actor/12345/profile' ), 'handle: unknown shape falls back to the host' );
tufte_formats_assert_same( '', tufte_formats_fediverse_handle( 'not a url' ), 'handle: invalid url is empty, never @@' );
tufte_formats_assert_same( '', tufte_formats_fediverse_handle( '' ), 'handle: empty url is empty' );

$post = wp_insert_post( array( 'post_title' => 'nm-note', 'post_status' => 'publish' ) );
set_post_format( $post, 'status' );

tufte_formats_assert_same( 0, tufte_formats_reply_count( $post ), 'reply count: none' );

$mk = static function ( array $over ) use ( $post ) {
	return wp_insert_comment( array_merge( array(
		'comment_post_ID'  => $post,
		'comment_approved' => 1,
		'comment_content'  => 'x',
		'comment_author'   => 'A',
		'comment_type'     => 'comment',
	), $over ) );
};

$like   = $mk( array( 'comment_type' => 'like' ) );
$repost = $mk( array( 'comment_type' => 'repost' ) );
$quote  = $mk( array( 'comment_type' => 'quote' ) );
$mention = $mk( array( 'comment_type' => 'mention' ) );
tufte_formats_assert_same( 0, tufte_formats_reply_count( $post ), 'reply count: likes, reposts, quotes, and mentions alone are zero' );

$reply = $mk( array() );
$hold  = $mk( array( 'comment_approved' => 0 ) );
tufte_formats_assert_same( 1, tufte_formats_reply_count( $post ), 'reply count: counts approved replies only' );

$fedi = $mk( array( 'comment_author_url' => 'https://mastodon.social/@priya', 'comment_meta' => array( 'protocol' => 'activitypub' ) ) );
$wm   = $mk( array( 'comment_author_url' => 'https://author.example.net/', 'comment_meta' => array( 'protocol' => 'webmention', 'webmention_source_url' => 'https://blog.example.com/post/1' ) ) );
$wm_nourl = $mk( array( 'comment_author_url' => 'https://fallback.example.org/me', 'comment_meta' => array( 'protocol' => 'webmention' ) ) );
$wm_bad   = $mk( array( 'comment_author_url' => '', 'comment_meta' => array( 'protocol' => 'webmention', 'webmention_source_url' => 'not a url' ) ) );
$legacy   = $mk( array( 'comment_type' => 'webmention', 'comment_author_url' => 'https://blog.example.com/post/1' ) );

$src = tufte_formats_comment_source( get_comment( $fedi ) );
tufte_formats_assert_same( array( 'type' => 'fediverse', 'text' => '@priya@mastodon.social' ), $src, 'source: fediverse reply shows the handle' );

$src = tufte_formats_comment_source( get_comment( $wm ) );
tufte_formats_assert_same( array( 'type' => 'webmention', 'text' => 'via blog.example.com' ), $src, 'source: webmention shows via host' );

$src = tufte_formats_comment_source( get_comment( $reply ) );
tufte_formats_assert_same( array( 'type' => '', 'text' => '' ), $src, 'source: a plain site comment has none' );

$src = tufte_formats_comment_source( get_comment( $wm_nourl ) );
tufte_formats_assert_same( array( 'type' => 'webmention', 'text' => 'via fallback.example.org' ), $src, 'source: webmention without a source url falls back to the author url' );

$src = tufte_formats_comment_source( get_comment( $wm_bad ) );
tufte_formats_assert_same( array( 'type' => '', 'text' => '' ), $src, 'source: webmention with an unusable url prints nothing' );

$src = tufte_formats_comment_source( get_comment( $legacy ) );
tufte_formats_assert_same( array( 'type' => '', 'text' => '' ), $src, 'source: comment_type webmention without the protocol meta is not a webmention' );

$weird = $mk( array( 'comment_author_url' => '', 'comment_meta' => array( 'protocol' => 'activitypub' ) ) );
$src   = tufte_formats_comment_source( get_comment( $weird ) );
tufte_formats_assert_same( array( 'type' => '', 'text' => '' ), $src, 'source: fediverse reply with no author url prints nothing (no "@@")' );

$classes = apply_filters( 'comment_class', array( 'comment' ), '', $fedi, get_comment( $fedi ), $post );
tufte_formats_assert_same( true, in_array( 'is-fediverse', $classes, true ), 'comment_class: fediverse reply gets is-fediverse' );
$classes = apply_filters( 'comment_class', array( 'comment' ), '', $reply, get_comment( $reply ), $post );
tufte_formats_assert_same( false, in_array( 'is-fediverse', $classes, true ), 'comment_class: site comment does not' );


// Scope: essays are untouched.
$essay = wp_insert_post( array( 'post_title' => 'nm-essay', 'post_status' => 'publish' ) );
$efedi = wp_insert_comment( array(
	'comment_post_ID' => $essay, 'comment_approved' => 1, 'comment_content' => 'x', 'comment_author' => 'A',
	'comment_author_url' => 'https://mastodon.social/@priya', 'comment_meta' => array( 'protocol' => 'activitypub' ),
) );
$classes = apply_filters( 'comment_class', array( 'comment' ), '', $efedi, get_comment( $efedi ), $essay );
tufte_formats_assert_same( false, in_array( 'is-fediverse', $classes, true ), 'comment_class: fediverse reply on an essay gets no is-fediverse' );

$date_block = static function ( int $comment_id ) {
	$parsed = array( 'blockName' => 'core/comment-date', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
	$inst   = new WP_Block( $parsed, array( 'commentId' => $comment_id ) );
	return tufte_formats_comment_source_line( '<div>date</div>', $parsed, $inst );
};
tufte_formats_assert_same( '<div>date</div>', $date_block( $efedi ), 'source line: essay thread is left unchanged' );
tufte_formats_assert_same( true, false !== strpos( $date_block( $fedi ), '@priya@mastodon.social' ), 'source line: note thread gets the handle' );
tufte_formats_assert_same( true, false !== strpos( $date_block( $wm ), 'via blog.example.com' ), 'source line: note webmention gets via host' );
tufte_formats_assert_same( '<div>date</div>', $date_block( $reply ), 'source line: plain comment unchanged' );

// Reply link on the Post Date block.
$link = static function ( int $post_id, string $class, string $html = '<div class="wp-block-post-date"><time datetime="2026-09-30T00:00:00+00:00">Sep 30</time></div>' ) {
	$parsed = array( 'blockName' => 'core/post-date', 'attrs' => array( 'className' => $class ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
	$inst   = new WP_Block( $parsed, array( 'postId' => $post_id ) );
	return tufte_formats_note_reply_link( $html, $parsed, $inst );
};
$plain = '<div class="wp-block-post-date"><time datetime="2026-09-30T00:00:00+00:00">Sep 30</time></div>';
tufte_formats_assert_same( true, false !== strpos( $link( $post, 'tufte-note-date' ), 'class="note-replies"' ), 'reply link: shown for a note with replies' );
tufte_formats_assert_same( true, false !== strpos( $link( $post, 'tufte-note-date' ), tufte_formats_reply_count( $post ) . ' replies' ), 'reply link: prints the reply count' );
$out = $link( $post, 'tufte-note-date' );
tufte_formats_assert_same( true, false !== strpos( $out, '<time id="note-date-' . $post . '"' ), 'reply link: time gets an id' );
tufte_formats_assert_same( true, false !== strpos( $out, 'aria-describedby="note-date-' . $post . '"' ), 'reply link: anchor describedby the date' );
tufte_formats_assert_same( true, 1 === substr_count( $out, 'id="note-date-' . $post . '"' ), 'reply link: id appears once' );
$kept = $link( $post, 'tufte-note-date', '<div><time id="mine" datetime="x">Sep 30</time></div>' );
tufte_formats_assert_same( true, false !== strpos( $kept, 'aria-describedby="mine"' ), 'reply link: an existing time id is reused' );
tufte_formats_assert_same( $plain, $link( $post, 'other-class' ), 'reply link: skipped without the tufte-note-date class' );
tufte_formats_assert_same( $plain, $link( $essay, 'tufte-note-date' ), 'reply link: skipped for essays' );
$quiet = wp_insert_post( array( 'post_title' => 'nm-quiet', 'post_status' => 'publish' ) );
set_post_format( $quiet, 'status' );
$none = $link( $quiet, 'tufte-note-date' );
tufte_formats_assert_same( true, false !== strpos( $none, '>Reply</a>' ), 'reply link: says "Reply" at zero replies' );
tufte_formats_assert_same( true, false !== strpos( $none, 'href="' . get_permalink( $quiet ) . '#respond"' ), 'reply link: zero replies links to the comment form' );
tufte_formats_assert_same( true, false !== strpos( $none, 'aria-describedby="note-date-' . $quiet . '"' ), 'reply link: zero-reply link is described by the date' );
tufte_formats_assert_same( true, false !== strpos( $link( $post, 'tufte-note-date' ), 'href="' . get_permalink( $post ) . '#responses"' ), 'reply link: replies link to the thread' );

// View note: a visual-only second link to the permalink, in lists but not on the note's own page.
tufte_formats_assert_same( true, false !== strpos( $none, '<p class="note-view"><a href="' . get_permalink( $quiet ) . '" aria-hidden="true" tabindex="-1">View note</a></p>' ), 'view note: shown in a list, hidden from assistive tech and the tab order' );
add_filter( 'tufte_formats_show_view_note', '__return_false' );
tufte_formats_assert_same( false, strpos( $link( $quiet, 'tufte-note-date' ), 'note-view' ), 'view note: not shown on the note itself' );
remove_filter( 'tufte_formats_show_view_note', '__return_false' );
wp_delete_post( $quiet, true );
wp_delete_comment( $efedi, true );
wp_delete_post( $essay, true );

foreach ( array( $like, $repost, $quote, $mention, $reply, $hold, $fedi, $wm, $wm_nourl, $wm_bad, $legacy, $weird ) as $cid ) {
	wp_delete_comment( $cid, true );
}
wp_delete_post( $post, true );
