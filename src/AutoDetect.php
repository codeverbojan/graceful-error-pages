<?php
/**
 * Brand auto-detection from WordPress settings and theme.json.
 *
 * @package GracefulErrorPages
 */

declare( strict_types=1 );

namespace GracefulErrorPages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects site branding from WordPress settings, Customizer, and theme.json.
 *
 * Supports both classic and block (FSE) themes. Used on activation to
 * populate default options so the error page is branded immediately.
 */
class AutoDetect {

	/**
	 * Default brand color when no theme mod is set.
	 *
	 * @var string
	 */
	public const DEFAULT_BRAND_COLOR = '#2563eb';

	/**
	 * Detect site branding from WordPress settings.
	 *
	 * @return array{site_name: string, logo_url: string, icon_url: string, brand_color: string}
	 */
	public static function detect(): array {
		return [
			'site_name'   => self::detect_site_name(),
			'logo_url'    => self::detect_logo_url(),
			'icon_url'    => self::detect_icon_url(),
			'brand_color' => self::detect_brand_color(),
		];
	}

	/**
	 * Detect the site name.
	 *
	 * @return string
	 */
	private static function detect_site_name(): string {
		$name = get_bloginfo( 'name' );

		return sanitize_text_field( $name );
	}

	/**
	 * Detect the custom logo URL.
	 *
	 * Works for both classic and block themes. WordPress syncs the
	 * site_logo option to the custom_logo theme mod since WP 5.8.
	 *
	 * @return string
	 */
	private static function detect_logo_url(): string {
		$logo_id = get_theme_mod( 'custom_logo' );

		if ( empty( $logo_id ) ) {
			return '';
		}

		$url = wp_get_attachment_image_url( (int) $logo_id, 'full' );

		return is_string( $url ) ? esc_url_raw( $url ) : '';
	}

	/**
	 * Detect the site icon (favicon) URL.
	 *
	 * @return string
	 */
	private static function detect_icon_url(): string {
		$url = get_site_icon_url();

		return esc_url_raw( $url );
	}

	/**
	 * Brand-like palette slugs to search, in priority order.
	 *
	 * Covers core block themes: TT2/TT3 use "primary", TT4 uses "accent",
	 * TT5 uses "accent-1". Third-party themes may use any of these.
	 *
	 * @var array<int, string>
	 */
	private const BRAND_SLUGS = [ 'primary', 'accent', 'accent-1', 'accent-2', 'accent-3' ];

	/**
	 * Detect the brand color from theme.json palette or Customizer.
	 *
	 * For block/hybrid themes with a theme.json, reads the color palette
	 * via wp_get_global_settings() and picks the first brand-like slug.
	 * Falls back to the Customizer's primary_color theme mod for classic themes.
	 *
	 * @return string
	 */
	private static function detect_brand_color(): string {
		$color = self::detect_color_from_theme_json();

		if ( '' !== $color ) {
			return $color;
		}

		return self::detect_color_from_customizer();
	}

	/**
	 * Read the brand color from the theme.json color palette.
	 *
	 * @return string Hex color or empty string if not found.
	 */
	private static function detect_color_from_theme_json(): string {
		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return '';
		}

		$palette = wp_get_global_settings( [ 'color', 'palette', 'theme' ] );

		if ( ! is_array( $palette ) || empty( $palette ) ) {
			return '';
		}

		foreach ( self::BRAND_SLUGS as $slug ) {
			foreach ( $palette as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}

				if ( ! isset( $entry['slug'], $entry['color'] ) || $slug !== $entry['slug'] ) {
					continue;
				}

				$hex = sanitize_hex_color( $entry['color'] );

				if ( is_string( $hex ) && '' !== $hex ) {
					return $hex;
				}
			}
		}

		return '';
	}

	/**
	 * Read the brand color from the Customizer theme mod.
	 *
	 * @return string Hex color or DEFAULT_BRAND_COLOR.
	 */
	private static function detect_color_from_customizer(): string {
		$color = get_theme_mod( 'primary_color', '' );

		if ( ! is_string( $color ) || '' === $color ) {
			return self::DEFAULT_BRAND_COLOR;
		}

		$sanitized = sanitize_hex_color( $color );

		return is_string( $sanitized ) && '' !== $sanitized ? $sanitized : self::DEFAULT_BRAND_COLOR;
	}
}
