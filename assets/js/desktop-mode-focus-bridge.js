/**
 * Infinite Monkeys Dark Glass — Desktop Mode window-focus bridge.
 *
 * Desktop Mode tracks window focus (.desktop-mode-window--focused) only
 * in the PARENT shell document. A chromeless window's own content is a
 * separate iframe document with no visibility into that state at all —
 * Desktop Mode's own iframe-bridge.js only sends messages the other way
 * (iframe → parent, to request focus on click). There's no existing
 * channel for "is MY window currently focused" to reach the iframe.
 *
 * This script runs in every admin_enqueue_scripts context (parent shell
 * AND every chromeless iframe, since that hook fires universally) and
 * plays one of two roles depending which context it finds itself in:
 *
 *   PARENT SHELL: watches every .desktop-mode-window for focus changes
 *   and posts the current state into that window's iframe.
 *
 *   CHROMELESS IFRAME: listens for that message and toggles
 *   body.imdg-window-focused / body.imdg-window-unfocused, so our own
 *   stylesheets can style content differently based on the OWN window's
 *   focus state — e.g. making a plugin's hardcoded opaque background
 *   translucent only while its window is focused.
 *
 * Never touches the Desktop Mode plugin's own files.
 */
( function () {
	'use strict';

	var MESSAGE_TYPE = 'imdg-window-focus';

	// ── Chromeless iframe role ─────────────────────────────────────────
	if ( window.self !== window.top && document.body.classList.contains( 'os-chromeless' ) ) {
		window.addEventListener( 'message', function ( event ) {
			if ( event.origin !== window.location.origin ) {
				return;
			}
			if ( ! event.data || event.data.type !== MESSAGE_TYPE ) {
				return;
			}
			document.body.classList.toggle( 'imdg-window-focused', !! event.data.focused );
			document.body.classList.toggle( 'imdg-window-unfocused', ! event.data.focused );
		} );

		// Newly opened windows are usually the focused one — assume
		// focused until told otherwise, so there's no flash of the
		// "unfocused" (opaque) look while waiting for the first sync.
		document.body.classList.add( 'imdg-window-focused' );
		return;
	}

	// ── Parent shell role ───────────────────────────────────────────────
	if ( ! document.body.classList.contains( 'os-active' ) ) {
		return;
	}

	function postFocusState( win ) {
		var iframe = win.querySelector( 'iframe' );
		if ( ! iframe || ! iframe.contentWindow ) {
			return;
		}
		var focused = win.classList.contains( 'os-window--focused' );
		try {
			iframe.contentWindow.postMessage(
				{ type: MESSAGE_TYPE, focused: focused },
				window.location.origin
			);
		} catch ( e ) {
			// Cross-origin iframe (shouldn't happen for chromeless windows) — ignore.
		}
	}

	function watchWindow( win ) {
		if ( win.imdgFocusBridgeAttached ) {
			return;
		}
		win.imdgFocusBridgeAttached = true;

		// Sync once the iframe has actually loaded (covers the initial
		// load race — posting before the iframe's listener exists would
		// silently lose the message).
		var iframe = win.querySelector( 'iframe' );
		if ( iframe ) {
			if ( iframe.contentDocument && iframe.contentDocument.readyState === 'complete' ) {
				postFocusState( win );
			}
			iframe.addEventListener( 'load', function () {
				postFocusState( win );
			} );
		}

		// Re-sync on every focus/unfocus toggle.
		var classObserver = new MutationObserver( function () {
			postFocusState( win );
		} );
		classObserver.observe( win, { attributes: true, attributeFilter: [ 'class' ] } );
	}

	function scanForWindows( root ) {
		if ( root.nodeType !== 1 ) {
			return;
		}
		if ( root.classList && root.classList.contains( 'os-window' ) ) {
			watchWindow( root );
		}
		if ( root.querySelectorAll ) {
			var found = root.querySelectorAll( '.os-window' );
			for ( var i = 0; i < found.length; i++ ) {
				watchWindow( found[ i ] );
			}
		}
	}

	scanForWindows( document.body );

	var windowObserver = new MutationObserver( function ( mutations ) {
		for ( var m = 0; m < mutations.length; m++ ) {
			var addedNodes = mutations[ m ].addedNodes;
			for ( var n = 0; n < addedNodes.length; n++ ) {
				scanForWindows( addedNodes[ n ] );
			}
		}
	} );
	windowObserver.observe( document.body, { childList: true, subtree: true } );
} )();
