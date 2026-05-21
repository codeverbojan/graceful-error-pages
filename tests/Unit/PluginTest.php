<?php
/**
 * Tests for the Plugin bootstrap class.
 *
 * @package GracefulErrorPages\Tests\Unit
 */

declare( strict_types=1 );

namespace GracefulErrorPages\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use GracefulErrorPages\Plugin;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Plugin bootstrap tests.
 */
class PluginTest extends TestCase {

	/**
	 * Set up Brain\Monkey before each test.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();

		Plugin::reset();
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
	 * Test that the GCEP_VERSION constant matches the expected version.
	 *
	 * @return void
	 */
	public function test_version_constant(): void {
		$this->assertSame( '1.0.0', GCEP_VERSION );
	}

	/**
	 * Test that boot() returns a Plugin instance.
	 *
	 * @return void
	 */
	/**
	 * Stub boot-related WP functions.
	 *
	 * @return void
	 */
	private function stub_boot_functions(): void {
		Functions\when( 'register_activation_hook' )->justReturn( null );
		Functions\when( 'register_deactivation_hook' )->justReturn( null );
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'register_shutdown_function' )->justReturn( null );
		Functions\when( 'is_admin' )->justReturn( false );
	}

	/**
	 * Test that boot() returns a Plugin instance.
	 *
	 * @return void
	 */
	public function test_boot_returns_plugin_instance(): void {
		$this->stub_boot_functions();

		$instance = Plugin::boot();

		$this->assertInstanceOf( Plugin::class, $instance );
	}

	/**
	 * Test that multiple boot() calls return the same instance (boot guard).
	 *
	 * @return void
	 */
	public function test_boot_guard_returns_same_instance(): void {
		$this->stub_boot_functions();

		$first  = Plugin::boot();
		$second = Plugin::boot();

		$this->assertSame( $first, $second );
	}

	/**
	 * Test that reset() allows a fresh boot.
	 *
	 * @return void
	 */
	public function test_reset_allows_fresh_boot(): void {
		$this->stub_boot_functions();

		$first = Plugin::boot();
		Plugin::reset();
		$second = Plugin::boot();

		$this->assertNotSame( $first, $second );
	}
}
