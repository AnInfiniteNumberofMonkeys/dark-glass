/**
 * Infinite Monkeys Dark Glass — Desktop Mode OS Settings "Save as Default".
 *
 * Desktop Mode's OS Settings window ships a hardcoded "Reset to defaults"
 * button (assets/js/os-settings-panel.js) whose handler closes over a
 * module-level `DEFAULTS` constant baked directly into that file — there
 * is no WordPress option, filter, or exposed setter for it (confirmed by
 * reading the source; see conversation notes). This script adds a
 * companion "Save as default" button next to it and reroutes Reset so it
 * restores YOUR saved baseline instead of Desktop Mode's shipped one,
 * without ever touching a Desktop Mode file — so it survives updates.
 *
 * How it works:
 *   - Desktop Mode persists OS Settings state client-side in
 *     localStorage['desktop-mode-os-settings'], read ONCE by the
 *     `OsSettings` class at construction (page load). There is no public
 *     setter, so mutating that key after load has no live effect until a
 *     reload — that's why both actions below end in either a save-only
 *     (no reload needed) or a reload (when we need the new state to take
 *     effect immediately).
 *   - "Save as default" snapshots that key verbatim into our own
 *     localStorage['imdg-os-settings-defaults'] key. Nothing else reads
 *     this key — it's purely our own baseline record.
 *   - Clicking "Reset to defaults" is intercepted in the CAPTURE phase on
 *     `document`, which fires before Desktop Mode's own click handler
 *     (bound directly on the button, so it only runs at the "target"
 *     phase). This lets us stop the click dead before Desktop Mode ever
 *     writes its real defaults to storage. If we have a saved baseline,
 *     we write it into the real settings key ourselves and reload the
 *     page so `OsSettings` picks it up fresh on next construction — no
 *     flash of the true defaults, no race with Desktop Mode's own
 *     save/apply/render cycle.
 *   - If nothing has been saved yet, we don't intercept at all — Reset
 *     falls through untouched to Desktop Mode's normal behavior.
 *
 * The "Save as default" button is injected reactively via
 * MutationObserver rather than once at load, because Desktop Mode
 * re-renders the whole settings panel (including the footer) on every
 * tab switch — same pattern already used in
 * desktop-mode-shadow-styles.js for shadow-root re-renders. The check is
 * idempotent (bails out if a button is already present in that footer),
 * so it self-heals regardless of whether a given re-render happens to
 * wipe it out or not.
 *
 * Both localStorage keys are per-browser, same as all of Desktop Mode's
 * own OS Settings state — this isn't a site-wide default, it's a
 * per-browser one, exactly like the settings it's overriding.
 *
 * Never touches the Desktop Mode plugin's own files.
 */
( function () {
	'use strict';

	// Only relevant inside the Desktop Mode shell.
	if ( ! document.body.classList.contains( 'os-active' ) ) {
		return;
	}

	var SETTINGS_KEY = 'desktop-mode-os-settings';
	var BASELINE_KEY = 'imdg-os-settings-defaults';
	var FOOTER_SELECTOR = '.os-settings__footer';
	var SAVE_BUTTON_ATTR = 'data-imdg-save-default';
	var ORIGINAL_LABEL_ATTR = 'data-imdg-original-label';

	// ── "Save as default" button injection ─────────────────────────────

	function makeSaveButton() {
		var btn = document.createElement( 'os-button' );
		btn.setAttribute( 'variant', 'ghost' );
		btn.setAttribute( SAVE_BUTTON_ATTR, '1' );
		btn.textContent = 'Save as default';
		btn.addEventListener( 'click', onSaveAsDefault );
		return btn;
	}

	function onSaveAsDefault( e ) {
		e.preventDefault();
		var btn = e.currentTarget;
		var raw = null;
		try {
			raw = window.localStorage.getItem( SETTINGS_KEY );
		} catch ( err ) {
			raw = null;
		}
		if ( ! raw ) {
			flashLabel( btn, 'Nothing to save' );
			return;
		}
		try {
			window.localStorage.setItem( BASELINE_KEY, raw );
			flashLabel( btn, 'Saved!' );
		} catch ( err ) {
			flashLabel( btn, 'Save failed' );
		}
	}

	function flashLabel( btn, text ) {
		var original = btn.getAttribute( ORIGINAL_LABEL_ATTR );
		if ( original === null ) {
			original = btn.textContent;
			btn.setAttribute( ORIGINAL_LABEL_ATTR, original );
		}
		btn.textContent = text;
		window.clearTimeout( btn._imdgFlashTimeout );
		btn._imdgFlashTimeout = window.setTimeout( function () {
			btn.textContent = original;
		}, 1600 );
	}

	function ensureSaveButton( footer ) {
		if ( footer.querySelector( '[' + SAVE_BUTTON_ATTR + ']' ) ) {
			return;
		}
		footer.appendChild( makeSaveButton() );
	}

	function scanForFooters( root ) {
		if ( root.matches && root.matches( FOOTER_SELECTOR ) ) {
			ensureSaveButton( root );
		}
		if ( root.querySelectorAll ) {
			var footers = root.querySelectorAll( FOOTER_SELECTOR );
			for ( var i = 0; i < footers.length; i++ ) {
				ensureSaveButton( footers[ i ] );
			}
		}
	}

	new MutationObserver( function () {
		scanForFooters( document.body );
	} ).observe( document.body, { childList: true, subtree: true } );

	// Catch the case where the panel is already open when this script runs.
	scanForFooters( document.body );

	// ── "Reset to defaults" interception ─────────────────────────────────

	document.addEventListener( 'click', function ( e ) {
		var path = typeof e.composedPath === 'function' ? e.composedPath() : [ e.target ];
		var button = null;
		for ( var i = 0; i < path.length; i++ ) {
			var el = path[ i ];
			if ( el && el.tagName === 'OS-BUTTON' && el.closest && ! el.hasAttribute( SAVE_BUTTON_ATTR ) &&
				el.closest( FOOTER_SELECTOR ) ) {
				button = el;
				break;
			}
		}
		if ( ! button ) {
			return;
		}
		var label = ( button.textContent || '' ).trim().toLowerCase();
		if ( label !== 'reset to defaults' ) {
			return;
		}

		var baseline = null;
		try {
			baseline = window.localStorage.getItem( BASELINE_KEY );
		} catch ( err ) {
			baseline = null;
		}
		if ( ! baseline ) {
			// No custom baseline saved yet — let Desktop Mode's own Reset
			// run normally, untouched.
			return;
		}

		e.preventDefault();
		e.stopImmediatePropagation();

		try {
			window.localStorage.setItem( SETTINGS_KEY, baseline );
		} catch ( err ) {
			if ( typeof console !== 'undefined' ) {
				console.error( '[imdg] failed to apply saved OS Settings baseline', err );
			}
			return;
		}
		window.location.reload();
	}, true );

} )();
