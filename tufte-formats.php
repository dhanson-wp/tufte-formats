<?php
/**
 * Plugin Name: Tufte Formats
 * Description: Post formats as notes for Tufte Blocks sites: the notes model, the Essays and Notes archive views, note reply counts and fediverse source lines, six-photo gallery grids in the stream, and in-place archive navigation.
 * Version:     0.3.0
 * Author:      Derek Hanson
 * License:     GPL-2.0-or-later
 * Text Domain: tufte-formats
 *
 * @package Tufte_Formats
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TUFTE_FORMATS_VERSION', '0.3.0' );
define( 'TUFTE_FORMATS_PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/parent-compat.php';
require_once __DIR__ . '/includes/notes.php';
require_once __DIR__ . '/includes/search.php';
require_once __DIR__ . '/includes/note-meta.php';
require_once __DIR__ . '/includes/archive-interactivity.php';
require_once __DIR__ . '/includes/link-notes.php';
require_once __DIR__ . '/includes/format-editing.php';
require_once __DIR__ . '/includes/gallery-stream.php';
