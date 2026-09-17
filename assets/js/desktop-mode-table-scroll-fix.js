/**
 * Infinite Monkeys Dark Glass — native App Framework window scroll fix.
 *
 * Confirmed live with the site owner: in Vivaldi/macOS, hovering the
 * mouse over <os-table> (the Installed → Table view) or the plugin card
 * grid (Installed → Cards view) makes the Plugins window's own scroll
 * stop dead — it moves a tiny bit, then sticks — while scrolling still
 * works fine the instant the pointer isn't over either of those. Chrome
 * doesn't reproduce it; this looks like something inside OpenStation's
 * own shadow-DOM component(s) swallowing the wheel event on some
 * browsers without actually needing to (the table's own internal
 * scroller doesn't overflow — confirmed live via scrollHeight ===
 * clientHeight — so there's nothing for it to legitimately consume).
 * Reported upstream: github.com/WordPress/openstation/issues/817
 *
 * Fix: intercept wheel input in the CAPTURE phase at the document, i.e.
 * before it ever reaches into a descendant's (possibly shadow-DOM)
 * event handlers, and manually apply the scroll to the window's real
 * scroll container (.os-window__body--native) ourselves — bypassing
 * whatever inside <os-table> or the card grid was eating it. A region
 * that genuinely has its own overflow closer to the pointer (the plugin
 * detail flyout's body, the Plugin Editor's code pane, …) is left
 * completely alone: we only step in when nothing between the pointer
 * and the window body actually needs the scroll.
 *
 * Never touches the OpenStation/Desktop Mode plugin's own files.
 */
( function () {
	'use strict';

	// Only meaningful once the OpenStation shell is active; harmless
	// no-op everywhere else (classic admin, chromeless iframes).
	if ( ! document.body.classList.contains( 'os-active' ) ) {
		return;
	}

	function hasRealOverflow( el ) {
		if ( ! el || el.nodeType !== 1 ) {
			return false;
		}
		var cs = getComputedStyle( el );
		if ( cs.overflowY !== 'auto' && cs.overflowY !== 'scroll' ) {
			return false;
		}
		return el.scrollHeight > el.clientHeight + 1;
	}

	document.addEventListener(
		'wheel',
		function ( e ) {
			// Leave pinch-zoom (ctrl/cmd+wheel) and predominantly
			// horizontal gestures alone — this is a vertical-scroll fix
			// only.
			if ( e.ctrlKey || e.metaKey || Math.abs( e.deltaX ) >= Math.abs( e.deltaY ) ) {
				return;
			}

			// composedPath (not e.target) so this still sees the real
			// origin when the event starts deep inside a shadow root,
			// e.g. <os-table>'s internal scroller.
			var path = e.composedPath ? e.composedPath() : [ e.target ];
			var windowBody = null;

			for ( var i = 0; i < path.length; i++ ) {
				var node = path[ i ];
				if ( ! node || node.nodeType !== 1 ) {
					continue;
				}
				if ( node.classList && node.classList.contains( 'os-window__body--native' ) ) {
					windowBody = node;
					break;
				}
				// Something closer to the pointer already has real
				// overflow of its own (a detail flyout, a code pane) —
				// that's legitimate; don't hijack scroll away from it.
				if ( hasRealOverflow( node ) ) {
					return;
				}
			}

			if ( ! windowBody || ! hasRealOverflow( windowBody ) ) {
				return;
			}

			// Already at a limit in the requested direction — nothing to
			// do, and no reason to swallow the event.
			if ( e.deltaY < 0 && windowBody.scrollTop <= 0 ) {
				return;
			}
			if ( e.deltaY > 0 && windowBody.scrollTop + windowBody.clientHeight >= windowBody.scrollHeight - 1 ) {
				return;
			}

			windowBody.scrollTop += e.deltaY;
			e.preventDefault();
			e.stopImmediatePropagation();
		},
		{ capture: true, passive: false }
	);
} )();
