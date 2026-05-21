<?php
/**
 * Template loading and merge tag rendering.
 *
 * @package GracefulErrorPages
 */

declare( strict_types=1 );

namespace GracefulErrorPages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads error page templates and replaces merge tags with context values.
 */
class TemplateEngine {

	/**
	 * Known merge tags and their context keys.
	 *
	 * @var array<string, string>
	 */
	public const MERGE_TAGS = [
		'{site_name}' => 'site_name',
		'{year}'      => 'year',
		'{home_url}'  => 'home_url',
		'{back_url}'  => 'back_url',
	];

	/**
	 * Render a template with the given context.
	 *
	 * @param string               $template Template slug (e.g. 'minimal').
	 * @param array<string, mixed> $context  Template variables.
	 * @return string Rendered HTML, or empty string on failure.
	 */
	public function render( string $template, array $context = [] ): string {
		$file = $this->resolve_path( $template );

		if ( '' === $file ) {
			return '';
		}

		$context = $this->build_context( $context );
		$context = $this->resolve_context_tags( $context );

		$output = $this->load_template( $file, $context );

		return $this->replace_merge_tags( $output, $context );
	}

	/**
	 * Get the list of available template slugs.
	 *
	 * @return array<string, string> Slug => human-readable label.
	 */
	public static function get_available_templates(): array {
		return [
			'minimal'   => __( 'Minimal', 'graceful-error-pages' ),
			'corporate' => __( 'Corporate', 'graceful-error-pages' ),
			'friendly'  => __( 'Friendly', 'graceful-error-pages' ),
			'dark'      => __( 'Dark', 'graceful-error-pages' ),
			'starter'   => __( 'Starter', 'graceful-error-pages' ),
		];
	}

	/**
	 * Resolve a template slug to an absolute file path.
	 *
	 * @param string $template Template slug.
	 * @return string Absolute path, or empty string if invalid.
	 */
	private function resolve_path( string $template ): string {
		if ( '' === $template ) {
			return '';
		}

		if ( str_contains( $template, '..' )
			|| str_contains( $template, '/' )
			|| str_contains( $template, '\\' )
			|| str_contains( $template, "\0" ) ) {
			return '';
		}

		$file = GCEP_DIR . 'templates/' . $template . '.php';

		if ( ! file_exists( $file ) ) {
			return '';
		}

		$real = realpath( $file );
		$base = realpath( GCEP_DIR . 'templates' );

		if ( false === $real || false === $base || ! str_starts_with( $real, $base . DIRECTORY_SEPARATOR ) ) {
			return '';
		}

		return $real;
	}

	/**
	 * Build the full context array with defaults.
	 *
	 * @param array<string, mixed> $context Overrides from the caller.
	 * @return array<string, mixed>
	 */
	private function build_context( array $context ): array {
		$home = home_url( '/' );

		$defaults = [
			'site_name'          => get_option( 'gcep_site_name', get_bloginfo( 'name' ) ),
			'logo_url'           => get_option( 'gcep_logo_url', '' ),
			'icon_url'           => get_option( 'gcep_icon_url', '' ),
			'brand_color'        => get_option( 'gcep_brand_color', AutoDetect::DEFAULT_BRAND_COLOR ),
			'bg_color'           => get_option( 'gcep_bg_color', '' ),
			'text_color'         => get_option( 'gcep_text_color', '' ),
			'dark_mode'          => get_option( 'gcep_dark_mode', 'auto' ),
			'error_title'        => $this->option_or_fallback( 'gcep_error_title', __( 'Something went wrong', 'graceful-error-pages' ) ),
			'error_message'      => $this->option_or_fallback( 'gcep_error_message', __( 'The page you were looking for could not be loaded. Please try again later.', 'graceful-error-pages' ) ),
			'home_url'           => $home,
			'back_url'           => '',
			'back_link'          => false,
			'response_code'      => 500,
			'charset'            => get_bloginfo( 'charset' ),
			'text_direction'     => is_rtl() ? 'rtl' : 'ltr',
			'year'               => gmdate( 'Y' ),
			'template'           => 'minimal',
			'css_url'            => GCEP_URL . 'assets/build/css/error-page.css',
			'primary_btn_text'   => $this->option_or_fallback( 'gcep_primary_btn_text', __( 'Go to Homepage', 'graceful-error-pages' ) ),
			'primary_btn_url'    => $this->option_or_fallback( 'gcep_primary_btn_url', $home ),
			'secondary_btn_text' => $this->option_or_fallback( 'gcep_secondary_btn_text', __( 'Go Back', 'graceful-error-pages' ) ),
			'secondary_btn_url'  => get_option( 'gcep_secondary_btn_url', '' ),
			'copyright'          => get_option( 'gcep_copyright', '' ),
			'support_link'       => get_option( 'gcep_support_link', '' ),
		];

		return array_merge( $defaults, $context );
	}

	/**
	 * Return an option value if non-empty, otherwise the fallback.
	 *
	 * @param string $option   Option name.
	 * @param string $fallback Fallback value.
	 * @return string
	 */
	private function option_or_fallback( string $option, string $fallback ): string {
		$value = get_option( $option, '' );

		return ( is_string( $value ) && '' !== $value ) ? $value : $fallback;
	}

	/**
	 * Resolve merge tags within context string values.
	 *
	 * Runs before template rendering so that template escaping functions
	 * (esc_html, esc_url, etc.) handle the resolved values, not raw {tag} text.
	 *
	 * @param array<string, mixed> $context Template variables.
	 * @return array<string, mixed>
	 */
	private function resolve_context_tags( array $context ): array {
		$replacements = [];

		foreach ( self::MERGE_TAGS as $tag => $key ) {
			if ( isset( $context[ $key ] ) && is_scalar( $context[ $key ] ) ) {
				$replacements[ $tag ] = (string) $context[ $key ];
			}
		}

		if ( empty( $replacements ) ) {
			return $context;
		}

		$search  = array_keys( $replacements );
		$replace = array_values( $replacements );

		foreach ( $context as $ctx_key => $value ) {
			if ( ! is_string( $value ) ) {
				continue;
			}

			if ( isset( self::MERGE_TAGS[ '{' . $ctx_key . '}' ] ) ) {
				continue;
			}

			$context[ $ctx_key ] = str_replace( $search, $replace, $value );
		}

		return $context;
	}

	/**
	 * Load a template file and capture its output.
	 *
	 * @param string               $file    Absolute path to template.
	 * @param array<string, mixed> $context Template variables.
	 * @return string
	 */
	private function load_template( string $file, array $context ): string {
		// $context is available to the included template file.
		unset( $context['file'] );

		ob_start();
		include $file;
		return (string) ob_get_clean();
	}

	/**
	 * Replace merge tags in rendered output.
	 *
	 * This is a post-render safety net. Primary resolution happens in
	 * resolve_context_tags() before rendering. Values are escaped here
	 * because this operates on already-rendered HTML.
	 *
	 * @param string               $output  Rendered HTML.
	 * @param array<string, mixed> $context Template variables.
	 * @return string
	 */
	private function replace_merge_tags( string $output, array $context ): string {
		$replacements = [];

		foreach ( self::MERGE_TAGS as $tag => $key ) {
			if ( isset( $context[ $key ] ) && is_scalar( $context[ $key ] ) ) {
				$replacements[ $tag ] = esc_html( (string) $context[ $key ] );
			}
		}

		if ( empty( $replacements ) ) {
			return $output;
		}

		return str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			$output
		);
	}
}
