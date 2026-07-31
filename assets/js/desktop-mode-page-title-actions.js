/**
 * Infinite Monkeys Dark Glass — Page title action button + notice wrapper.
 *
 * WordPress renders each `.page-title-action` button (Add New, Import,
 * Export, etc.) as a separate direct child of `.wrap`, immediately after
 * `.wp-heading-inline` — never grouped in a container of their own.
 * That's fine when a screen only registers one, but several plugins
 * (WooCommerce among them) add a second or third via the same
 * `page-title-action` convention, and with this theme's own floats
 * already removed from these buttons (see admin.css section 8), they had
 * nothing to arrange them into a row — each was just its own block-level
 * flow item, stacking vertically with no shared layout. Admin notices
 * (`.notice` divs from `do_action( 'admin_notices' )`) render the same
 * way, as further direct children of `.wrap` below the buttons.
 *
 * This groups every `.page-title-action` that is a DIRECT child of a
 * given `.wrap` into one new row container (`.imdg-page-title-actions`),
 * and every `.notice` that is a DIRECT child of that same `.wrap` into a
 * second, nested column container (`.imdg-page-title-notices`) placed
 * inside the row alongside the buttons — so a single flexbox rule in
 * admin.css can lay the buttons out in a row that wraps onto additional
 * lines when needed, with any notices stacked in a column that fills the
 * remaining width on that same row, instead of each element needing its
 * own positioning logic.
 *
 * Notices commonly arrive AFTER the initial page render (e.g. a bulk
 * action's AJAX success/error notice, or one injected by Heartbeat), so
 * — unlike a one-shot "already wrapped" guard — this re-scans on every
 * mutation and simply moves any new direct .wrap children it finds; once
 * moved, they're no longer direct children of .wrap and won't be found
 * again, which keeps this naturally idempotent without needing a flag.
 *
 * Runs on every admin screen, not just Desktop Mode's chromeless windows
 * — grouping is harmless (and correct) even with only one button and no
 * notices, since it just becomes a single-item flex row. Enqueued
 * unconditionally, like the other structural fixes in this plugin; no
 * Desktop Mode dependency, self-contained.
 */
( function () {
	'use strict';

	/**
	 * Finds (or creates) the shared row container for a given `.wrap`,
	 * and moves every `.page-title-action` / `.notice` that is currently a
	 * DIRECT child of that `.wrap` into it (buttons straight into the row,
	 * notices into a nested column inside the row). No-op if neither is
	 * present and no row exists yet.
	 *
	 * @param {Element} wrap
	 */
	function processWrap( wrap ) {
		var row = wrap.querySelector( ':scope > .imdg-page-title-actions' );

		var actions = [];
		var notices = [];
		var anchor = null;

		var child = wrap.firstElementChild;
		while ( child ) {
			if ( child !== row ) {
				if ( child.classList.contains( 'page-title-action' ) ) {
					actions.push( child );
					if ( ! anchor ) {
						anchor = child;
					}
				} else if ( child.classList.contains( 'notice' ) ) {
					notices.push( child );
					if ( ! anchor ) {
						anchor = child;
					}
				}
			}
			child = child.nextElementSibling;
		}

		if ( actions.length < 1 && notices.length < 1 ) {
			return;
		}

		if ( ! row ) {
			row = document.createElement( 'div' );
			row.className = 'imdg-page-title-actions';
			anchor.parentNode.insertBefore( row, anchor );
		}

		var noticesCol = row.querySelector( ':scope > .imdg-page-title-notices' );

		if ( notices.length ) {
			if ( ! noticesCol ) {
				noticesCol = document.createElement( 'div' );
				noticesCol.className = 'imdg-page-title-notices';
			}
			// Always the row's first child, so notices sit on the left
			// with buttons to their right, regardless of which arrived
			// first (insertBefore(node, node) — when noticesCol is
			// already the first child — is a safe no-op repositioning).
			row.insertBefore( noticesCol, row.firstElementChild );
			notices.forEach( function ( notice ) {
				noticesCol.appendChild( notice );
			} );
		}

		// Buttons always go after the notices column (or at the start of
		// the row if there's no notices column at all) — appendChild
		// naturally lands each new button after whatever's already there.
		actions.forEach( function ( action ) {
			row.appendChild( action );
		} );
	}

	/**
	 * Scans a root (document, or a node just added to the DOM) for `.wrap`
	 * elements and processes their page-title-action / notice children.
	 *
	 * @param {Document|Element} root
	 */
	function scan( root ) {
		if ( root.nodeType === 1 && root.classList && root.classList.contains( 'wrap' ) ) {
			processWrap( root );
		}
		if ( root.querySelectorAll ) {
			root.querySelectorAll( '.wrap' ).forEach( processWrap );
		}
	}

	scan( document );

	// Desktop Mode's native windows and chromeless iframes render their
	// `.wrap` asynchronously (the window opens, then its content loads
	// in), so a single run at script-load time isn't enough — watch for
	// it the same way every other structural fix in this plugin does.
	new MutationObserver( function ( mutations ) {
		for ( var m = 0; m < mutations.length; m++ ) {
			var mutation = mutations[ m ];

			// Case 1: a brand new subtree appeared somewhere (a whole
			// window's content loading in, for example). The added node
			// itself might BE a `.wrap`, or might CONTAIN one nested
			// inside it — scan() handles both.
			var addedNodes = mutation.addedNodes;
			for ( var n = 0; n < addedNodes.length; n++ ) {
				var node = addedNodes[ n ];
				if ( node.nodeType !== 1 ) {
					continue;
				}
				scan( node );
			}

			// Case 2: a new child (e.g. a bulk-action notice, or one
			// injected later by Heartbeat) landed inside a `.wrap` that
			// was already processed earlier. The added node itself isn't
			// a `.wrap` and doesn't contain one, so scan() above misses
			// this — mutation.target is the parent whose children just
			// changed, so walking up from there catches it.
			var target = mutation.target;
			if ( target && target.nodeType === 1 && target.closest ) {
				var ancestorWrap = target.closest( '.wrap' );
				if ( ancestorWrap ) {
					processWrap( ancestorWrap );
				}
			}
		}
	} ).observe( document.body, { childList: true, subtree: true } );
} )();
