<?php
/**
 * Block editor asset trimming.
 *
 * Several plugins load large asset bundles into the plain WordPress block
 * editor screen unconditionally -- on every post/page edit, regardless of
 * post type and regardless of whether the loaded features are actually used
 * on that post. None of this affects the real Bricks builder (each block
 * below bails out via bricks_is_builder() where relevant), and none of it
 * touches the frontend.
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

add_action( 'enqueue_block_assets', function () {
	if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
		return;
	}

	$wsf_filters = [
		'wsf_enqueue_js_public', 'wsf_enqueue_js_sortable', 'wsf_enqueue_js_select2',
		'wsf_enqueue_js_input_mask', 'wsf_enqueue_js_captcha', 'wsf_enqueue_js_checkbox',
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
		'wsf_enqueue_js_section-repeatable', 'wsf_enqueue_js_tracking',
		'wsf_enqueue_css_skin', 'wsf_enqueue_css_style', 'wsf_enqueue_css_layout',
		'wsf_enqueue_css_base', 'wsf_enqueue_css_button', 'wsf_enqueue_css_checkbox',
		'wsf_enqueue_css_color', 'wsf_enqueue_css_number', 'wsf_enqueue_css_radio',
		'wsf_enqueue_css_select', 'wsf_enqueue_css_tab', 'wsf_enqueue_css_tel',
		'wsf_enqueue_css_textarea', 'wsf_enqueue_css_datetime', 'wsf_enqueue_css_file',
		'wsf_enqueue_css_google_address', 'wsf_enqueue_css_legal', 'wsf_enqueue_css_media_capture',
		'wsf_enqueue_css_meter', 'wsf_enqueue_css_password', 'wsf_enqueue_css_progress',
		'wsf_enqueue_css_range', 'wsf_enqueue_css_signature', 'wsf_enqueue_css_summary',
		'wsf_enqueue_css_validate',
	];

	foreach ( $wsf_filters as $filter ) {
		add_filter( $filter, '__return_false', 100000 );
	}
}, 5 );

// ── Bricks Extras + Bricks Advanced Themer: drop their block-editor assets ──
// Both plugins load CSS/JS into the plain block editor unconditionally.
// Dequeue by matching each registered asset's source path back to its plugin
// folder rather than hard-coding handle names, which are an implementation
// detail. Skipped inside the real Bricks builder.

add_action( 'enqueue_block_assets', function () {
	if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
		return;
	}

	global $wp_styles, $wp_scripts;
	$target_plugin_dirs = [ '/plugins/bricksextras/', '/plugins/bricks-advanced-themer/' ];

	foreach ( [ $wp_styles, $wp_scripts ] as $registry ) {
		if ( ! $registry instanceof WP_Dependencies ) {
			continue;
		}
		foreach ( (array) $registry->queue as $handle ) {
			$src = isset( $registry->registered[ $handle ]->src ) ? $registry->registered[ $handle ]->src : '';
			if ( ! $src ) {
				continue;
			}
			foreach ( $target_plugin_dirs as $dir ) {
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
}, 30 );

// ── Rank Math: drop only the Table of Contents block's editor CSS ──────────
// Path-scoped to modules/schema/blocks/toc/ specifically, so schema.css (a
// sibling folder that powers the Schema metabox) is never touched.

add_action( 'enqueue_block_assets', function () {
	if ( ! is_admin() ) {
		return;
	}

	global $wp_styles;
	$target = '/plugins/seo-by-rank-math/includes/modules/schema/blocks/toc/';

	foreach ( (array) $wp_styles->queue as $handle ) {
		$src = isset( $wp_styles->registered[ $handle ]->src ) ? $wp_styles->registered[ $handle ]->src : '';
		if ( $src && strpos( $src, $target ) !== false ) {
			wp_dequeue_style( $handle );
		}
	}
}, 30 );

// ── Bricks core: keep bricks-admin / bricks-gutenberg out of the plain editor ──
// Bricks hooks admin_enqueue_scripts (every wp-admin screen) and
// enqueue_block_assets unconditionally for any post type it supports, whether
// or not that specific post is built with Bricks. Skipped inside the real
// Bricks builder.

add_action( 'admin_enqueue_scripts', function () {
	if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
		return;
	}
	wp_dequeue_style( 'bricks-admin' );
	wp_dequeue_style( 'bricks-admin-rtl' );
	wp_dequeue_script( 'bricks-admin' );
}, 20 );

add_action( 'enqueue_block_assets', function () {
	if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
		return;
	}
	wp_dequeue_script( 'bricks-gutenberg' );

	// Only relevant if Bricks' "Components in Block Editor" setting is ever turned on:
	wp_dequeue_style( 'bricks-frontend-gutenberg' );
	wp_dequeue_style( 'bricks-font-awesome-6' );
	wp_dequeue_style( 'bricks-font-awesome-6-brands' );
	wp_dequeue_style( 'bricks-ionicons' );
	wp_dequeue_style( 'bricks-themify-icons' );
	wp_dequeue_script( 'bricks-scripts' );
	wp_dequeue_script( 'bricks-gutenberg-components' );
	wp_dequeue_script( 'bricks-gutenberg-icon-fonts-bridge' );
}, 20 );

// ── Desktop Mode: never load these specific dashboard widget scripts ────────
// Registered by registries/widgets.php, which force-enqueues every registered
// widget's script on every Desktop Mode shell page (admin_enqueue_scripts,
// priority 20) so widgets are ready without a dynamic-load roundtrip. There
// is no per-widget opt-out, so we dequeue by handle one tick later.

add_action( 'admin_enqueue_scripts', function () {
	$handles = [
		'desktop-mode-drafts-widget',
		'desktop-mode-focus-timer-widget',
		'desktop-mode-heartbeat-widget',
		'desktop-mode-jazz-quote-widget',
		'desktop-mode-post-stats-widget',
		'desktop-mode-comments-widget',
		'desktop-mode-site-views-widget',
		'desktop-mode-starter-widget',
		'desktop-mode-living-tree-wallpaper',
	];
	foreach ( $handles as $handle ) {
		wp_dequeue_script( $handle );
	}
}, 21 );
