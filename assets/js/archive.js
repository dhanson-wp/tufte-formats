/**
 * Archive in-place navigation.
 *
 * Filter links and pagination links inside the router region swap the region
 * without a full page load. Anything else (modified clicks, other targets,
 * other URLs) is left to the browser. Plain ES module; the plugin has no build step.
 */
import { store, getElement, withSyncEvent } from '@wordpress/interactivity';

// Keep in step with TUFTE_FORMATS_ARCHIVE_STREAM_QUERY_ID (21) in includes/notes.php.
const PAGINATION_PARAM = 'query-21-page';
const prefetched = new Set();
let navId = 0;

/**
 * Whether a path is one of the archive views (state.paths, from the server), or a page of one.
 *
 * @param {string} pathname Link path.
 * @return {boolean} Archive view path.
 */
const isArchivePath = ( pathname ) => ( state.paths || [] ).includes( pathname.replace( /page\/\d+\/$/, '' ) );

/**
 * The eligible archive link for an event, or null.
 *
 * @param {Event} event Click, mouseover, or focusin event.
 * @param {Element} region Router region root.
 * @param {Set<string>} [seen] Hrefs to skip before any URL parsing.
 * @return {HTMLAnchorElement|null} Link.
 */
const eligibleLink = ( event, region, seen ) => {
	const target = event.target;
	const link = target instanceof Element ? target.closest( 'a' ) : null;
	if ( ! link || ! link.href || ( seen && seen.has( link.href ) ) || ! region.contains( link ) ) {
		return null;
	}
	if ( link.target && link.target !== '_self' ) {
		return null;
	}
	if ( link.hasAttribute( 'download' ) || link.origin !== window.location.origin ) {
		return null;
	}
	if ( ! isArchivePath( link.pathname ) ) {
		return null;
	}
	for ( const key of new URL( link.href ).searchParams.keys() ) {
		if ( key !== PAGINATION_PARAM ) {
			return null;
		}
	}
	return link;
};

/**
 * The permalink of the note a click landed on, when the click should open it.
 *
 * A note is clickable anywhere except on its own links, buttons, form controls, players (video, audio, and embeds), and
 * lightbox images, and never while text is selected, so reading, copying, and enlarging a photo still work.
 *
 * @param {Event} event Click event.
 * @param {Element} region Router region root.
 * @return {string} Permalink, or an empty string.
 */
const noteOpenTarget = ( event, region ) => {
	const target = event.target;
	if ( ! ( target instanceof Element ) ) {
		return '';
	}
	const card = target.closest( '.wp-block-post' );
	if ( ! card || ! region.contains( card ) || card.classList.contains( 'format-standard' ) ) {
		return '';
	}
	if ( target.closest( 'a, button, input, select, textarea, summary, label, video, audio, iframe, .wp-lightbox-container' ) ) {
		return '';
	}
	if ( window.getSelection()?.toString() ) {
		return '';
	}
	return card.querySelector( '.wp-block-post-date a' )?.href || '';
};

const isPlainClick = ( event ) =>
	event.button === 0 &&
	! event.metaKey &&
	! event.ctrlKey &&
	! event.altKey &&
	! event.shiftKey &&
	! event.defaultPrevented;

const reducedMotion = () => window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

/** Words for screen readers, read from the page that was just swapped in. */
const describeView = ( region ) => {
	const current = region.querySelector( '.tufte-filter a[aria-current="page"]' );
	const label = current ? current.textContent.trim().toLowerCase() : 'all';
	let message = 'all' === label ? 'Showing all posts' : `Showing ${ label }`;
	const here = region.querySelector( '.page-numbers.current' );
	if ( here ) {
		const numbers = [ ...region.querySelectorAll( '.page-numbers' ) ]
			.map( ( el ) => parseInt( el.textContent.replace( /\D/g, '' ), 10 ) )
			.filter( ( n ) => ! Number.isNaN( n ) );
		const total = Math.max( ...numbers );
		message = `Page ${ here.textContent.trim() } of ${ total }. ${ message }`;
	}
	return message;
};

const { state } = store( 'tufte-formats/archive', {
	state: {
		isNavigating: false,
	},
	actions: {
		navigate: withSyncEvent( function* ( event ) {
			const { ref: region } = getElement();

			// A click on the body of a note opens the note.
			const noteHref = noteOpenTarget( event, region );
			if ( noteHref && event.button === 0 && ! event.defaultPrevented ) {
				event.preventDefault();
				if ( event.metaKey || event.ctrlKey ) {
					window.open( noteHref, '_blank', 'noopener' );
				} else if ( ! event.shiftKey && ! event.altKey ) {
					window.location.assign( noteHref );
				}
				return;
			}

			const link = eligibleLink( event, region );
			if ( ! link || ! isPlainClick( event ) ) {
				return;
			}
			event.preventDefault();

			const href = link.href;
			if ( href === window.location.href ) {
				return;
			}
			const isFilter = !! link.closest( '.tufte-filter' );
			const id = ++navId;

			state.isNavigating = true;
			try {
				const { actions } = yield import( '@wordpress/interactivity-router' );
				// Loading the router can finish out of order when clicks come fast; a stale click must not navigate after a newer one.
				if ( id !== navId ) {
					return;
				}
				yield actions.navigate( href, { screenReaderAnnouncement: false } );
			} catch ( error ) {
				// A newer click took over; a late failure here must not pull the user away from it.
				if ( id !== navId ) {
					return;
				}
				// The link must always work: fall back to a full page load.
				state.isNavigating = false;
				window.location.assign( href );
				return;
			}

			// A newer click took over; it owns the busy state, focus, scroll, and announcement.
			if ( id !== navId ) {
				return;
			}
			state.isNavigating = false;

			const root = document.querySelector( '.tufte-archive-region' );
			if ( ! root ) {
				return;
			}

			if ( isFilter ) {
				// The filter row was re-rendered; keep keyboard focus on the same filter.
				[ ...root.querySelectorAll( '.tufte-filter a' ) ]
					.find( ( a ) => a.href === href )
					?.focus( { preventScroll: true } );
			} else {
				root.scrollIntoView( { behavior: reducedMotion() ? 'auto' : 'smooth', block: 'start' } );
				( root.querySelector( '.wp-block-post-title a' ) ||
					root.querySelector( '.wp-block-post-template a[href]' ) )?.focus( { preventScroll: true } );
			}

			try {
				const { speak } = yield import( '@wordpress/a11y' );
				speak( describeView( root ), 'polite' );
			} catch ( error ) {
				// Announcements are a courtesy; ignore a failed load.
			}
		} ),

		prefetch: withSyncEvent( function* ( event ) {
			const { ref: region } = getElement();
			const link = eligibleLink( event, region, prefetched );
			if ( ! link ) {
				return;
			}
			const href = link.href;
			try {
				const { actions } = yield import( '@wordpress/interactivity-router' );
				yield actions.prefetch( href );
				prefetched.add( href );
			} catch ( error ) {
				// Prefetch is an optimization; a click still navigates.
			}
		} ),
	},
} );
