<?php
/**
 * Front-end script loading.
 *
 * Loads jQuery, underscore, wp-util and every script that depends on jQuery with
 * the defer attribute, so they stop blocking the first render.
 *
 * Deferred scripts run in document order after the HTML is parsed and before
 * DOMContentLoaded. A script printed in the footer therefore still runs after
 * jQuery in the head, but only if it is deferred too. A blocking script that
 * needs jQuery would run first and fail, which is why dependents are deferred
 * along with jQuery itself.
 *
 * Inline scripts cannot be deferred. An inline script that calls jQuery while
 * the page is still being parsed would fail, so a leading
 * jQuery( document ).ready( ... ) or jQuery( function ... ) in an inline script
 * is rewritten to imdgReady( ... ), which waits for DOMContentLoaded.
 *
 * Per-site changes go through these filters:
 *   imdg_defer_scripts          handles always deferred
 *   imdg_defer_scripts_exclude  handles that must stay blocking
 *   imdg_defer_scripts_enabled  return false to turn the feature off
 */

defined( 'ABSPATH' ) || exit;

/**
 * Requests where script loading is left alone.
 *
 * @return bool
 */
function imdg_scripts_skip_request() {
    if ( is_admin() || is_customize_preview() || is_feed() ) {
        return true;
    }

    if ( ! apply_filters( 'imdg_defer_scripts_enabled', true ) ) {
        return true;
    }

    // Leave the Bricks builder and its preview iframe untouched.
    foreach ( array( 'bricks_is_builder', 'bricks_is_builder_main', 'bricks_is_builder_iframe' ) as $fn ) {
        if ( function_exists( $fn ) && $fn() ) {
            return true;
        }
    }

    return false;
}

/**
 * Handles that are always deferred.
 *
 * @return string[]
 */
function imdg_scripts_to_defer() {
    return apply_filters( 'imdg_defer_scripts', array(
        'jquery-core',
        'jquery-migrate',
        'underscore',
        'wp-util',
    ) );
}

/**
 * Handles that must stay blocking.
 *
 * @return string[]
 */
function imdg_scripts_to_keep_blocking() {
    return apply_filters( 'imdg_defer_scripts_exclude', array() );
}

/**
 * Whether a registered script depends on one of the target handles,
 * directly or through other scripts.
 *
 * @param string   $handle  Script handle.
 * @param string[] $targets Handles to look for.
 * @param array    $seen    Handles already visited.
 * @return bool
 */
function imdg_script_depends_on( $handle, array $targets, array &$seen = array() ) {
    if ( isset( $seen[ $handle ] ) ) {
        return false;
    }
    $seen[ $handle ] = true;

    $registered = wp_scripts()->registered;
    if ( empty( $registered[ $handle ] ) ) {
        return false;
    }

    foreach ( (array) $registered[ $handle ]->deps as $dep ) {
        if ( in_array( $dep, $targets, true ) || imdg_script_depends_on( $dep, $targets, $seen ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Adds defer to the script tag. The filter receives the tag together with any
 * inline "before" and "after" scripts for the handle, so only the tag that has
 * a src attribute is changed.
 *
 * @param string $tag    Script markup.
 * @param string $handle Script handle.
 * @param string $src    Script URL.
 * @return string
 */
function imdg_defer_script_tag( $tag, $handle, $src ) {
    if ( imdg_scripts_skip_request() ) {
        return $tag;
    }

    if ( ! preg_match( '/<script\b[^>]*\ssrc=[^>]*>/i', $tag, $src_tag ) ) {
        return $tag;
    }

    // Already deferred, async, a module, or handled by the Perfmatters delay.
    if ( preg_match( '/\s(?:defer|async)(?=[\s=>])|\stype=["\']module["\']|pmdelayedscript/i', $src_tag[0] ) ) {
        return $tag;
    }

    if ( in_array( $handle, imdg_scripts_to_keep_blocking(), true ) ) {
        return $tag;
    }

    $needs_defer = in_array( $handle, imdg_scripts_to_defer(), true )
        || imdg_script_depends_on( $handle, array( 'jquery', 'jquery-core', 'jquery-migrate' ) );

    if ( ! $needs_defer ) {
        return $tag;
    }

    $tag = preg_replace( '/<script\b(?=[^>]*\ssrc=)/i', '<script defer', $tag, 1 );

    // Inline "after" scripts run while the page is still parsing, before the deferred
    // file has executed. Hold back the ones that use jQuery until DOMContentLoaded.
    if ( ! preg_match( '/<script\b[^>]*\ssrc=[^>]*>\s*<\/script>/i', $tag, $full, PREG_OFFSET_CAPTURE ) ) {
        return $tag;
    }
    $pos = $full[0][1] + strlen( $full[0][0] );
    $head = substr( $tag, 0, $pos );
    $tail = substr( $tag, $pos );

    $tail = preg_replace_callback(
        '/(<script\b(?![^>]*\bsrc=)(?![^>]*pmdelayedscript)(?![^>]*\btype=["\'](?!text\/javascript)[^"\']*["\'])[^>]*>)(.*?)(<\/script>)/is',
        function ( $m ) {
            if ( ! preg_match( '/\bjQuery\b/', $m[2] ) || false !== strpos( $m[2], 'imdgReady' ) ) {
                return $m[0];
            }
            return $m[1] . 'document.addEventListener("DOMContentLoaded",function(){' . $m[2] . "\n" . '});' . $m[3];
        },
        $tail
    );

    return $head . $tail;
}
add_filter( 'script_loader_tag', 'imdg_defer_script_tag', 20, 3 );

/**
 * Helper that inline scripts can call before jQuery has loaded. It waits for
 * DOMContentLoaded, by which time the deferred scripts have run.
 */
function imdg_print_ready_helper() {
    if ( imdg_scripts_skip_request() ) {
        return;
    }

    echo '<script id="imdg-ready">window.imdgReady=function(f){document.addEventListener("DOMContentLoaded",function(){window.jQuery(f);},{once:true});};</script>' . "\n";
}
add_action( 'wp_head', 'imdg_print_ready_helper', 1 );

/**
 * Rewrites a leading jQuery( document ).ready( or jQuery( function in an inline
 * script to imdgReady(. Scripts with a src, a delayed type or a non-JavaScript
 * type are not touched.
 *
 * @param string $html Page markup.
 * @return string
 */
function imdg_rewrite_inline_jquery_ready( $html ) {
    // The helper must be on the page, otherwise there is nothing to call.
    if ( false === strpos( $html, 'id="imdg-ready"' ) ) {
        return $html;
    }

    $pattern = '/(<script\b(?![^>]*\bsrc=)(?![^>]*pmdelayedscript)(?![^>]*\btype=["\'](?!text\/javascript)[^"\']*["\'])[^>]*>\s*)jQuery\(\s*(document\s*\)\s*\.ready\(|function)/i';

    return preg_replace_callback(
        $pattern,
        function ( $m ) {
            $replacement = 0 === stripos( $m[2], 'document' ) ? 'imdgReady(' : 'imdgReady(function';
            return $m[1] . $replacement;
        },
        $html
    );
}

add_action( 'template_redirect', function () {
    if ( imdg_scripts_skip_request() ) {
        return;
    }
    ob_start( 'imdg_rewrite_inline_jquery_ready' );
}, 2 );
