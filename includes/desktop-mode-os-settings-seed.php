<?php
/**
 * Infinite Monkeys Dark Glass — cross-site OS Settings transport.
 *
 * Desktop Mode's OS Settings have TWO stores, not one:
 *
 *   1. localStorage['desktop-mode-os-settings'] — client-side, for
 *      instant read-back on paint. Per-browser, per-origin.
 *   2. User meta 'desktop_mode_os_settings' (see Desktop Mode's own
 *      includes/os-settings.php) — the DURABLE source of truth,
 *      fetched via `GET /wp-json/desktop-mode/v1/os-settings` shortly
 *      after every page load and used to overwrite whatever's in
 *      localStorage. Desktop Mode's own file header says it plainly:
 *      "user meta is the durable source of truth."
 *
 *   This second store is why an earlier version of this file (which
 *   only seeded localStorage) failed on a genuinely fresh WordPress
 *   install: our localStorage seed landed fine, but moments later
 *   Desktop Mode's REST sync fetched this brand-new user's (empty)
 *   meta, got Desktop Mode's own built-in defaults back, and
 *   overwrote our seeded localStorage value with those — every
 *   single page load, since the user meta stayed empty forever
 *   (nothing had ever saved it). Confirmed live: localStorage held
 *   our values right after the inline seed script ran, then reverted
 *   to Desktop Mode's factory wallpaper/gradient by the next reload.
 *
 * So this file now seeds BOTH stores:
 *   - User meta, via `desktop_mode_save_os_settings()` (Desktop Mode's
 *     own sanitizer — keeps us schema-compatible even if a future
 *     Desktop Mode version adds fields we don't know about), hooked at
 *     `admin_init`. This is the real fix: once meta is seeded, Desktop
 *     Mode's own REST sync returns OUR values instead of its defaults,
 *     so it stops fighting the localStorage seed below.
 *   - localStorage, via an inline script at `admin_head` priority 0 (as
 *     early as WordPress allows), purely so the very first paint
 *     doesn't flash Desktop Mode's default wallpaper before the REST
 *     fetch resolves. Also seeds imdg-os-settings-defaults, the
 *     baseline desktop-mode-os-settings-defaults.js's Reset
 *     interception reads from, so "Reset to defaults" on a brand-new
 *     site restores this transported baseline immediately too.
 *
 * Both the user-meta write and both localStorage writes are guarded to
 * only fire when that particular store is still empty — this never
 * overwrites a real customization, however it got there (Desktop
 * Mode's own UI, a prior "Save as default" click, or a prior run of
 * this same seed).
 *
 * The values below were captured live from bricks.infinitemonkeys.ca
 * (localStorage.getItem('desktop-mode-os-settings') there). To update
 * the transported baseline after further tweaking OS Settings on the
 * source site, re-capture that value and replace the $seed array in
 * both functions below (kept in sync manually — see
 * imdg_os_settings_seed_payload()).
 *
 * Never touches the Desktop Mode plugin's own files.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The transported baseline, shared by both the user-meta seed and the
 * localStorage seed below so they can never drift apart.
 *
 * @return array
 */
function imdg_os_settings_seed_payload() {
	return array(
		'wallpaper'                   => 'custom-gradient',
		'accent'                      => 'wp-blue',
		'dockSize'                    => 'default',
		'desktopLayout'               => 'classic',
		'dockRailRenderer'            => 'default',
		'unfocusEffect'               => 'darken',
		'windowLinkRenderer'          => 'svg-splines',
		'windowLinkVisibility'        => 'always',
		'windowLinksEnabled'          => true,
		'windowLinkRaiseOnFocus'      => true,
		'windowLinkHighlight'         => true,
		'customGradient'              => array(
			'from'  => '#1a2123',
			'to'    => '#34123b',
			'angle' => 135,
		),
		'customImage'                 => null,
		'wallpaperSettings'           => array(),
		'libraryHdOnly'               => true,
		'ai'                          => array( 'enabled' => false ),
		'heartbeatRate'               => 60,
		'nativePostsEnabled'          => true,
		'nativePostsHiddenColumns'    => array(),
		'nativePagesEnabled'          => true,
		'nativeUsersEnabled'          => false,
		'nativePluginsEnabled'        => true,
		'nativeCommentsEnabled'       => false,
		'showDesktopOnWallpaperClick' => false,
		'showPostStatusRibbons'       => true,
		'developerModeEnabled'        => false,
		'foldersSharingEnabled'       => true,
		'itemVisibility'              => array(
			'desktop-mode-content-graph'                                          => 'hidden',
			'desktop-mode-games'                                                  => 'hidden',
			'toplevel_page_woocommerce-marketing'                                 => 'hidden',
			'desktop-mode-my-wordpress'                                           => 'hidden',
			'toplevel_page_adminpagewc-settingstabcheckoutfrompayments_menu_item' => 'hidden',
			'desktop-mode-recycle-bin'                                            => 'dock',
		),
		'dockOrder'                   => array(),
		'dockPromotedPositions'       => array(),
	);
}

/**
 * Seeds the DURABLE store: Desktop Mode's own user meta. This is what
 * actually fixes cross-site transport — see the file header for why
 * localStorage alone isn't enough.
 *
 * Hooked at admin_init (not admin_head) since this is a pure server-
 * side write with no output-ordering constraint — it only has to run
 * before the browser's REST fetch, which is a separate later request,
 * so any point in this page's lifecycle is early enough.
 */
add_action( 'admin_init', function () {

	if ( ! defined( 'OPENSTATION_VERSION' ) || ! function_exists( 'openstation_save_os_settings' ) ) {
		return;
	}

	$user_id = get_current_user_id();
	if ( $user_id <= 0 ) {
		return;
	}

	$meta_key = defined( 'OPENSTATION_OS_SETTINGS_META_KEY' ) ? OPENSTATION_OS_SETTINGS_META_KEY : 'desktop_mode_os_settings';

	// Only seed a user who has never had this saved — real customizations
	// (by this user, on this site) always win.
	if ( ! empty( get_user_meta( $user_id, $meta_key, true ) ) ) {
		return;
	}

	// desktop_mode_save_os_settings() runs this through Desktop Mode's own
	// sanitizer, so we stay schema-compatible even if a future Desktop
	// Mode version adds fields this file doesn't know about.
	openstation_save_os_settings( $user_id, imdg_os_settings_seed_payload() );

}, 20 );

/**
 * Seeds localStorage purely for instant first paint — see the file
 * header. Without the user-meta seed above, this alone doesn't stick.
 */
add_action( 'admin_head', function () {

	if ( ! defined( 'OPENSTATION_VERSION' ) ) {
		return;
	}

	$seed_json = wp_json_encode( imdg_os_settings_seed_payload() );

	?>
	<script>
	( function () {
		'use strict';
		try {
			if ( ! window.localStorage.getItem( 'desktop-mode-os-settings' ) ) {
				window.localStorage.setItem(
					'desktop-mode-os-settings',
					<?php echo wp_json_encode( $seed_json ); ?>
				);
			}
			if ( ! window.localStorage.getItem( 'imdg-os-settings-defaults' ) ) {
				window.localStorage.setItem(
					'imdg-os-settings-defaults',
					<?php echo wp_json_encode( $seed_json ); ?>
				);
			}
		} catch ( e ) {}
	} )();
	</script>
	<?php

}, 0 );
