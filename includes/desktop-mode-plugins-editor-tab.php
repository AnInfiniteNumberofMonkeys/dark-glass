<?php
/**
 * Adds back the "Plugin File Editor" as a tab in Desktop Mode's native
 * Plugins window.
 *
 * Desktop Mode's native Plugins window (includes/plugins-window/window.php)
 * hand-rolls its own tab strip (Installed / Add Plugin / Desktop Mode
 * plugins) directly in its template callback, instead of deriving it from
 * WordPress's `$submenu['plugins.php']` the way the (iframe-based)
 * Appearance window does. That's why the classic "Plugin Editor" submenu
 * item disappears once the native Plugins window is enabled — it's never
 * one of the three hardcoded tabs.
 *
 * This splices a fourth tab directly into that hand-rolled markup via the
 * `desktop_mode_plugins_window_template_html` filter Desktop Mode already
 * exposes for exactly this purpose — one flat `<wpd-tabs>` strip, no nested
 * wrapper. (An earlier version of this file used
 * `desktop_mode_register_window_tab()` instead, which is the right tool for
 * windows that don't already roll their own tabs — but on a window that
 * does, it wraps the whole existing template as a second, nested "main" tab
 * and produces two stacked tab bars. This filter-based approach avoids
 * that.)
 *
 * The new tab's content is the real plugin-editor.php screen, loaded in a
 * chromeless iframe — the same technique Desktop Mode itself uses for
 * every classic-admin screen it embeds in a window.
 *
 * @since 1.1.5
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
 * Builds the `<wpd-tabpanel>` markup for the Plugin Editor tab — a
 * chromeless iframe pointing at the real plugin-editor.php screen.
 *
 * @since 1.1.5
 *
 * @return string
 */
function imdg_plugins_window_editor_tab_panel_html() {
	$src = add_query_arg( 'desktop_mode_chromeless', '1', admin_url( 'plugin-editor.php' ) );

	ob_start();
	?>
	<wpd-tabpanel for="editor" class="desktop-mode-plugins__panel">
		<div class="desktop-mode-plugins__editor" data-desktop-mode-plugins-editor-host style="height:100%;display:flex;">
			<iframe
				src="<?php echo esc_url( $src ); ?>"
				title="<?php esc_attr_e( 'Plugin Editor', 'infinite-monkeys-dark-glass' ); ?>"
				style="flex:1;width:100%;height:100%;min-height:520px;border:0;background:transparent;"
			></iframe>
		</div>
	</wpd-tabpanel>
	<?php
	return (string) ob_get_clean();
}

/**
 * Filters the native Plugins window's template HTML to splice in a fourth
 * "Plugin Editor" tab button + panel, alongside the existing hand-rolled
 * Installed / Add Plugin / Desktop Mode plugins tabs.
 *
 * Bails out untouched if the expected markup hooks
 * (`data-desktop-mode-plugins-tabs`, `<wpd-flyout`) aren't found, rather
 * than risk inserting into a shape a future Desktop Mode update has
 * changed.
 *
 * @since 1.1.5
 *
 * @param string $html Default template HTML.
 * @return string
 */
function imdg_add_editor_tab_to_plugins_window( $html ) {
	if ( ! imdg_plugins_window_editor_tab_visible() ) {
		return $html;
	}
	if ( false === strpos( $html, 'data-desktop-mode-plugins-tabs' ) || false === strpos( $html, '</wpd-tabs>' ) ) {
		return $html;
	}

	// Add the tab button as the last child of the existing <wpd-tabs> strip.
	$tab_button = '<wpd-tab value="editor">' . esc_html__( 'Plugin Editor', 'infinite-monkeys-dark-glass' ) . '</wpd-tab>';
	$html       = str_replace( '</wpd-tabs>', $tab_button . '</wpd-tabs>', $html );

	// Add the matching panel right before the detail flyout (or at the end
	// of the markup if the flyout hook isn't there for some reason).
	$panel = imdg_plugins_window_editor_tab_panel_html();
	if ( false !== strpos( $html, '<wpd-flyout' ) ) {
		$html = str_replace( '<wpd-flyout', $panel . '<wpd-flyout', $html );
	} else {
		$html .= $panel;
	}

	return $html;
}
add_filter( 'desktop_mode_plugins_window_template_html', 'imdg_add_editor_tab_to_plugins_window' );

/**
 * Allows `<iframe>` inside native-window template HTML.
 *
 * `desktop_mode_kses_native_window_template()` runs every native window's
 * rendered template through `wp_kses()` with a fixed allowlist of tags
 * (built-in HTML elements + every `<wpd-*>` component) — `<iframe>` isn't
 * on it, so the Editor tab's chromeless iframe was being silently stripped
 * before it ever reached the DOM even though the tab itself switched
 * correctly. Desktop Mode exposes this filter for exactly this situation
 * rather than requiring every plugin embedding an iframe to reimplement
 * kses from scratch.
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
add_filter( 'desktop_mode_native_window_allowed_html', 'imdg_allow_iframe_in_native_windows' );

/**
 * Fixes CodeMirror's line/gutter overlap in the Plugin Editor tab.
 *
 * CodeMirror measures character and gutter widths at the moment it
 * initializes. The Editor tab's `<wpd-tabpanel>` starts `hidden`
 * (`display:none`) until the user actually clicks the tab, so
 * plugin-editor.php's own CodeMirror boots inside the iframe while its
 * container has zero width — the gutter ends up sized for a 0px-wide
 * editor and the two never re-sync, so line content overlaps the
 * line-number gutter once the tab becomes visible.
 *
 * This adds a small script to the SHELL page (not the sanitized native-
 * window template, which can't carry a `<script>` tag through kses) that
 * watches for the panel's `hidden` attribute being removed and calls
 * CodeMirror's own `.refresh()` on the instance inside the iframe — safe to
 * do since the iframe is same-origin (a `wp-admin/` URL on this site), so
 * `iframe.contentDocument` is directly reachable.
 *
 * @since 1.1.6
 */
function imdg_plugins_window_editor_tab_refresh_script() {
	if ( function_exists( 'desktop_mode_is_chromeless_request' ) && desktop_mode_is_chromeless_request() ) {
		// Don't print this on plugin-editor.php's own admin_footer when it
		// renders standalone inside the iframe — it has nothing to find.
		return;
	}
	if ( function_exists( 'desktop_mode_is_enabled' ) && ! desktop_mode_is_enabled() ) {
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
		// Two rAFs (rather than calling synchronously) let the browser
		// finish laying out the now-visible panel before CodeMirror
		// re-measures it — a single frame is sometimes not enough.
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
		document.querySelectorAll( 'wpd-tabpanel[for="editor"]' ).forEach( function ( panel ) {
			if ( ! panel.hasAttribute( 'hidden' ) ) {
				refreshEditorIframe( panel );
			}
		} );
	}

	// Desktop Mode's own chromeless-bridge script intercepts every click on
	// an internal /wp-admin/ link inside ANY chromeless iframe on the page
	// (regardless of whose iframe it is) and hands the URL to the parent
	// via postMessage instead of letting the iframe navigate itself — the
	// shell then decides whether to open a new window or drive an existing
	// one. That's why clicking a file in the "Plugin Files" sidebar did
	// nothing: our Editor iframe isn't one of Desktop Mode's own registered
	// windows, so the shell has no idea what to do with that message and
	// silently drops it. Since we're effectively standing in as our own
	// "parent" for this iframe, we listen for that same message ourselves
	// and drive the iframe's navigation directly — the URL Desktop Mode
	// hands back already carries the desktop_mode_chromeless=1 flag, so
	// chromeless styling is preserved automatically.
	window.addEventListener( 'message', function ( e ) {
		if ( e.origin !== window.location.origin ) {
			return;
		}
		if ( ! e.data || e.data.type !== 'desktop-mode-iframe-admin-link' || ! e.data.url ) {
			return;
		}
		document.querySelectorAll( 'wpd-tabpanel[for="editor"] iframe' ).forEach( function ( ifr ) {
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
