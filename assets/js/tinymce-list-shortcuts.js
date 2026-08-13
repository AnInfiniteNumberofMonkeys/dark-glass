/**
 * TinyMCE List Keyboard Shortcuts
 *
 * The Gutenberg block-list shortcut (block-list-shortcut.js) only covers
 * the block editor — it converts whole selected *blocks* into a list block,
 * which has no equivalent inside a single classic-editor/TinyMCE field.
 *
 * This script wires the same key combo (Shift+Cmd/Ctrl+B / Shift+Cmd/Ctrl+1)
 * to TinyMCE's own native list commands instead, so the shortcut also works
 * inside ACF WYSIWYG fields, the classic post editor, and any other TinyMCE
 * instance on the page.
 *
 * ACF WYSIWYG fields are frequently initialized dynamically (repeaters,
 * flexible content layouts, clone fields) well after page load, so this
 * binds via TinyMCE's own `AddEditor` event — which fires for every editor
 * instance regardless of when or how it was created — rather than assuming
 * a fixed set of editors exists at DOM-ready time.
 *
 * @package InfiniteMonkeysDarkGlass
 */

(function () {
    'use strict';

    function addListShortcuts(editor) {
        // Guard against double-registration if AddEditor ever fires twice
        // for the same instance (e.g. an ACF field re-initialized in place).
        if (editor.imdgListShortcutsAdded) {
            return;
        }
        editor.imdgListShortcutsAdded = true;

        editor.addShortcut('meta+shift+b', 'Convert to bullet list', function () {
            editor.execCommand('InsertUnorderedList');
        });

        editor.addShortcut('meta+shift+1', 'Convert to numbered list', function () {
            editor.execCommand('InsertOrderedList');
        });
    }

    function bindToTinyMCE(tmce) {
        // Cover editors that already exist by the time this runs (e.g. the
        // main post content editor, which can initialize before this script
        // attaches its AddEditor listener below).
        if (tmce.editors && tmce.editors.length) {
            tmce.editors.forEach(addListShortcuts);
        }

        // Cover every editor created afterward. This is what makes ACF
        // WYSIWYG fields work, including ones added dynamically inside
        // repeaters and flexible content layouts — each triggers its own
        // AddEditor event when it initializes.
        tmce.on('AddEditor', function (e) {
            addListShortcuts(e.editor);
        });
    }

    if (window.tinymce) {
        bindToTinyMCE(window.tinymce);
        return;
    }

    // TinyMCE may not be loaded yet depending on script order (e.g. if this
    // script is enqueued without a hard dependency on it). Poll briefly
    // rather than assuming a load order.
    var attempts = 0;
    var timer = setInterval(function () {
        attempts++;
        if (window.tinymce) {
            clearInterval(timer);
            bindToTinyMCE(window.tinymce);
        } else if (attempts > 40) { // ~10s at 250ms
            clearInterval(timer);
        }
    }, 250);

})();
