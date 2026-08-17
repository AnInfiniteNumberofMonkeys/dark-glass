/**
 * Infinite Monkeys Dark Glass — Bricks builder same-tab handoff.
 *
 * Bricks' visual editor isn't a wp-admin page at all — it loads the
 * actual front-end page/post URL with `?bricks=run` appended. Desktop
 * Mode's entire windowing system is deliberately scoped to `/wp-admin/`
 * URLs only (confirmed by reading its own routing code), so it never
 * attempts to window a Bricks edit link in the first place. Left
 * completely alone, clicking "Edit with Bricks" navigates just the
 * chromeless content iframe to that front-end URL — Bricks' own
 * frame-identity handling then inconsistently decides whether to stay
 * framed or frame-bust up to the top window, which is unreliable.
 *
 * This intercepts any click on a link whose href matches the
 * `?bricks=run` pattern — wherever it appears (Pages/Posts row actions,
 * the post editor's own "Edit with Bricks" button, etc.) — and
 * deterministically navigates the TOP-LEVEL window (the actual browser
 * tab) to that URL, escaping the Desktop Mode shell in place rather
 * than leaving it up to Bricks' own frame-busting behavior or opening a
 * separate tab.
 *
 * Scoped to chromeless windows only; this pattern only ever appears
 * inside admin page content, never in the parent shell chrome itself.
 *
 * NOTE: only covers light-DOM links (the classic wp-admin list tables
 * and post editor rendered inside a chromeless iframe). If Desktop
 * Mode's native Pages/Posts reimplementation (wpd-table, shadow DOM)
 * ever surfaces its own "Edit with Bricks" action, a click there won't
 * be caught by this script — shadow DOM event retargeting means a
 * light-DOM listener only ever sees the shadow HOST as e.target, not
 * the actual anchor inside. That would need the same recursive
 * shadow-root observer pattern used in desktop-mode-shadow-styles.js.
 */
( function () {
	'use strict';

	var BRICKS_RUN_PATTERN = /[?&]bricks=run(?:&|$)/;

	function isBricksBuilderLink( el ) {
		if ( ! el || el.tagName !== 'A' ) {
			return false;
		}
		var href = el.getAttribute( 'href' ) || '';
		return BRICKS_RUN_PATTERN.test( href );
	}

	/*
	 * The listener is attached unconditionally and immediately, with no
	 * dependency on document.body existing yet — addEventListener on
	 * `document` is always safe, even before <body> is parsed. The actual
	 * "are we in a chromeless window" check happens INSIDE the handler,
	 * evaluated fresh on every single click, rather than once at script
	 * load time. This is deliberate: the previous version checked
	 * document.body.classList once at load time and only attached the
	 * listener if that passed — if this script (loaded in the footer,
	 * after many other scripts) finished loading even slightly late, or
	 * that one check had any timing quirk, the listener never got
	 * attached at all for the rest of that page's lifetime, silently
	 * falling through to normal navigation. Checking on every click
	 * instead removes that race entirely, since by the time ANY click can
	 * happen, document.body is guaranteed to exist.
	 */
	document.addEventListener( 'click', function ( e ) {
		if ( ! document.body || ! document.body.classList.contains( 'os-chromeless' ) ) {
			return;
		}
		if ( e.defaultPrevented ) {
			return;
		}
		// Only plain left-clicks — let modifier-key clicks (open in
		// background tab, etc.) and middle-clicks behave natively.
		if ( e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ) {
			return;
		}

		var el = e.target;
		while ( el && el.nodeType === 1 ) {
			if ( isBricksBuilderLink( el ) ) {
				e.preventDefault();
				e.stopPropagation();
				if ( typeof e.stopImmediatePropagation === 'function' ) {
					e.stopImmediatePropagation();
				}
				// Navigate the actual browser tab (top window), not just
				// this iframe, and not a new tab — same tab, top level.
				window.top.location.href = el.href;
				return;
			}
			el = el.parentElement;
		}
	}, true );
} )();
