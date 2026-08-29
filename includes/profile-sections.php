<?php
/**
 * Hides selected sections of the standard WordPress user profile screen
 * (profile.php / user-edit.php):
 *   - Personal Options (Visual Editor / Admin Color Scheme / etc.)
 *   - Username and Nickname rows under Name
 *   - Website row under Contact Info
 *
 * Username, Nickname, and Website are hidden via CSS (see the "User
 * profile forms" block in admin.css) since core gives each of those
 * table rows a stable, unique class. Personal Options has no such
 * class on either its <h2> heading or the <table> that follows it, so
 * that section is hidden via a small JS file instead -- see
 * assets/js/profile-sections-hide.js for the full explanation.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_enqueue_scripts', function ( $hook ) {

	if ( ! in_array( $hook, [ 'profile.php', 'user-edit.php' ], true ) ) {
		return;
	}

	$profile_sections_hide_path = IMDG_PLUGIN_DIR . 'assets/js/profile-sections-hide.js';
	wp_enqueue_script(
		'imdg-profile-sections-hide',
		IMDG_PLUGIN_URL . 'assets/js/profile-sections-hide.js',
		[],
		file_exists( $profile_sections_hide_path ) ? filemtime( $profile_sections_hide_path ) : IMDG_VERSION,
		true // Load in footer
	);

} );
