/**
 * Advanced Themer: open components with their instance on double-click.
 *
 * Advanced Themer's structure panel tweak "Double-click to edit component"
 * (db-click-edit-component) opens a component by calling
 * $_setActiveComponent( componentId ). Bricks' own Edit button passes the
 * instance element instead, which also sets $_state.componentInstanceId.
 *
 * Without an instance ID, the canvas renders the component in its isolated
 * preview area. That area deep-watches the component's elements and, 400 ms
 * after every change, calls $_rerenderControls(), which gives every panel
 * control a new key. Vue rebuilds every control, including the field being
 * typed in, so focus is lost after each keystroke or two.
 *
 * This wraps $_setActiveComponent. When it receives a component ID during a
 * double-click on that component's instance in the structure panel, it passes
 * the instance instead, the same way Bricks' Edit button does. Every other
 * call goes through unchanged.
 *
 * Tested with Bricks 2.4.3 and Advanced Themer 3.5.
 * Loaded in the builder main window only.
 */
( function () {
	'use strict';

	var PATCH_FLAG = '__imdgAtComponentEditFix';
	var dblClickedId = null;

	// Capture-phase listeners on document run before Advanced Themer's own
	// capture listener on #bricks-structure, so the id is set in time.
	document.addEventListener( 'dblclick', function ( e ) {
		var row = e.target.closest ? e.target.closest( '#bricks-structure .element[data-id]' ) : null;
		if ( ! row ) {
			return;
		}
		dblClickedId = row.dataset.id;
		setTimeout( function () {
			dblClickedId = null;
		}, 0 );
	}, true );

	function getProps() {
		var body = document.querySelector( '.brx-body' );
		return body && body.__vue_app__ ? body.__vue_app__.config.globalProperties : null;
	}

	// Returns true once patched (or already patched).
	function patch() {
		var props = getProps();
		if ( ! props ) {
			return false;
		}
		if ( props[ PATCH_FLAG ] ) {
			return true;
		}
		if ( typeof props.$_setActiveComponent !== 'function' || typeof props.$_getDynamicElementById !== 'function' ) {
			return false;
		}

		var original = props.$_setActiveComponent;

		props.$_setActiveComponent = function ( target ) {
			if ( typeof target === 'string' && dblClickedId && ! props.$_state.activeComponent ) {
				var instance = props.$_getDynamicElementById( dblClickedId );
				if ( instance && instance.cid === target ) {
					return original.call( this, instance );
				}
			}
			return original.apply( this, arguments );
		};

		props[ PATCH_FLAG ] = true;
		return true;
	}

	// The builder's Vue app mounts after this script runs, so poll briefly.
	if ( ! patch() ) {
		var timer = setInterval( function () {
			if ( patch() ) {
				clearInterval( timer );
			}
		}, 250 );
		setTimeout( function () {
			clearInterval( timer );
		}, 60000 );
	}
} )();
