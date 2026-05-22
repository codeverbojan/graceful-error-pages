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
		$this->enqueue_error_styles( $context );

		$output = $this->load_template( $file, $context );

		return $this->replace_merge_tags( $output, $context );
	}

	/**
	 * Output a template directly (no output buffering).
	 *
	 * Each value is escaped at point of output inside the template file itself
	 * (esc_html, esc_attr, esc_url, wp_kses_post). This avoids capturing the
	 * full HTML into a variable and then echoing it unescaped.
	 *
	 * Unlike render(), this does not run post-render merge tag replacement.
	 * Templates must use $context variables, not raw {tag} text in markup.
	 *
	 * @param string               $template Template slug (e.g. 'minimal').
	 * @param array<string, mixed> $context  Template variables.
	 * @return void
	 */
	public function display( string $template, array $context = [] ): void {
		$file = $this->resolve_path( $template );

		if ( '' === $file ) {
			return;
		}

		$context = $this->build_context( $context );
		$context = $this->resolve_context_tags( $context );
		$this->enqueue_error_styles( $context );

		unset( $context['file'] );

		include $file;
	}

	/**
	 * Check whether a template slug resolves to a valid file.
	 *
	 * @param string $template Template slug.
	 * @return bool
	 */
	public function has_template( string $template ): bool {
		return '' !== $this->resolve_path( $template );
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
	 * Register and enqueue the error page stylesheet and inline CSS custom properties.
	 *
	 * Uses wp_register_style() + wp_enqueue_style() + wp_add_inline_style()
	 * so templates can output styles via wp_print_styles().
	 *
	 * @param array<string, mixed> $context Template context.
	 * @return void
	 */
	public function enqueue_error_styles( array $context ): void {
		$css_url = (string) ( $context['css_url'] ?? '' );
		$version = defined( 'GCEP_VERSION' ) ? constant( 'GCEP_VERSION' ) : '1.0.0';

		if ( '' !== $css_url ) {
			wp_register_style( 'gcep-error-page', $css_url, [], $version );
		} else {
			wp_register_style( 'gcep-error-page', false, [], $version );
		}
		wp_enqueue_style( 'gcep-error-page' );

		$inline_css = $this->build_css_custom_properties( $context );
		if ( '' !== $inline_css ) {
			wp_add_inline_style( 'gcep-error-page', $inline_css );
		}
	}

	/**
	 * Build CSS custom properties from context values.
	 *
	 * @param array<string, mixed> $context Template context.
	 * @return string CSS rule or empty string.
	 */
	private function build_css_custom_properties( array $context ): string {
		$hex_pattern = '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/';
		$props       = [];

		$brand = (string) ( $context['brand_color'] ?? '' );
		if ( '' !== $brand && preg_match( $hex_pattern, $brand ) ) {
			$props[] = '--gcep-brand-color: ' . $brand;
		}

		$bg = (string) ( $context['bg_color'] ?? '' );
		if ( '' !== $bg && preg_match( $hex_pattern, $bg ) ) {
			$props[] = '--gcep-bg-color: ' . $bg;
		}

		$text = (string) ( $context['text_color'] ?? '' );
		if ( '' !== $text && preg_match( $hex_pattern, $text ) ) {
			$props[] = '--gcep-text-color: ' . $text;
		}

		if ( empty( $props ) ) {
			return '';
		}

		return "body.gcep-error-page[data-dark-mode] {\n\t" . implode( ";\n\t", $props ) . ";\n}";
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
