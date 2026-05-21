=== Graceful Error Pages ===
Contributors: josifoskibojan
Tags: error-page, wp-die, branding, error-handling, custom-error
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Replace WordPress's ugly error screens with branded, professional pages — in one click.

== Description ==

Every WordPress site shows the same plain white error screen when something goes wrong.
It looks broken. It destroys trust. Graceful Error Pages replaces it with a page that
matches your brand — automatically, the moment you activate it.

**What it replaces:**

* The `wp_die()` error screen (permission errors, expired links, security blocks)
* PHP fatal error screens (white screen of death)

**Features:**

* **Zero-config activation** — works immediately with auto-detected site name, logo, and colors
* **Five built-in templates** — Minimal, Corporate, Friendly, Dark, and Starter
* **Brand customization** — logo, colors, heading, message, and redirect URL
* **Merge tags** — dynamic content like {site_name} and {year}
* **Self-contained styling** — no theme or CDN dependencies (works even during fatal errors)
* **API safe** — only overrides HTML output; REST API, AJAX, and JSON responses are untouched
* **WP-CLI safe** — automatically skips CLI contexts
* **Lightweight** — zero overhead on normal page loads; only runs when an error occurs
* **Fully translatable** — all strings use WordPress i18n functions

**How it works:**

1. Activate the plugin
2. Your site's error pages are instantly branded with auto-detected settings
3. Optionally customize via Settings > Error Pages

**Source code:** [github.com/codeverbojan/graceful-error-pages](https://github.com/codeverbojan/graceful-error-pages)

== Installation ==

1. Upload the `graceful-error-pages` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. That's it — error pages are now branded automatically
4. Optionally go to **Settings > Error Pages** to customize the template, colors, and messaging

== Frequently Asked Questions ==

= Does this work out of the box? =

Yes. On activation the plugin auto-detects your site name, logo, and brand color from your
WordPress settings. Works with both classic and block (FSE) themes. No configuration is required.

= Which error screens does this replace? =

Two types: (1) the `wp_die()` screen shown for permission errors, expired nonces, and
similar issues, and (2) the PHP fatal error screen (white screen of death).

= Will this break my REST API or AJAX? =

No. The plugin only overrides the HTML die handler. REST API, AJAX, JSON, and JSONP
handlers are left completely untouched.

= Does this work with WP-CLI? =

Yes. The plugin detects CLI contexts and skips the custom handler entirely.

= Does this affect wp-admin? =

Not by default. The handler only fires on the front end unless you explicitly enable
admin override in the settings.

= Can I create my own template? =

The five built-in templates cover most use cases. Custom template support is planned
for a future release.

= Does this work with WooCommerce? =

Yes. The plugin works with any WordPress site, including WooCommerce stores. Error pages
are styled independently of your theme or any other plugin.

= Does the plugin load anything on every page? =

No. The plugin registers its handler on init but only renders output when `wp_die()` is
actually called or a fatal error occurs. There is zero performance impact on normal pages.

== Screenshots ==

1. The default Minimal template replacing the WordPress error screen.
2. The settings page — Design tab with template picker.
3. The settings page — Content tab with messaging options.
4. The Corporate template with site logo.
5. The Dark template with dark mode styling.

== Changelog ==

= 1.0.1 =
* Add @wordpress/scripts build process, rename prefix to gcep, upgrade PHPStan 2


= 1.0.0 =
* Initial commit — Graceful Error Pages WordPress plugin
* Fix: Wp.org compliance audit fixes
* Fix: Pin composer platform to PHP 8.1 for CI matrix compatibility
* Fix: Harden error handler for real-world edge cases


= 1.0.0 =
* Initial release
* Custom wp_die() handler with branded error pages
* PHP fatal error shutdown handler with self-contained styling
* Five built-in templates: Minimal, Corporate, Friendly, Dark, Starter
* Admin settings page under Settings > Error Pages
* Auto-detection of site name, logo, and brand color on activation
* Merge tags for dynamic content
* Full i18n support

== Upgrade Notice ==

= 1.0.1 =
Add @wordpress/scripts build process, rename prefix to gcep, upgrade PHPStan 2

= 1.0.0 =
1 new feature(s), 3 fix(es).

= 1.0.0 =
Initial release.
