<?php
/**
 * Settings sanitization callbacks.
 *
 * @package GracefulErrorPages
 */

declare( strict_types=1 );

namespace GracefulErrorPages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides static sanitization methods for plugin settings.
 *
 * Each method is designed to be used as a `sanitize_callback` for
 * `register_setting()`.
 */
class Sanitizer {

	/**
	 * Sanitize the template slug.
	 *
	 * @param mixed $value Raw input.
	 * @return string Valid template slug, or 'minimal' as fallback.
	 */
	public static function template( $value ): string {
		$value = is_string( $value ) ? sanitize_text_field( $value ) : '';

		$valid = TemplateEngine::get_available_templates();

		return isset( $valid[ $value ] ) ? $value : 'minimal';
	}

	/**
	 * Sanitize a hex color value.
	 *
	 * @param mixed $value Raw input.
	 * @return string Sanitized hex color or empty string.
	 */
	public static function hex_color( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$sanitized = sanitize_hex_color( $value );

		return is_string( $sanitized ) ? $sanitized : '';
	}

	/**
	 * Sanitize a URL value.
	 *
	 * @param mixed $value Raw input.
	 * @return string Sanitized URL or empty string.
	 */
	public static function url( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		return esc_url_raw( $value );
	}

	/**
	 * Sanitize a plain text field.
	 *
	 * @param mixed $value Raw input.
	 * @return string Sanitized text.
	 */
	public static function text( $value ): string {
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	/**
	 * Sanitize a rich text field (allows safe HTML).
	 *
	 * @param mixed $value Raw input.
	 * @return string Sanitized HTML.
	 */
	public static function kses( $value ): string {
		return is_string( $value ) ? wp_kses_post( $value ) : '';
	}

	/**
	 * Sanitize a boolean stored as 0/1 integer.
	 *
	 * @param mixed $value Raw input.
	 * @return int 1 or 0.
	 */
	public static function boolean( $value ): int {
		return empty( $value ) ? 0 : 1;
	}

	/**
	 * Sanitize the dark mode setting.
	 *
	 * @param mixed $value Raw input.
	 * @return string One of: auto, on, off, disabled.
	 */
	public static function dark_mode( $value ): string {
		$allowed = [ 'auto', 'on', 'off', 'disabled' ];
		$value   = is_string( $value ) ? sanitize_text_field( $value ) : '';

		return in_array( $value, $allowed, true ) ? $value : 'auto';
	}

	/**
	 * Sanitize the scope setting.
	 *
	 * @param mixed $value Raw input.
	 * @return string One of: frontend, admin, everywhere.
	 */
	public static function scope( $value ): string {
		$allowed = [ 'frontend', 'admin', 'everywhere' ];
		$value   = is_string( $value ) ? sanitize_text_field( $value ) : '';

		return in_array( $value, $allowed, true ) ? $value : 'frontend';
	}
}
