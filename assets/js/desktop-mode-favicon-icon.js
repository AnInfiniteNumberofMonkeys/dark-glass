/**
 * Infinite Monkeys Dark Glass — WP logo → site favicon (admin bar).
 *
 * The default WordPress logo on the top admin bar is a dashicon glyph
 * rendered via `content` on `.ab-icon::before` (hidden in admin.css).
 * This replaces it with the site's own favicon/Site Icon image.
 *
 * Applied via JS rather than a plain CSS background-image rule: for
 * reasons not fully pinned down, a stylesheet-declared background-image
 * on this specific element (#wp-admin-bar-wp-logo .ab-icon) is silently
 * ignored by the browser regardless of specificity or !important —
 * confirmed the browser never even attempts to fetch the image when set
 * via a <style>/<link> rule, while the exact same declaration set via
 * element.style.setProperty() applies and fetches immediately. Setting
 * it here as an inline style sidesteps whatever that is. (Every other
 * property on the same selector — background-color, border, etc. —
 * applies normally via the stylesheet; it's specific to background-image
 * on this element.)
 *
 * The URL is localized from PHP (see includes/enqueue.php) since it's
 * per-site data (get_site_icon_url()), not something a shared stylesheet
 * can know. If a site has no Site Icon configured, imdgFaviconIcon.url
 * is empty and this script is a no-op — enqueue.php's own CSS fallback
 * hides the logo item entirely in that case instead of leaving an empty
 * box.
 *
 * Re-applies on mutation in case Desktop Mode ever re-renders the
 * toolbar markup after this script's first pass, so the icon doesn't
 * silently revert to blank.
 */
( function () {
	'use strict';

	if ( typeof imdgFaviconIcon === 'undefined' || ! imdgFaviconIcon.url ) {
		return;
	}

	var ICON_SELECTOR = '#wp-admin-bar-wp-logo .ab-icon';
	var FAVICON_CSS_VALUE = 'url("' + imdgFaviconIcon.url + '")';

	function apply() {
		var icon = document.querySelector( ICON_SELECTOR );
		if ( ! icon ) {
			return;
		}
		if ( icon.style.getPropertyValue( 'background-image' ) === FAVICON_CSS_VALUE ) {
			return;
		}
		icon.style.setProperty( 'background-image', FAVICON_CSS_VALUE, 'important' );
		icon.style.setProperty( 'background-size', '32px 32px', 'important' );
		icon.style.setProperty( 'background-position', 'center', 'important' );
		icon.style.setProperty( 'background-repeat', 'no-repeat', 'important' );
	}

	function init() {
		apply();

		var toolbar = document.getElementById( 'wpadminbar' );
		if ( ! toolbar || typeof MutationObserver === 'undefined' ) {
			return;
		}

		// Cheap guard against churn: apply() itself no-ops once the style
		// already matches, so a mutation loop here can't cause runaway
		// observer/mutation ping-pong.
		new MutationObserver( apply ).observe( toolbar, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: [ 'style' ],
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
