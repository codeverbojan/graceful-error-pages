<?php
/**
 * Tests for the Preview class.
 *
 * @package GracefulErrorPages\Tests\Unit
 */

declare( strict_types=1 );

namespace GracefulErrorPages\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use GracefulErrorPages\Preview;
use GracefulErrorPages\TemplateEngine;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Preview unit tests.
 */
class PreviewTest extends TestCase {

	/**
	 * The preview instance.
	 *
	 * @var Preview
	 */
	private Preview $preview;

	/**
	 * Mock template engine.
	 *
	 * @var TemplateEngine|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_engine;

	/**
	 * Set up Brain\Monkey and Preview before each test.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();

		$_GET = [];

		$this->mock_engine = $this->createMock( TemplateEngine::class );
		$this->preview     = new Preview( $this->mock_engine );

		Functions\when( '__' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
	}

	/**
	 * Tear down Brain\Monkey after each test.
	 *
	 * @return void
	 */
	protected function tear_down(): void {
		$_GET = [];
		Monkey\tearDown();
		parent::tear_down();
	}

	/**
	 * Test: register hooks the AJAX action.
	 *
	 * @return void
	 */
	public function test_register_hooks_ajax_action(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'wp_ajax_gep_preview', [ $this->preview, 'handle' ] );

		$this->preview->register();

		$this->assertTrue( true );
	}

	/**
	 * Test: NONCE_ACTION and ACTION constants have expected values.
	 *
	 * @return void
	 */
	public function test_constants_are_defined(): void {
		$this->assertSame( 'gep_preview', Preview::ACTION );
		$this->assertSame( 'gep_preview', Preview::NONCE_ACTION );
	}

	/**
	 * Test: build_preview_context includes secondary button fields from GET params.
	 *
	 * @return void
	 */
	public function test_context_includes_secondary_button_from_get(): void {
		$_GET['gep_secondary_btn_text'] = 'Contact Us';
		$_GET['gep_secondary_btn_url']  = 'https://example.com/contact';

		Functions\when( 'get_option' )->justReturn( '' );

		$method = new \ReflectionMethod( $this->preview, 'build_preview_context' );
		$method->setAccessible( true );

		$context = $method->invoke( $this->preview );

		$this->assertSame( 'Contact Us', $context['secondary_btn_text'] );
		$this->assertSame( 'https://example.com/contact', $context['secondary_btn_url'] );
	}

	/**
	 * Test: build_preview_context includes secondary button fields from saved options.
	 *
	 * @return void
	 */
	public function test_context_includes_secondary_button_from_options(): void {
		Functions\when( 'get_option' )->alias(
			function ( string $key, $default = false ) {
				$options = [
					'gep_secondary_btn_text' => 'Saved Text',
					'gep_secondary_btn_url'  => 'https://example.com/saved',
				];
				return $options[ $key ] ?? $default;
			}
		);

		$method = new \ReflectionMethod( $this->preview, 'build_preview_context' );
		$method->setAccessible( true );

		$context = $method->invoke( $this->preview );

		$this->assertSame( 'Saved Text', $context['secondary_btn_text'] );
		$this->assertSame( 'https://example.com/saved', $context['secondary_btn_url'] );
	}

	/**
	 * Test: build_preview_context omits empty optional fields.
	 *
	 * @return void
	 */
	public function test_context_omits_empty_optional_fields(): void {
		Functions\when( 'get_option' )->justReturn( '' );

		$method = new \ReflectionMethod( $this->preview, 'build_preview_context' );
		$method->setAccessible( true );

		$context = $method->invoke( $this->preview );

		$this->assertArrayNotHasKey( 'secondary_btn_text', $context );
		$this->assertArrayNotHasKey( 'secondary_btn_url', $context );
		$this->assertArrayNotHasKey( 'logo_url', $context );
	}

	/**
	 * Test: build_preview_context includes all expected optional field keys.
	 *
	 * @return void
	 */
	public function test_context_supports_all_optional_fields(): void {
		$all_options = [
			'gep_logo_url'           => 'https://example.com/logo.png',
			'gep_icon_url'           => 'https://example.com/icon.png',
			'gep_brand_color'        => '#ff0000',
			'gep_bg_color'           => '#ffffff',
			'gep_text_color'         => '#333333',
			'gep_dark_mode'          => '1',
			'gep_site_name'          => 'Test Site',
			'gep_primary_btn_text'   => 'Go Home',
			'gep_primary_btn_url'    => 'https://example.com',
			'gep_secondary_btn_text' => 'Contact',
			'gep_secondary_btn_url'  => 'https://example.com/contact',
			'gep_support_link'       => 'https://example.com/support',
			'gep_copyright'          => '2026 Test',
		];

		foreach ( $all_options as $key => $value ) {
			$_GET[ $key ] = $value;
		}

		Functions\when( 'get_option' )->justReturn( '' );

		$method = new \ReflectionMethod( $this->preview, 'build_preview_context' );
		$method->setAccessible( true );

		$context = $method->invoke( $this->preview );

		$expected_keys = [
			'logo_url', 'icon_url', 'brand_color', 'bg_color', 'text_color',
			'dark_mode', 'site_name', 'primary_btn_text', 'primary_btn_url',
			'secondary_btn_text', 'secondary_btn_url', 'support_link', 'copyright',
		];

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $context, "Missing context key: $key" );
		}
	}

	/**
	 * Test: build_preview_context always includes required fields with defaults.
	 *
	 * @return void
	 */
	public function test_context_always_has_required_fields_with_defaults(): void {
		Functions\when( 'get_option' )->justReturn( '' );

		$method = new \ReflectionMethod( $this->preview, 'build_preview_context' );
		$method->setAccessible( true );

		$context = $method->invoke( $this->preview );

		$this->assertSame( 'Something went wrong', $context['error_title'] );
		$this->assertStringContainsString( 'preview of your error page', $context['error_message'] );
		$this->assertTrue( $context['back_link'] );
		$this->assertSame( '#', $context['back_url'] );
		$this->assertSame( 500, $context['response_code'] );
	}

	/**
	 * Test: GET parameters override saved options.
	 *
	 * @return void
	 */
	public function test_get_params_override_saved_options(): void {
		$_GET['gep_site_name'] = 'From GET';

		Functions\when( 'get_option' )->alias(
			function ( string $key, $default = false ) {
				if ( 'gep_site_name' === $key ) {
					return 'From Database';
				}
				return $default;
			}
		);

		$method = new \ReflectionMethod( $this->preview, 'get_preview_param' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->preview, 'gep_site_name', '' );

		$this->assertSame( 'From GET', $result );
	}

	/**
	 * Test: get_preview_param falls back to saved option when GET is absent.
	 *
	 * @return void
	 */
	public function test_falls_back_to_saved_option(): void {
		Functions\when( 'get_option' )->alias(
			function ( string $key, $default = false ) {
				if ( 'gep_site_name' === $key ) {
					return 'From Database';
				}
				return $default;
			}
		);

		$method = new \ReflectionMethod( $this->preview, 'get_preview_param' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->preview, 'gep_site_name', '' );

		$this->assertSame( 'From Database', $result );
	}

	/**
	 * Test: get_preview_param returns empty string from DB rather than fallback.
	 *
	 * The fallback only applies when get_option returns a non-string value.
	 * An empty string from the database is a valid string value.
	 *
	 * @return void
	 */
	public function test_empty_string_option_is_not_replaced_by_fallback(): void {
		Functions\when( 'get_option' )->justReturn( '' );

		$method = new \ReflectionMethod( $this->preview, 'get_preview_param' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->preview, 'gep_site_name', 'default_val' );

		$this->assertSame( '', $result );
	}

	/**
	 * Test: get_preview_param returns fallback when option is non-string.
	 *
	 * @return void
	 */
	public function test_returns_fallback_for_non_string_option(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$method = new \ReflectionMethod( $this->preview, 'get_preview_param' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->preview, 'gep_test', 'fallback' );

		$this->assertSame( 'fallback', $result );
	}
}
