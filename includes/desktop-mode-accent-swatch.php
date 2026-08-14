<?php
/**
 * Infinite Monkeys Dark Glass — Accent swatch sync.
 *
 * OpenStation's OS Settings → Appearance panel offers a fixed list of
 * accent-color swatches (see Desktop Mode's own includes/accents.php),
 * filterable via `openstation_accent_colors`. The first swatch,
 * "Pulse", ships as OpenStation's own brand pink (#f252fc).
 *
 * This site's own admin theme defines its own brand accent as the CSS
 * custom property --color-accent (see assets/css/admin.css, currently
 * #d700ff). This file overrides the "Pulse" swatch's value so the
 * OS Settings picker offers OUR accent color under that first swatch
 * instead of OpenStation's default pink.
 *
 * PHP can't read a CSS custom property at runtime, so the hex below is
 * a plain literal that must be kept in sync by hand with --color-accent
 * in assets/css/admin.css. If that variable's value ever changes,
 * update IMDG_PULSE_SWATCH_HEX below to match.
 *
 * Never touches the Desktop Mode / OpenStation plugin's own files.
 *
 * @package Infinite_Monkeys_Dark_Glass
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keep in sync with --color-accent in assets/css/admin.css.
 */
const IMDG_PULSE_SWATCH_HEX = '#d700ff';

add_filter( 'openstation_accent_colors', function ( $colors ) {
	if ( ! is_array( $colors ) ) {
		return $colors;
	}
	foreach ( $colors as &$entry ) {
		if ( is_array( $entry ) && isset( $entry['id'] ) && 'pulse' === $entry['id'] ) {
			$entry['value'] = IMDG_PULSE_SWATCH_HEX;
		}
	}
	unset( $entry );
	return $colors;
} );
