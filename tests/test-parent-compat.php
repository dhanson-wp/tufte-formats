<?php
declare(strict_types=1);

// The parent theme's Status-only notes hooks must be gone once the plugin has run.
if ( ! function_exists( 'tufte_blocks_notes_exclude_main' ) ) {
	echo "SKIP  parent notes functions not loaded (parent theme is not Tufte Blocks base); compat test not applicable\n";
	return;
}

$parent_hooks = array(
	array( 'pre_get_posts', 'tufte_blocks_notes_exclude_main' ),
	array( 'query_loop_block_query_vars', 'tufte_blocks_notes_query_block' ),
	array( 'render_block_core/post-title', 'tufte_blocks_notes_hide_title' ),
	// Only on the parent's feature branch, not in the shipped 1.9.0, so it counts only when the callback exists.
	array( 'single_template_hierarchy', 'tufte_blocks_notes_single_template' ),
);
$parent_hooks = array_values(
	array_filter(
		$parent_hooks,
		static function ( array $h ): bool {
			return function_exists( $h[1] );
		}
	)
);

// Re-add the parent hooks as the parent theme would, then run the compat function.
foreach ( $parent_hooks as $h ) {
	if ( ! has_filter( $h[0], $h[1] ) ) {
		add_filter( $h[0], $h[1], 10, 3 );
	}
}
foreach ( $parent_hooks as $h ) {
	tufte_formats_assert_same( true, false !== has_filter( $h[0], $h[1] ), "setup: parent hook {$h[1]} is registered" );
}

tufte_formats_unhook_parent_notes();

foreach ( $parent_hooks as $h ) {
	tufte_formats_assert_same( false, has_filter( $h[0], $h[1] ), "compat: parent hook {$h[1]} is removed" );
}

// The plugin's own hooks stay.
tufte_formats_assert_same( true, false !== has_filter( 'pre_get_posts', 'tufte_formats_notes_exclude_main' ), 'compat: plugin pre_get_posts hook stays' );
tufte_formats_assert_same( true, false !== has_filter( 'render_block_core/post-title', 'tufte_formats_notes_hide_title' ), 'compat: plugin title hook stays' );

// Running it again is harmless.
tufte_formats_unhook_parent_notes();
tufte_formats_assert_same( false, has_filter( 'pre_get_posts', 'tufte_blocks_notes_exclude_main' ), 'compat: running twice is safe' );
