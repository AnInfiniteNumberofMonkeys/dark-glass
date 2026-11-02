/**
 * Infinite Monkeys Dark Glass -- native Pages and Users windows default
 * to "Details table".
 *
 * OpenStation's native list apps keep their view choice purely in memory:
 * each app's state factory hard-codes the starting view every time the
 * window mounts, so they always open on their card layout. There is no
 * option, filter, or stored setting to change that.
 *
 *   Pages (assets/js/apps/pages.min.js)  -- view:"desk" ("Page directory")
 *   Users (assets/js/apps/users.min.js)  -- view:"people" ("People cards")
 *
 * This script watches for each app's view selector to appear and fires
 * OpenStation's own os-pick event on it with value "table" -- exactly
 * what a user's pick does, so OpenStation's handler updates its state
 * and repaints normally.
 *
 * It acts once per window instance (the select element survives
 * repaints, so it is marked after the first switch). Switching back to
 * the card layout by hand therefore sticks until that window is closed.
 * The Posts window shares the Pages app but is deliberately left alone
 * (the selector requires .desktop-mode-pages).
 *
 * Never touches the Desktop Mode plugin's own files.
 */
( function () {
	'use strict';

	// Native app windows render in the parent shell, not in iframes.
	if ( ! document.body || ! document.body.classList.contains( 'os-active' ) ) {
		return;
	}

	var MARK = 'data-imdg-view-default';
	var DEFAULT_VIEW = 'table';

	// Each target: a selector for the view <os-select>, plus an optional
	// test for selects that have no unique attribute of their own.
	var TARGETS = [
		{
			// Pages window's "Content view" picker.
			selector: '.os-app-list.desktop-mode-pages os-select[data-os-posts-view]:not([' + MARK + '])'
		},
		{
			// Users window's "Directory view" picker. It shares the
			// os-people__extra class with the role filter and sort pickers,
			// so it is identified by its people/table options.
			selector: 'os-select.os-people__extra:not([' + MARK + '])',
			test: function ( sel ) {
				return !! sel.querySelector( 'os-option[value="people"]' ) &&
					!! sel.querySelector( 'os-option[value="table"]' );
			}
		}
	];

	function apply() {
		for ( var t = 0; t < TARGETS.length; t++ ) {
			var target = TARGETS[ t ];
			var selects = document.querySelectorAll( target.selector );
			for ( var i = 0; i < selects.length; i++ ) {
				var sel = selects[ i ];
				if ( target.test && ! target.test( sel ) ) {
					continue;
				}
				sel.setAttribute( MARK, '' );
				if ( sel.getAttribute( 'value' ) === DEFAULT_VIEW ) {
					continue;
				}
				sel.dispatchEvent( new CustomEvent( 'os-pick', {
					detail: { value: DEFAULT_VIEW },
					bubbles: true,
					composed: true
				} ) );
			}
		}
	}

	var queued = false;
	new MutationObserver( function () {
		if ( queued ) {
			return;
		}
		queued = true;
		// Let the app's own render pass finish before switching views.
		queueMicrotask( function () {
			queued = false;
			apply();
		} );
	} ).observe( document.body, { childList: true, subtree: true } );

	apply();
} )();
