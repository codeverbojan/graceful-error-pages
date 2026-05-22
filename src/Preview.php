<?php
/**
 * Error page preview endpoint.
 *
 * @package GracefulErrorPages
 */

declare( strict_types=1 );

namespace GracefulErrorPages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides a secure AJAX endpoint for previewing the error page
 * in an iframe from the settings page.
 */
class Preview {

	/**
	 * The AJAX action name.
	 *
	 * @var string
	 */
	public const ACTION = 'gcep_preview';

	/**
	 * The nonce action string.
	 *
	 * @var string
	 */
	public const NONCE_ACTION = 'gcep_preview';

	/**
	 * The template engine instance.
	 *
	 * @var TemplateEngine
	 */
	private TemplateEngine $template_engine;

	/**
	 * Constructor.
	 *
	 * @param TemplateEngine $template_engine The template engine.
	 */
	public function __construct( TemplateEngine $template_engine ) {
		$this->template_engine = $template_engine;
	}

	/**
	 * Register the AJAX handler.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, [ $this, 'handle' ] );
	}

	/**
	 * Handle the preview AJAX request.
	 *
	 * Renders a full HTML error page suitable for iframe embedding.
	 * Accepts optional query parameters for unsaved form values so
	 * the preview reflects the current form state, not just saved options.
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) ) {
			wp_die( '', '', [ 'response' => 405 ] );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to preview error pages.', 'graceful-error-pages' ), '', [ 'response' => 403 ] );
		}

		check_ajax_referer( self::NONCE_ACTION );

		$template = $this->get_preview_param( 'gcep_template', 'minimal' );
		$valid    = array_keys( TemplateEngine::get_available_templates() );
		if ( ! in_array( $template, $valid, true ) ) {
			$template = 'minimal';
		}

		$context = $this->build_preview_context();

		if ( ! $this->template_engine->has_template( $template ) ) {
			$template = 'minimal';
		}

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'X-Frame-Options: SAMEORIGIN' );
			header( 'X-Robots-Tag: noindex, nofollow' );
		}

		$this->template_engine->display( $template, $context );

		die();
	}

	/**
	 * Build the preview template context.
	 *
	 * Uses query parameter overrides when present (unsaved form values),
	 * falling back to saved options for anything not in the request.
	 *
	 * @return array<string, mixed>
	 */
	private function build_preview_context(): array {
		$error_title = $this->get_preview_param( 'gcep_error_title', '' );
		if ( '' === $error_title ) {
			$error_title = __( 'Something went wrong', 'graceful-error-pages' );
		}

		$error_message = $this->get_preview_param( 'gcep_error_message', '', 'kses' );
		if ( '' === $error_message ) {
			$error_message = __( 'This is a preview of your error page. The actual error details will appear here when a real error occurs.', 'graceful-error-pages' );
		}

		$context = [
			'error_title'   => $error_title,
			'error_message' => $error_message,
			'back_link'     => true,
			'back_url'      => '#',
			'response_code' => 500,
		];

		$url_params = [
			'logo_url'          => 'gcep_logo_url',
			'icon_url'          => 'gcep_icon_url',
			'primary_btn_url'   => 'gcep_primary_btn_url',
			'secondary_btn_url' => 'gcep_secondary_btn_url',
			'support_link'      => 'gcep_support_link',
		];

		foreach ( $url_params as $key => $option_name ) {
			$value = $this->get_preview_param( $option_name, '', 'url' );
			if ( '' !== $value ) {
				$context[ $key ] = $value;
			}
		}

		$color_params = [
			'brand_color' => 'gcep_brand_color',
			'bg_color'    => 'gcep_bg_color',
			'text_color'  => 'gcep_text_color',
		];

		foreach ( $color_params as $key => $option_name ) {
			$value = $this->get_preview_param( $option_name, '', 'color' );
			if ( '' !== $value ) {
				$context[ $key ] = $value;
			}
		}

		$text_params = [
			'dark_mode'          => 'gcep_dark_mode',
			'site_name'          => 'gcep_site_name',
			'primary_btn_text'   => 'gcep_primary_btn_text',
			'secondary_btn_text' => 'gcep_secondary_btn_text',
			'copyright'          => 'gcep_copyright',
		];

		foreach ( $text_params as $key => $option_name ) {
			$value = $this->get_preview_param( $option_name, '' );
			if ( '' !== $value ) {
				$context[ $key ] = $value;
			}
		}

		return $context;
	}

	/**
	 * Read a preview parameter from the query string, falling back to the
	 * saved option value.
	 *
	 * @param string $name     The option/parameter name (e.g. 'gcep_template').
	 * @param string $fallback Fallback if neither query param nor option exists.
	 * @param string $type     Sanitization type: 'text' (default), 'url', 'color', or 'kses'.
	 * @return string
	 */
	private function get_preview_param( string $name, string $fallback, string $type = 'text' ): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce already verified in handle().
		if ( isset( $_GET[ $name ] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_GET[ $name ] ) );
			return $this->sanitize_by_type( $raw, $type );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$saved = get_option( $name, $fallback );

		return is_string( $saved ) ? $saved : $fallback;
	}

	/**
	 * Sanitize a value by type.
	 *
	 * @param mixed  $value The raw value.
	 * @param string $type  Sanitization type: 'text', 'url', 'color', or 'kses'.
	 * @return string
	 */
	private function sanitize_by_type( $value, string $type ): string {
		switch ( $type ) {
			case 'url':
				return esc_url_raw( (string) $value );
			case 'color':
				$color = sanitize_hex_color( (string) $value );
				return is_string( $color ) ? $color : '';
			case 'kses':
				return wp_kses_post( (string) $value );
			default:
				return sanitize_text_field( (string) $value );
		}
	}
}
