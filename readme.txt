=== Infinite Monkeys Dark Glass Admin Theme ===
Contributors: infinitemonkeys
Tags: admin theme, dark mode, wordpress admin, admin panel, backend theme, custom admin theme, dark theme, modern admin, white label, night mode
Requires at least: 5.9
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.2.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A clean, minimalist dark admin theme for WordPress with frosted glass effects — built for night owls.

== Description ==

The Dark Glass admin theme provides a clean, simplified design for your WordPress Admin area. Our goal was to simplify and modernize the visual design of the WordPress back end with a primary focus on reducing clutter and bringing a more minimalist design aesthetic — with a dark theme more suitable for fellow night owls.

**Features include:**

* Deep dark theme with purple/magenta accent colours
* Frosted glass effect on admin bar and sidebar submenus (using CSS backdrop-filter)
* Animated glowing page background via radial gradients
* Restyled sidebar, admin bar, content area, forms, buttons, tables, and notices
* Fully compatible with Desktop Mode (https://wordpress.org/plugins/desktop-mode/)
* Dark-themed TinyMCE (classic editor) and Gutenberg block editor canvas
* Bricks Builder editor styles
* Dracula theme for the WordPress code/theme file editors (CodeMirror)
* Styles for popular 3rd-party plugins including ACF, WooCommerce, Bricks Builder, WPCodeBox, Rank Math, and more
* Optional admin behaviour improvements: suppressed noise emails, extended login sessions, hidden admin bar for non-administrators

**Styling Coverage:**

The plugin ships three stylesheets:

1. **Core WordPress admin** — login page, admin bar, sidebar, content area, forms, buttons, tables, post editor, classic editor, block editor chrome, dashboard, plugins page, media library, and more.
2. **3rd-party plugins** — ACF, Advanced Themer, WS Form, Admin Columns Pro, WooCommerce, TablePress, WPGridBuilder, CheckoutWC, Perfmatters, Members, LearnDash, WPCodeBox, MainWP, Rank Math, WP Offload Media, MotionPage, Bricks Boost, and more.
3. **Bricks Builder editor** — loaded only inside the Bricks builder interface.

**Note:** This plugin is opinionated — it is not a settings-based theme builder. It reflects the design preferences of An Infinite Number of Monkeys and is intended as a starting point for developers who want a polished dark admin out of the box and are comfortable customising the CSS files directly. Design inspired by the Fleekdash plugin.

== Installation ==

1. Upload the `infinite-monkeys-dark-glass` folder to `/wp-content/plugins/`, or install via the WordPress Plugins screen.
2. Activate the plugin via the **Plugins** screen.
3. No configuration is required. The theme activates immediately.

== Frequently Asked Questions ==

= Does this work with my page builder? =

The plugin includes styles for the Bricks Builder editor. Elementor, Divi, and other builders are not currently included, however you can still benefit from the styling of the admin area.

= Will this break my front end? =

No. All styles are scoped to WordPress admin pages (`admin_enqueue_scripts`) or the Bricks Builder editor context. Front-end pages are not affected.

= Can I customise the colours? =

Yes. All colours are defined as CSS custom properties in `assets/css/admin.css` under the `:root` block (Section 1). Change the values there and they will cascade through the rest of the stylesheet.

= The frosted glass effect isn't showing. =

`backdrop-filter` requires the elements behind the overlay to be visible (not fully opaque). If you have a solid background this effect may not be apparent. It also requires a modern browser — Firefox requires the `layout.css.backdrop-filter.enabled` flag on older versions.

= Is this compatible with the WordPress Customizer? =

The Customizer has partial coverage. Most core controls are styled; edge cases may need additional overrides.

== Screenshots ==

1. WordPress admin dashboard with the Dark Glass theme applied.
2. Admin sidebar and submenu with frosted glass overlay effect.
3. Post editor with dark Gutenberg block editor canvas.
4. Plugins page styled with status indicators.

== Changelog ==

= 1.2.5 =
Switched the update mechanism from a self-hosted JSON manifest
(infinitemonkeys.ca/wp-content/uploads/dark-glass/infinite-monkeys-dark-glass.json)
to GitHub releases via Plugin Update Checker, pointed at the public
https://github.com/AnInfiniteNumberofMonkeys/dark-glass repo. No
per-site configuration needed - the repo is public, so no
authentication token is required anywhere.

Migration note: sites running a version older than 1.2.5 are still on
the old JSON-manifest checker and have no way to discover this change
on their own - they need one final update distributed the old way
(uploading this version's zip and pointing the JSON manifest's version
and download_url at it) to get onto a version that contains the new
GitHub-based checker. Every update after that flows through GitHub
automatically: bump the version in the main plugin file, push to
GitHub, and publish a release tagged to match.
