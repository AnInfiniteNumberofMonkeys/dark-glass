<?php
/**
 * Infinite Monkeys Dark Glass — cross-site OS Settings transport.
 *
 * Desktop Mode's OS Settings live entirely client-side in
 * localStorage['desktop-mode-os-settings'] (see
 * assets/js/desktop-mode-os-settings-defaults.js for the full write-up
 * of that mechanism, including why there's no server-side option or
 * filter to hook instead). localStorage is scoped per browser origin,
 * so a fresh WordPress install — even with Dark Glass active — starts
 * with an empty key, and Desktop Mode falls back to its own shipped
 * DEFAULTS the first time OsSettings constructs.
 *
 * This seeds that key, ONE TIME PER BROWSER, with the actual OS
 * Settings snapshot captured live from bricks.infinitemonkeys.ca (via
 * localStorage.getItem('desktop-mode-os-settings') on that site) — so
 * installing Dark Glass on any new site effectively "transports" that
 * configuration to the new install, with no manual configuration or
 * "Save as default" click needed there.
 *
 * Also seeds imdg-os-settings-defaults (the baseline read by
 * desktop-mode-os-settings-defaults.js's Reset interception) with the
 * same snapshot, so "Reset to defaults" on a brand-new site immediately
 * restores this transported baseline too, rather than Desktop Mode's
 * own defaults, without the admin needing to click "Save as default"
 * again on every new site.
 *
 * Both writes are deliberately guarded to only fire if the respective
 * key is completely empty (first-ever visit to wp-admin in that
 * browser for that site's origin) — this never overwrites an existing
 * customization, whether it came from Desktop Mode's own UI, a prior
 * "Save as default" click, or a prior run of this same seed.
 *
 * Must run before Desktop Mode's own script reads localStorage at
 * construction, so this is an inline script hooked at admin_head
 * priority 0 — as early in <head> as WordPress allows, ahead of every
 * enqueued script regardless of whether Desktop Mode loads its own in
 * the head or the footer.
 *
 * To update the transported baseline after further tweaking OS Settings
 * on the source site: re-capture localStorage['desktop-mode-os-settings']
 * there and replace the $seed array below.
 *
 * Never touches the Desktop Mode plugin's own files.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_head', function () {

	// Nothing to seed if Desktop Mode isn't even installed here.
	if ( ! defined( 'DESKTOP_MODE_VERSION' ) ) {
		return;
	}

	// Captured live from bricks.infinitemonkeys.ca — see file header.
	$seed = array(
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
		'wallpaperSettings'           => new stdClass(),
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
		'dockPromotedPositions'       => new stdClass(),
	);

	$seed_json = wp_json_encode( $seed );

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
