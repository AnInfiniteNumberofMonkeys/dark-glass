<?php
/**
 * Block editor asset trimming.
 *
 * Several plugins load large asset bundles into the plain WordPress block
 * editor screen unconditionally -- on every post/page edit, regardless of
 * post type and regardless of whether the loaded features are actually used
 * on that post. None of this affects the real Bricks builder (each block
 * below bails out via bricks_is_builder() where relevant), and none of it
 * touches the frontend (everything runs only in wp-admin / the block editor).
 *
 * IMPORTANT: every dequeue in this file runs at priority PHP_INT_MAX on
 * THREE hooks -- admin_enqueue_scripts, enqueue_block_assets, and
 * enqueue_block_editor_assets -- because different plugins enqueue their
 * unwanted assets via different hooks and at wildly different priorities
 * (observed: default 10, 20, 30, even 9999). Running last on all three,
 * rather than guessing one hook/priority per plugin, is what makes this
 * reliable across plugin updates. Handles are both dequeued AND
 * deregistered, in case anything else references them as a dependency.
 *
 * @package Infinite_Monkeys_Dark_Glass
 */

defined( 'ABSPATH' ) || exit;

// ── WS Form: cancel its "load every field module, just in case" bundle ──────
// WS Form's own enqueue_block_assets() fires an internal action on every
// wp-admin screen that force-enables every field-type JS/CSS module via its
// own filters (priority 99999). We re-force those same filters back to false
// at a higher priority (100000), using WS Form's documented filter API --
// not internal handle names, which are an implementation detail that could
// change on update. Registered at priority 5 so it's in place before WS
// Form's own callback (default priority 10) runs. Skipped inside the real
// Bricks builder, where WS Form's own Bricks integration deliberately needs
// this bundle for live element previews.

// WS Form's Bricks integration (includes/third-party/bricks/bricks.php) is
// required on `init` at priority 11 and immediately fires its own enqueue
// call synchronously -- NOT on enqueue_block_assets, which is far too late
// to matter (confirmed live: the old enqueue_block_assets-based filters had
// zero effect in the Bricks builder because WS Form had already finished
// enqueueing by the time they registered). Registering on init at priority
// 10 -- one tick before WS Form's own require -- is what actually works.
//
// These are the SAME global filters a real front-end form uses to decide
// what it needs (e.g. an actual captcha field), so this only ever runs in
// is_admin() or bricks_is_builder() contexts -- never on a genuine visitor
// page load, or real forms on the live site would break.
//
// JS field-type modules: forced off in the plain block editor ONLY. WS Form
// forms render via JS hydration (a JSON config gets read client-side to
// build the actual field markup), so blocking JS in the Bricks canvas broke
// rendering entirely, not just interactivity -- reverted after confirming
// live. Left at WS Form's own default (on) inside the Bricks builder.
//
// CSS field-type styling: forced off in the plain block editor (nothing
// Bricks-rendered to visually match there), left alone inside the Bricks
// builder so forms still look correct while editing.

add_action( 'init', function () {
	$in_bricks_builder = function_exists( 'bricks_is_builder' ) && bricks_is_builder();
	$on_ws_form_page   = isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'ws-form' ) === 0;

	if ( $on_ws_form_page ) {
		return; // WS Form's own Forms/Add Form/Edit Form/Styles/Settings screens need everything.
	}

	if ( ! is_admin() && ! $in_bricks_builder ) {
		return; // Genuine front-end request -- never touch these filters here.
	}

	$wsf_js_filters = [
		'wsf_enqueue_js_common', 'wsf_enqueue_js_public', 'wsf_enqueue_js_sortable', 'wsf_enqueue_js_select2',
		'wsf_enqueue_js_input_mask', 'wsf_enqueue_js_loader', 'wsf_enqueue_js_custom',
		'wsf_enqueue_js_captcha', 'wsf_enqueue_js_checkbox',
		'wsf_enqueue_js_select', 'wsf_enqueue_js_radio', 'wsf_enqueue_js_tab', 'wsf_enqueue_js_tel',
		'wsf_enqueue_js_intl_tel_input', 'wsf_enqueue_js_color', 'wsf_enqueue_js_color_picker',
		'wsf_enqueue_js_consent', 'wsf_enqueue_js_datetime', 'wsf_enqueue_js_date_translate',
		'wsf_enqueue_js_datetime_picker', 'wsf_enqueue_js_file', 'wsf_enqueue_js_dropzonejs',
		'wsf_enqueue_js_geo', 'wsf_enqueue_js_google_map', 'wsf_enqueue_js_google_address',
		'wsf_enqueue_js_google_route', 'wsf_enqueue_js_legal', 'wsf_enqueue_js_media_capture',
		'wsf_enqueue_js_password', 'wsf_enqueue_js_password_strength', 'wsf_enqueue_js_progress',
		'wsf_enqueue_js_rating', 'wsf_enqueue_js_signature', 'wsf_enqueue_js_signature_pad',
		'wsf_enqueue_js_textarea', 'wsf_enqueue_js_validate', 'wsf_enqueue_js_wp_editor',
		'wsf_enqueue_js_wp_html_editor', 'wsf_enqueue_js_analytics', 'wsf_enqueue_js_calc',
		'wsf_enqueue_js_cascade', 'wsf_enqueue_js_conditional', 'wsf_enqueue_js_ecommerce',
		'wsf_enqueue_js_section-repeatable', 'wsf_enqueue_js_section_repeatable', 'wsf_enqueue_js_tracking',
	];
	if ( ! $in_bricks_builder ) {
		foreach ( $wsf_js_filters as $filter ) {
			add_filter( $filter, '__return_false', 100000 );
		}
	}

	if ( $in_bricks_builder ) {
		return; // Leave JS+CSS filters at default (on) -- WS Form forms in the canvas need the real rendering engine, not just styling.
	}

	$wsf_css_filters = [
		'wsf_enqueue_css_skin', 'wsf_enqueue_css_style', 'wsf_enqueue_css_layout',
		'wsf_enqueue_css_loader', 'wsf_enqueue_css_custom',
		'wsf_enqueue_css_base', 'wsf_enqueue_css_button', 'wsf_enqueue_css_checkbox',
		'wsf_enqueue_css_color', 'wsf_enqueue_css_number', 'wsf_enqueue_css_radio',
		'wsf_enqueue_css_select', 'wsf_enqueue_css_tab', 'wsf_enqueue_css_tel',
		'wsf_enqueue_css_textarea', 'wsf_enqueue_css_datetime', 'wsf_enqueue_css_file',
		'wsf_enqueue_css_google_address', 'wsf_enqueue_css_legal', 'wsf_enqueue_css_media_capture',
		'wsf_enqueue_css_meter', 'wsf_enqueue_css_password', 'wsf_enqueue_css_progress',
		'wsf_enqueue_css_range', 'wsf_enqueue_css_signature', 'wsf_enqueue_css_summary',
		'wsf_enqueue_css_validate',
	];
	foreach ( $wsf_css_filters as $filter ) {
		add_filter( $filter, '__return_false', 100000 );
	}
}, 10 );

// ── WooCommerce: strip block asset properties at registration time ────────
// The block editor's iframe canvas is populated by WordPress core's
// _wp_get_iframed_editor_assets() (wp-includes/block-editor.php), which
// independently loops EVERY registered block type and force-enqueues its
// editor_style_handles -- completely outside any dequeue-able action. The
// only way to stop this is to make sure that property is empty at
// registration time. Scoped to is_admin() + woocommerce/* only, so frontend
// rendering of any WooCommerce block already placed anywhere is untouched.
// This is separate from the path-based dequeue below, which handles
// WooCommerce's own enqueue_editor_assets() bypass on the outer admin page
// -- both are needed.

add_filter( 'register_block_type_args', function ( $args, $name ) {
	if ( ! is_admin() || strpos( (string) $name, 'woocommerce/' ) !== 0 ) {
		return $args;
	}

	foreach ( [
		'editor_script_handles',
		'script_handles',
		'view_script_handles',
		'view_script_module_ids',
		'editor_style_handles',
		'style_handles',
		'view_style_handles',
	] as $key ) {
		if ( isset( $args[ $key ] ) ) {
			$args[ $key ] = [];
		}
	}

	return $args;
}, 20, 2 );

// ── Run-last pass: handle-based dequeues ────────────────────────────────
// Registered at priority PHP_INT_MAX on all three hooks so it always runs
// after whatever enqueued the handle, regardless of that plugin's own
// hook/priority. Both dequeues AND deregisters each handle.

$imdg_dequeue_handles = function () {
	$in_bricks_builder = function_exists( 'bricks_is_builder' ) && bricks_is_builder();

	// Bricks core -- keep bricks-admin / bricks-gutenberg out of the plain editor
	// ONLY. bricks-admin.css/js is loaded by Bricks on EVERY single wp-admin
	// page with zero gating -- it powers Bricks' OWN admin screens (Templates
	// list thumbnail sizing, the loading-spinner state, the import
	// file-chooser visibility toggle, etc). Dequeuing it broadly broke the
	// Templates list page. Scoped to a real post/page edit screen only, so it
	// never touches Bricks' own admin pages.
	$screen_obj = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$on_post_edit_screen = $screen_obj && 'post' === $screen_obj->base;
	$bricks_handles = [];
	if ( $on_post_edit_screen && ! $in_bricks_builder ) {
		$bricks_handles = [
			'bricks-admin', 'bricks-admin-rtl', 'bricks-gutenberg',
			// Only relevant if Bricks' "Components in Block Editor" setting is ever turned on:
			'bricks-frontend-gutenberg', 'bricks-font-awesome-6', 'bricks-font-awesome-6-brands',
			'bricks-ionicons', 'bricks-themify-icons', 'bricks-scripts',
			'bricks-gutenberg-components', 'bricks-gutenberg-icon-fonts-bridge',
		];
	}

	// OpenStation (formerly Desktop Mode): dashboard widgets (registries/
	// widgets.php) + native windows (Content Graph, My WordPress, WooCommerce
	// Customer) singled out for removal. Widget handles were renamed from
	// desktop-mode-*-widget to os-*-widget in the OpenStation rebrand; window
	// handles were NOT renamed (confirmed per-file in source). This dequeue
	// pass is mostly a no-op for the widgets/windows/wallpaper specifically --
	// they're injected client-side, not through wp_scripts() -- the real fix
	// for those is the registry-neutralization further down. Kept here as a
	// harmless backstop for anything that IS server-enqueued normally.
	$dm_handles = [
		'os-drafts-widget',
		'os-focus-timer-widget',
		'os-heartbeat-widget',
		'os-jazz-quote-widget',
		'os-post-stats-widget',
		'os-comments-widget',
		'os-site-views-widget',
		'os-starter-widget',
		'os-living-tree-wallpaper',
		'os-snow-wallpaper',
		'os-my-wordpress-woocommerce',
		'desktop-mode-content-graph',
		'desktop-mode-my-wordpress',
	];

	// WS Form: the two deliberately-unconditional admin CSS files (separate
	// from the field-module bundle handled above via filters). Left alone on
	// WS Form's own admin pages -- 'ws-form-wp' specifically styles the 'Add
	// Form' feature itself, so dequeuing it there broke the Add Form screen.
	$on_ws_form_page  = isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'ws-form' ) === 0;
	$ws_form_handles  = $on_ws_form_page ? [] : [ 'ws-form-template', 'ws-form-wp' ];

	// WooCommerce: bundled Jetpack asset-data script, unconditional everywhere.
	$other_handles = [ 'jetpack-script-data' ];

	$all_handles = array_merge( $bricks_handles, $dm_handles, $ws_form_handles, $other_handles );

	foreach ( $all_handles as $handle ) {
		wp_dequeue_style( $handle );
		wp_dequeue_script( $handle );
		wp_deregister_style( $handle );
		wp_deregister_script( $handle );
	}

	// EmailKit: unconditional global admin CSS, left alone on EmailKit's own
	// settings pages (slug starts with "emailkit").
	$on_emailkit_page = isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'emailkit' ) === 0;
	if ( ! $on_emailkit_page ) {
		wp_dequeue_style( 'emailkit-admin-style' );
		wp_deregister_style( 'emailkit-admin-style' );
		wp_dequeue_script( 'emailkit-admin-wc-js' );
		wp_deregister_script( 'emailkit-admin-wc-js' );
	}
};
add_action( 'admin_enqueue_scripts', $imdg_dequeue_handles, PHP_INT_MAX );
add_action( 'enqueue_block_assets', $imdg_dequeue_handles, PHP_INT_MAX );
add_action( 'enqueue_block_editor_assets', $imdg_dequeue_handles, PHP_INT_MAX );

// ── Run-last pass: path-based dequeues ──────────────────────────────────
// Dequeues by matching each registered asset's source path back to a plugin
// folder, rather than hard-coding handle names (an implementation detail
// that changes on update, and in WooCommerce's case, numbers in the
// hundreds). Scoped to specific subfolders, not whole plugins, so e.g. the
// WooCommerce Product Data metabox (a different folder entirely) is never
// touched -- only its Blocks package is. Skipped inside the real Bricks
// builder for the Bricks-related targets.

$imdg_dequeue_by_path = function () {
	global $wp_styles, $wp_scripts;

	$in_bricks_builder = function_exists( 'bricks_is_builder' ) && bricks_is_builder();

	$target_dirs = [
		'/plugins/seo-by-rank-math/includes/modules/schema/blocks/toc/', // TOC block only -- schema.css lives elsewhere and is untouched.
		'/plugins/woocommerce/assets/client/blocks/',                    // WooCommerce Blocks package only -- not the whole plugin.
	];
	if ( ! $in_bricks_builder ) {
		$target_dirs[] = '/plugins/bricksextras/';
		// Scoped to /assets/ specifically, NOT the whole plugin root -- Bricks
		// Advanced Themer bundles its own copy of ACF Pro under
		// /plugins/bricks-advanced-themer/plugins/acf-pro/, and on sites where
		// that's the only ACF instance active, ACF_PATH resolves there. The
		// broader target was dequeuing ACF's own admin CSS/JS as collateral
		// damage (confirmed live: ACF_PATH === bricks-advanced-themer's bundled
		// copy, broken field-group-editor styling traced to this). The actual
		// backend/builder/Gutenberg-sync CSS we're trimming all lives under
		// assets/css/, so narrowing here fixes ACF without losing the intended
		// trim.
		$target_dirs[] = '/plugins/bricks-advanced-themer/assets/';
	}

	foreach ( [ $wp_styles, $wp_scripts ] as $registry ) {
		if ( ! $registry instanceof WP_Dependencies ) {
			continue;
		}
		foreach ( (array) $registry->queue as $handle ) {
			$src = isset( $registry->registered[ $handle ]->src ) ? $registry->registered[ $handle ]->src : '';
			if ( ! $src ) {
				continue;
			}
			foreach ( $target_dirs as $dir ) {
				if ( strpos( $src, $dir ) !== false ) {
					if ( $registry === $wp_styles ) {
						wp_dequeue_style( $handle );
					} else {
						wp_dequeue_script( $handle );
					}
				}
			}
		}
	}
};
add_action( 'admin_enqueue_scripts', $imdg_dequeue_by_path, PHP_INT_MAX );
add_action( 'enqueue_block_assets', $imdg_dequeue_by_path, PHP_INT_MAX );
add_action( 'enqueue_block_editor_assets', $imdg_dequeue_by_path, PHP_INT_MAX );
// ── Desktop Mode: the widget/window JS files aren't PHP-enqueued at all ────
// Confirmed by testing: no amount of wp_dequeue_script/wp_deregister_script
// on any hook/priority stops widget-drafts.min.js, widget-starter.min.js,
// widget-focus-timer.min.js, etc. -- because they were never in WordPress's
// PHP-side script queue to begin with. The desktop-mode shell script reads
// a JSON config (serverWidgets / nativeWindows) localized via
// wp_localize_script( 'desktop-mode', 'desktopModeConfig', $config ) and
// injects <script> tags itself, client-side, entirely outside wp_scripts().
// Desktop Mode ships a documented filter on that exact config --
// desktop_mode_shell_config (includes/render/assets.php) -- so we strip our
// unwanted entries out of serverWidgets/nativeWindows before it's ever sent
// to the browser. This is the only place these can actually be stopped.

add_filter( 'openstation_shell_config', function ( $config ) {
	// Renamed from desktop_mode_shell_config when the plugin rebranded to
	// OpenStation. Function names below were renamed too (desktop_mode_* ->
	// openstation_*) -- our function_exists() guards silently no-op'd
	// against the old names after the rebrand, which is why this whole
	// section stopped working with no errors.
	$unwanted_widget_ids = [
		'desktop-mode/drafts',
		'desktop-mode/focus-timer',
		'desktop-mode/heartbeat',
		'desktop-mode/jazz-quote',
		'desktop-mode/post-stats',
		'desktop-mode/recent-comments',
		'desktop-mode/site-views',
		'desktop-mode/starter',
	];
	if ( ! empty( $config['serverWidgets'] ) && is_array( $config['serverWidgets'] ) ) {
		$config['serverWidgets'] = array_values( array_filter(
			$config['serverWidgets'],
			static function ( $widget ) use ( $unwanted_widget_ids ) {
				return empty( $widget['id'] ) || ! in_array( $widget['id'], $unwanted_widget_ids, true );
			}
		) );
	}

	$unwanted_window_ids = [
		'desktop-mode-content-graph',
		'desktop-mode-my-wordpress',
	];
	if ( ! empty( $config['nativeWindows'] ) && is_array( $config['nativeWindows'] ) ) {
		$config['nativeWindows'] = array_values( array_filter(
			$config['nativeWindows'],
			static function ( $window ) use ( $unwanted_window_ids ) {
				return empty( $window['id'] ) || ! in_array( $window['id'], $unwanted_window_ids, true );
			}
		) );
	}

	return $config;
} );
// ── Desktop Mode: neutralize widgets/windows at the registry, not the payload ──
// Confirmed by inspecting window.desktopModeConfig live: the
// desktop_mode_shell_config filter above IS applied to the initial page
// load, but Desktop Mode's "live refresh" system (chromeless-bridge.php,
// wp.desktop.refreshMenu()) re-fetches desktop_mode_build_menu_payload()
// directly from the registry -- several call sites, none going through that
// filter -- and overwrites the parent shell's state shortly after boot.
// That's why dequeuing/filtering downstream never stuck.
//
// The only place that reliably reaches every consumer is the registry
// itself. desktop_mode_register_widget() / desktop_mode_register_window()
// have no "unregister" API, but the registry is a plain "last call wins"
// store with no protection against re-registration -- so we re-register
// each unwanted id with an empty script/style handle right after Desktop
// Mode's own widget/window files have already registered them. A resolved
// script/style payload for an empty handle is empty, so nothing loads,
// regardless of which of the several sync paths asks for it. Hooked on
// init (priority 20) to run after Desktop Mode's own top-level
// require_once calls (which run during normal plugin loading, before
// init fires).

add_action( 'init', function () {
	if ( ! function_exists( 'openstation_register_widget' ) || ! function_exists( 'openstation_register_window' ) ) {
		return;
	}

	$unwanted_widgets = [
		'desktop-mode/drafts'          => __( 'Drafts (disabled)', 'infinite-monkeys-dark-glass' ),
		'desktop-mode/focus-timer'     => __( 'Focus Timer (disabled)', 'infinite-monkeys-dark-glass' ),
		'desktop-mode/heartbeat'       => __( 'Heartbeat (disabled)', 'infinite-monkeys-dark-glass' ),
		'desktop-mode/jazz-quote'      => __( 'Jazz Quote (disabled)', 'infinite-monkeys-dark-glass' ),
		'desktop-mode/post-stats'      => __( 'Post Stats (disabled)', 'infinite-monkeys-dark-glass' ),
		'desktop-mode/recent-comments' => __( 'Recent Comments (disabled)', 'infinite-monkeys-dark-glass' ),
		'desktop-mode/site-views'      => __( 'Site Views (disabled)', 'infinite-monkeys-dark-glass' ),
		'desktop-mode/starter'         => __( 'Starter Widget (disabled)', 'infinite-monkeys-dark-glass' ),
	];
	foreach ( $unwanted_widgets as $id => $label ) {
		openstation_register_widget( $id, [
			'label'  => $label,
			'script' => '',
		] );
	}

	$noop_template = static function () {};
	// IDs stayed the same after the OpenStation rebrand for these two, but
	// registration priorities vary wildly per module (confirmed in source:
	// content-graph registers at init@20, my-wordpress at init@99, the new
	// woo-customer window at init@25) -- this whole callback is now
	// registered at PHP_INT_MAX (see bottom of this block) instead of a
	// fixed priority, so it always runs after every one of them regardless
	// of which priority a given module uses.
	$unwanted_windows = [
		'desktop-mode-content-graph' => __( 'Content Graph (disabled)', 'infinite-monkeys-dark-glass' ),
		'desktop-mode-my-wordpress'  => __( 'My WordPress (disabled)', 'infinite-monkeys-dark-glass' ),
		// New in the OpenStation WooCommerce integration -- the "Customer"
		// window (my-wordpress-woocommerce.min.js), registered at init@25.
		'desktop-mode-woo-customer'  => __( 'WooCommerce Customer (disabled)', 'infinite-monkeys-dark-glass' ),
	];
	foreach ( $unwanted_windows as $id => $title ) {
		openstation_register_window( $id, [
			'title'     => $title,
			'template'  => $noop_template,
			'script'    => '',
			'style'     => '',
			'placement' => 'none',
		] );
	}
}, PHP_INT_MAX );
// ── Desktop Mode: neutralize the Living Tree wallpaper the same way ────────
// Same eager-load-the-whole-catalog issue as the widgets/windows above --
// living-tree-wallpaper.min.js loads regardless of which wallpaper is
// actually selected (confirmed: this site's active wallpaper is
// 'custom-gradient', not 'wp-living-tree'). Canvas wallpapers require a
// non-empty `script` handle by validation, so instead of blanking it (which
// desktop_mode_register_wallpaper() would reject), we re-register as a
// plain 'css' type with a static value -- valid without a script, and the
// picker entry still works if anyone ever selects it, it just won't animate.

add_action( 'init', function () {
	if ( ! function_exists( 'openstation_register_wallpaper' ) ) {
		return;
	}
	// living-tree registers at init@6; snow-wallpaper is a second canvas
	// wallpaper Eric flagged separately (confirmed neither is the site's
	// active wallpaper). Registered at PHP_INT_MAX so this always runs last
	// regardless of either module's own priority.
	$unwanted_wallpapers = [ 'wp-living-tree', 'wp-snow' ];
	foreach ( $unwanted_wallpapers as $id ) {
		openstation_register_wallpaper( $id, [
			'label' => __( 'Disabled', 'infinite-monkeys-dark-glass' ),
			'type'  => 'css',
			'value' => '#1a1a1a',
		] );
	}
}, PHP_INT_MAX );
// ── WS Form: dequeue-by-handle safety net for the plain block editor ───────
// Belt-and-suspenders alongside the filter-based approach above. Skips the
// Bricks builder entirely (WS Form's rendering engine needs to load there),
// and skips WS Form's own admin pages. Handle pattern confirmed from
// ws-form-pro/public/class-ws-form-public.php: enqueue_internal_js()/
// enqueue_internal_css() build the handle as "ws-form-{$script}" where
// $script is the same name used in the wsf_enqueue_js_*/wsf_enqueue_css_*
// filter suffix (e.g. 'captcha' -> 'ws-form-captcha').

add_action( 'wp_enqueue_scripts', function () {
	$on_ws_form_page = isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'ws-form' ) === 0;
	if ( $on_ws_form_page ) {
		return; // WS Form's own Forms/Add Form/Edit Form/Styles/Settings screens need everything.
	}

	if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
		return; // WS Form forms in the Bricks canvas need the real rendering engine -- don't block JS here.
	}

	if ( ! is_admin() ) {
		return; // Never touch a genuine front-end visitor's page.
	}

	$wsf_js_names = [
		'common', 'public', 'sortable', 'select2', 'input_mask', 'loader', 'custom',
		'captcha', 'checkbox', 'select', 'radio', 'tab', 'tel', 'intl_tel_input',
		'color', 'color_picker', 'consent', 'datetime', 'date_translate', 'datetime_picker',
		'file', 'dropzonejs', 'geo', 'google_map', 'google_address', 'google_route',
		'legal', 'media_capture', 'password', 'password_strength', 'progress', 'rating',
		'signature', 'signature_pad', 'textarea', 'validate', 'wp_editor', 'wp_html_editor',
		'analytics', 'calc', 'cascade', 'conditional', 'ecommerce', 'section-repeatable',
		'section_repeatable', 'tracking',
	];
	foreach ( $wsf_js_names as $name ) {
		wp_dequeue_script( 'ws-form-' . $name );
		wp_deregister_script( 'ws-form-' . $name );
	}
}, PHP_INT_MAX );

// ── WooCommerce cart/checkout: keep frontend-only scripts out of admin+builder ──
// WC_Frontend_Scripts (WooCommerce core) and Checkout for WooCommerce's
// AssetManager both hook wp_enqueue_scripts only -- the standard FRONTEND
// hook. Since bricks_is_builder() is defined as "NOT is_admin(), with the
// builder query param" (Bricks intentionally runs its whole canvas outside
// /wp-admin/), wp_enqueue_scripts fires during a Bricks builder session too,
// same as any other frontend page load -- so cart-fragments.js and Checkout
// for WooCommerce's side-cart vendor bundles load there even though nothing
// in the canvas needs live cart/checkout behaviour. Gated the same way as
// the WS Form safety net above: only touches is_admin() or bricks_is_builder()
// contexts, never a genuine front-end visitor's page.

add_action( 'wp_enqueue_scripts', function () {
	$in_admin_context = is_admin() || ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() );
	if ( ! $in_admin_context ) {
		return; // Never touch a genuine front-end visitor's page.
	}

	// WooCommerce core: mini-cart AJAX refresh script.
	wp_dequeue_script( 'wc-cart-fragments' );
	wp_deregister_script( 'wc-cart-fragments' );

	// Checkout for WooCommerce: side-cart feature and its webpack vendor
	// chunks, matched by plugin folder path rather than guessing chunk
	// handle names (webpack splitChunks output is an implementation detail).
	global $wp_scripts;
	if ( $wp_scripts instanceof WP_Dependencies ) {
		foreach ( (array) $wp_scripts->queue as $handle ) {
			$src = isset( $wp_scripts->registered[ $handle ]->src ) ? $wp_scripts->registered[ $handle ]->src : '';
			if ( $src && strpos( $src, '/plugins/checkout-for-woocommerce/' ) !== false ) {
				wp_dequeue_script( $handle );
			}
		}
	}
}, PHP_INT_MAX );
