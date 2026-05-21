<?php
/**
 * Tests for the Handler class.
 *
 * @package GracefulErrorPages\Tests\Unit
 */

declare( strict_types=1 );

namespace GracefulErrorPages\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use GracefulErrorPages\Handler;
use GracefulErrorPages\TemplateEngine;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Handler unit tests.
 */
class HandlerTest extends TestCase {

	/**
	 * The handler instance.
	 *
	 * @var Handler
	 */
	private Handler $handler;

	/**
	 * Mock template engine.
	 *
	 * @var TemplateEngine|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_engine;

	/**
	 * Set up Brain\Monkey and handler before each test.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();

		$this->mock_engine = $this->createMock( TemplateEngine::class );
		$this->handler     = new Handler( $this->mock_engine );

		Functions\when( '__' )->returnArg();

		Functions\when( 'get_option' )->alias(
			function ( string $key, $default = false ) {
				$options = [
					'gcep_scope'        => 'frontend',
					'gcep_admin_bypass' => 1,
					'gcep_template'     => 'minimal',
				];
				return $options[ $key ] ?? $default;
			}
		);
	}

	/**
	 * Tear down Brain\Monkey after each test.
	 *
	 * @return void
	 */
	protected function tear_down(): void {
		Monkey\tearDown();
		parent::tear_down();
	}

	/**
	 * Test: filter returns default handler for CLI context.
	 *
	 * @return void
	 */
	public function test_filter_returns_default_for_cli(): void {
		$default = static function () {};

		$result = $this->handler->filter_wp_die_handler( $default );

		$this->assertSame( $default, $result );
	}

	/**
	 * Test: filter returns custom handler for frontend requests.
	 *
	 * @return void
	 */
	public function test_filter_returns_custom_for_frontend(): void {
		Functions\when( 'php_sapi_name' )->justReturn( 'apache2handler' );
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'wp_is_json_request' )->justReturn( false );
		Functions\when( 'rest_get_url_prefix' )->justReturn( 'wp-json' );
		Functions\when( 'wp_unslash' )->returnArg();

		$_SERVER['REQUEST_URI'] = '/some-page/';

		$default = static function () {};
		$result  = $this->handler->filter_wp_die_handler( $default );

		$this->assertIsArray( $result );
		$this->assertSame( $this->handler, $result[0] );
		$this->assertSame( 'handle_wp_die', $result[1] );

		unset( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Test: filter returns default handler for admin when scope is frontend.
	 *
	 * @return void
	 */
	public function test_filter_returns_default_for_admin(): void {
		Functions\when( 'php_sapi_name' )->justReturn( 'apache2handler' );
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'wp_is_json_request' )->justReturn( false );
		Functions\when( 'rest_get_url_prefix' )->justReturn( 'wp-json' );
		Functions\when( 'wp_unslash' )->returnArg();

		$_SERVER['REQUEST_URI'] = '/wp-admin/';

		$default = static function () {};
		$result  = $this->handler->filter_wp_die_handler( $default );

		$this->assertSame( $default, $result );

		unset( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Test: filter returns default handler for AJAX requests.
	 *
	 * @return void
	 */
	public function test_filter_returns_default_for_ajax(): void {
		Functions\when( 'php_sapi_name' )->justReturn( 'apache2handler' );
		Functions\when( 'wp_doing_ajax' )->justReturn( true );

		$default = static function () {};
		$result  = $this->handler->filter_wp_die_handler( $default );

		$this->assertSame( $default, $result );
	}

	/**
	 * Test: filter returns default handler for REST requests.
	 *
	 * @return void
	 */
	public function test_filter_returns_default_for_rest(): void {
		Functions\when( 'php_sapi_name' )->justReturn( 'apache2handler' );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'wp_is_json_request' )->justReturn( false );
		Functions\when( 'rest_get_url_prefix' )->justReturn( 'wp-json' );
		Functions\when( 'wp_unslash' )->returnArg();

		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts';

		$default = static function () {};
		$result  = $this->handler->filter_wp_die_handler( $default );

		$this->assertSame( $default, $result );

		unset( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Test: handle_wp_die renders template with string message.
	 *
	 * @return void
	 */
	public function test_handle_wp_die_with_string_message(): void {
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = [] ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
		Functions\when( 'headers_sent' )->justReturn( true );
		$this->mock_engine
			->expects( $this->once() )
			->method( 'render' )
			->with(
				'minimal',
				$this->callback(
					function ( array $ctx ): bool {
						return 'Test error' === $ctx['error_message']
							&& 'Something went wrong' === $ctx['error_title'];
					}
				)
			)
			->willReturn( '<html>Test</html>' );

		$this->expectOutputString( '<html>Test</html>' );

		$this->handler->handle_wp_die( 'Test error', '', [ 'exit' => false ] );
	}

	/**
	 * Test: handle_wp_die extracts message from WP_Error.
	 *
	 * @return void
	 */
	public function test_handle_wp_die_with_wp_error(): void {
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = [] ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
		Functions\when( 'headers_sent' )->justReturn( true );

		$error = new \WP_Error( 'test', 'WP Error message' );

		$this->mock_engine
			->expects( $this->once() )
			->method( 'render' )
			->with(
				'minimal',
				$this->callback(
					function ( array $ctx ): bool {
						return 'WP Error message' === $ctx['error_message'];
					}
				)
			)
			->willReturn( '<html>Error</html>' );

		$this->expectOutputString( '<html>Error</html>' );

		$this->handler->handle_wp_die( $error, '', [ 'exit' => false ] );
	}

	/**
	 * Test: smart title detection maps known error strings.
	 *
	 * @return void
	 */
	public function test_smart_title_detection(): void {
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = [] ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
		Functions\when( 'headers_sent' )->justReturn( true );

		$this->mock_engine
			->method( 'render' )
			->with(
				'minimal',
				$this->callback(
					function ( array $ctx ): bool {
						return 'Link Expired' === $ctx['error_title'];
					}
				)
			)
			->willReturn( '<html>Expired</html>' );

		$this->expectOutputString( '<html>Expired</html>' );

		$this->handler->handle_wp_die(
			'The link you followed has expired.',
			'',
			[ 'exit' => false ]
		);
	}

	/**
	 * Test: explicit title overrides smart detection.
	 *
	 * @return void
	 */
	public function test_explicit_title_overrides_smart_detection(): void {
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = [] ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
		Functions\when( 'headers_sent' )->justReturn( true );

		$this->mock_engine
			->method( 'render' )
			->with(
				'minimal',
				$this->callback(
					function ( array $ctx ): bool {
						return 'Custom Title' === $ctx['error_title'];
					}
				)
			)
			->willReturn( '<html>Custom</html>' );

		$this->expectOutputString( '<html>Custom</html>' );

		$this->handler->handle_wp_die(
			'The link you followed has expired.',
			'Custom Title',
			[ 'exit' => false ]
		);
	}

	/**
	 * Data provider for smart title keyword → expected title mappings.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function smart_title_provider(): array {
		return [
			'expired keyword'      => [ 'Your session has expired', 'Link Expired' ],
			'nonce keyword'        => [ 'Invalid nonce verification', 'Link Expired' ],
			'not allowed keyword'  => [ 'You are not allowed to do that', 'Access Denied' ],
			'permission keyword'   => [ 'No permission to access', 'Access Denied' ],
			'forbidden keyword'    => [ 'Forbidden resource', 'Forbidden' ],
			'unauthorized keyword' => [ 'Unauthorized access attempt', 'Access Denied' ],
			'security keyword'     => [ 'Security check failed', 'Security Error' ],
			'cheatin keyword'      => [ "Cheatin' uh?", 'Access Denied' ],
		];
	}

	/**
	 * Test: all SMART_TITLES keywords map to correct translatable titles.
	 *
	 * @dataProvider smart_title_provider
	 *
	 * @param string $message        The error message containing the keyword.
	 * @param string $expected_title The expected smart title.
	 * @return void
	 */
	public function test_all_smart_title_keywords( string $message, string $expected_title ): void {
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = [] ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
		Functions\when( 'headers_sent' )->justReturn( true );

		$this->mock_engine
			->method( 'render' )
			->with(
				'minimal',
				$this->callback(
					function ( array $ctx ) use ( $expected_title ): bool {
						return $expected_title === $ctx['error_title'];
					}
				)
			)
			->willReturn( '<html>Smart</html>' );

		$this->expectOutputString( '<html>Smart</html>' );

		$this->handler->handle_wp_die(
			$message,
			'',
			[ 'exit' => false ]
		);
	}

	/**
	 * Test: no matching keyword falls back to default title.
	 *
	 * @return void
	 */
	public function test_smart_title_no_match_falls_back_to_default(): void {
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = [] ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
		Functions\when( 'headers_sent' )->justReturn( true );

		$this->mock_engine
			->method( 'render' )
			->with(
				'minimal',
				$this->callback(
					function ( array $ctx ): bool {
						return 'Something went wrong' === $ctx['error_title'];
					}
				)
			)
			->willReturn( '<html>Fallback</html>' );

		$this->expectOutputString( '<html>Fallback</html>' );

		$this->handler->handle_wp_die(
			'Some random unmatched error message.',
			'',
			[ 'exit' => false ]
		);
	}

	/**
	 * Test: smart title matching is case-insensitive.
	 *
	 * @return void
	 */
	public function test_smart_title_case_insensitive(): void {
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = [] ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
		Functions\when( 'headers_sent' )->justReturn( true );

		$this->mock_engine
			->method( 'render' )
			->with(
				'minimal',
				$this->callback(
					function ( array $ctx ): bool {
						return 'Forbidden' === $ctx['error_title'];
					}
				)
			)
			->willReturn( '<html>Case</html>' );

		$this->expectOutputString( '<html>Case</html>' );

		$this->handler->handle_wp_die(
			'FORBIDDEN access to resource.',
			'',
			[ 'exit' => false ]
		);
	}

	/**
	 * Test: args defaults are applied.
	 *
	 * @return void
	 */
	public function test_args_defaults_applied(): void {
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = [] ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
		Functions\when( 'headers_sent' )->justReturn( true );

		$this->mock_engine
			->method( 'render' )
			->with(
				'minimal',
				$this->callback(
					function ( array $ctx ): bool {
						return 500 === $ctx['response_code']
							&& false === $ctx['back_link']
							&& 'UTF-8' === $ctx['charset'];
					}
				)
			)
			->willReturn( '<html>Defaults</html>' );

		$this->expectOutputString( '<html>Defaults</html>' );

		$this->handler->handle_wp_die( 'Error', '', [ 'exit' => false ] );
	}

	/**
	 * Test: register calls add_filter and register_shutdown_function.
	 *
	 * @return void
	 */
	public function test_register_hooks(): void {
		$filter_called   = false;
		$shutdown_called = false;

		Functions\when( 'add_filter' )->alias(
			function () use ( &$filter_called ) {
				$filter_called = true;
			}
		);
		Functions\when( 'register_shutdown_function' )->alias(
			function () use ( &$shutdown_called ) {
				$shutdown_called = true;
			}
		);

		$this->handler->register();

		$this->assertTrue( $filter_called, 'add_filter should be called' );
		$this->assertTrue( $shutdown_called, 'register_shutdown_function should be called' );
	}

	/**
	 * Test: filter returns default for admin bypass with manage_options capability.
	 *
	 * @return void
	 */
	public function test_filter_returns_default_for_admin_bypass(): void {
		Functions\when( 'php_sapi_name' )->justReturn( 'apache2handler' );
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'wp_is_json_request' )->justReturn( false );
		Functions\when( 'rest_get_url_prefix' )->justReturn( 'wp-json' );
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'current_user_can' )->justReturn( true );

		$_SERVER['REQUEST_URI'] = '/some-page/';

		$default = static function () {};
		$result  = $this->handler->filter_wp_die_handler( $default );

		$this->assertSame( $default, $result );

		unset( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Test: handle_wp_die accepts array as title (WP 2-argument form).
	 *
	 * @return void
	 */
	public function test_handle_wp_die_title_as_array(): void {
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = [] ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
		Functions\when( 'headers_sent' )->justReturn( true );

		$this->mock_engine
			->expects( $this->once() )
			->method( 'render' )
			->with(
				'minimal',
				$this->callback(
					function ( array $ctx ): bool {
						return 403 === $ctx['response_code']
							&& 'Forbidden' === $ctx['error_message'];
					}
				)
			)
			->willReturn( '<html>403</html>' );

		$this->expectOutputString( '<html>403</html>' );

		$this->handler->handle_wp_die( 'Forbidden', [ 'response' => 403, 'exit' => false ] );
	}

	/**
	 * Test: normalize_message handles non-string non-WP_Error types safely.
	 *
	 * @return void
	 */
	public function test_handle_wp_die_with_integer_message(): void {
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = [] ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
		Functions\when( 'headers_sent' )->justReturn( true );

		$this->mock_engine
			->expects( $this->once() )
			->method( 'render' )
			->with(
				'minimal',
				$this->callback(
					function ( array $ctx ): bool {
						return '0' === $ctx['error_message'];
					}
				)
			)
			->willReturn( '<html>Zero</html>' );

		$this->expectOutputString( '<html>Zero</html>' );

		$this->handler->handle_wp_die( 0, '', [ 'exit' => false ] );
	}

	/**
	 * Test: filter returns default handler for cron context.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_filter_returns_default_for_cron(): void {
		Functions\when( 'php_sapi_name' )->justReturn( 'apache2handler' );
		define( 'DOING_CRON', true );

		$default = static function () {};
		$result  = $this->handler->filter_wp_die_handler( $default );

		$this->assertSame( $default, $result );
	}

	/**
	 * Test: filter returns default for frontend when scope is admin-only.
	 *
	 * @return void
	 */
	public function test_filter_returns_default_for_admin_scope_on_frontend(): void {
		Functions\when( 'php_sapi_name' )->justReturn( 'apache2handler' );
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'wp_is_json_request' )->justReturn( false );
		Functions\when( 'rest_get_url_prefix' )->justReturn( 'wp-json' );
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'get_option' )->alias(
			function ( string $key, $default = false ) {
				$options = [
					'gcep_scope'        => 'admin',
					'gcep_admin_bypass' => true,
					'gcep_template'     => 'minimal',
				];
				return $options[ $key ] ?? $default;
			}
		);

		$_SERVER['REQUEST_URI'] = '/some-page/';

		$default = static function () {};
		$result  = $this->handler->filter_wp_die_handler( $default );

		$this->assertSame( $default, $result );

		unset( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Test: response code is validated within valid HTTP range.
	 *
	 * @return void
	 */
	public function test_invalid_response_code_defaults_to_500(): void {
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = [] ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
		Functions\when( 'headers_sent' )->justReturn( true );

		$this->mock_engine
			->expects( $this->once() )
			->method( 'render' )
			->with(
				'minimal',
				$this->callback(
					function ( array $ctx ): bool {
						return 500 === $ctx['response_code'];
					}
				)
			)
			->willReturn( '<html>Invalid</html>' );

		$this->expectOutputString( '<html>Invalid</html>' );

		$this->handler->handle_wp_die( 'Error', '', [ 'response' => 999, 'exit' => false ] );
	}

	/**
	 * Test: back_link=true is passed to template context.
	 *
	 * @return void
	 */
	public function test_back_link_arg_honored(): void {
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = [] ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
		Functions\when( 'headers_sent' )->justReturn( true );

		$this->mock_engine
			->method( 'render' )
			->with(
				'minimal',
				$this->callback(
					function ( array $ctx ): bool {
						return true === $ctx['back_link'];
					}
				)
			)
			->willReturn( '<html>Back</html>' );

		$this->expectOutputString( '<html>Back</html>' );

		$this->handler->handle_wp_die(
			'Error',
			'',
			[ 'back_link' => true, 'exit' => false ]
		);
	}

	/**
	 * Test: explicit charset arg is passed to template context.
	 *
	 * @return void
	 */
	public function test_charset_arg_honored(): void {
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = [] ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
		Functions\when( 'headers_sent' )->justReturn( true );

		$this->mock_engine
			->method( 'render' )
			->with(
				'minimal',
				$this->callback(
					function ( array $ctx ): bool {
						return 'ISO-8859-1' === $ctx['charset'];
					}
				)
			)
			->willReturn( '<html>Charset</html>' );

		$this->expectOutputString( '<html>Charset</html>' );

		$this->handler->handle_wp_die(
			'Error',
			'',
			[ 'charset' => 'ISO-8859-1', 'exit' => false ]
		);
	}

	/**
	 * Test: explicit response code is passed to template context.
	 *
	 * @return void
	 */
	public function test_response_code_arg_honored(): void {
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = [] ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
		Functions\when( 'headers_sent' )->justReturn( true );

		$this->mock_engine
			->method( 'render' )
			->with(
				'minimal',
				$this->callback(
					function ( array $ctx ): bool {
						return 403 === $ctx['response_code'];
					}
				)
			)
			->willReturn( '<html>403</html>' );

		$this->expectOutputString( '<html>403</html>' );

		$this->handler->handle_wp_die(
			'Forbidden',
			'',
			[ 'response' => 403, 'exit' => false ]
		);
	}

	/**
	 * Test: RTL text direction is honored.
	 *
	 * @return void
	 */
	public function test_rtl_text_direction_honored(): void {
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = [] ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
		Functions\when( 'headers_sent' )->justReturn( true );

		$this->mock_engine
			->method( 'render' )
			->with(
				'minimal',
				$this->callback(
					function ( array $ctx ): bool {
						return 'rtl' === $ctx['text_direction'];
					}
				)
			)
			->willReturn( '<html>RTL</html>' );

		$this->expectOutputString( '<html>RTL</html>' );

		$this->handler->handle_wp_die(
			'Error',
			'',
			[ 'text_direction' => 'rtl', 'exit' => false ]
		);
	}

	/**
	 * Test: text_direction constrained to ltr/rtl.
	 *
	 * @return void
	 */
	public function test_text_direction_constrained(): void {
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = [] ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
		Functions\when( 'headers_sent' )->justReturn( true );

		$this->mock_engine
			->expects( $this->once() )
			->method( 'render' )
			->with(
				'minimal',
				$this->callback(
					function ( array $ctx ): bool {
						return 'ltr' === $ctx['text_direction'];
					}
				)
			)
			->willReturn( '<html>Dir</html>' );

		$this->expectOutputString( '<html>Dir</html>' );

		$this->handler->handle_wp_die(
			'Error',
			'',
			[ 'text_direction' => 'invalid', 'exit' => false ]
		);
	}

	/**
	 * Test: WP_Error with multiple messages includes all of them.
	 *
	 * @return void
	 */
	public function test_handle_wp_die_wp_error_multiple_messages(): void {
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = [] ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
		Functions\when( 'headers_sent' )->justReturn( true );

		$error = new \WP_Error( 'first', 'First error.' );
		$error->add( 'second', 'Second error.' );
		$error->add( 'third', 'Third error.' );

		$this->mock_engine
			->expects( $this->once() )
			->method( 'render' )
			->with(
				'minimal',
				$this->callback(
					function ( array $ctx ): bool {
						return "First error.\nSecond error.\nThird error." === $ctx['error_message'];
					}
				)
			)
			->willReturn( '<html>Multi</html>' );

		$this->expectOutputString( '<html>Multi</html>' );

		$this->handler->handle_wp_die( $error, '', [ 'exit' => false ] );
	}

	/**
	 * Test: WP_Error with single message returns just the string.
	 *
	 * @return void
	 */
	public function test_handle_wp_die_wp_error_single_message(): void {
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = [] ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
		Functions\when( 'headers_sent' )->justReturn( true );

		$error = new \WP_Error( 'only', 'Only error.' );

		$this->mock_engine
			->expects( $this->once() )
			->method( 'render' )
			->with(
				'minimal',
				$this->callback(
					function ( array $ctx ): bool {
						return 'Only error.' === $ctx['error_message'];
					}
				)
			)
			->willReturn( '<html>Single</html>' );

		$this->expectOutputString( '<html>Single</html>' );

		$this->handler->handle_wp_die( $error, '', [ 'exit' => false ] );
	}

	/**
	 * Test: register() allocates reserved memory.
	 *
	 * @return void
	 */
	public function test_register_allocates_reserved_memory(): void {
		Functions\when( 'add_filter' )->justReturn( true );

		$this->handler->register();

		$ref = new \ReflectionProperty( Handler::class, 'reserved_memory' );
		$ref->setAccessible( true );
		$value = $ref->getValue( $this->handler );

		$this->assertIsString( $value );
		$this->assertSame( 16384, strlen( $value ) );
	}

	/**
	 * Test: handle_fatal_error frees reserved memory.
	 *
	 * @return void
	 */
	public function test_handle_fatal_error_frees_reserved_memory(): void {
		Functions\when( 'add_filter' )->justReturn( true );

		$this->handler->register();

		$ref = new \ReflectionProperty( Handler::class, 'reserved_memory' );
		$ref->setAccessible( true );

		$this->assertNotNull( $ref->getValue( $this->handler ) );

		$this->handler->handle_fatal_error();

		$this->assertNull( $ref->getValue( $this->handler ) );
	}

	/**
	 * Test: should_skip returns true for Customizer preview.
	 *
	 * @return void
	 */
	public function test_filter_returns_default_for_customizer_preview(): void {
		$default = static function () {};

		Functions\when( 'php_sapi_name' )->justReturn( 'apache2handler' );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'wp_is_json_request' )->justReturn( false );
		Functions\when( 'rest_get_url_prefix' )->justReturn( 'wp-json' );
		Functions\when( 'is_customize_preview' )->justReturn( true );
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'current_user_can' )->justReturn( false );

		$result = $this->handler->filter_wp_die_handler( $default );

		$this->assertSame( $default, $result );
	}
}
