<?php
/**
 * Miscellaneous WordPress admin behaviour tweaks.
 *
 * These are functional changes (security hardening, UX improvements, email
 * suppression) that ship alongside the visual theme. They are separated from
 * the enqueueing logic so each concern lives in its own file.
 */

defined( 'ABSPATH' ) || exit;


// ── Email suppression ─────────────────────────────────────────────────────────

// New user registration admin notification
add_filter( 'wp_new_user_notification_email_admin', '__return_false' );

// Password reset admin notification
add_filter( 'wp_password_change_notification_email', '__return_false' );

// Auto-update email notifications
add_filter( 'auto_plugin_update_send_email', '__return_false' );

// Fires after password reset — default action sends an email
remove_action( 'after_password_reset', 'wp_password_change_notification' );

// WooCommerce password change notification
add_filter( 'woocommerce_disable_password_change_notification', '__return_true' );

add_action( 'woocommerce_email', function ( $mailer ) {
    foreach ( $mailer->emails as $email ) {
        remove_action(
            'woocommerce_email_footer',
            [ $email, 'mobile_messaging' ],
            9
        );
    }
} );

// ── Admin notices ─────────────────────────────────────────────────────────────

// Hide all admin notices for non-administrator users
add_action( 'admin_head', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        remove_all_actions( 'admin_notices' );
    }
}, 1 );

// ── Admin bar ────────────────────────────────────────────────────────────────

// Hide the admin bar on the front end for everyone except administrators
add_action( 'after_setup_theme', function () {
    if ( ! current_user_can( 'administrator' ) && ! is_admin() ) {
        show_admin_bar( false );
    }
} );

// ── Login / session ───────────────────────────────────────────────────────────

// Extend the authentication cookie lifetime to 4 weeks
add_filter( 'auth_cookie_expiration', function () {
    return 4 * WEEK_IN_SECONDS;
} );

// Pre-check the "Remember Me" checkbox on the login form
add_action( 'init', function () {
    add_filter( 'login_footer', function () {
        echo "<script>document.getElementById('rememberme').checked = true;</script>\n";
    } );
} );

// ── WordPress core behaviour ──────────────────────────────────────────────────

// Suppress the "browser happy" check that pings api.wordpress.org
add_filter( 'pre_http_request', function ( $ret, array $request, string $url ) {
    if ( preg_match( '!^https?://api\.wordpress\.org/core/browse-happy/!i', $url ) ) {
        return new WP_Error(
            'http_request_failed',
            sprintf( 'Request to %s is not allowed.', $url )
        );
    }
    return $ret;
}, 10, 3 );

// Restrict Gutenberg's internal link-search dropdown to useful post types only
// (removes attachments / media items from suggestions).
// Guarded to REST API requests only so AJAX-based plugins (e.g. Admin Columns
// Pro) are not affected by this post type restriction.
add_filter( 'pre_get_posts', function ( $query ) {
    if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
        return $query;
    }
    if ( wp_doing_ajax() ) {
        return $query;
    }
    if ( $query->is_search() ) {
        $query->set( 'post_type', [ 'post', 'page', 'product', 'faq' ] );
    }
    return $query;
} );

/**
 * Restrict generated image size variants to an explicit allowlist.
 *
 * Keeps: thumbnail, medium, large, cfw_cart_thumb, and the original upload.
 * All other registered sizes (medium_large, 1536x1536, 2048x2048, WooCommerce
 * sizes, theme sizes, etc.) are suppressed at generation time.
 *
 * Hooks into intermediate_image_sizes_advanced so the filter fires for both
 * new uploads and any manual regeneration via WP-CLI or a plugin.
 */
 
add_filter( 'intermediate_image_sizes_advanced', 'imonkeys_restrict_image_sizes', 99 );
 
function imonkeys_restrict_image_sizes( $new_sizes ) {
    $allowed = [
        'thumbnail',
        'medium',
        'large',
        'cfw_cart_thumb',
    ];
 
    foreach ( $new_sizes as $size_name => $size_data ) {
        if ( ! in_array( $size_name, $allowed, true ) ) {
            unset( $new_sizes[ $size_name ] );
        }
    }
 
    return $new_sizes;
}

/* Ensure WPCodebox stylesheets are loaded before Bricks stylesheets */
add_action( 'template_redirect', function() {
    ob_start( function( $buffer ) {
        if ( preg_match_all( '/(<link[^>]+wpcb2-external-style[^>]*>)/i', $buffer, $matches ) ) {
            // Remove all WPCodebox link tags from wherever they are
            foreach ( $matches[1] as $link ) {
                $buffer = str_replace( $link, '', $buffer );
            }
            // Reinsert them all together immediately after <head>
            $all_links = "\n" . implode( "\n", $matches[1] );
            $buffer    = str_replace( '<head>', '<head>' . $all_links, $buffer );
        }
        return $buffer;
    });
}, 1 );

