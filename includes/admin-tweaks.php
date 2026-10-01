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

/**
 * Keep WPCodeBox external stylesheets ahead of Bricks stylesheets.
 *
 * Only <link class="wpcb2-external-style"> tags that sit inside <head> AFTER the
 * first Bricks stylesheet are moved, and they are inserted immediately before it.
 * Links printed in the footer (or anywhere after </head>) are left where they are,
 * and so are links that already come before Bricks.
 */
add_action( 'template_redirect', function () {
    if ( is_feed() || is_robots() || is_trackback() ) {
        return;
    }
    ob_start( 'imonkeys_wpcb_before_bricks' );
}, 1 );

function imonkeys_wpcb_before_bricks( $buffer ) {
    $head_end = stripos( $buffer, '</head>' );
    if ( false === $head_end ) {
        return $buffer;
    }

    $head = substr( $buffer, 0, $head_end );
    $rest = substr( $buffer, $head_end );

    // First Bricks stylesheet in the head (Bricks core, child theme, post CSS, etc.).
    // Bricks Advanced Themer is skipped because it is a separate plugin.
    $anchor_regex = '/<(?:link|style)\b[^>]*\sid=["\']bricks-(?!advanced-themer)[^"\']*["\'][^>]*>/i';
    if ( ! preg_match( $anchor_regex, $head, $anchor, PREG_OFFSET_CAPTURE ) ) {
        return $buffer;
    }
    $anchor_pos = $anchor[0][1];

    $before = substr( $head, 0, $anchor_pos );
    $after  = substr( $head, $anchor_pos );

    // Pull WPCodeBox links out of the part of the head that follows the Bricks anchor.
    $moved = array();
    $after = preg_replace_callback(
        '/[ \t]*<link\b[^>]*\bclass=["\'][^"\']*\bwpcb2-external-style\b[^"\']*["\'][^>]*>[ \t]*\R?/i',
        function ( $m ) use ( &$moved ) {
            $moved[] = trim( $m[0] );
            return '';
        },
        $after
    );

    if ( empty( $moved ) ) {
        return $buffer;
    }

    return $before . implode( "\n", $moved ) . "\n" . $after . $rest;
}
