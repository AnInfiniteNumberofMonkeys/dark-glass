/**
 * Infinite Monkeys Dark Glass — TEMPORARY MOBILE COMPATIBILITY SHIM.
 *
 * Desktop Mode is not currently mobile-compatible. This file (and its
 * matching CSS block in admin.css, and the wp_is_mobile() guard around
 * frosted-glass.js in enqueue.php) work around that on our end. Remove
 * all three once the Desktop Mode plugin ships native mobile support.
 *
 * Enqueued UNCONDITIONALLY (see enqueue.php) rather than gated by
 * wp_is_mobile() — confirmed that server-side User-Agent sniffing and
 * our client-side @media query (max-width / hover:none+pointer:coarse)
 * can disagree for a real device, since they're two entirely different
 * detection mechanisms. The CSS fix reached the device fine (confirmed
 * live) while this script silently never loaded when gated by
 * wp_is_mobile() — exactly what a client/server mismatch looks like.
 * So this now always loads and checks the SAME media query our CSS
 * uses, guaranteeing they can never disagree.
 *
 * Why this exists at all: a plain CSS `#wpadminbar { display: none
 * !important; }` is NOT sufficient on its own — confirmed live that
 * something dynamically re-asserts the admin bar's visibility (setting
 * display:none via a stylesheet rule gets silently overridden, but
 * setting it directly via inline style works, which is only possible
 * if some other script keeps re-triggering visibility afterwards).
 * Rather than chase down exactly what does that, this just wins the
 * argument every time by re-applying an inline !important style
 * whenever the element's own style/attributes change.
 */
( function () {
	'use strict';

	var MOBILE_QUERY = '(max-width: 782px), (hover: none) and (pointer: coarse)';
	if ( ! window.matchMedia( MOBILE_QUERY ).matches ) {
		return;
	}

	/*
	 * Checks the IMPORTANCE flag specifically, not just the value —
	 * confirmed live this was the actual bug in an earlier version.
	 * bar.style.display returns just the VALUE ("none"), not whether
	 * it's flagged !important. Something else was setting a plain
	 * (non-important) display:none, which made a value-only guard think
	 * there was nothing to do, so it never re-asserted !important —
	 * letting an external !important rule win instead. This does need a
	 * guard of SOME kind though (rather than writing unconditionally on
	 * every call): since this runs from a MutationObserver watching the
	 * style attribute, writing to that same attribute unconditionally on
	 * every callback — even when the value doesn't change — would fire
	 * the observer again and create a busy-loop.
	 */
	function hideAdminBar() {
		var bar = document.getElementById( 'wpadminbar' );
		if ( ! bar ) {
			return;
		}
		var alreadyCorrect = bar.style.display === 'none' &&
			bar.style.getPropertyPriority( 'display' ) === 'important';
		if ( ! alreadyCorrect ) {
			bar.style.setProperty( 'display', 'none', 'important' );
		}
	}

	hideAdminBar();

	var existing = document.getElementById( 'wpadminbar' );
	if ( existing ) {
		new MutationObserver( hideAdminBar ).observe( existing, {
			attributes: true,
			attributeFilter: [ 'style', 'class' ],
		} );
	}

	// The bar itself may not exist yet at script-load time (e.g. while
	// Desktop Mode is still building out the shell) — keep watching
	// document.body for it to appear, same reasoning as above.
	new MutationObserver( function () {
		hideAdminBar();
		var el = document.getElementById( 'wpadminbar' );
		if ( el && ! el.imdgObserved ) {
			el.imdgObserved = true;
			new MutationObserver( hideAdminBar ).observe( el, {
				attributes: true,
				attributeFilter: [ 'style', 'class' ],
			} );
		}
	} ).observe( document.body, { childList: true, subtree: true } );

	/*
	 * Belt-and-braces: a MutationObserver only fires in response to a
	 * DOM change, so if whatever fights us re-asserts visibility via some
	 * mechanism that doesn't trigger one (unlikely but not ruled out),
	 * this polling fallback guarantees eventual consistency regardless.
	 */
	setInterval( hideAdminBar, 250 );
} )();
