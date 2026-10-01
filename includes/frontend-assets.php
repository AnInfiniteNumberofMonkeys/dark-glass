<?php
/**
 * Front-end stylesheet tweaks.
 *
 * Dequeues stylesheets the front end does not need and moves non-critical ones
 * from the head to the footer.
 *
 * The same lists ship on every site. A handle that is not enqueued on a given
 * request is ignored, so a missing plugin is harmless. Per-site changes go
 * through the imdg_dequeue_styles and imdg_footer_styles filters, and the whole
 * feature can be turned off with the imdg_optimize_frontend_styles filter.
 *
 * Handles are WordPress style handles, which is the element id without its
 * "-css" suffix: id="bricks-child-css" is the handle "bricks-child". A tag with
 * id="x-inline-css" is inline CSS attached to the handle "x", so it moves or is
 * removed together with that handle.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles to remove from the front end entirely.
 *
 * @return string[]
 */
function imdg_styles_to_dequeue() {
    return apply_filters( 'imdg_dequeue_styles', array(
        'bricks-child',
        'bricks-advanced-themer',
        'classic-theme-styles',
    ) );
}

/**
 * Handles to print in the footer instead of the head.
 *
 * @return string[]
 */
function imdg_styles_to_footer() {
    return apply_filters( 'imdg_footer_styles', array(
        'woocommerce-conditional-product-fees-for-checkout',
        'wc-aelia-cs-frontend',
        'cfw-blocks-styles',
        'woocommerce-inline',
        'bricks-woocommerce',
        'bricks-color-palettes',
        'bricks-global-variables',
        'bricks-theme-style-default',
        'x-notification-bar',
        'x-popover',
        'bricks-splide',
        'x-pro-slider',
        'x-read-more-less',
        'x-ws-forms',
        'cfw-grid',
        'cfw-side-cart-styles',
        'bricks-frontend-inline',
    ) );
}

/**
 * Runs just before wp_head prints styles (wp_print_styles is priority 8), so
 * everything enqueued on wp_enqueue_scripts is already in the queue.
 */
function imdg_optimize_frontend_styles() {
    if ( is_admin() || is_customize_preview() ) {
        return;
    }

    if ( ! apply_filters( 'imdg_optimize_frontend_styles', true ) ) {
        return;
    }

    // Leave the Bricks builder and its preview iframe untouched.
    foreach ( array( 'bricks_is_builder', 'bricks_is_builder_main', 'bricks_is_builder_iframe' ) as $fn ) {
        if ( function_exists( $fn ) && $fn() ) {
            return;
        }
    }

    $styles  = wp_styles();
    $dequeue = imdg_styles_to_dequeue();
    $footer  = array_diff( imdg_styles_to_footer(), $dequeue );

    // Taken from the queue so the original enqueue order is kept in the footer.
    $moved = array_values( array_intersect( $styles->queue, $footer ) );

    foreach ( $dequeue as $handle ) {
        wp_dequeue_style( $handle );
    }

    if ( empty( $moved ) ) {
        return;
    }

    foreach ( $moved as $handle ) {
        wp_dequeue_style( $handle );
    }

    // Print the handles directly instead of re-enqueuing them. Styles enqueued
    // during wp_footer are "late" styles, and WordPress 6.9+ hoists those back
    // into the head through its template enhancement output buffer.
    // Priority 5 keeps them ahead of WPCodeBox footer snippets (priority 10).
    add_action( 'wp_footer', function () use ( $moved ) {
        wp_print_styles( $moved );
    }, 5 );
}
add_action( 'wp_head', 'imdg_optimize_frontend_styles', 7 );
