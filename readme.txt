=== Tufte Formats ===
Tags: post formats, notes, block theme
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Post formats as notes for Tufte Blocks sites.

== Description ==

The full description, tests, and deploy notes live in README.md.

== Changelog ==

= 0.4.0 =
* Archive views are configurable. The `tufte_formats_archive_views` filter maps more page URIs to views, and `tufte_formats_archive_view_formats` makes a view that lists only certain formats (for example, photos from image and gallery notes). Each view other than All gets its own `page-archive-{view}` template.
* In-place navigation works on every archive view, including added ones: the views' paths reach the script from the server, and the screen reader announcement reads the current filter's label.

= 0.3.1 =
* A gallery note split into several smaller galleries now stays short in the archive stream too. The six-photo limit counts across all of a note's galleries, so later galleries wait for the note page while the text stays, and the "+N" sits on the last photo shown.

= 0.3.0 =
* Gallery notes in the archive stream show at most six photos in an even grid. A bigger gallery's sixth square says "+N" and links to the note, where every photo shows. Smaller galleries fill the row, and per-photo captions wait for the note page.

= 0.2.1 =
* Clicking a photo in the archive stream now opens it in the lightbox and leaves you on the archive. Before, the note opened behind the lightbox.

= 0.2.0 =
* Place archives from Tufte Places now list your notes, so a place shows the photos you took there, not just the essays.
* A new tufte_formats_note_archive_taxonomies filter picks which taxonomy archives keep notes. It starts with tags and places.

= 0.1.0 =
* First release as its own plugin: the notes model, the Essays and Notes archive views, note meta, and search with a format filter.
