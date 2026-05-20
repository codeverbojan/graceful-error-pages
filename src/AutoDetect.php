<?php
/**
 * Brand auto-detection from WordPress Customizer settings.
 *
 * @package GracefulErrorPages
 */

declare( strict_types=1 );

namespace GracefulErrorPages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects site branding (name, logo, icon, color) from WordPress settings.
 *
 * Used on activation to populate default options so the error page
 * is branded immediately without configuration.
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
	 * Detect the custom logo URL from the Customizer.
	 *
	 * Uses get_theme_mod('custom_logo') for the attachment ID, then
	 * wp_get_attachment_image_url() for the actual URL.
	 * Note: get_custom_logo() returns HTML, not a URL.
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
	 * Detect the brand color from the Customizer.
	 *
	 * @return string
	 */
	private static function detect_brand_color(): string {
		$color = get_theme_mod( 'primary_color', '' );

		if ( ! is_string( $color ) || '' === $color ) {
			return self::DEFAULT_BRAND_COLOR;
		}

		$sanitized = sanitize_hex_color( $color );

		return is_string( $sanitized ) && '' !== $sanitized ? $sanitized : self::DEFAULT_BRAND_COLOR;
	}
}
