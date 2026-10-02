<?php
/**
 * Front-end script loading.
 *
 * Loads jQuery, underscore, wp-util and every script that depends on any of
 * them with the defer attribute, so they stop blocking the first render.
 *
 * Deferred scripts run in document order after the HTML is parsed and before
 * DOMContentLoaded. A script printed in the footer therefore still runs after
 * jQuery in the head, but only if it is deferred too. A blocking script that
 * needs jQuery or underscore would run first and fail, which is why dependents
 * are deferred along with the libraries themselves.
 *
 * Inline scripts attached to a deferred handle with wp_add_inline_script()
 * ("before" and "after") cannot simply stay inline, because they would run
 * while the page is still being parsed, ahead of the deferred file they belong
 * to. They are re-emitted as deferred data: URI scripts, which keeps them in
 * the same position in the deferred queue (before script, file, after script).
 *
 * Inline scripts that are not attached to a handle, and that start with a
 * leading jQuery( document ).ready( ... ) or jQuery( function ... ), are
 * rewritten to imdgReady( ... ), which waits for DOMContentLoaded.
 *
 * Per-site changes go through these filters:
 * imdg_defer_scripts handles always deferred
 * imdg_defer_scripts_exclude handles that must stay blocking
 * imdg_defer_scripts_enabled return false to turn the feature off
 * imdg_keep_jquery_blocking_on_products return false to defer jQuery on
 * single product pages too (default: blocking)
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
	$handles = array();

	if ( imdg_keep_jquery_blocking() ) {
		$handles = array( 'jquery-core', 'jquery-migrate' );
	}

	return apply_filters( 'imdg_defer_scripts_exclude', $handles );
}

/**
 * Whether jQuery stays blocking for this request.
 *
 * Bricks attaches its product gallery thumbnail slider handler on
 * DOMContentLoaded, while WooCommerce initializes the gallery from a jQuery
 * ready callback. With jQuery deferred, jQuery can already be ready while the
 * remaining deferred scripts download, so the gallery init fires before Bricks
 * is listening and the thumbnail slider stays hidden (opacity 0).
 * A blocking jQuery keeps those ready callbacks after DOMContentLoaded.
 * Scripts that depend on jQuery are still deferred.
 *
 * Return false from imdg_keep_jquery_blocking_on_products to turn this off.
 *
 * @return bool
 */
function imdg_keep_jquery_blocking() {
	$is_product = function_exists( 'is_product' ) && is_product();

	return (bool) apply_filters( 'imdg_keep_jquery_blocking_on_products', $is_product );
}

/**
 * Handles whose dependents must be deferred: jQuery plus every handle that is
 * itself deferred (underscore, wp-util and anything added by the
 * imdg_defer_scripts filter).
 *
 * @return string[]
 */
function imdg_defer_targets() {
	return array_values( array_unique( array_merge( array( 'jquery' ), imdg_scripts_to_defer() ) ) );
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
 * Re-emits an inline script as a deferred script so it stays in order with the
 * deferred file it belongs to. Empty scripts and scripts carrying a CSP nonce
 * are returned unchanged.
 *
 * The sourceURL comment keeps the original id (for example
 * editor-js-after) in console errors.
 *
 * @param string $open_tag Opening script tag, with its attributes.
 * @param string $code     Script body.
 * @return string
 */
function imdg_inline_to_deferred( $open_tag, $code ) {
	if ( '' === trim( $code ) || preg_match( '/\snonce=/i', $open_tag ) ) {
		return $open_tag . $code . '</script>';
	}

	$id = 'imdg-inline';
	if ( preg_match( '/\sid=["\']([^"\']+)["\']/i', $open_tag, $id_match ) ) {
		$id = $id_match[1];
	}

	$code .= "\n//# sourceURL=" . $id . '.js';

	return '<script defer id="' . esc_attr( $id ) . '" src="data:text/javascript;base64,' . base64_encode( $code ) . '"></script>';
}

/**
 * Adds defer to the script tag. The filter receives the tag together with any
 * inline "before" and "after" scripts for the handle, so only the tag that has
 * a src attribute gets the defer attribute, and the inline scripts around it
 * are converted to deferred scripts so the order is kept.
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
		|| imdg_script_depends_on( $handle, imdg_defer_targets() );

	if ( ! $needs_defer ) {
		return $tag;
	}

	$tag = preg_replace( '/<script\b(?=[^>]*\ssrc=)/i', '<script defer', $tag, 1 );

	if ( ! preg_match( '/<script\b[^>]*\ssrc=[^>]*>\s*<\/script>/i', $tag, $full, PREG_OFFSET_CAPTURE ) ) {
		return $tag;
	}

	$pos  = $full[0][1] + strlen( $full[0][0] );
	$head = substr( $tag, 0, $pos );
	$tail = substr( $tag, $pos );

	$inline = '/(<script\b(?![^>]*\bsrc=)(?![^>]*pmdelayedscript)(?![^>]*\btype=["\'](?!text\/javascript)[^"\']*["\'])[^>]*>)(.*?)(<\/script>)/is';

	// "before" scripts sit ahead of the file. The data (-js-extra) and
	// translation (-js-translations) scripts are left inline: they only set
	// variables or call a script that is not deferred.
	$head = preg_replace_callback(
		$inline,
		function ( $m ) {
			if ( ! preg_match( '/\sid=["\'][^"\']*-js-before["\']/i', $m[1] ) ) {
				return $m[0];
			}
			return imdg_inline_to_deferred( $m[1], $m[2] );
		},
		$head
	);

	// Everything after the file is an "after" script.
	$tail = preg_replace_callback(
		$inline,
		function ( $m ) {
			return imdg_inline_to_deferred( $m[1], $m[2] );
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
