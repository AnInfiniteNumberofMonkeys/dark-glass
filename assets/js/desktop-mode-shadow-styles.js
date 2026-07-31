/**
 * Infinite Monkeys Dark Glass — Desktop Mode shadow-DOM style injection.
 *
 * Several Desktop Mode components use `attachShadow({ mode: 'open' })`
 * with no exposed ::part() hook for specific internal elements, so their
 * shadow roots are reachable from outside scripts via `element.shadowRoot`
 * but not from a normal page-level stylesheet. This script attaches a
 * stylesheet directly into each matching element's shadow root via
 * `adoptedStyleSheets`, which survives the component's own re-renders
 * (unlike an appended <style> element, which gets wiped out the next
 * time the component replaces its shadow content).
 *
 * IMPORTANT — nested shadow roots: some components (e.g. the expandable
 * plugin detail panel) render entirely INSIDE another component's shadow
 * root (confirmed live: a whole detail view, including nested <wpd-tabs>
 * and <wpd-button> instances, lives inside <wpd-table>'s own shadow
 * root). Neither a light-DOM ::part()/host-selector rule (admin.css) nor
 * a MutationObserver watching only document.body can reach that far —
 * ::part() only pierces one shadow boundary, and shadow DOM mutations
 * don't bubble up to an observer on an ancestor's light DOM. So this
 * script recursively walks into every shadow root it finds (scanRoot),
 * and attaches its own MutationObserver to EACH one (observeRoot) so
 * newly-added nested custom elements get caught too, at any depth.
 *
 * `!important` is used deliberately and safely throughout: each
 * stylesheet is fully contained inside that element's own shadow root,
 * so it can't leak out and affect anything else on the page — it only
 * needs to beat the plugin's own same-specificity rules inside that
 * same shadow root.
 *
 * Covers:
 *   - wpd-menu-item: window title-bar "actions" menu checkbox/checkmark/
 *     button hover color (no ::part() hooks on any of these).
 *   - wpd-tab: individual tab button inside <wpd-tabs> (e.g. "All posts
 *     / Categories / Tags", or the plugin detail panel's own tabs).
 *   - wpd-save-status: the little save/sync indicator dot (e.g. in the
 *     Edit user window's header).
 *   - wpd-segment: individual segment button inside a <wpd-segmented>
 *     group (e.g. "All / Published / Draft" status filter).
 *   - wpd-text-field / wpd-select / wpd-textarea: form field labels and
 *     the actual input/select/textarea, inside form windows like Edit
 *     user (none of these expose a ::part() hook for their label).
 *   - wpd-button: ALL variants (primary/secondary/ghost/danger), mainly
 *     to cover instances nested inside another component's shadow root
 *     where the admin.css ::part(button) rules can't reach — top-level
 *     instances get both, harmlessly redundant.
 *   - wpd-table: the alternating row stripe, per-cell background/border
 *     (no ::part() hook on individual <th>/<td> cells, and ::part()
 *     doesn't support :nth-child chaining), plus the plugin detail
 *     panel's own plain-element classes and action links, which live
 *     directly inside wpd-table's shadow root.
 *
 * Never touches the Desktop Mode plugin's own files.
 */
( function () {
	'use strict';

	// Only relevant inside the Desktop Mode shell.
	if ( ! document.body.classList.contains( 'desktop-mode-active' ) ) {
		return;
	}

	var TAG_CSS = {
		'WPD-MENU-ITEM': [
			'.wpd-menu-item__check {',
			'    border: 1.5px solid rgba(255,255,255,0.4) !important;',
			'}',
			':host([checked]) .wpd-menu-item__check::after {',
			'    left: 5px !important;',
			'}',
			'button:hover,',
			'button:focus-visible {',
			'    color: #fff !important;',
			'}'
		].join( '\n' ),

		'WPD-TAB': [
			'button {',
			'    color: var(--color-foreground) !important;',
			'    padding: 8px 16px !important;',
			'    border-radius: 50px !important;',
			'    line-height: 1 !important;',
			'    transition: background 150ms ease, color 150ms ease !important;',
			'}',
			'button:hover {',
			'    background: rgba(255,255,255,0.2) !important;',
			'}',
			':host([aria-selected="true"]) button {',
			'    background: var(--color-accent) !important;',
			'    color: var(--color-white) !important;',
			'}'
		].join( '\n' ),

		'WPD-SAVE-STATUS': [
			':host([phase="saved"]) .wpd-save-status__indicator {',
			'    background: var(--color-accent) !important;',
			'}'
		].join( '\n' ),

		'WPD-SEGMENT': [
			'button {',
			'    color: var(--color-foreground) !important;',
			'}',
			':host([aria-checked="true"]) button {',
			'    background: var(--color-accent) !important;',
			'    color: var(--color-white) !important;',
			'}'
		].join( '\n' ),

		'WPD-TEXT-FIELD': [
			'.wpd-text-field__label {',
			'    color: var(--color-muted-foreground) !important;',
			'}',
			'.wpd-text-field__input {',
			'    border: 2px solid var(--color-border) !important;',
			'    border-radius: 5px !important;',
			'    color: var(--color-foreground) !important;',
			'}',
			'.wpd-text-field__input:hover {',
			'    border-color: var(--color-primary) !important;',
			'}',
			'.wpd-text-field__input:focus {',
			'    border-color: var(--color-primary) !important;',
			'    box-shadow: none !important;',
			'}'
		].join( '\n' ),

		'WPD-SELECT': [
			'.wpd-select__label {',
			'    color: var(--color-muted-foreground) !important;',
			'}',
			'select {',
			'    border: 1px solid var(--color-border) !important;',
			'    border-radius: 5px !important;',
			'    color: var(--color-foreground) !important;',
			'    cursor: pointer !important;',
			'}',
			'select:hover {',
			'    border-color: var(--color-primary) !important;',
			'}'
		].join( '\n' ),

		'WPD-TEXTAREA': [
			'.wpd-textarea__label {',
			'    color: var(--color-muted-foreground) !important;',
			'}',
			'textarea {',
			'    border: 2px solid var(--color-border) !important;',
			'    border-radius: 5px !important;',
			'    color: var(--color-foreground) !important;',
			'}',
			'textarea:hover {',
			'    border-color: var(--color-primary) !important;',
			'}',
			'textarea:focus {',
			'    border-color: var(--color-primary) !important;',
			'    box-shadow: none !important;',
			'}'
		].join( '\n' ),

		'WPD-BUTTON': [
			'button {',
			'    border-radius: 5px !important;',
			'    transition: var(--transition-common) !important;',
			'}',
			':host([variant="primary"]) button {',
			'    background: var(--color-primary) !important;',
			'    color: var(--color-white) !important;',
			'}',
			':host([variant="primary"]) button:hover {',
			'    background: var(--color-accent) !important;',
			'}',
			':host([variant="secondary"]) button {',
			'    background: var(--color-diffused) !important;',
			'    color: var(--color-white) !important;',
			'}',
			':host([variant="ghost"]) button {',
			'    background: transparent !important;',
			'    color: var(--color-foreground) !important;',
			'    border: 1px solid var(--color-border) !important;',
			'}',
			':host([variant="ghost"]) button:hover {',
			'    background: var(--color-background-hover) !important;',
			'    color: var(--color-white) !important;',
			'}',
			':host([variant="danger"]) button {',
			'    background: transparent !important;',
			'    color: var(--color-error) !important;',
			'    border: 1px solid var(--color-error) !important;',
			'}',
			':host([variant="danger"]) button:hover {',
			'    background: var(--color-error) !important;',
			'    color: var(--color-white) !important;',
			'}'
		].join( '\n' ),

		'WPD-CHIP': [
			'.wpd-chip {',
			'    background: var(--color-muted) !important;',
			'    color: var(--color-foreground) !important;',
			'}'
		].join( '\n' ),

		'WPD-CARD': [
			':host([compact]) {',
			'    background: var(--color-background) !important;',
			'}',
			/*
			 * The plugin detail panel's changelog entries are individually
			 * a <wpd-card class="desktop-mode-plugins__detail-changelog-entry">
			 * — a SEPARATE custom element with its own shadow root (slots
			 * only, confirmed live), not a plain div inside wpd-table's shadow
			 * root. So this has to be styled here via :host(), same pattern
			 * as :host([compact]) above, rather than as a WPD-TABLE descendant
			 * selector (which can't reach across the shadow boundary).
			 */
			':host(.desktop-mode-plugins__detail-changelog-entry) {',
			'    background: var(--color-background) !important;',
			'    border-color: var(--color-border) !important;',
			'}'
		].join( '\n' ),

		'WPD-RATING-SUMMARY': [
			'.summary-card {',
			'    background: var(--color-background) !important;',
			'}',
			'.row__track {',
			'    background: var(--color-muted) !important;',
			'}',
			'.row__fill {',
			'    background: var(--_fill) !important;',
			'}'
		].join( '\n' ),

		'WPD-CRUMB-CHAIN': [
			/*
			 * .wpd-crumb is NOT its own custom element — confirmed live by
			 * instantiating <wpd-crumb-chain> and inspecting its shadow
			 * root: it renders plain <span class="wpd-crumb"> children
			 * (with --first/--middle/--last modifiers) directly inside
			 * this shadow root, alongside .wpd-crumb__label. So this is
			 * styled here as a plain class, same pattern as the plugin
			 * detail panel's plain-element classes inside WPD-TABLE below.
			 */
			'.wpd-crumb {',
			'    background: var(--color-subtle) !important;',
			'    color: var(--color-foreground) !important;',
			'}'
		].join( '\n' ),

		'WPD-TABLE': [
			'th,',
			'td {',
			'    background: none !important;',
			'}',
			'thead th {',
			'    background: var(--color-dark-background) !important;',
			'}',
			'tr[part~="row"] td {',
			'    border-bottom: 1px solid var(--color-border-muted) !important;',
			'}',
			'tr[part~="row"]:nth-child(even) {',
			'    background: var(--color-background-row-alt) !important;',
			'}',
			'tr[part~="row"]:hover {',
			'    background: var(--color-background-hover) !important;',
			'}',
			/*
			 * The expandable plugin detail panel renders directly inside
			 * this same shadow root (confirmed live) — plain elements,
			 * not a separate component, so they're styled here rather
			 * than needing their own TAG_CSS entry. .wpd-chip itself
			 * turned out to be a SEPARATE nested custom element
			 * (<wpd-chip>) with its own shadow root — see that entry
			 * above instead; confirmed live via getRootNode().host.
			 */
			'.desktop-mode-plugins__detail-hero,',
			'.desktop-mode-plugins__detail-tabs-wrap {',
			'    background: none !important;',
			'}',
			'.desktop-mode-plugins__detail-byline,',
			'.desktop-mode-plugins__detail-title {',
			'    color: var(--color-foreground) !important;',
			'}',
			'.desktop-mode-plugins__detail-faq-item {',
			'    background: var(--color-background) !important;',
			'    border-color: var(--color-border) !important;',
			'}',
			'.desktop-mode-plugins__detail-faq-q:hover {',
			'    background: var(--color-white-trans-5) !important;',
			'}',
			'a[data-wp-action] {',
			'    color: var(--color-primary) !important;',
			'}',
			/*
			 * wpd-table is shared between Plugins and Users, so these two
			 * are scoped with :host-context() to only apply within the
			 * Users window — confirmed :host-context() support live
			 * before relying on it. Role label and action-button cells
			 * have no distinguishing class, only column position (Role is
			 * the 4th column, Actions is the last, per the live header row).
			 */
			':host-context(.desktop-mode-users) td:nth-child(4) > span {',
			'    background: var(--color-diffused) !important;',
			'    color: var(--color-foreground) !important;',
			'    padding: 2px 8px !important;',
			'    border-radius: 4px !important;',
			'}',
			':host-context(.desktop-mode-users) td:nth-child(4) > span span {',
			'    background: none !important;',
			'    padding: 0 !important;',
			'}',
			':host-context(.desktop-mode-users) td:last-child button {',
			'    background: var(--color-muted) !important;',
			'    border-color: var(--color-border) !important;',
			'}',
			'.filter-input,',
			'.filter-select {',
			'    background: var(--color-input-bg) !important;',
			'    color: var(--color-input) !important;',
			'    border-color: var(--color-border) !important;',
			'}',
			/*
			 * The plugin detail panel's own <wpd-tabs> (a DIRECT shadow
			 * child of wpd-table, so styleable as a host from here, same
			 * as everything else in this entry) ships a bottom border our
			 * light-DOM wpd-tabs rule in this file can't reach, since it's
			 * nested inside wpd-table's own shadow root.
			 */
			'wpd-tabs {',
			'    border-bottom: none !important;',
			'}',
			/*
			 * The cursor reset for native windows (admin.css) is light-DOM
			 * only, so it can't selectively re-enable pointer for just the
			 * clickable bits inside this shadow root — the row expander
			 * and action links/buttons.
			 */
			'.expander,',
			'a[data-wp-action],',
			'button {',
			'    cursor: pointer !important;',
			'}',
			/*
			 * Expanding "excerpt" panel (Posts/Pages) — first direct child
			 * of .subtable-inner ships its own opaque light-gray background,
			 * and its own nested divs (the "Excerpt" label and the actual
			 * text) each have their own explicit dark color that beats
			 * simple inheritance, so both need the broad descendant selector.
			 */
			'.subtable-inner > div {',
			'    background: var(--color-background-20) !important;',
			'    border-radius: var(--radius-md) !important;',
			'}',
			'.subtable-inner > div,',
			'.subtable-inner > div * {',
			'    color: var(--color-foreground) !important;',
			'}',
			/*
			 * Page-type tags ("Posts page", "Front page", etc.) use
			 * dashicons whose glyphs never rendered inside this shadow root
			 * at all — confirmed live: ::before content was literally "none"
			 * here even though the SAME icon class works fine in the
			 * light-DOM admin sidebar. Rather than hand-picking codepoints
			 * one icon at a time (fragile — the previous version of this
			 * file did exactly that and still missed icons elsewhere), the
			 * actual dashicons ruleset is now cloned wholesale into every
			 * shadow root this script touches — see ensureDashiconsSheet()
			 * below. This block now only needs the color treatment, not the
			 * icon rendering itself.
			 */
			'span:has(> .dashicons-admin-post),',
			'span:has(> .dashicons-admin-post) * {',
			'    color: #713dde !important;',
			'}',
			'span:has(> .dashicons-admin-home),',
			'span:has(> .dashicons-admin-home) * {',
			'    color: #3b9cdf !important;',
			'}',
			'span:has(> .dashicons):not(:has(> .dashicons-admin-post)):not(:has(> .dashicons-admin-home)) {',
			'    filter: brightness(2) saturate(1.2);',
			'}'
		].join( '\n' )
	};

	var TAG_NAMES = Object.keys( TAG_CSS );
	var TAG_SELECTOR = TAG_NAMES.map( function ( t ) {
		return t.toLowerCase();
	} ).join( ',' );

	// Built once per tag and reused across every instance when
	// Constructable Stylesheets are supported.
	var sharedSheets = {};
	var supportsAdopted = 'adoptedStyleSheets' in Document.prototype && typeof CSSStyleSheet === 'function';
	if ( supportsAdopted ) {
		TAG_NAMES.forEach( function ( tag ) {
			try {
				var sheet = new CSSStyleSheet();
				sheet.replaceSync( TAG_CSS[ tag ] );
				sharedSheets[ tag ] = sheet;
			} catch ( e ) {
				sharedSheets[ tag ] = null;
			}
		} );
	}

	/**
	 * Injects the appropriate override stylesheet into a single
	 * element's shadow root, guarding against double-injection.
	 * Prefers `adoptedStyleSheets` (survives the component's own
	 * re-renders); falls back to an appended <style> element on older
	 * browsers, re-checking on every scan pass since that fallback CAN
	 * be wiped out by a re-render.
	 *
	 * @param {Element} el
	 */
	function injectInto( el ) {
		if ( ! el.shadowRoot ) {
			return;
		}
		var tag = el.tagName;
		var cssText = TAG_CSS[ tag ];
		if ( ! cssText ) {
			return;
		}

		var sheet = sharedSheets[ tag ];
		if ( sheet ) {
			if ( el.shadowRoot.adoptedStyleSheets.indexOf( sheet ) === -1 ) {
				el.shadowRoot.adoptedStyleSheets = el.shadowRoot.adoptedStyleSheets.concat( sheet );
			}
			return;
		}

		// Fallback path for browsers without Constructable Stylesheets.
		if ( el.shadowRoot.querySelector( 'style[data-imdg]' ) ) {
			return;
		}
		var style = document.createElement( 'style' );
		style.setAttribute( 'data-imdg', '' );
		style.textContent = cssText;
		el.shadowRoot.appendChild( style );
	}

	var observedRoots = ( typeof WeakSet === 'function' ) ? new WeakSet() : null;

	/**
	 * Builds (once) a stylesheet containing every dashicons-related rule
	 * currently loaded on the page, by scanning document.styleSheets and
	 * filtering for selectors mentioning "dashicons" — rather than
	 * cloning an entire bundled stylesheet wholesale (confirmed live:
	 * dashicons is bundled inside WP's combined load-styles.php alongside
	 * ~4500 unrelated rules) or hand-picking individual icon codepoints
	 * one at a time (fragile — easy to miss icons elsewhere). This runs
	 * once and the resulting sheet is reused for every shadow root.
	 *
	 * @return {CSSStyleSheet|false} The built sheet, or false if it
	 *                                couldn't be built (e.g. no
	 *                                Constructable Stylesheets support, or
	 *                                no accessible dashicons rules found).
	 */
	var dashiconsSheet;
	function buildDashiconsSheet() {
		if ( dashiconsSheet !== undefined ) {
			return dashiconsSheet;
		}
		if ( ! supportsAdopted ) {
			dashiconsSheet = false;
			return dashiconsSheet;
		}
		var cssText = '';
		for ( var i = 0; i < document.styleSheets.length; i++ ) {
			var rules;
			try {
				rules = document.styleSheets[ i ].cssRules;
			} catch ( e ) {
				continue; // Cross-origin stylesheet; inaccessible, skip.
			}
			if ( ! rules ) {
				continue;
			}
			for ( var j = 0; j < rules.length; j++ ) {
				var rule = rules[ j ];
				if ( rule.selectorText && rule.selectorText.indexOf( 'dashicons' ) !== -1 ) {
					cssText += rule.cssText + '\n';
				}
			}
		}
		if ( ! cssText ) {
			dashiconsSheet = false;
			return dashiconsSheet;
		}
		try {
			var sheet = new CSSStyleSheet();
			sheet.replaceSync( cssText );
			dashiconsSheet = sheet;
		} catch ( e ) {
			dashiconsSheet = false;
		}
		return dashiconsSheet;
	}

	/**
	 * Attaches the shared dashicons sheet to a shadow root, if it isn't
	 * already there. Called for EVERY shadow root this script touches
	 * (not just ones matching a known TAG_CSS entry), so any dashicon
	 * anywhere renders correctly, not just ones we've explicitly noticed.
	 *
	 * @param {ShadowRoot} shadowRoot
	 */
	function ensureDashiconsSheet( shadowRoot ) {
		var sheet = buildDashiconsSheet();
		if ( ! sheet ) {
			return;
		}
		if ( shadowRoot.adoptedStyleSheets.indexOf( sheet ) === -1 ) {
			shadowRoot.adoptedStyleSheets = shadowRoot.adoptedStyleSheets.concat( sheet );
		}
	}

	/**
	 * Processes a single element: injects styles if it's a known tag,
	 * and — critically — recurses into its shadow root if it has one,
	 * both scanning what's already there and setting up an observer for
	 * anything added later. This is what lets the script reach
	 * components nested inside ANOTHER component's shadow root.
	 *
	 * @param {Element} el
	 */
	function processElement( el ) {
		if ( TAG_CSS[ el.tagName ] ) {
			injectInto( el );
		}
		if ( el.shadowRoot ) {
			ensureDashiconsSheet( el.shadowRoot );
			scanRoot( el.shadowRoot );
			observeRoot( el.shadowRoot );
		}
	}

	/**
	 * Scans every element currently inside a root (document body or a
	 * shadow root) for known tags / nested shadow roots.
	 *
	 * @param {Node} root
	 */
	function scanRoot( root ) {
		var all = root.querySelectorAll( '*' );
		for ( var i = 0; i < all.length; i++ ) {
			processElement( all[ i ] );
		}
	}

	/**
	 * Attaches a MutationObserver to a root (document body or a shadow
	 * root) so elements added later — at any depth, including further
	 * nested shadow roots — get processed too. Safe to call more than
	 * once per root; only attaches once.
	 *
	 * @param {Node} root
	 */
	function observeRoot( root ) {
		if ( observedRoots ) {
			if ( observedRoots.has( root ) ) {
				return;
			}
			observedRoots.add( root );
		}

		var observer = new MutationObserver( function ( mutations ) {
			for ( var m = 0; m < mutations.length; m++ ) {
				var addedNodes = mutations[ m ].addedNodes;
				for ( var n = 0; n < addedNodes.length; n++ ) {
					var node = addedNodes[ n ];
					if ( node.nodeType !== 1 ) {
						continue;
					}
					processElement( node );
					if ( node.querySelectorAll ) {
						var descendants = node.querySelectorAll( '*' );
						for ( var d = 0; d < descendants.length; d++ ) {
							processElement( descendants[ d ] );
						}
					}
				}
			}
		} );

		observer.observe( root, { childList: true, subtree: true } );
	}

	scanRoot( document.body );
	observeRoot( document.body );

	/**
	 * "Admin colour scheme" section in the Edit user window has no
	 * distinguishing class — the only way to find it is by matching the
	 * text content of its heading span, then hiding the whole containing
	 * div (a direct child of <wpd-form>, confirmed live via parentElement
	 * chain walk). Runs on the same schedule as the shadow-style scan
	 * (once now, then on every mutation) since it needs the same
	 * "appears asynchronously when a window opens" handling.
	 */
	function hideColorSchemeSection( root ) {
		if ( root.nodeType !== 1 && root.nodeType !== 11 ) {
			return;
		}
		var spans = root.querySelectorAll ? root.querySelectorAll( 'span' ) : [];
		for ( var i = 0; i < spans.length; i++ ) {
			var text = spans[ i ].textContent.trim().toLowerCase();
			if ( text === 'admin colour scheme' || text === 'admin color scheme' ) {
				var container = spans[ i ].parentElement;
				if ( container && container.style.display !== 'none' ) {
					container.style.display = 'none';
				}
			}
		}
	}

	hideColorSchemeSection( document.body );
	new MutationObserver( function ( mutations ) {
		for ( var m = 0; m < mutations.length; m++ ) {
			var addedNodes = mutations[ m ].addedNodes;
			for ( var n = 0; n < addedNodes.length; n++ ) {
				hideColorSchemeSection( addedNodes[ n ] );
			}
		}
	} ).observe( document.body, { childList: true, subtree: true } );
} )();
