/**
 * Dynamic TinyMCE style injector
 *
 * WordPress's tiny_mce_before_init filter runs once on page load and covers
 * the main classic editor. However, ACF WYSIWYG fields and other plugins that
 * render TinyMCE instances dynamically (after DOMContentLoaded) won't pick up
 * those styles. This script uses a MutationObserver to watch for new TinyMCE
 * iframes and injects the theme styles into each one as it appears.
 *
 * The CSS to inject is passed from PHP via wp_localize_script() as
 * imdgTinyMCE.styles — this keeps the source of truth in the CSS file
 * (assets/css/tinymce-content.css) rather than duplicated here.
 */

( function () {

    // CSS provided by PHP via wp_localize_script; fall back to empty string
    // if the localised variable is missing for any reason.
    var styles = ( typeof imdgTinyMCE !== 'undefined' && imdgTinyMCE.styles )
        ? imdgTinyMCE.styles
        : '';

    if ( ! styles ) return;

    // ── Inject styles into a single TinyMCE iframe ────────────────────────────
    function injectIntoIframe( iframe ) {
        function doInject() {
            try {
                var doc = iframe.contentDocument || iframe.contentWindow.document;
                if ( ! doc || ! doc.head ) return;
                if ( doc.getElementById( 'imdg-tinymce-styles' ) ) return; // already injected

                var el       = doc.createElement( 'style' );
                el.id        = 'imdg-tinymce-styles';
                el.innerHTML = styles;
                doc.head.appendChild( el );
            } catch ( e ) {
                // Cross-origin iframe — silently skip
            }
        }

        if ( iframe.contentDocument && iframe.contentDocument.readyState === 'complete' ) {
            doInject();
        } else {
            iframe.addEventListener( 'load', doInject );
        }
    }

    // ── Watch for new TinyMCE iframes added to the DOM ────────────────────────
    // TinyMCE iframes have IDs ending in "_ifr" (e.g. "acf-editor-1_ifr").
    var observer = new MutationObserver( function ( mutations ) {
        mutations.forEach( function ( mutation ) {
            mutation.addedNodes.forEach( function ( node ) {
                if ( node.nodeType !== 1 ) return; // element nodes only

                if ( node.tagName === 'IFRAME' && node.id && node.id.endsWith( '_ifr' ) ) {
                    injectIntoIframe( node );
                }

                // Also check descendants in case a whole subtree was added
                node.querySelectorAll( 'iframe[id$="_ifr"]' ).forEach( injectIntoIframe );
            } );
        } );
    } );

    observer.observe( document.body, { childList: true, subtree: true } );

} )();
