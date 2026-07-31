<?php
/**
 * Bricks Builder compatibility filters.
 *
 * These filters correct or extend Bricks Builder behaviour and are only
 * registered when the Bricks functions they depend on exist — so the plugin
 * remains safe to install on sites where Bricks is not active.
 */

defined( 'ABSPATH' ) || exit;

// ── SVG element controls ──────────────────────────────────────────────────────
// Re-applies !important to the SVG stroke/fill controls, restoring the
// behaviour that was present in Bricks 2.1.4 and removed in later versions.

add_filter( 'bricks/elements/svg/controls', function ( $controls ) {
    foreach ( [ 'stroke', 'strokeWidth', 'fill' ] as $key ) {
        if ( isset( $controls[ $key ]['css'][0] ) ) {
            $controls[ $key ]['css'][0]['important'] = true;
        }
    }
    return $controls;
}, 10 );

// ── External links plugin ─────────────────────────────────────────────────────
// Prevents the WP External Links plugin from rewriting links inside the
// Bricks editor, which can cause builder UI issues.

add_filter( 'wpel_apply_settings', '__return_false' );

// ── Link / post picker ────────────────────────────────────────────────────────
// Removes media attachment posts from the Bricks link-picker dropdown so only
// searchable post types appear (consistent with the Gutenberg tweak above).

add_filter( 'bricks/helpers/get_posts_args', function ( $args ) {
    $post_types = get_post_types( [ 'exclude_from_search' => false ] );
    unset( $post_types['attachment'] );
    $args['post_type'] = $post_types;
    return $args;
} );

/**
 * Add "bricks-page" class to the .post-state span for Bricks pages on the
 * All Pages admin screen.
 *
 * WordPress renders one <span class="post-state"> per state, so we cannot
 * rely on querySelector() to find the right one. Instead, we inject a hidden
 * marker element into the Bricks state label via display_post_states, then
 * use JS to walk from the marker up to its parent .post-state span.
 */
 
add_filter( 'display_post_states', function ( $states ) {
 
    foreach ( $states as $key => $label ) {
        if ( stripos( $key, 'bricks' ) !== false ) {
            $states[ $key ] = $label . '<span class="imdg-bricks-marker" hidden></span>';
            break;
        }
    }
 
    return $states;
 
}, 99 );
 
// ── Bricks Boost shortcuts-bar DOM placement ──────────────────────────────────
// Bricks Boost appends #bricks-element-shortcuts-bar to document.body.
// Dark Glass repositions it as a CSS grid column inside #bricks-structure,
// but grid-column only works on direct children of the grid container.
// This observer waits for both elements to exist, then physically moves
// the bar into #bricks-structure so the CSS placement rules take effect.

add_action( 'wp_footer', function () {
    if ( ! ( function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main() ) ) {
        return;
    }
    ?>
    <script>
    (function () {
        var observer = new MutationObserver(function () {
            var bar       = document.querySelector('#bricks-element-shortcuts-bar');
            var structure = document.querySelector('#bricks-structure');
            if (bar && structure && bar.parentNode !== structure) {
                observer.disconnect();
                structure.appendChild(bar);
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    })();
    </script>
    <?php
} );

add_action( 'admin_footer-edit.php', function () {
 
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'page' ) {
        return;
    }
 
    ?>
    <script>
    ( function () {
        document.querySelectorAll( '.imdg-bricks-marker' ).forEach( function ( marker ) {
            var span = marker.closest( '.post-state' );
            if ( span ) {
                span.classList.add( 'bricks-page' );
            }
        } );
    } )();
    </script>
    <?php
 
} );