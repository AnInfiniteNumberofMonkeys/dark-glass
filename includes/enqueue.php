<?php
/**
 * Enqueue all plugin styles and scripts.
 *
 * CSS load order:
 *   1. admin.css          — Core WordPress admin styles
 *   2. third-party.css    — 3rd-party plugin overrides (loaded after so they
 *                           take precedence over any shared selectors)
 *
 * Note on third-party.css: All 3rd-party plugin sections currently live in a
 * single file. If you want per-plugin stylesheets loaded conditionally (e.g.
 * only on ACF pages), split the sections out and enqueue each one with an
 * appropriate `$hook` check inside the admin_enqueue_scripts callback.
 */

defined( 'ABSPATH' ) || exit;

// ── Google Fonts ─────────────────────────────────────────────────────────

add_action( 'admin_head', function () {
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<style>:root { --admin-font: "Nunito Sans"; }</style>' . "\n";
} );

add_action( 'admin_enqueue_scripts', function () {
    wp_enqueue_style(
        'imdg-google-fonts',
        'https://fonts.googleapis.com/css2?family=Nunito+Sans:opsz,wght@6..12,400;6..12,500;6..12,700&family=Open+Sans:ital@1&display=swap',
        [],
        null
    );
} );

// ── Admin stylesheets ────────────────────────────────────────────────────

add_action( 'admin_enqueue_scripts', function () {

    // Core WP admin styles — loaded at default priority
    $imdg_admin_css_path = IMDG_PLUGIN_DIR . 'assets/css/admin.css';
    wp_enqueue_style(
        'imdg-admin',
        IMDG_PLUGIN_URL . 'assets/css/admin.css',
        [ 'imdg-google-fonts' ],
        file_exists( $imdg_admin_css_path ) ? filemtime( $imdg_admin_css_path ) : IMDG_VERSION
    );

} );

// Top admin bar: replace the WordPress logo with this site's own
// favicon/Site Icon. Applied via JS as an inline style on the icon
// element -- a plain CSS background-image rule doesn't reliably win here
// (WP core's own #wpadminbar .ab-icon rule ties on specificity and
// !important, and source order isn't guaranteed), so this sets it as an
// inline style instead, which always wins regardless of stylesheet
// cascade. The URL is per-site data, so it's localized to the script
// rather than hardcoded in the shared stylesheet. Falls back to hiding
// the icon entirely (via a plain, reliable CSS rule) on installs with no
// Site Icon configured, rather than showing an empty box.
add_action( 'admin_enqueue_scripts', function () {

    $imdg_favicon_url = get_site_icon_url( 64 );

    $desktop_mode_favicon_icon_path = IMDG_PLUGIN_DIR . 'assets/js/desktop-mode-favicon-icon.js';
    wp_enqueue_script(
        'imdg-desktop-mode-favicon-icon',
        IMDG_PLUGIN_URL . 'assets/js/desktop-mode-favicon-icon.js',
        [],
        file_exists( $desktop_mode_favicon_icon_path ) ? filemtime( $desktop_mode_favicon_icon_path ) : IMDG_VERSION,
        true // Load in footer
    );
    wp_localize_script( 'imdg-desktop-mode-favicon-icon', 'imdgFaviconIcon', [
        'url' => $imdg_favicon_url ? esc_url( $imdg_favicon_url ) : '',
    ] );

    if ( ! $imdg_favicon_url ) {
        wp_add_inline_style(
            'imdg-admin',
            '#wp-admin-bar-wp-logo { display: none !important; }'
        );
    }

} );


// 3rd-party plugin overrides — hooked at priority 9999 so this stylesheet is
// enqueued after all plugins have registered their own styles at default
// priority (10). The imdg-admin dependency ensures correct ordering in the
// rendered <link> tags regardless of when this hook fires.
add_action( 'admin_enqueue_scripts', function () {

    $imdg_third_party_css_path = IMDG_PLUGIN_DIR . 'assets/css/third-party.css';
    wp_enqueue_style(
        'imdg-third-party',
        IMDG_PLUGIN_URL . 'assets/css/third-party.css',
        [ 'imdg-admin' ],
        file_exists( $imdg_third_party_css_path ) ? filemtime( $imdg_third_party_css_path ) : IMDG_VERSION
    );

}, 9999 );

// ── Bricks Builder editor styles ─────────────────────────────────────────
// Loaded only when inside the Bricks builder, not on the front end or admin.

add_action( 'wp_enqueue_scripts', function () {
    if ( function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main() ) {
        wp_enqueue_style( 'imdg-bricks-editor', IMDG_PLUGIN_URL . 'assets/css/bricks-editor.css', [], IMDG_VERSION );
    }
} );

// ── Admin JavaScript ─────────────────────────────────────────────────────

add_action( 'admin_enqueue_scripts', function () {

    /*
     * TEMPORARY MOBILE COMPATIBILITY SHIM — remove this wp_is_mobile()
     * guard once Desktop Mode ships native mobile support (see the
     * matching block in admin.css). Not used on mobile there anyway.
     */
    if ( ! wp_is_mobile() ) {
        // Frosted-glass overlay for admin bar and sidebar submenus
        wp_enqueue_script(
            'imdg-frosted-glass',
            IMDG_PLUGIN_URL . 'assets/js/frosted-glass.js',
            [],
            IMDG_VERSION,
            true // Load in footer
        );
    }

    /*
     * TEMPORARY MOBILE COMPATIBILITY SHIM — enqueued unconditionally,
     * NOT gated by wp_is_mobile() like frosted-glass.js above. Confirmed
     * live that server-side UA sniffing and the client-side @media
     * query this script checks internally can disagree for a real
     * device (the CSS mobile fixes reached the device fine while this
     * script, when gated by wp_is_mobile(), silently never loaded) —
     * see the file header for the full explanation. It's a no-op on
     * desktop since it bails out immediately if the media query
     * doesn't match.
     */
    $desktop_mode_mobile_shim_path = IMDG_PLUGIN_DIR . 'assets/js/desktop-mode-mobile-shim.js';
    wp_enqueue_script(
        'imdg-desktop-mode-mobile-shim',
        IMDG_PLUGIN_URL . 'assets/js/desktop-mode-mobile-shim.js',
        [],
        file_exists( $desktop_mode_mobile_shim_path ) ? filemtime( $desktop_mode_mobile_shim_path ) : IMDG_VERSION,
        true // Load in footer
    );

    // Dynamic TinyMCE iframe style injection (for ACF and other late-rendered fields)
    wp_enqueue_script(
        'imdg-tinymce-inject',
        IMDG_PLUGIN_URL . 'assets/js/tinymce-inject.js',
        [],
        IMDG_VERSION,
        true // Load in footer
    );

    // TinyMCE list keyboard shortcuts (Shift+Cmd/Ctrl+B / Shift+Cmd/Ctrl+1).
    // Covers ACF WYSIWYG fields and the classic editor -- the block-editor
    // shortcut above (imdg-block-list-shortcut) only works on Gutenberg
    // blocks, which has no equivalent inside a single TinyMCE field. Binds
    // via TinyMCE's own AddEditor event so it reaches ACF fields added
    // dynamically in repeaters/flexible content, not just editors present
    // at page load.
    $tinymce_list_shortcuts_path = IMDG_PLUGIN_DIR . 'assets/js/tinymce-list-shortcuts.js';
    wp_enqueue_script(
        'imdg-tinymce-list-shortcuts',
        IMDG_PLUGIN_URL . 'assets/js/tinymce-list-shortcuts.js',
        [],
        file_exists( $tinymce_list_shortcuts_path ) ? filemtime( $tinymce_list_shortcuts_path ) : IMDG_VERSION,
        true // Load in footer
    );

    // Pass the TinyMCE content CSS to the JS injector.
    // The CSS file re-declares the relevant custom properties because injected
    // iframe documents do not inherit :root variables from the parent page.
    $tinymce_css_path = IMDG_PLUGIN_DIR . 'assets/css/tinymce-content.css';
    wp_localize_script( 'imdg-tinymce-inject', 'imdgTinyMCE', [
        'styles' => file_exists( $tinymce_css_path )
            ? file_get_contents( $tinymce_css_path )
            : '',
    ] );

    // Desktop Mode shadow-DOM style injection for <wpd-menu-item> (see file
    // header for why this can't be done via a normal stylesheet). Enqueued
    // unconditionally like the other admin scripts above; it self-guards at
    // runtime by checking for body.desktop-mode-active before doing anything.
    $desktop_mode_shadow_styles_path = IMDG_PLUGIN_DIR . 'assets/js/desktop-mode-shadow-styles.js';
    wp_enqueue_script(
        'imdg-desktop-mode-shadow-styles',
        IMDG_PLUGIN_URL . 'assets/js/desktop-mode-shadow-styles.js',
        [],
        file_exists( $desktop_mode_shadow_styles_path ) ? filemtime( $desktop_mode_shadow_styles_path ) : IMDG_VERSION,
        true // Load in footer
    );

    // Desktop Mode window-focus bridge (see file header). Enqueued
    // unconditionally since it needs to run in BOTH the parent shell AND
    // every chromeless iframe — admin_enqueue_scripts fires in both
    // contexts, and the script itself detects which role to play.
    $desktop_mode_focus_bridge_path = IMDG_PLUGIN_DIR . 'assets/js/desktop-mode-focus-bridge.js';
    wp_enqueue_script(
        'imdg-desktop-mode-focus-bridge',
        IMDG_PLUGIN_URL . 'assets/js/desktop-mode-focus-bridge.js',
        [],
        file_exists( $desktop_mode_focus_bridge_path ) ? filemtime( $desktop_mode_focus_bridge_path ) : IMDG_VERSION,
        true // Load in footer
    );

    // Desktop background click → Overview command (see file header).
    // Enqueued unconditionally like the other structural fixes above;
    // self-guards on body.os-active.
    $desktop_mode_background_overview_path = IMDG_PLUGIN_DIR . 'assets/js/desktop-mode-background-overview.js';
    wp_enqueue_script(
        'imdg-desktop-mode-background-overview',
        IMDG_PLUGIN_URL . 'assets/js/desktop-mode-background-overview.js',
        [],
        file_exists( $desktop_mode_background_overview_path ) ? filemtime( $desktop_mode_background_overview_path ) : IMDG_VERSION,
        true // Load in footer
    );

    // Bricks builder new-tab handoff (see file header). Enqueued
    // unconditionally; attaches its click listener immediately regardless
    // of DOM readiness, checking body.desktop-mode-chromeless fresh on
    // every click rather than once at load time (this used to load in
    // the footer with a load-time-only check, which left a race window
    // where a slow-to-load footer script meant the listener never
    // attached at all for that page). Loaded in the head now that the
    // guard no longer depends on document.body existing yet, closing
    // that race as early as possible.
    $desktop_mode_bricks_newtab_path = IMDG_PLUGIN_DIR . 'assets/js/desktop-mode-bricks-newtab.js';
    wp_enqueue_script(
        'imdg-desktop-mode-bricks-newtab',
        IMDG_PLUGIN_URL . 'assets/js/desktop-mode-bricks-newtab.js',
        [],
        file_exists( $desktop_mode_bricks_newtab_path ) ? filemtime( $desktop_mode_bricks_newtab_path ) : IMDG_VERSION,
        false // Load in head
    );

    // Groups .page-title-action buttons living directly inside .wrap into
    // a shared flex container (see the file header for why a stylesheet
    // alone can't do this). Enqueued unconditionally like the other
    // structural fixes above; no Desktop Mode dependency.
    $desktop_mode_page_title_actions_path = IMDG_PLUGIN_DIR . 'assets/js/desktop-mode-page-title-actions.js';
    wp_enqueue_script(
        'imdg-desktop-mode-page-title-actions',
        IMDG_PLUGIN_URL . 'assets/js/desktop-mode-page-title-actions.js',
        [],
        file_exists( $desktop_mode_page_title_actions_path ) ? filemtime( $desktop_mode_page_title_actions_path ) : IMDG_VERSION,
        true // Load in footer
    );

    // Adds a "Save as default" button next to Desktop Mode's OS Settings
    // "Reset to defaults" button, and reroutes Reset to restore that
    // saved baseline instead of Desktop Mode's own hardcoded defaults
    // (see the file header for the full explanation -- there is no
    // option/filter for this, it's a module-level constant in Desktop
    // Mode's own os-settings-panel.js). Enqueued unconditionally like the
    // other structural fixes above; self-guards on body.desktop-mode-active.
    $desktop_mode_os_settings_defaults_path = IMDG_PLUGIN_DIR . 'assets/js/desktop-mode-os-settings-defaults.js';
    wp_enqueue_script(
        'imdg-desktop-mode-os-settings-defaults',
        IMDG_PLUGIN_URL . 'assets/js/desktop-mode-os-settings-defaults.js',
        [],
        file_exists( $desktop_mode_os_settings_defaults_path ) ? filemtime( $desktop_mode_os_settings_defaults_path ) : IMDG_VERSION,
        true // Load in footer
    );

} );
