<?php
/**
 * Bricks builder tools: Quick Add Bar and Variable Picker.
 *
 * Adapted from Bricks Boost (James Lively) with the developer's permission.
 * Fixed configuration, no settings page. The DOM ids and class names
 * (#bricks-element-shortcuts-bar, .brxbo-bar-*, .brxbo-vp-*) match Bricks
 * Boost so the existing Dark Glass rules in bricks-editor.css and the bar
 * relocation script in bricks-compat.php keep working unchanged.
 *
 * Both features stand down while Bricks Boost is active, so the builder
 * never shows two bars or two pickers.
 */

defined( 'ABSPATH' ) || exit;

// ── Quick Add Bar elements, top to bottom ─────────────────────────────────
// 'separator' draws a divider line. Icons are the Bricks element icons.

function imdg_bbt_quick_add_elements() {
	return [
		[ 'name' => 'section',    'label' => 'Section',    'icon' => 'ti-layout-accordion-separated' ],
		[ 'name' => 'container',  'label' => 'Container',  'icon' => 'ti-layout-width-default' ],
		[ 'name' => 'block',      'label' => 'Block',      'icon' => 'ti-layout-width-full' ],
		[ 'name' => 'separator' ],
		[ 'name' => 'heading',    'label' => 'Heading',    'icon' => 'ti-text' ],
		[ 'name' => 'text-basic', 'label' => 'Basic Text', 'icon' => 'ti-align-justify' ],
		[ 'name' => 'text',       'label' => 'Rich Text',  'icon' => 'ti-align-left' ],
		[ 'name' => 'text-link',  'label' => 'Text Link',  'icon' => 'ti-link' ],
		[ 'name' => 'button',     'label' => 'Button',     'icon' => 'ti-control-stop' ],
		[ 'name' => 'code',       'label' => 'Code',       'icon' => 'ion-ios-code' ],
		[ 'name' => 'separator' ],
		[ 'name' => 'image',      'label' => 'Image',      'icon' => 'ti-image' ],
		[ 'name' => 'icon',       'label' => 'Icon',       'icon' => 'ti-star' ],
		[ 'name' => 'svg',        'label' => 'SVG',        'icon' => 'ti-vector' ],
	];
}

// ── Variable Picker: input type => Bricks variable category ───────────────
// 'palette' reads a color palette by name; 'category' reads a global
// variable category by name. 'layout' is 'grid' (swatches) or 'list'.

function imdg_bbt_picker_map() {
	return [
		'color'         => [ 'palette'  => 'Default',         'layout' => 'grid' ],
		'typography'    => [ 'category' => 'Typography',      'layout' => 'list' ],
		'spacing'       => [ 'category' => 'Spacing',         'layout' => 'list' ],
		'border-radius' => [ 'category' => 'Rounded corners', 'layout' => 'list' ],
		'grid'          => [ 'category' => 'Grids',           'layout' => 'list' ],
	];
}

/**
 * Build the picker data: one entry per mapped input type that has variables.
 * Each item: label (shown), insert (written into the input), group, and for
 * colors swatch (CSS background) and shade (palette shade letter or 'base').
 */
function imdg_bbt_picker_data() {
	$data = [];

	$palettes   = get_option( 'bricks_color_palette', [] );
	$categories = get_option( 'bricks_global_variables_categories', [] );
	$variables  = get_option( 'bricks_global_variables', [] );

	foreach ( imdg_bbt_picker_map() as $type => $map ) {
		$items = [];

		if ( isset( $map['palette'] ) && is_array( $palettes ) ) {
			foreach ( $palettes as $palette ) {
				if ( ( $palette['name'] ?? '' ) !== $map['palette'] || empty( $palette['colors'] ) ) {
					continue;
				}
				foreach ( $palette['colors'] as $color ) {
					if ( empty( $color['id'] ) ) {
						continue;
					}
					$raw    = (string) ( $color['raw'] ?? '' );
					$swatch = $raw !== '' ? $raw : (string) ( $color['light'] ?? $color['hex'] ?? '' );
					$insert = strpos( $raw, 'var(' ) === 0 ? $raw : 'var(--bricks-color-' . $color['id'] . ')';
					$group  = 'other';
					$shade  = 'base';
					if ( preg_match( '/^var\(--([a-zA-Z0-9_-]+?)(?:-([a-zA-Z])-\d+)?\)$/', $raw, $m ) ) {
						$group = $m[1];
						$shade = isset( $m[2] ) ? strtolower( $m[2] ) : 'base';
					}
					$items[] = [
						'label'  => $raw !== '' ? $raw : (string) ( $color['name'] ?? $swatch ),
						'insert' => $insert,
						'swatch' => $swatch,
						'group'  => $group,
						'shade'  => $shade,
					];
				}
			}
		}

		if ( isset( $map['category'] ) && is_array( $categories ) && is_array( $variables ) ) {
			$cat_id = '';
			foreach ( $categories as $cat ) {
				if ( ( $cat['name'] ?? '' ) === $map['category'] ) {
					$cat_id = $cat['id'] ?? '';
					break;
				}
			}
			if ( $cat_id !== '' ) {
				foreach ( $variables as $var ) {
					if ( empty( $var['name'] ) || ( $var['category'] ?? '' ) !== $cat_id ) {
						continue;
					}
					$items[] = [
						'label'  => $var['name'],
						'insert' => 'var(--' . $var['name'] . ')',
						'group'  => strtok( $var['name'], '-' ),
					];
				}
			}
		}

		if ( $items ) {
			$data[ $type ] = [
				'title'  => isset( $map['palette'] ) ? 'Palette: ' . $map['palette'] : $map['category'],
				'layout' => $map['layout'],
				'items'  => $items,
			];
		}
	}

	return $data;
}

// ── Enqueue in the builder (main window only, not the canvas iframe) ──────

add_action( 'wp_enqueue_scripts', function () {
	if ( ! ( function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main() ) ) {
		return;
	}
	if ( class_exists( 'Bricks_Boost' ) ) {
		return;
	}

	$css = IMDG_PLUGIN_DIR . 'assets/css/bricks-builder-tools.css';
	wp_enqueue_style(
		'imdg-bricks-builder-tools',
		IMDG_PLUGIN_URL . 'assets/css/bricks-builder-tools.css',
		[],
		file_exists( $css ) ? filemtime( $css ) : IMDG_VERSION
	);

	$qa = IMDG_PLUGIN_DIR . 'assets/js/bricks-quick-add.js';
	wp_enqueue_script(
		'imdg-bricks-quick-add',
		IMDG_PLUGIN_URL . 'assets/js/bricks-quick-add.js',
		[],
		file_exists( $qa ) ? filemtime( $qa ) : IMDG_VERSION,
		true
	);
	wp_localize_script( 'imdg-bricks-quick-add', 'imdgQuickAdd', imdg_bbt_quick_add_elements() );

	$picker = imdg_bbt_picker_data();
	if ( $picker ) {
		$vp = IMDG_PLUGIN_DIR . 'assets/js/bricks-var-picker.js';
		wp_enqueue_script(
			'imdg-bricks-var-picker',
			IMDG_PLUGIN_URL . 'assets/js/bricks-var-picker.js',
			[],
			file_exists( $vp ) ? filemtime( $vp ) : IMDG_VERSION,
			true
		);
		wp_localize_script( 'imdg-bricks-var-picker', 'imdgVarPicker', $picker );
	}
}, 20 );

// ── Quick Access -> Pinned panel switcher ───────────────────────────────
// Independent of Bricks Boost, so it loads even when Boost is active.

add_action( 'wp_enqueue_scripts', function () {
	if ( ! ( function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main() ) ) {
		return;
	}
	$qc = IMDG_PLUGIN_DIR . 'assets/css/bricks-quick-access-pinned.css';
	wp_enqueue_style(
		'imdg-bricks-quick-access-pinned',
		IMDG_PLUGIN_URL . 'assets/css/bricks-quick-access-pinned.css',
		[],
		file_exists( $qc ) ? filemtime( $qc ) : IMDG_VERSION
	);
	$qp = IMDG_PLUGIN_DIR . 'assets/js/bricks-quick-access-pinned.js';
	wp_enqueue_script(
		'imdg-bricks-quick-access-pinned',
		IMDG_PLUGIN_URL . 'assets/js/bricks-quick-access-pinned.js',
		[],
		file_exists( $qp ) ? filemtime( $qp ) : IMDG_VERSION,
		true
	);
}, 20 );
