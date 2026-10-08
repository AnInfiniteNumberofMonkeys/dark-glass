/**
 * Infinite Monkeys Dark Glass -- Desktop right-click menu trim + "Close all windows".
 *
 * Right-clicking the empty desktop opens OpenStation's wallpaper context
 * menu. Its built-in items (New folder, Add widget, Sort by, ...) are
 * defined inside the shell bundle, and other OpenStation modules append
 * more (Upload files, Upload folder, New note) through the same JS
 * filter, `os.wallpaper-context-menu` (wp.hooks). This script hooks that
 * filter at a late priority so it sees the final list, then:
 *
 *   1. Keeps only: New URL, Show desktop, OpenStation Preferences.
 *   2. Adds "Close all windows" with the shortcut shown in its label.
 *
 * The close-all action reuses OpenStation's own implementation by
 * posting the same `os-window-close-all` message the shell already
 * listens for. That means the user's "Don't ask again" preference
 * (confirmCloseAllWindows) and the confirmation dialog behave exactly
 * as they do for the keyboard shortcut (Alt+Cmd/Ctrl+W).
 *
 * Never touches the OpenStation plugin's own files.
 */
( function () {
	'use strict';

	// Only runs in the parent shell, where the desktop lives.
	if ( ! document.body || ! document.body.classList.contains( 'os-active' ) ) {
		return;
	}

	var hooks = window.wp && window.wp.hooks;
	if ( ! hooks || typeof hooks.addFilter !== 'function' ) {
		return;
	}

	// Built-in item ids to keep (everything else is removed).
	var KEEP_IDS = [ 'new-url', 'show-desktop', 'os-settings' ];
	var CLOSE_ALL_ID = 'imdg/close-all-windows';

	function isMac() {
		var platform = ( navigator.userAgentData && navigator.userAgentData.platform ) || navigator.platform || '';
		return /mac|iphone|ipad|ipod/i.test( platform );
	}

	// Option+Command+W on macOS, Ctrl+Alt+W elsewhere.
	function shortcutLabel() {
		return isMac() ? '⌥⌘W' : 'Ctrl+Alt+W';
	}

	function closeAllWindows() {
		window.postMessage( { type: 'os-window-close-all' }, window.location.origin );
	}

	hooks.addFilter( 'os.wallpaper-context-menu', 'imdg/wallpaper-menu', function ( items ) {
		if ( ! Array.isArray( items ) ) {
			return items;
		}

		var kept = items.filter( function ( item ) {
			return item && KEEP_IDS.indexOf( item.id ) !== -1;
		} );

		kept.push( {
			id: CLOSE_ALL_ID,
			label: 'Close all windows (' + shortcutLabel() + ')',
			icon: 'dashicons-no-alt',
			// Between Show desktop (20) and OpenStation Preferences (30).
			sort: 25,
			onClick: closeAllWindows
		} );

		return kept;
	}, 999 );
} )();
