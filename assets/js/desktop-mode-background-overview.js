/**
 * Infinite Monkeys Dark Glass — Desktop background → Overview trigger.
 *
 * OpenStation's "Arrange Windows → Overview" admin-bar command
 * (window.wp.os.windowManager.enterOverview(), wired in Desktop Mode's
 * own admin-bar.js) has no equivalent gesture on the desktop itself.
 * This script adds one: a plain click on the empty desktop background
 * runs the same Overview command.
 *
 * #os-wallpaper is a purely visual layer (pointer-events: none), so it
 * never actually receives clicks — confirmed live via
 * document.elementFromPoint(). The real background hit-target is
 * #os-area itself: its icon (#os-files-layer), widget (#os-widgets),
 * and window layers all have pointer-events: none over their own empty
 * space, so a click that isn't on an icon/widget/window bubbles
 * through to #os-area as the target. That's what this checks against.
 *
 * A real click is told apart from the start of a marquee (rubber-band)
 * selection drag by movement distance between mousedown and click,
 * since Desktop Mode's own marquee drag threshold isn't exposed to
 * outside scripts. Movement past that distance is treated as a
 * drag-select and ignored here, so selecting icons by dragging over
 * the background still works normally.
 *
 * Never touches the Desktop Mode plugin's own files.
 */
( function () {
	'use strict';

	// Only runs in the parent shell (the desktop is active there); a
	// chromeless iframe's own document never has #os-wallpaper.
	if ( ! document.body.classList.contains( 'os-active' ) ) {
		return;
	}

	var DRAG_THRESHOLD_PX = 4;
	var downX = null;
	var downY = null;

	document.addEventListener( 'mousedown', function ( e ) {
		if ( e.button !== 0 ) {
			downX = downY = null;
			return;
		}
		downX = e.clientX;
		downY = e.clientY;
	}, true );

	document.addEventListener( 'click', function ( e ) {
		var area = document.getElementById( 'os-area' );
		// Strict target match — icons, widgets, and windows sitting on
		// the desktop are their own click targets (their layers only
		// pass clicks through over empty space), so this only fires for
		// clicks that land on the bare background itself.
		if ( ! area || e.target !== area ) {
			return;
		}

		// Ignore clicks that were actually the end of a marquee drag.
		if ( downX !== null && downY !== null ) {
			var dx = Math.abs( e.clientX - downX );
			var dy = Math.abs( e.clientY - downY );
			if ( dx > DRAG_THRESHOLD_PX || dy > DRAG_THRESHOLD_PX ) {
				return;
			}
		}

		var manager = window.wp && window.wp.os && window.wp.os.windowManager;
		if ( ! manager || typeof manager.enterOverview !== 'function' ) {
			return;
		}

		// Already in Overview — nothing to do.
		if ( area.classList.contains( 'os-area--overview' ) ) {
			return;
		}

		manager.enterOverview();
	} );
} )();
