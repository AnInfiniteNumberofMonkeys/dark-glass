<?php
/**
 * Block List Keyboard Shortcut
 *
 * Adds keyboard shortcuts to convert selected blocks into a bullet list
 * (Shift+Cmd/Ctrl+B) or a numbered list (Shift+Cmd/Ctrl+1) in the Gutenberg
 * block editor.
 *
 * Absorbed from the standalone "Block List Keyboard Shortcut" plugin
 * (v1.1.0) so sites running Dark Glass no longer need it installed
 * separately. Logic is unchanged from the standalone plugin; only the
 * enqueue wiring was adapted to Dark Glass's IMDG_* constants and
 * filemtime-based cache busting.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'enqueue_block_editor_assets', function () {

    $imdg_block_list_shortcut_path = IMDG_PLUGIN_DIR . 'assets/js/block-list-shortcut.js';

    wp_enqueue_script(
        'imdg-block-list-shortcut',
        IMDG_PLUGIN_URL . 'assets/js/block-list-shortcut.js',
        [
            'wp-blocks',
            'wp-element',
            'wp-data',
            'wp-keyboard-shortcuts',
            'wp-rich-text',
            'wp-dom-ready',
        ],
        file_exists( $imdg_block_list_shortcut_path ) ? filemtime( $imdg_block_list_shortcut_path ) : IMDG_VERSION,
        true // Load in footer
    );

} );
