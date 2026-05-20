<?php
/**
 * Error handler: wp_die override + fatal error shutdown handler.
 *
 * @package GracefulErrorPages
 */

declare( strict_types=1 );

namespace GracefulErrorPages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Intercepts wp_die() and PHP fatal errors to render branded error pages.
 */
class Handler {

	/**
	 * Fatal error types to catch in the shutdown handler.
	 *
	 * @var array<int>
	 */
	private const FATAL_ERROR_TYPES = [
		E_ERROR,
		E_PARSE,
		E_COMPILE_ERROR,
		E_CORE_ERROR,
	];

	/**
	 * Get smart title mappings: substrings in error messages → contextual titles.
	 *
	 * @return array<string, string>
	 */
	private function get_smart_titles(): array {
		return [
			'expired'      => __( 'Link Expired', 'graceful-error-pages' ),
			'nonce'        => __( 'Link Expired', 'graceful-error-pages' ),
			'not allowed'  => __( 'Access Denied', 'graceful-error-pages' ),
			'permission'   => __( 'Access Denied', 'graceful-error-pages' ),
			'forbidden'    => __( 'Forbidden', 'graceful-error-pages' ),
			'unauthorized' => __( 'Access Denied', 'graceful-error-pages' ),
			'security'     => __( 'Security Error', 'graceful-error-pages' ),
			'cheatin'      => __( 'Access Denied', 'graceful-error-pages' ),
		];
	}

	/**
	 * The template engine.
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
	 * Register hooks: wp_die filter and shutdown handler.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_die_handler', [ $this, 'filter_wp_die_handler' ] );
		register_shutdown_function( [ $this, 'handle_fatal_error' ] );
	}

	/**
	 * Filter the wp_die handler.
	 *
	 * Returns a custom handler when all guards pass, or the default handler
	 * when interception should be skipped.
	 *
	 * @param callable $default_handler The default wp_die handler.
	 * @return callable
	 */
	public function filter_wp_die_handler( callable $default_handler ): callable {
		if ( $this->should_skip() ) {
			return $default_handler;
		}

		return [ $this, 'handle_wp_die' ];
	}

	/**
	 * Custom wp_die handler.
	 *
	 * Normalizes the message, builds context, renders the template, and exits.
	 *
	 * @param string|\WP_Error           $message The error message or WP_Error.
	 * @param string|array<string,mixed> $title   The error title, or args array (WP 2-arg form).
	 * @param array<string,mixed>|string $args    Optional arguments.
	 * @return void
	 */
	public function handle_wp_die( $message, $title = '', $args = [] ): void {
		// WordPress allows $title to be an array of args (2-argument form).
		if ( is_array( $title ) ) {
			$args  = $title;
			$title = '';
		}

		if ( ! is_string( $title ) ) {
			$title = '';
		}

		if ( is_string( $args ) ) {
			$args = [];
		}

		$defaults = [
			'response'       => 500,
			'back_link'      => false,
			'charset'        => '',
			'text_direction' => 'ltr',
			'exit'           => true,
			'code'           => '',
			'link_url'       => '',
			'link_text'      => '',
		];

		$args = wp_parse_args( $args, $defaults );

		$message_text = $this->normalize_message( $message );
		$title_text   = $this->resolve_title( $title, $message_text );
		$charset      = '' !== $args['charset']
			? $args['charset']
			: get_bloginfo( 'charset' );
		$charset      = is_string( $charset ) ? preg_replace( '/[\r\n]/', '', $charset ) : 'UTF-8';

		$response_code = (int) $args['response'];
		if ( $response_code < 100 || $response_code > 599 ) {
			$response_code = 500;
		}

		if ( ! headers_sent() ) {
			status_header( $response_code );
			nocache_headers();
			header( 'Content-Type: text/html; charset=' . $charset );
		}

		$context = [
			'error_title'    => $title_text,
			'error_message'  => $message_text,
			'response_code'  => $response_code,
			'back_link'      => (bool) $args['back_link'],
			'back_url'       => is_string( $args['link_url'] ) ? $args['link_url'] : '',
			'charset'        => $charset,
			'text_direction' => ( is_string( $args['text_direction'] ) && in_array( $args['text_direction'], [ 'ltr', 'rtl' ], true ) )
				? $args['text_direction']
				: 'ltr',
		];

		$template = get_option( 'gep_template', 'minimal' );
		$output   = $this->template_engine->render(
			is_string( $template ) ? $template : 'minimal',
			$context
		);

		if ( '' === $output ) {
			$output = $this->template_engine->render( 'minimal', $context );
		}

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template handles escaping.

		if ( $args['exit'] ) {
			die();
		}
	}

	/**
	 * Shutdown handler for PHP fatal errors.
	 *
	 * Self-contained: renders inline HTML/CSS without file includes.
	 *
	 * @return void
	 */
	public function handle_fatal_error(): void {
		$error = error_get_last();

		if ( null === $error || ! in_array( $error['type'], self::FATAL_ERROR_TYPES, true ) ) {
			return;
		}

		if ( $this->is_cli() ) {
			return;
		}

		if ( function_exists( 'get_option' ) && ! get_option( 'gep_fatal_errors', 1 ) ) {
			return;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return;
		}

		if ( $this->is_ajax_or_json() ) {
			$this->send_fatal_json( $error );
			return;
		}

		if ( function_exists( 'is_admin' ) && is_admin()
			&& 'everywhere' !== ( function_exists( 'get_option' ) ? get_option( 'gep_scope', 'frontend' ) : 'frontend' )
		) {
			return;
		}

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		$this->render_fatal_html( $error );
	}

	/**
	 * Determine if the handler should skip (return default handler).
	 *
	 * @return bool
	 */
	private function should_skip(): bool {
		if ( $this->is_cli() ) {
			return true;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}

		if ( $this->is_ajax_or_json() ) {
			return true;
		}

		if ( $this->is_rest_request() ) {
			return true;
		}

		$scope = get_option( 'gep_scope', 'frontend' );

		if ( is_admin() && 'frontend' === $scope ) {
			return true;
		}

		if ( ! is_admin() && 'admin' === $scope ) {
			return true;
		}

		if ( get_option( 'gep_admin_bypass', 1 ) && current_user_can( 'manage_options' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if running in CLI context.
	 *
	 * @return bool
	 */
	private function is_cli(): bool {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		return 'cli' === php_sapi_name();
	}

	/**
	 * Check if the request is AJAX or JSON.
	 *
	 * @return bool
	 */
	private function is_ajax_or_json(): bool {
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return true;
		}

		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the request is a REST API request.
	 *
	 * @return bool
	 */
	private function is_rest_request(): bool {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		$rest_prefix = rest_get_url_prefix();

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Comparing prefix only.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

		return is_string( $request_uri ) && str_contains( $request_uri, '/' . $rest_prefix . '/' );
	}

	/**
	 * Normalize the error message from string, WP_Error, or other types.
	 *
	 * @param mixed $message The message.
	 * @return string
	 */
	private function normalize_message( $message ): string {
		if ( $message instanceof \WP_Error ) {
			return $message->get_error_message();
		}

		if ( is_scalar( $message ) || ( is_object( $message ) && method_exists( $message, '__toString' ) ) ) {
			return (string) $message;
		}

		return '';
	}

	/**
	 * Resolve the error title, using smart detection if no explicit title.
	 *
	 * @param string $title   The explicit title.
	 * @param string $message The error message (for smart detection).
	 * @return string
	 */
	private function resolve_title( string $title, string $message ): string {
		if ( '' !== $title ) {
			return $title;
		}

		$lower = strtolower( $message );

		foreach ( $this->get_smart_titles() as $keyword => $smart_title ) {
			if ( str_contains( $lower, $keyword ) ) {
				return $smart_title;
			}
		}

		return __( 'Something went wrong', 'graceful-error-pages' );
	}

	/**
	 * Send a JSON response for fatal errors during AJAX/JSON requests.
	 *
	 * @param array{type: int, message: string, file: string, line: int} $error The error.
	 * @return void
	 */
	private function send_fatal_json( array $error ): void {
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		if ( ! headers_sent() ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			status_header( 500 );
		}

		$data = [ 'error' => true ];

		if ( $this->should_show_debug() ) {
			$data['message'] = $error['message'];
			$data['file']    = $error['file'];
			$data['line']    = $error['line'];
		}

		echo wp_json_encode( $data );
	}

	/**
	 * Render self-contained HTML for fatal errors.
	 *
	 * Uses inline CSS only — no file includes, no theme dependencies.
	 *
	 * @param array{type: int, message: string, file: string, line: int} $error The error.
	 * @return void
	 */
	private function render_fatal_html( array $error ): void {
		$site_name = function_exists( 'get_option' ) ? get_option( 'gep_site_name', '' ) : '';
		if ( ! is_string( $site_name ) || '' === $site_name ) {
			$site_name = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : '';
		}

		$brand_color = function_exists( 'get_option' ) ? get_option( 'gep_brand_color', '#2563eb' ) : '#2563eb';
		if ( ! is_string( $brand_color ) || ! preg_match( '/^#[0-9a-fA-F]{3,8}$/', $brand_color ) ) {
			$brand_color = '#2563eb';
		}
		$home_url = function_exists( 'home_url' ) ? home_url( '/' ) : '/';

		if ( ! headers_sent() ) {
			if ( function_exists( 'status_header' ) ) {
				status_header( 500 );
			}
			if ( function_exists( 'nocache_headers' ) ) {
				nocache_headers();
			}
			header( 'Content-Type: text/html; charset=utf-8' );
		}

		$show_debug = $this->should_show_debug();
		$lang       = function_exists( 'get_locale' ) ? str_replace( '_', '-', get_locale() ) : 'en';

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- All values escaped inline below.
		echo '<!DOCTYPE html><html lang="' . esc_attr( $lang ) . '"><head>';
		echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
		echo '<meta name="robots" content="noindex,nofollow">';
		echo '<title>' . esc_html__( 'Server Error', 'graceful-error-pages' ) . '</title>';
		echo '<style>';
		echo '*{margin:0;padding:0;box-sizing:border-box}';
		echo 'body{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1rem;';
		echo 'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;';
		echo 'background:#f9fafb;color:#1f2937}';
		echo '.c{max-width:480px;text-align:center;padding:2.5rem 2rem;background:#fff;border-radius:8px;';
		echo 'box-shadow:0 1px 3px rgba(0,0,0,.1)}';
		echo 'h1{font-size:1.5rem;margin-bottom:.75rem}';
		echo 'p{color:#6b7280;margin-bottom:2rem}';
		echo 'a{display:inline-block;padding:.625rem 1.5rem;color:#fff;';
		echo 'background:' . esc_attr( $brand_color ) . ';';
		echo 'border-radius:8px;text-decoration:none;font-size:.875rem}';
		echo 'a:focus-visible{outline:2px solid ' . esc_attr( $brand_color ) . ';outline-offset:2px}';
		echo '.d{margin-top:1.5rem;padding:1rem;background:#fef2f2;border-radius:6px;text-align:left;';
		echo 'font-size:.8rem;color:#991b1b;word-break:break-all}';
		echo '@media(prefers-color-scheme:dark){body{background:#111827;color:#f9fafb}';
		echo '.c{background:#1f2937}.d{background:#451a1a;color:#fca5a5}}';
		echo '</style></head><body><main class="c">';
		echo '<h1>' . esc_html__( 'Something went wrong', 'graceful-error-pages' ) . '</h1>';
		echo '<p>' . esc_html__( 'We encountered an unexpected error. Please try again later.', 'graceful-error-pages' ) . '</p>';
		echo '<a href="' . esc_url( $home_url ) . '">' . esc_html__( 'Go to Homepage', 'graceful-error-pages' ) . '</a>';

		if ( $show_debug ) {
			echo '<div class="d">';
			echo '<strong>' . esc_html__( 'Debug Info', 'graceful-error-pages' ) . '</strong><br>';
			echo esc_html( $error['message'] ) . '<br>';
			echo esc_html( $error['file'] ) . ':' . (int) $error['line'];
			echo '</div>';
		}

		echo '</main></body></html>';
		// phpcs:enable
	}

	/**
	 * Determine if debug info should be shown.
	 *
	 * @return bool
	 */
	private function should_show_debug(): bool {
		if ( function_exists( 'get_option' ) && ! get_option( 'gep_show_debug', 1 ) ) {
			return false;
		}

		return defined( 'WP_DEBUG' ) && WP_DEBUG
			&& defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;
	}
}
