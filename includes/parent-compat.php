<?php
/**
 * Compatibility with the parent theme.
 *
 * Tufte Blocks 1.9.0 registers its own notes model, which treats only the
 * Status format as a note. This plugin owns the notes model (every
 * non-standard format), so the parent's hooks are removed to leave one model.
 *
 * @package Tufte_Formats
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remove the parent theme's notes hooks if it registered them.
 *
 * @return void
 */
function tufte_formats_unhook_parent_notes(): void {
	$hooks = array(
		array( 'pre_get_posts', 'tufte_blocks_notes_exclude_main' ),
		array( 'query_loop_block_query_vars', 'tufte_blocks_notes_query_block' ),
		array( 'render_block_core/post-title', 'tufte_blocks_notes_hide_title' ),
		array( 'single_template_hierarchy', 'tufte_blocks_notes_single_template' ),
	);

	foreach ( $hooks as $hook ) {
		remove_filter( $hook[0], $hook[1], 10 );
	}
}
add_action( 'after_setup_theme', 'tufte_formats_unhook_parent_notes', 99 );
