<?php
/**
 * Adds back the "Plugin Editor" as a tab in OpenStation's Plugins
 * window.
 *
 * OpenStation's Plugins window used to be a hand-rolled server
 * template (includes/plugins-window/window.php), which is how the
 * original version of this file worked: it spliced a fourth tab
 * button + panel into that template's raw HTML via the
 * `openstation_plugins_window_template_html` filter.
 *
 * As of OpenStation 1.1.x the Plugins window was rebuilt as an
 * "App" (apps/plugins/plugins.os.php + plugins.min.js) — a
 * client-rendered window with its own first-class tab system
 * (`App::tab()`). The window no longer produces server-rendered
 * "template HTML" at all (confirmed live: the dispatch response's
 * `html` field is empty for this window), so the old filter never
 * fires any more and the tab silently vanished. The
 * `openstation_register_window_tab()` registry is *also* a dead end
 * here: it targets `openstation_native_window_registry()`, and while
 * every App does get mirrored into that registry (via
 * `openstation_apps_register_windows()`), its template callback is
 * just a static spinner div (`openstation_apps_render_template()`)
 * — the tab-wrapping markup that registry powers is never invoked
 * for an App window, so tabs registered that way never render.
 *
 * The supported seam for this now is `openstation_apps_loaded`: it
 * fires once every `.os.php` app file has been loaded and registered,
 * handing us the live `Registry` object. Pulling the already-
 * registered 'desktop-mode-plugins' `App` out of it and calling its
 * own public `->tab()` method mutates that *same* instance (PHP
 * objects are handles, not copies) — the tab then flows through
 * `App::manifest()` —> the client config —> the tab strip exactly
 * like the app's own built-in tabs, and `App::has_view()` /
 * `App::render()` (see `Runtime::dispatch()`) transparently serve its
 * content when the client requests `view: "editor"`.
 *
 * @since 1.1.5
 * @since 1.5.7 Rebuilt for the OpenStation "App" architecture — the
 *              filter-splice approach stopped working when the
 *              Plugins window moved off server-rendered templates.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the current user should see the Plugin Editor tab.
 *
 * Mirrors WP core's own gating for the classic Plugin Editor submenu:
 * it's a single-site-only feature, and `current_user_can( 'edit_plugins' )`
 * already resolves to false when `DISALLOW_FILE_EDIT` or
 * `DISALLOW_FILE_MODS` is defined (WP strips the capability at the
 * `map_meta_cap()` level), so no separate constant check is needed here.
 *
 * @since 1.1.5
 *
 * @return bool
 */
function imdg_plugins_window_editor_tab_visible() {
	return ! is_multisite() && current_user_can( 'edit_plugins' );
}

/**
 * Renders the Plugin Editor tab's body: a chromeless iframe pointing
 * at the real plugin-editor.php screen — the same technique
 * OpenStation itself uses for every classic-admin screen it embeds in
 * a window. Registered as the tab's `view` callback, so the App
 * framework calls it with `( State $state, Os $os )` and captures
 * whatever it echoes (see `App::render()` / `View::capture()`).
 *
 * @since 1.5.7
 *
 * @param mixed $state Unused — required by the `App::tab()` view signature.
 * @param mixed $os    Unused — required by the `App::tab()` view signature.
 * @return void
 */
function imdg_plugins_window_editor_tab_view( $state, $os ) {
	$src = add_query_arg( 'openstation_chromeless', '1', admin_url( 'plugin-editor.php' ) );
	?>
	<div class="desktop-mode-plugins__editor" data-desktop-mode-plugins-editor-host style="height:100%;display:flex;">
		<iframe
			src="<?php echo esc_url( $src ); ?>"
			title="<?php esc_attr_e( 'Plugin Editor', 'infinite-monkeys-dark-glass' ); ?>"
			style="flex:1;width:100%;height:100%;min-height:520px;border:0;background:transparent;"
		></iframe>
	</div>
	<?php
}

/**
 * Registers the Plugin Editor tab on the Plugins app, once every
 * `.os.php` app file has loaded.
 *
 * Bails out silently if the app isn't found (an OpenStation update
 * renaming or restructuring the app id) rather than fatal — the tab
 * just won't appear, same failure mode as the old filter-based splice
 * silently no-op'ing when its markup hooks weren't found.
 *
 * @since 1.5.7
 *
 * @param \OpenStation\App\Registry $registry The live app registry.
 * @return void
 */
function imdg_register_plugins_editor_tab( $registry ) {
	if ( ! imdg_plugins_window_editor_tab_visible() ) {
		return;
	}
	$app = $registry->get( 'desktop-mode-plugins' );
	if ( ! $app ) {
		return;
	}
	$app->tab(
		'editor',
		array(
			'label'    => __( 'Plugin Editor', 'infinite-monkeys-dark-glass' ),
			'view'     => 'imdg_plugins_window_editor_tab_view',
			'position' => 100,
		)
	);
}
add_action( 'openstation_apps_loaded', 'imdg_register_plugins_editor_tab' );

/**
 * Allows `<iframe>` inside native-window template HTML.
 *
 * Kept as a defensive measure from the original implementation —
 * App-rendered tab HTML travels back to the client as JSON (the
 * dispatch response's `html` field) rather than through
 * `desktop_mode_kses_native_window_template()`, so this filter may no
 * longer be load-bearing for THIS tab specifically, but other native
 * windows (and a future OpenStation version) may still route through
 * it, and `<iframe>` isn't on the default allowlist there either.
 * Cheap to keep, safe to keep.
 *
 * @since 1.1.5
 *
 * @param array $allowed wp_kses-shaped allowlist.
 * @return array
 */
function imdg_allow_iframe_in_native_windows( $allowed ) {
	if ( ! isset( $allowed['iframe'] ) ) {
		$allowed['iframe'] = array(
			'src'             => true,
			'title'           => true,
			'style'           => true,
			'class'           => true,
			'id'              => true,
			'name'            => true,
			'width'           => true,
			'height'          => true,
			'loading'         => true,
			'sandbox'         => true,
			'allow'           => true,
			'allowfullscreen' => true,
			'referrerpolicy'  => true,
		);
	}
	return $allowed;
}
add_filter( 'openstation_native_window_allowed_html', 'imdg_allow_iframe_in_native_windows' );

/**
 * Fixes CodeMirror's line/gutter overlap in the Plugin Editor tab.
 *
 * CodeMirror measures character and gutter widths at the moment it
 * initializes. The Editor tab's panel starts hidden (`display:none`)
 * until the user actually clicks the tab, so plugin-editor.php's own
 * CodeMirror boots inside the iframe while its container has zero
 * width — the gutter ends up sized for a 0px-wide editor and the two
 * never re-sync, so line content overlaps the line-number gutter once
 * the tab becomes visible.
 *
 * This adds a small script to the SHELL page that watches for the
 * panel's `hidden` attribute being removed and calls CodeMirror's own
 * `.refresh()` on the instance inside the iframe — safe to do since
 * the iframe is same-origin (a wp-admin/ URL on this site), so
 * `iframe.contentDocument` is directly reachable.
 *
 * @since 1.1.6
 */
function imdg_plugins_window_editor_tab_refresh_script() {
	if ( function_exists( 'openstation_is_chromeless_request' ) && openstation_is_chromeless_request() ) {
		// Don't print this on plugin-editor.php's own admin_footer when it
		// renders standalone inside the iframe — it has nothing to find.
		return;
	}
	if ( function_exists( 'openstation_is_enabled' ) && ! openstation_is_enabled() ) {
		return;
	}
	if ( ! imdg_plugins_window_editor_tab_visible() ) {
		return;
	}

	$js = <<<'JS'
( function () {
	function refreshEditorIframe( panel ) {
		var iframe = panel.querySelector( 'iframe' );
		if ( ! iframe || iframe.dataset.imdgRefreshed ) {
			return;
		}
		function doRefresh() {
			var doc;
			try {
				doc = iframe.contentDocument;
			} catch ( e ) {
				return;
			}
			if ( ! doc || 'complete' !== doc.readyState ) {
				return;
			}
			var wrappers = doc.querySelectorAll( '.CodeMirror' );
			if ( ! wrappers.length ) {
				return;
			}
			wrappers.forEach( function ( wrapper ) {
				if ( wrapper.CodeMirror ) {
					wrapper.CodeMirror.refresh();
				}
			} );
			iframe.dataset.imdgRefreshed = '1';
		}
		function scheduleRefresh() {
			window.requestAnimationFrame( function () {
				window.requestAnimationFrame( doRefresh );
			} );
		}
		if ( iframe.contentDocument && 'complete' === iframe.contentDocument.readyState ) {
			scheduleRefresh();
		} else {
			iframe.addEventListener( 'load', scheduleRefresh, { once: true } );
		}
	}

	function checkPanels() {
		// Broad selector on purpose: the exact wrapper element/attribute
		// the App framework uses for an inactive tab panel isn't part of
		// its documented contract, so we scan for any iframe whose
		// nearest hidden-attributed ancestor just became visible, rather
		// than hard-coding a selector that could silently stop matching
		// on the next OpenStation update.
		document.querySelectorAll( '[data-desktop-mode-plugins-editor-host] iframe' ).forEach( function ( iframe ) {
			var ancestor = iframe.closest( '[hidden]' );
			if ( ! ancestor ) {
				refreshEditorIframe( iframe.parentElement );
			}
		} );
	}

	window.addEventListener( 'message', function ( e ) {
		if ( e.origin !== window.location.origin ) {
			return;
		}
		if ( ! e.data || e.data.type !== 'os-iframe-admin-link' || ! e.data.url ) {
			return;
		}
		document.querySelectorAll( '[data-desktop-mode-plugins-editor-host] iframe' ).forEach( function ( ifr ) {
			if ( e.source === ifr.contentWindow ) {
				ifr.src = e.data.url;
			}
		} );
	} );

	var observer = new MutationObserver( checkPanels );
	observer.observe( document.documentElement, {
		attributes: true,
		attributeFilter: [ 'hidden' ],
		subtree: true,
	} );

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', checkPanels );
	} else {
		checkPanels();
	}
} )();
JS;

	wp_print_inline_script_tag( $js );
}
add_action( 'admin_footer', 'imdg_plugins_window_editor_tab_refresh_script', 100 );
