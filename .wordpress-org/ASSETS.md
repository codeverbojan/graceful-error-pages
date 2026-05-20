# WordPress.org Plugin Assets

This directory contains assets for the wordpress.org plugin listing.
These files are deployed via `10up/action-wordpress-plugin-asset-update`
and are NOT included in the plugin zip (excluded by `.distignore`).

## Icons (included as SVG)

- `icon.svg` — Vector plugin icon (preferred by wp.org)

To generate PNG fallbacks from the SVG:
```bash
# Requires Inkscape or rsvg-convert
rsvg-convert -w 128 -h 128 icon.svg > icon-128x128.png
rsvg-convert -w 256 -h 256 icon.svg > icon-256x256.png
```

## Banners (included as SVG)

- `banner-772x250.svg` — Standard banner

To generate PNG versions:
```bash
rsvg-convert -w 772 -h 250 banner-772x250.svg > banner-772x250.png
rsvg-convert -w 1544 -h 500 banner-772x250.svg > banner-1544x500.png
```

## Screenshots (manual capture required)

Capture these from a running wp-env instance (`npm run env:start`):

1. `screenshot-1.png` — The default Minimal template replacing the WordPress error screen
2. `screenshot-2.png` — The settings page — Design tab with template picker
3. `screenshot-3.png` — The settings page — Content tab with messaging options
4. `screenshot-4.png` — The Corporate template with site logo
5. `screenshot-5.png` — The Dark template with dark mode styling

Screenshot descriptions must match the `== Screenshots ==` section in `readme.txt`.
