/**
 * Frosted glass overlay — admin bar & sidebar submenus
 *
 * Creates fixed-position overlay divs with backdrop-filter: blur() that track
 * submenu wrappers on hover. This approach is used because the submenus
 * themselves have background colours set by WordPress core and various plugins
 * that are difficult to override cleanly with CSS alone. The overlay sits
 * behind the submenu content (z-index: menuZ - 1) so it only blurs whatever
 * is visible through the translucent submenu background.
 */

( function () {

    // ── Shared overlay store ──────────────────────────────────────────────────
    // Map of wrapper element → { overlay, hideTimer }
    const wrapperMap = new Map();

    // ── Create a positioned overlay element ───────────────────────────────────
    function createOverlay( insertBefore, withCaret ) {
        const el = document.createElement( 'div' );
        el.className = 'imdg-blur-overlay';

        Object.assign( el.style, {
            position             : 'fixed',
            pointerEvents        : 'none',
            zIndex               : ( getZ( insertBefore ) - 1 ).toString(),
            backdropFilter       : 'blur(5px)',
            webkitBackdropFilter : 'blur(5px)',
            background           : 'rgba(255, 255, 255, 0.2)',
            borderRadius         : 'var(--radius-md, 0.6rem)',
            opacity              : '0',
            transition           : 'opacity 0.15s ease',
        } );

        if ( withCaret ) {
            const caret = document.createElement( 'div' );
            Object.assign( caret.style, {
                position     : 'absolute',
                top          : '-8px',
                left         : '40px',
                transform    : 'translateX(-50%)',
                width        : '0',
                height       : '0',
                borderLeft   : '8px solid transparent',
                borderRight  : '8px solid transparent',
                borderBottom : '8px solid rgba(255, 255, 255, 0.15)',
            } );
            el.appendChild( caret );
        }

        insertBefore.parentNode.insertBefore( el, insertBefore );
        return el;
    }

    // ── Get computed z-index of a reference element ───────────────────────────
    function getZ( el ) {
        return parseInt( getComputedStyle( el ).zIndex, 10 ) || 9999;
    }

    // ── Get or create map entry for a wrapper ─────────────────────────────────
    function getEntry( wrapper, insertBefore, withCaret ) {
        if ( ! wrapperMap.has( wrapper ) ) {
            wrapperMap.set( wrapper, {
                overlay   : createOverlay( insertBefore, withCaret ),
                hideTimer : null,
            } );
        }
        return wrapperMap.get( wrapper );
    }

    // ── Position all visible overlays each frame ──────────────────────────────
    function trackAll() {
        wrapperMap.forEach( ( entry, wrapper ) => {
            if ( parseFloat( entry.overlay.style.opacity ) > 0 ) {
                const rect = wrapper.getBoundingClientRect();
                Object.assign( entry.overlay.style, {
                    top    : rect.top    + 'px',
                    left   : rect.left   + 'px',
                    width  : rect.width  + 'px',
                    height : rect.height + 'px',
                } );
            }
        } );
        requestAnimationFrame( trackAll );
    }

    // ── Show overlay ──────────────────────────────────────────────────────────
    function showOverlay( wrapper, insertBefore, withCaret ) {
        const entry = getEntry( wrapper, insertBefore, withCaret );
        clearTimeout( entry.hideTimer );
        entry.hideTimer = null;

        const rect = wrapper.getBoundingClientRect();
        Object.assign( entry.overlay.style, {
            top    : rect.top    + 'px',
            left   : rect.left   + 'px',
            width  : rect.width  + 'px',
            height : rect.height + 'px',
            opacity: '1',
        } );
    }

    // ── Hide overlay (debounced) ───────────────────────────────────────────────
    function hideOverlay( wrapper ) {
        const entry = wrapperMap.get( wrapper );
        if ( ! entry ) return;
        clearTimeout( entry.hideTimer );
        entry.hideTimer = setTimeout( () => {
            entry.overlay.style.opacity = '0';
        }, 80 );
    }

    // ── Cancel a pending hide ─────────────────────────────────────────────────
    function cancelHide( wrapper ) {
        const entry = wrapperMap.get( wrapper );
        if ( ! entry ) return;
        clearTimeout( entry.hideTimer );
        entry.hideTimer = null;
    }

    // ── Strip solid background from submenu elements ──────────────────────────
    function clearBackground( ...els ) {
        els.forEach( el => { if ( el ) el.style.background = 'transparent'; } );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ADMIN BAR
    // ═══════════════════════════════════════════════════════════════════════════

    const ADMINBAR_TOP = [
        '#wpadminbar .ab-top-menu > li.menupop',
        '#wpadminbar .ab-top-secondary > li.menupop',
    ].join( ', ' );

    function bindAdminBarMenupop( menupop, insertBefore, isTopLevel ) {
        const wrapper = menupop.querySelector( ':scope > .ab-sub-wrapper' );
        if ( ! wrapper ) return;

        clearBackground(
            menupop.querySelector( '.ab-sub-wrapper' ),
            menupop.querySelector( '.ab-submenu' )
        );

        menupop.addEventListener( 'mouseenter', () => showOverlay( wrapper, insertBefore, isTopLevel ) );
        menupop.addEventListener( 'mouseleave', () => hideOverlay( wrapper ) );
        wrapper.addEventListener( 'mouseover',  () => cancelHide( wrapper ) );

        menupop.addEventListener( 'focusin', () => showOverlay( wrapper, insertBefore, isTopLevel ) );
        menupop.addEventListener( 'focusout', () => {
            setTimeout( () => {
                if ( ! menupop.contains( document.activeElement ) ) hideOverlay( wrapper );
            }, 50 );
        } );
    }

    function initAdminBar() {
        const adminBar = document.getElementById( 'wpadminbar' );
        if ( ! adminBar ) return;

        document.querySelectorAll( ADMINBAR_TOP ).forEach( topMenupop => {
            bindAdminBarMenupop( topMenupop, adminBar, true );
            topMenupop.querySelectorAll( 'li.menupop' ).forEach( nested => {
                bindAdminBarMenupop( nested, adminBar, false );
            } );
        } );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SIDEBAR
    // ═══════════════════════════════════════════════════════════════════════════

    const SIDEBAR_TRIGGER = '#adminmenu .wp-not-current-submenu';

    function bindSidebarItem( item, insertBefore ) {
        const wrapper = item.querySelector( '.wp-submenu' );
        if ( ! wrapper ) return;

        clearBackground( wrapper );

        item.addEventListener( 'mouseenter', () => showOverlay( wrapper, insertBefore, false ) );
        item.addEventListener( 'mouseleave', () => hideOverlay( wrapper ) );
        wrapper.addEventListener( 'mouseover', () => cancelHide( wrapper ) );
    }

    function initSidebar() {
        const adminMenu = document.getElementById( 'adminmenu' );
        if ( ! adminMenu ) return;

        document.querySelectorAll( SIDEBAR_TRIGGER ).forEach( item => {
            bindSidebarItem( item, adminMenu );
        } );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // INIT
    // ═══════════════════════════════════════════════════════════════════════════

    function init() {
        initAdminBar();
        initSidebar();
        requestAnimationFrame( trackAll );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

} )();
