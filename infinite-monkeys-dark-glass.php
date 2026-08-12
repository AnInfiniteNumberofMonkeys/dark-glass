<?php
/**
 * Plugin Name:       Infinite Monkeys Dark Glass Admin Theme
 * Plugin URI:        https://infinitemonkeys.ca/wp-admin-theme
 * Description:       A clean, minimalist dark admin theme for WordPress with frosted glass effects and a more modern aesthetic for fellow night owls. Compatible with WP Desktop Mode for those who also value efficiency.
 * Version:           1.4.4
 * Author:            An Infinite Number of Monkeys
 * Author URI:        https://infinitemonkeys.ca
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Text Domain:       infinite-monkeys-dark-glass
 */

defined( 'ABSPATH' ) || exit;

// ── Plugin constants ────────────────────────────────────────────────────────
define( 'IMDG_VERSION',    '1.4.4' );
define( 'IMDG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IMDG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// ── Load modules ────────────────────────────────────────────────────────────
require_once IMDG_PLUGIN_DIR . 'includes/enqueue.php';
require_once IMDG_PLUGIN_DIR . 'includes/editor-styles.php';
require_once IMDG_PLUGIN_DIR . 'includes/codemirror.php';
require_once IMDG_PLUGIN_DIR . 'includes/admin-tweaks.php';
require_once IMDG_PLUGIN_DIR . 'includes/bricks-compat.php';
require_once IMDG_PLUGIN_DIR . 'includes/editor-asset-trim.php';
require_once IMDG_PLUGIN_DIR . 'includes/updater.php';
require_once IMDG_PLUGIN_DIR . 'includes/desktop-mode-plugins-editor-tab.php';
require_once IMDG_PLUGIN_DIR . 'includes/desktop-mode-os-settings-seed.php';
