<?php
/**
 * Rocket.net CDN cache purge from the admin bar.
 *
 * The Rocket.net mu-plugin adds "CDN Cache > Purge Everything" to the admin bar
 * as a plain link ending in cdn-action=purge, and it purges when WordPress
 * loads that URL. With OpenStation (Desktop Mode) active the click is not
 * delivered as a normal page load: no purge request reaches WordPress, so
 * nothing is purged and the success notice never appears.
 *
 * This file sends the click to admin-ajax.php instead. The handler calls the
 * mu-plugin's own CDN_Clear_Cache_Hooks::purge_cache() and the result is shown
 * in a small notice that works in the desktop shell, in wp-admin and on the
 * front end. The original link stays in place as the fallback when JavaScript
 * is off.
 *
 * Turn it off with:
 * add_filter( 'imdg_cdn_purge_enabled', '__return_false' );
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the purge button override should run for the current user.
 *
 * @return bool
 */
function imdg_cdn_purge_enabled() {
	if ( ! apply_filters( 'imdg_cdn_purge_enabled', true ) ) {
		return false;
	}

	if ( ! is_callable( array( 'CDN_Clear_Cache_Hooks', 'purge_cache' ) ) ) {
		return false;
	}

	return current_user_can( 'manage_options' );
}

/**
 * Turns the "messages" list from the Rocket.net API response into plain text.
 *
 * @param mixed $messages Value of $result->messages.
 * @return string
 */
function imdg_cdn_purge_messages_to_text( $messages ) {
	$out = array();

	foreach ( (array) $messages as $message ) {
		if ( is_scalar( $message ) ) {
			$text = (string) $message;
		} else {
			$parts = (array) $message;
			$text  = isset( $parts['message'] ) ? (string) $parts['message'] : '';
		}

		$text = sanitize_text_field( $text );
		if ( '' !== $text ) {
			$out[] = $text;
		}
	}

	return implode( ', ', $out );
}

/**
 * AJAX handler: purge everything through the Rocket.net mu-plugin.
 */
function imdg_cdn_purge_ajax() {
	check_ajax_referer( 'imdg_cdn_purge', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array( 'message' => __( 'You do not have permission to purge the CDN cache.', 'infinite-monkeys-dark-glass' ) ),
			403
		);
	}

	if ( ! is_callable( array( 'CDN_Clear_Cache_Hooks', 'purge_cache' ) ) ) {
		wp_send_json_error(
			array( 'message' => __( 'The Rocket.net CDN cache plugin is not loaded on this site.', 'infinite-monkeys-dark-glass' ) ),
			501
		);
	}

	$result = CDN_Clear_Cache_Hooks::purge_cache();

	if ( $result instanceof stdClass && ! empty( $result->success ) ) {
		wp_send_json_success(
			array( 'message' => __( 'CDN Cache has been Successfully Purged.', 'infinite-monkeys-dark-glass' ) )
		);
	}

	$message = __( 'The CDN cache could not be purged. Try again.', 'infinite-monkeys-dark-glass' );

	if ( $result instanceof stdClass && ! empty( $result->messages ) ) {
		$from_api = imdg_cdn_purge_messages_to_text( $result->messages );
		if ( '' !== $from_api ) {
			$message = $from_api;
		}
	}

	// The Rocket.net plugin stops sending purge requests after an HTTP 406 and
	// keeps them off until its api_requests_disabled file is deleted.
	if ( is_callable( array( 'CDN_Clear_Cache_Request_Guard', 'disabled' ) ) && CDN_Clear_Cache_Request_Guard::disabled() ) {
		$message = __( 'Purge requests are switched off after an earlier error from Rocket.net. Delete the api_requests_disabled file in the mu-plugins folder to turn them back on.', 'infinite-monkeys-dark-glass' );
	}

	wp_send_json_error( array( 'message' => $message ), 502 );
}
add_action( 'wp_ajax_imdg_cdn_purge', 'imdg_cdn_purge_ajax' );

/**
 * Prints the click handler. It runs in the head so it is registered before
 * most other scripts, and listens in the capture phase on window so it sees the
 * click first.
 */
function imdg_cdn_purge_print_script() {
	if ( ! imdg_cdn_purge_enabled() || ! is_admin_bar_showing() ) {
		return;
	}

	// OpenStation windows are iframes without a visible admin bar.
	if ( function_exists( 'openstation_is_chromeless_request' ) && openstation_is_chromeless_request() ) {
		return;
	}

	$config = wp_json_encode(
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'imdg_cdn_purge' ),
			'working' => __( 'Purging the CDN cache...', 'infinite-monkeys-dark-glass' ),
			/* translators: %d: HTTP status code. */
			'failed'  => __( 'The purge request failed (HTTP %d). Reload the page and try again.', 'infinite-monkeys-dark-glass' ),
			'network' => __( 'Could not reach the server. Check your connection and try again.', 'infinite-monkeys-dark-glass' ),
		),
		JSON_HEX_TAG | JSON_HEX_AMP
	);
	?>
<script id="imdg-cdn-purge">
(function () {
	if (window.imdgCdnPurgeBound) { return; }
	window.imdgCdnPurgeBound = true;

	var cfg = <?php echo $config; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode with JSON_HEX_TAG. ?>;
	var PURGE_URL = /[?&]cdn-action=purge(?:&|#|$)/;
	var busy = false;
	var timer = null;

	function notify(message, ok) {
		var box = document.getElementById('imdg-cdn-notice');
		if (!box) {
			box = document.createElement('div');
			box.id = 'imdg-cdn-notice';
			box.setAttribute('role', 'status');
			box.setAttribute('aria-live', 'polite');
			box.style.cssText = 'position:fixed;top:48px;right:16px;z-index:2147483000;max-width:min(420px,calc(100vw - 32px));padding:12px 16px;border-radius:6px;font:14px/1.45 system-ui,-apple-system,"Segoe UI",sans-serif;box-shadow:0 8px 28px rgba(0,0,0,.35);color:#fff;';
			(document.body || document.documentElement).appendChild(box);
		}
		box.style.background = ok === false ? '#9c2b1e' : (ok === true ? '#17633f' : '#27323a');
		box.textContent = message;
		box.hidden = false;
		clearTimeout(timer);
		if (ok !== null) {
			timer = setTimeout(function () { box.hidden = true; }, 6000);
		}
	}

	function purge() {
		if (busy) { return; }
		busy = true;
		notify(cfg.working, null);

		var body = new URLSearchParams();
		body.set('action', 'imdg_cdn_purge');
		body.set('nonce', cfg.nonce);

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		})
			.then(function (res) {
				return res.json().catch(function () { return null; }).then(function (data) {
					return { status: res.status, data: data };
				});
			})
			.then(function (out) {
				if (out.data && out.data.success) {
					notify(out.data.data.message, true);
					return;
				}
				var message = out.data && out.data.data && out.data.data.message
					? out.data.data.message
					: cfg.failed.replace('%d', out.status);
				notify(message, false);
			})
			.catch(function () { notify(cfg.network, false); })
			.then(function () { busy = false; });
	}

	window.addEventListener('click', function (event) {
		if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) { return; }
		var link = event.target && event.target.closest ? event.target.closest('a') : null;
		if (!link) { return; }
		var isPurge = !!link.closest('#wp-admin-bar-cdn_menu_purge_everything') || PURGE_URL.test(link.getAttribute('href') || '');
		if (!isPurge) { return; }
		event.preventDefault();
		event.stopImmediatePropagation();
		purge();
	}, true);
}());
</script>
	<?php
}
add_action( 'wp_head', 'imdg_cdn_purge_print_script', 1 );
add_action( 'admin_head', 'imdg_cdn_purge_print_script', 1 );
