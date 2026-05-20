# Changelog

All notable changes to Graceful Error Pages will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.0.0] - Unreleased

### Added

- Custom `wp_die()` handler with branded error pages
- PHP fatal error shutdown handler with self-contained styling
- Five built-in templates: Minimal, Corporate, Friendly, Dark, Starter
- Admin settings page under Settings > Error Pages
- Template preview with live customization
- Auto-detection of site name, logo, and brand color on activation
- Merge tags for dynamic content ({site_name}, {year}, etc.)
- Full i18n support with text domain `graceful-error-pages`
- WP-CLI and REST API safe (only overrides HTML die handler)
