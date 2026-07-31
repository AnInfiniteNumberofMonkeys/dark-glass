<?php
/**
 * Inject styles into editor iframe contexts.
 *
 * Both TinyMCE (classic editor) and the Gutenberg block editor canvas run
 * inside their own iframes. CSS custom properties defined in the admin page's
 * :root are NOT available inside those iframes, so each injected stylesheet
 * re-declares the variables it needs at the top of its own :root block.
 *
 * Styles are loaded from flat CSS files rather than embedded PHP strings so
 * that they can be edited in any text editor or version-controlled cleanly.
 *
 *   assets/css/tinymce-content.css      — Classic editor & ACF WYSIWYG fields
 *   assets/css/block-editor-content.css — Gutenberg block editor canvas
 */

defined( 'ABSPATH' ) || exit;

/**
 * Minify a CSS string for safe embedding inside a JavaScript string literal.
 *
 * Removes block comments, collapses whitespace and newlines. This is necessary
 * because TinyMCE's content_style value is written directly into a JS object
 * literal — any unescaped newlines or comment blocks will cause a syntax error.
 *
 * @param  string $css Raw CSS.
 * @return string      Minified single-line CSS.
 */
function imdg_minify_css( $css ) {
    // Remove block comments (/* ... */)
    $css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );
    // Collapse whitespace and newlines to a single space
    $css = preg_replace( '/\s+/', ' ', $css );
    return trim( $css );
}

// ── Classic editor (TinyMCE) — PHP-side init ─────────────────────────────────
// Fires once when TinyMCE initialises. Covers the classic post editor and any
// TinyMCE instances already present in the DOM on page load.

add_action( 'after_setup_theme', function () {
    add_filter( 'tiny_mce_before_init', function ( $mce_init ) {

        $css_path = IMDG_PLUGIN_DIR . 'assets/css/tinymce-content.css';
        if ( ! file_exists( $css_path ) ) {
            return $mce_init;
        }

        $styles = imdg_minify_css( file_get_contents( $css_path ) );

        $mce_init['content_style'] =
            ( isset( $mce_init['content_style'] ) ? $mce_init['content_style'] . ' ' : '' )
            . $styles;

        return $mce_init;
    }, 99 );
} );

// ── Gutenberg block editor canvas ─────────────────────────────────────────────
// The block editor renders post content inside an iframe. Styles added via
// block_editor_settings_all are injected into that iframe document.

add_filter( 'block_editor_settings_all', function ( $settings ) {

    $css_path = IMDG_PLUGIN_DIR . 'assets/css/block-editor-content.css';
    if ( ! file_exists( $css_path ) ) {
        return $settings;
    }

    $settings['styles'][] = [ 'css' => file_get_contents( $css_path ) ];

    return $settings;
}, 99 );