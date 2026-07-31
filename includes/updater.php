<?php
/**
 * Plugin update checker.
 *
 * Wires up Plugin Update Checker (vendored in vendor/plugin-update-checker,
 * https://github.com/YahnisElsts/plugin-update-checker) so every site
 * running this plugin sees a normal wp-admin update notification whenever
 * a new tagged release goes up on GitHub.
 *
 * The repo (https://github.com/AnInfiniteNumberofMonkeys/dark-glass) is
 * public, so no authentication token is needed anywhere - unlike a
 * private-repo setup, this requires zero configuration on any site, ever.
 *
 * To release an update:
 *   1. Bump the version in the main plugin file (the "Version:" header
 *      AND the IMDG_VERSION constant - both need to match and both need
 *      to go up) and in readme.txt's "Stable tag:".
 *   2. Push the change to the main branch on GitHub.
 *   3. On GitHub, go to the repo's "Releases" page and draft a new
 *      release, creating a new tag named vX.Y.Z to match the version
 *      from step 1 exactly, and publish it.
 *
 * WordPress checks for updates every 12 hours, or immediately when the
 * user visits Dashboard -> Updates and clicks "Check Again".
 *
 * This replaces the old self-hosted JSON-manifest update mechanism
 * (which checked infinitemonkeys.ca/wp-content/uploads/dark-glass/
 * infinite-monkeys-dark-glass.json). That file and the manual
 * zip-upload-and-edit-JSON process are no longer used once a site is
 * running a version of the plugin that contains this file - see the
 * migration note in readme.txt for how existing sites get onto it.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', function () {
	$loader = IMDG_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
	if ( ! file_exists( $loader ) ) {
		return;
	}
	require_once $loader;

	$update_checker = \YahnisElsts\PluginUpdateChecker\v5p7\PucFactory::buildUpdateChecker(
		'https://github.com/AnInfiniteNumberofMonkeys/dark-glass/',
		IMDG_PLUGIN_DIR . 'infinite-monkeys-dark-glass.php',
		'infinite-monkeys-dark-glass'
	);

	// Use GitHub releases (not just tags) as the update source, so a
	// changelog can be attached to each release. No setAuthentication()
	// call needed - the repo is public.
	$update_checker->getVcsApi()->enableReleaseAssets();
} );
