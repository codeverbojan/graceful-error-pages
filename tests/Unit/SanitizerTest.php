<?php
/**
 * Tests for the Sanitizer class.
 *
 * @package GracefulErrorPages\Tests\Unit
 */

declare( strict_types=1 );

namespace GracefulErrorPages\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use GracefulErrorPages\Sanitizer;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Sanitizer unit tests.
 */
class SanitizerTest extends TestCase {

	/**
	 * Set up Brain\Monkey before each test.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();
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
	 * Test template sanitizer accepts valid slugs.
	 *
	 * @return void
	 */
	/**
	 * Stub i18n functions needed by TemplateEngine::get_available_templates().
	 *
	 * @return void
	 */
	private function stub_i18n(): void {
		Functions\when( '__' )->returnArg();
	}

	/**
	 * Test template sanitizer accepts valid slugs.
	 *
	 * @return void
	 */
	public function test_template_accepts_valid_slug(): void {
		$this->stub_i18n();
		Functions\when( 'sanitize_text_field' )->returnArg();

		$this->assertSame( 'minimal', Sanitizer::template( 'minimal' ) );
		$this->assertSame( 'dark', Sanitizer::template( 'dark' ) );
		$this->assertSame( 'corporate', Sanitizer::template( 'corporate' ) );
		$this->assertSame( 'friendly', Sanitizer::template( 'friendly' ) );
		$this->assertSame( 'starter', Sanitizer::template( 'starter' ) );
	}

	/**
	 * Test template sanitizer rejects invalid slugs.
	 *
	 * @return void
	 */
	public function test_template_rejects_invalid_slug(): void {
		$this->stub_i18n();
		Functions\when( 'sanitize_text_field' )->returnArg();

		$this->assertSame( 'minimal', Sanitizer::template( 'nonexistent' ) );
		$this->assertSame( 'minimal', Sanitizer::template( '' ) );
		$this->assertSame( 'minimal', Sanitizer::template( '../evil' ) );
	}

	/**
	 * Test template sanitizer handles non-string input.
	 *
	 * @return void
	 */
	public function test_template_handles_non_string(): void {
		$this->stub_i18n();
		Functions\when( 'sanitize_text_field' )->returnArg();

		$this->assertSame( 'minimal', Sanitizer::template( null ) );
		$this->assertSame( 'minimal', Sanitizer::template( 123 ) );
		$this->assertSame( 'minimal', Sanitizer::template( [] ) );
	}

	/**
	 * Test hex_color sanitizer accepts valid colors.
	 *
	 * @return void
	 */
	public function test_hex_color_accepts_valid(): void {
		Functions\when( 'sanitize_hex_color' )->returnArg();

		$this->assertSame( '#3b82f6', Sanitizer::hex_color( '#3b82f6' ) );
		$this->assertSame( '#fff', Sanitizer::hex_color( '#fff' ) );
	}

	/**
	 * Test hex_color sanitizer returns empty for invalid.
	 *
	 * @return void
	 */
	public function test_hex_color_rejects_invalid(): void {
		Functions\when( 'sanitize_hex_color' )->justReturn( null );

		$this->assertSame( '', Sanitizer::hex_color( 'not-a-color' ) );
	}

	/**
	 * Test hex_color handles non-string input.
	 *
	 * @return void
	 */
	public function test_hex_color_handles_non_string(): void {
		$this->assertSame( '', Sanitizer::hex_color( null ) );
		$this->assertSame( '', Sanitizer::hex_color( 42 ) );
		$this->assertSame( '', Sanitizer::hex_color( [] ) );
	}

	/**
	 * Test boolean sanitizer coerces truthy values to 1.
	 *
	 * @return void
	 */
	public function test_boolean_truthy_returns_1(): void {
		$this->assertSame( 1, Sanitizer::boolean( '1' ) );
		$this->assertSame( 1, Sanitizer::boolean( 1 ) );
		$this->assertSame( 1, Sanitizer::boolean( true ) );
		$this->assertSame( 1, Sanitizer::boolean( 'yes' ) );
	}

	/**
	 * Test boolean sanitizer coerces falsy values to 0.
	 *
	 * @return void
	 */
	public function test_boolean_falsy_returns_0(): void {
		$this->assertSame( 0, Sanitizer::boolean( '0' ) );
		$this->assertSame( 0, Sanitizer::boolean( 0 ) );
		$this->assertSame( 0, Sanitizer::boolean( false ) );
		$this->assertSame( 0, Sanitizer::boolean( null ) );
		$this->assertSame( 0, Sanitizer::boolean( '' ) );
	}

	/**
	 * Test dark_mode sanitizer accepts valid values.
	 *
	 * @return void
	 */
	public function test_dark_mode_accepts_valid(): void {
		Functions\when( 'sanitize_text_field' )->returnArg();

		$this->assertSame( 'auto', Sanitizer::dark_mode( 'auto' ) );
		$this->assertSame( 'on', Sanitizer::dark_mode( 'on' ) );
		$this->assertSame( 'off', Sanitizer::dark_mode( 'off' ) );
		$this->assertSame( 'disabled', Sanitizer::dark_mode( 'disabled' ) );
	}

	/**
	 * Test dark_mode sanitizer rejects invalid and defaults to auto.
	 *
	 * @return void
	 */
	public function test_dark_mode_rejects_invalid(): void {
		Functions\when( 'sanitize_text_field' )->returnArg();

		$this->assertSame( 'auto', Sanitizer::dark_mode( 'invalid' ) );
		$this->assertSame( 'auto', Sanitizer::dark_mode( '' ) );
	}

	/**
	 * Test dark_mode handles non-string input.
	 *
	 * @return void
	 */
	public function test_dark_mode_handles_non_string(): void {
		$this->assertSame( 'auto', Sanitizer::dark_mode( null ) );
		$this->assertSame( 'auto', Sanitizer::dark_mode( 42 ) );
	}

	/**
	 * Test scope sanitizer accepts valid values.
	 *
	 * @return void
	 */
	public function test_scope_accepts_valid(): void {
		Functions\when( 'sanitize_text_field' )->returnArg();

		$this->assertSame( 'frontend', Sanitizer::scope( 'frontend' ) );
		$this->assertSame( 'admin', Sanitizer::scope( 'admin' ) );
		$this->assertSame( 'everywhere', Sanitizer::scope( 'everywhere' ) );
	}

	/**
	 * Test scope sanitizer rejects invalid and defaults to frontend.
	 *
	 * @return void
	 */
	public function test_scope_rejects_invalid(): void {
		Functions\when( 'sanitize_text_field' )->returnArg();

		$this->assertSame( 'frontend', Sanitizer::scope( 'invalid' ) );
		$this->assertSame( 'frontend', Sanitizer::scope( '' ) );
	}

	/**
	 * Test scope handles non-string input.
	 *
	 * @return void
	 */
	public function test_scope_handles_non_string(): void {
		$this->assertSame( 'frontend', Sanitizer::scope( null ) );
		$this->assertSame( 'frontend', Sanitizer::scope( 42 ) );
	}
}
