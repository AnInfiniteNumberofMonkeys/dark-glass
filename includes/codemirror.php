<?php
/**
 * Apply the Dracula theme to WordPress's built-in CodeMirror file editors.
 *
 * The Dracula theme CSS ships with the plugin at assets/vendor/dracula.css.
 * The .cm-s-dracula selector in that file must match the theme name set here.
 *
 * This applies to:
 *   - Appearance → Theme File Editor  (theme-editor.php)
 *   - Plugins    → Plugin File Editor (plugin-editor.php)
 */

defined( 'ABSPATH' ) || exit;

// Tell CodeMirror to use the Dracula theme.
add_filter( 'wp_code_editor_settings', function ( $settings ) {
    $settings['codemirror']['theme'] = 'dracula';
    return $settings;
} );

// Enqueue the Dracula theme CSS on pages that initialise CodeMirror.
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( ! in_array( $hook, [ 'theme-editor.php', 'plugin-editor.php' ], true ) ) {
        return;
    }

    wp_enqueue_style(
        'imdg-codemirror-dracula',
        IMDG_PLUGIN_URL . 'assets/vendor/dracula.css',
        [],
        IMDG_VERSION
    );
} );
