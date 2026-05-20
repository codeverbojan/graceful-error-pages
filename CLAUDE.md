# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

Graceful Error Pages — a free WordPress plugin that replaces the default `wp_die()` error screen and PHP fatal error screen with branded, professional pages. Zero-config on activation; settings for customization. Targets wordpress.org approval.

The full product spec lives in `docs/14-WP-ERROR-SCREENS.md` (git-ignored, dev-only). The plugin is being extracted from production code running in the SampleHQ platform.

**Sister plugin for reference architecture:** `samplehq-request-form` (same parent `wp-content/plugins/` directory). This plugin follows the same patterns: PSR-4 autoloading, namespace-based source layout, WPCS 3.x, PHPStan, Husky hooks, GitHub Actions CI/CD with `10up/action-wordpress-plugin-deploy`.

## Architecture

### Source Layout (PSR-4 Autoloading)

```
graceful-error-pages/
├── graceful-error-pages.php        # Main plugin file: headers, constants, spl_autoload_register, Plugin::boot()
├── src/
│   ├── Plugin.php                  # Main class: boot(), register_hooks(), activate(), deactivate()
│   ├── Handler.php                 # wp_die handler + fatal error shutdown handler
│   ├── Settings.php                # Admin settings page (WordPress Settings API)
│   ├── TemplateEngine.php          # Template loading + merge tag rendering ({site_name}, {year})
│   └── AutoDetect.php              # Logo/color/name detection from Customizer
├── templates/
│   ├── minimal.php                 # Default: clean, light, centered
│   ├── corporate.php               # Logo-forward, structured
│   ├── friendly.php                # Warm, illustration, encouraging
│   ├── dark.php                    # Dark background, modern
│   └── starter.php                 # Bare minimum styled text
├── assets/
│   ├── css/
│   │   ├── error-page.css          # Error page styles (self-contained, NO CDN)
│   │   └── admin.css               # Settings page styles
│   ├── js/
│   │   └── admin.js                # Live preview, color picker
│   └── images/                     # Default SVG icons (<10KB total)
├── languages/
│   └── graceful-error-pages.pot    # i18n template
├── tests/
│   ├── bootstrap.php               # PHPUnit bootstrap (Brain\Monkey, WP stubs)
│   └── Unit/                       # Unit tests
├── uninstall.php                   # Clean up options on delete (load Migrator directly, NOT Plugin::boot())
├── readme.txt                      # WordPress.org readme
├── phpcs.xml.dist                  # WPCS 3.x config
├── phpstan.neon                    # PHPStan config (level 6)
├── phpstan-constants.php           # Plugin constants for PHPStan
├── phpunit.xml.dist                # Unit test config
├── composer.json                   # PHP deps + scripts
├── .wp-env.json                    # wp-env local dev environment
├── .editorconfig                   # Tabs for PHP/JS/CSS per WP standards
├── .distignore                     # Files excluded from wordpress.org zip
├── .gitignore
└── .github/
    └── workflows/
        ├── ci.yml                  # Lint + Unit tests + Build (every push/PR)
        └── release.yml             # Full CI + Build zip + GitHub Release + SVN deploy (on tag)
```

### Plugin Bootstrap Pattern

Follow the `samplehq-request-form` pattern exactly:

1. **Main file** (`graceful-error-pages.php`): Plugin headers, `declare(strict_types=1)`, ABSPATH guard, define constants (`GEP_VERSION`, `GEP_FILE`, `GEP_DIR`, `GEP_URL`), register PSR-4 autoloader via `spl_autoload_register()` mapping `GracefulErrorPages\` to `src/`, call `Plugin::boot()`
2. **Plugin class** (`src/Plugin.php`): Private constructor, static `boot()` with boot guard, `register_hooks()`, `activate()`, `deactivate()`. No singleton — store instance in `$GLOBALS['gep_plugin']`
3. **Namespace:** `GracefulErrorPages` (PSR-4, maps to `src/`)
4. **Constant prefix:** `GEP_` for all constants
5. **Option prefix:** `gep_` for all `wp_options` keys
6. **Hook prefix:** `gep_` for all custom hooks

### Key Technical Constraints

**All error page CSS must be fully self-contained.** No theme dependencies, no external CDN, no build step. When `wp_die()` fires the theme may not be loaded; during fatals `wp_head()`/`wp_footer()` won't work. Templates use inline or plugin-bundled CSS only.

**Only override the HTML die handler.** Leave `wp_die_ajax_handler`, `wp_die_json_handler`, and `wp_die_jsonp_handler` alone — never break REST API or AJAX.

**Don't interfere with WP-CLI.** Check `php_sapi_name() === 'cli'` and the `WP_CLI` constant.

**Don't interfere with wp-admin by default.** Guard with `!is_admin()` unless the user explicitly enables admin override in settings. Some wp-admin contexts (Gutenberg saves, Customizer preview, Site Health, plugin editor recovery) expect WordPress's own die output.

**Respect the `$args` parameter.** `wp_die()` passes title, response code, back_link, charset, text direction, exit behavior — the handler must honor all of them.

**Fatal error handler must be self-contained.** Use `register_shutdown_function()`. Render with inline CSS only. Skip CLI context (`WP_CLI`, PHPUnit, cron). Return JSON for AJAX requests. Show debug info only when `WP_DEBUG && WP_DEBUG_DISPLAY`.

**Zero performance overhead.** The handler only fires when `wp_die()` is called — no hooks on normal page loads.

## Build & Quality Commands

```bash
# --- PHP Linting ---
composer lint                       # PHPCS (WordPress standard)
composer lint:fix                   # PHPCBF auto-fix
composer analyze                    # PHPStan (level 6)
composer test                       # PHPUnit unit tests
composer check                      # All three: lint + analyze + test

# --- Local Dev Environment ---
npm run env:start                   # Start wp-env (WP 6.9, PHP 8.2)
npm run env:stop                    # Stop wp-env
npm run env:clean                   # Clean wp-env data
npm run plugin-check                # Run Plugin Check (PCP) inside wp-env

# --- i18n ---
wp i18n make-pot . languages/graceful-error-pages.pot --slug=graceful-error-pages

# --- Distribution ---
bash bin/build-zip.sh               # Build production zip
bash bin/release.sh 1.0.0           # Full release: bump, changelog, commit, tag, push

# --- Single test ---
vendor/bin/phpunit --filter TestClassName::testMethodName
```

## WordPress.org Compliance (2026)

### Mandatory for Approval

- **Plugin Check (PCP) must pass** — errors in `plugin_repo` category block submission. Run via `npm run plugin-check` or `wp plugin check graceful-error-pages`
- **WPCS 3.x clean** — `phpcs --standard=WordPress` with zero errors
- **2FA enabled** on wordpress.org account
- **All output escaped** at point of output: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`
- **All input sanitized**: `sanitize_text_field()`, `sanitize_hex_color()`, `absint()`, `esc_url_raw()`
- **Nonces on all forms**: `wp_nonce_field()` / `wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'action' )`
- **Capability checks** on all admin actions: `current_user_can()`
- **`$wpdb->prepare()`** for all custom SQL
- **Unique prefix** on all global functions, constants, options — `gep_` / `GEP_` / `GracefulErrorPages\`
- **No bundled WP core libraries** (jQuery, etc.) — use what WP ships
- **JS/CSS enqueued properly** — no inline scripts (except error page templates which are self-contained by necessity)
- **`wp_json_encode()`** instead of `json_encode()`
- **All strings translatable**: `__()`, `esc_html__()`, `esc_attr__()` with text domain `graceful-error-pages`
- **External services disclosed** in readme.txt if any

### Plugin Headers (main file)

```php
/**
 * @wordpress-plugin
 * Plugin Name:       Graceful Error Pages
 * Plugin URI:        https://bojanjosifoski.com/graceful-error-pages
 * Description:       Replace WordPress's ugly error screens with branded, professional pages — in one click.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Codever
 * Author URI:        https://codever.io
 * Text Domain:       graceful-error-pages
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */
```

## CI/CD Pipeline

### GitHub Actions Workflows

**`ci.yml`** — runs on every push to `main` and every PR:
- **Lint job**: PHP syntax check, PHPCS, PHPStan, (no JS linting needed — minimal JS)
- **Unit tests job**: Matrix across PHP 8.1, 8.2, 8.3, 8.4
- **Build job**: Verify distribution zip can be built
- **Heavy jobs** (only on release via `workflow_call` with `inputs.full: true`): Integration tests (WP 6.4–6.9 × PHP matrix), Plugin Check via `wordpress/plugin-check-action@v1`

**`release.yml`** — triggered by pushing a `v*` tag:
1. Calls `ci.yml` with `full: true` (runs ALL checks including Plugin Check)
2. Validates changelog exists for this version in `readme.txt`
3. Generates `.pot` translation file
4. Builds distribution zip via `.distignore`
5. Creates GitHub Release with zip artifact
6. Deploys to wordpress.org SVN via `10up/action-wordpress-plugin-deploy@stable`

### Release Process

```bash
bash bin/release.sh 1.0.0
```

This script runs sequentially:
1. Validates clean working tree + semver format
2. Generates changelog from conventional commits
3. Pauses for review (non-interactive mode continues for Claude)
4. Bumps version across all locations (plugin header, `GEP_VERSION`, `readme.txt`, `composer.json`, `phpstan-constants.php`, `tests/bootstrap.php`)
5. Injects changelog + upgrade notice into `readme.txt`
6. Syncs `package-lock.json`
7. Validates `readme.txt` structure
8. Commits `release: v1.0.0`, tags, pushes

### Version Sync Locations

Version must stay in sync across:
- `graceful-error-pages.php` — plugin header `Version:` + `GEP_VERSION` constant
- `readme.txt` — `Stable tag:`
- `composer.json` — `"version"`
- `phpstan-constants.php` — `GEP_VERSION`
- `tests/bootstrap.php` — `GEP_VERSION`

### Pre-commit / Pre-push Hooks (Husky)

- **Pre-commit**: `lint-staged` runs PHPCS on staged `.php` files
- **Pre-push**: Runs full PHPCS, PHPStan, and unit tests locally before push

### Conventional Commits

Follow conventional commit format for automatic changelog generation:
- `feat:` → Added
- `fix:` → Fixed
- `perf:` / `a11y:` → Improved
- `security:` → Security
- `ci:`, `test:`, `docs:`, `build:`, `chore:` → skipped (not user-facing)

## Dev Dependencies

### Composer (PHP)

```json
{
  "require": { "php": ">=8.0" },
  "require-dev": {
    "brain/monkey": "^2.6",
    "dealerdirect/phpcodesniffer-composer-installer": "^1.0",
    "php-stubs/wordpress-stubs": "^6.0",
    "phpcompatibility/phpcompatibility-wp": "^2.1",
    "phpstan/extension-installer": "^1.0",
    "phpstan/phpstan": "^1.0",
    "phpunit/phpunit": "^9.6",
    "squizlabs/php_codesniffer": "^3.7",
    "szepeviktor/phpstan-wordpress": "^1.0",
    "wp-coding-standards/wpcs": "^3.0",
    "wp-phpunit/wp-phpunit": "^6.9",
    "yoast/phpunit-polyfills": "^2.0"
  }
}
```

### npm (JS/tooling)

```json
{
  "devDependencies": {
    "@wordpress/env": "11.5.0",
    "husky": "^9.1.7",
    "lint-staged": "^17.0.2"
  }
}
```

No webpack or `@wordpress/scripts` needed — this plugin has minimal JS (admin settings preview only). Enqueue vanilla JS directly.

## Key WordPress APIs

- `add_filter('wp_die_handler', ...)` — core filter since WP 3.0 for overriding the die handler
- `register_shutdown_function()` — for catching PHP fatal errors (`E_ERROR`, `E_PARSE`, `E_COMPILE_ERROR`, `E_CORE_ERROR`)
- WordPress Settings API for the admin page under Settings > Error Pages

## Auto-Detection on Activation

On activation, auto-detect and store defaults:
- Site name via `get_bloginfo('name')`
- Site icon via `get_site_icon_url()`
- Custom logo via `get_theme_mod('custom_logo')` + `wp_get_attachment_image_url()` (note: `get_custom_logo()` returns HTML, not a URL)
- Brand color from Customizer primary color, fallback `#3b82f6`
- Apply "Minimal" template as default

## Freemius Premium Architecture

The plugin uses Freemius for licensing, checkout, and premium distribution. This affects code organization from day one.

### Repository & Distribution Strategy

**Public GitHub repo with one codebase (free + premium).** This is the standard Freemius pattern used by Stackable, RatingWidget, FooGallery, etc. WordPress.org Guideline 4 requires public source only for code **distributed on wordpress.org**. Guideline 5 explicitly says premium code should be hosted elsewhere. PHP plugins ship as source anyway — the premium value is the license, updates, and support.

**Release flow:**
1. Push `v*` tag → CI builds full zip (free + premium code)
2. Upload to Freemius via `buttonizer/freemius-deploy` GitHub Action
3. Freemius PHP Preprocessor auto-strips `__premium_only` code → generates free zip
4. Deploy the **Freemius-generated free zip** to wordpress.org SVN (10up action)
5. Freemius hosts the premium zip on its CDN
6. Free users get updates from wordpress.org; licensed users get premium updates via Freemius SDK

**Security:** Only `public_key` goes in code. `secret_key`, `dev_id`, `plugin_id` are GitHub Actions secrets only.

### Code Separation Convention

Freemius auto-strips premium code when generating the free version for wordpress.org. Two mechanisms:

1. **File-level**: Files named `*__premium_only.php` are excluded entirely from the free build
2. **Block-level**: Code inside `if ( gep_fs()->is__premium_only() ) { ... }` blocks is stripped

Premium features live in `src/Pro/` with `__premium_only` suffixes:
```
src/Pro/
  RedirectRules__premium_only.php
  ErrorAnalytics__premium_only.php
  CustomEditor__premium_only.php
  MultisiteNetwork__premium_only.php
  WooTemplates__premium_only.php
  WhiteLabel__premium_only.php
  ImportExport__premium_only.php
  ErrorClassifier__premium_only.php
```

### Runtime License Checks

```php
gep_fs()->is__premium_only()           // Compile-time: stripped from free build
gep_fs()->can_use_premium_code()       // Has license AND running premium version
gep_fs()->is_paying()                  // Has valid paid license
gep_fs()->is_plan('pro')              // On specific plan or higher
```

In mixed files (e.g., Settings.php), use the combined pattern:
```php
if ( gep_fs()->is__premium_only() ) {
    if ( gep_fs()->can_use_premium_code() ) {
        $this->render_premium_settings();
    } else {
        $this->render_upgrade_teaser();
    }
}
```

### SDK Location

The Freemius SDK ships at `vendor/freemius/` and is initialized in `graceful-error-pages.php` before `Plugin::boot()`. It is NOT a dev dependency — it must be included in the production build (excluded from `.distignore` exclusion of `/vendor/`).

### Architecture Rule

When writing any feature during Phases 2-4, keep free and premium logic separable. Avoid mixing premium-only behavior into free methods. When in doubt, put premium logic in its own method or class so the `__premium_only` boundary is clean.

### Payout Note (North Macedonia)

Freemius payouts must use **Payoneer** or **wire transfer (IBAN/SWIFT)** — PayPal cannot receive payments in North Macedonia.
