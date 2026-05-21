<?php
/**
 * Tests for the AutoDetect class.
 *
 * @package GracefulErrorPages\Tests\Unit
 */

declare( strict_types=1 );

namespace GracefulErrorPages\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use GracefulErrorPages\AutoDetect;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * AutoDetect unit tests.
 */
class AutoDetectTest extends TestCase {

	/**
	 * Set up Brain\Monkey before each test.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();

		Functions\when( 'esc_url_raw' )->returnArg();
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
	 * Test that detect() returns the expected array keys.
	 *
	 * @return void
	 */
	public function test_detect_returns_expected_keys(): void {
		Functions\expect( 'get_bloginfo' )
			->with( 'name' )
			->andReturn( 'Test Site' );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\when( 'get_theme_mod' )->justReturn( '' );
		Functions\expect( 'get_site_icon_url' )->andReturn( '' );
		Functions\when( 'wp_get_global_settings' )->justReturn( [] );

		$result = AutoDetect::detect();

		$this->assertArrayHasKey( 'site_name', $result );
		$this->assertArrayHasKey( 'logo_url', $result );
		$this->assertArrayHasKey( 'icon_url', $result );
		$this->assertArrayHasKey( 'brand_color', $result );
	}

	/**
	 * Test that detect() populates site name from get_bloginfo.
	 *
	 * @return void
	 */
	public function test_detect_populates_site_name(): void {
		Functions\expect( 'get_bloginfo' )
			->with( 'name' )
			->andReturn( 'My WordPress Site' );
		Functions\expect( 'sanitize_text_field' )
			->andReturnFirstArg();
		Functions\expect( 'get_theme_mod' )
			->andReturn( '' );
		Functions\expect( 'get_site_icon_url' )
			->andReturn( '' );
		Functions\when( 'wp_get_global_settings' )->justReturn( [] );

		$result = AutoDetect::detect();

		$this->assertSame( 'My WordPress Site', $result['site_name'] );
	}

	/**
	 * Test that detect() returns fallback brand color when no theme mod is set.
	 *
	 * @return void
	 */
	public function test_fallback_brand_color(): void {
		Functions\expect( 'get_bloginfo' )->andReturn( 'Test' );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\when( 'get_theme_mod' )->justReturn( '' );
		Functions\expect( 'get_site_icon_url' )->andReturn( '' );
		Functions\when( 'wp_get_global_settings' )->justReturn( [] );

		$result = AutoDetect::detect();

		$this->assertSame( '#2563eb', $result['brand_color'] );
	}

	/**
	 * Test classic theme: brand color from Customizer theme mod.
	 *
	 * @return void
	 */
	public function test_custom_brand_color(): void {
		Functions\expect( 'get_bloginfo' )->andReturn( 'Test' );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\when( 'get_theme_mod' )->alias(
			function ( string $name, $default = false ) {
				if ( 'custom_logo' === $name ) {
					return 0;
				}
				if ( 'primary_color' === $name ) {
					return '#ff5733';
				}
				return $default;
			}
		);
		Functions\expect( 'sanitize_hex_color' )
			->once()
			->with( '#ff5733' )
			->andReturn( '#ff5733' );
		Functions\expect( 'get_site_icon_url' )->andReturn( '' );
		Functions\when( 'wp_get_global_settings' )->justReturn( [] );

		$result = AutoDetect::detect();

		$this->assertSame( '#ff5733', $result['brand_color'] );
	}

	/**
	 * Test FSE theme: brand color from theme.json "primary" slug (TT2/TT3).
	 *
	 * @return void
	 */
	public function test_brand_color_from_theme_json_primary_slug(): void {
		Functions\expect( 'get_bloginfo' )->andReturn( 'Test' );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\when( 'get_theme_mod' )->justReturn( '' );
		Functions\expect( 'get_site_icon_url' )->andReturn( '' );
		Functions\expect( 'wp_get_global_settings' )
			->once()
			->with( [ 'color', 'palette', 'theme' ] )
			->andReturn(
				[
					[ 'slug' => 'background', 'color' => '#ffffff', 'name' => 'Background' ],
					[ 'slug' => 'primary', 'color' => '#1a4548', 'name' => 'Primary' ],
					[ 'slug' => 'secondary', 'color' => '#ffe2c7', 'name' => 'Secondary' ],
				]
			);
		Functions\expect( 'sanitize_hex_color' )
			->once()
			->with( '#1a4548' )
			->andReturn( '#1a4548' );

		$result = AutoDetect::detect();

		$this->assertSame( '#1a4548', $result['brand_color'] );
	}

	/**
	 * Test FSE theme: brand color from theme.json "accent" slug (TT4).
	 *
	 * @return void
	 */
	public function test_brand_color_from_theme_json_accent_slug(): void {
		Functions\expect( 'get_bloginfo' )->andReturn( 'Test' );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\when( 'get_theme_mod' )->justReturn( '' );
		Functions\expect( 'get_site_icon_url' )->andReturn( '' );
		Functions\expect( 'wp_get_global_settings' )
			->once()
			->with( [ 'color', 'palette', 'theme' ] )
			->andReturn(
				[
					[ 'slug' => 'base', 'color' => '#f9f9f9', 'name' => 'Base' ],
					[ 'slug' => 'contrast', 'color' => '#111111', 'name' => 'Contrast' ],
					[ 'slug' => 'accent', 'color' => '#cfcabe', 'name' => 'Accent' ],
				]
			);
		Functions\expect( 'sanitize_hex_color' )
			->once()
			->with( '#cfcabe' )
			->andReturn( '#cfcabe' );

		$result = AutoDetect::detect();

		$this->assertSame( '#cfcabe', $result['brand_color'] );
	}

	/**
	 * Test FSE theme: brand color from theme.json "accent-1" slug (TT5).
	 *
	 * @return void
	 */
	public function test_brand_color_from_theme_json_accent_1_slug(): void {
		Functions\expect( 'get_bloginfo' )->andReturn( 'Test' );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\when( 'get_theme_mod' )->justReturn( '' );
		Functions\expect( 'get_site_icon_url' )->andReturn( '' );
		Functions\expect( 'wp_get_global_settings' )
			->once()
			->with( [ 'color', 'palette', 'theme' ] )
			->andReturn(
				[
					[ 'slug' => 'base', 'color' => '#ffffff', 'name' => 'Base' ],
					[ 'slug' => 'accent-1', 'color' => '#ffee58', 'name' => 'Accent 1' ],
					[ 'slug' => 'accent-2', 'color' => '#4caf50', 'name' => 'Accent 2' ],
				]
			);
		Functions\expect( 'sanitize_hex_color' )
			->once()
			->with( '#ffee58' )
			->andReturn( '#ffee58' );

		$result = AutoDetect::detect();

		$this->assertSame( '#ffee58', $result['brand_color'] );
	}

	/**
	 * Test FSE theme: "primary" takes priority over "accent" when both exist.
	 *
	 * @return void
	 */
	public function test_brand_color_primary_takes_priority_over_accent(): void {
		Functions\expect( 'get_bloginfo' )->andReturn( 'Test' );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\when( 'get_theme_mod' )->justReturn( '' );
		Functions\expect( 'get_site_icon_url' )->andReturn( '' );
		Functions\expect( 'wp_get_global_settings' )
			->once()
			->with( [ 'color', 'palette', 'theme' ] )
			->andReturn(
				[
					[ 'slug' => 'accent', 'color' => '#aaaaaa', 'name' => 'Accent' ],
					[ 'slug' => 'primary', 'color' => '#ff0000', 'name' => 'Primary' ],
				]
			);
		Functions\expect( 'sanitize_hex_color' )
			->once()
			->with( '#ff0000' )
			->andReturn( '#ff0000' );

		$result = AutoDetect::detect();

		$this->assertSame( '#ff0000', $result['brand_color'] );
	}

	/**
	 * Test FSE theme: empty palette falls back to Customizer then default.
	 *
	 * @return void
	 */
	public function test_brand_color_empty_palette_falls_back_to_default(): void {
		Functions\expect( 'get_bloginfo' )->andReturn( 'Test' );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\when( 'get_theme_mod' )->justReturn( '' );
		Functions\expect( 'get_site_icon_url' )->andReturn( '' );
		Functions\expect( 'wp_get_global_settings' )
			->once()
			->with( [ 'color', 'palette', 'theme' ] )
			->andReturn( [] );

		$result = AutoDetect::detect();

		$this->assertSame( '#2563eb', $result['brand_color'] );
	}

	/**
	 * Test FSE theme: palette with no matching brand slug falls through.
	 *
	 * @return void
	 */
	public function test_brand_color_no_matching_slug_falls_back_to_default(): void {
		Functions\expect( 'get_bloginfo' )->andReturn( 'Test' );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\when( 'get_theme_mod' )->justReturn( '' );
		Functions\expect( 'get_site_icon_url' )->andReturn( '' );
		Functions\expect( 'wp_get_global_settings' )
			->once()
			->with( [ 'color', 'palette', 'theme' ] )
			->andReturn(
				[
					[ 'slug' => 'custom-purple', 'color' => '#800080', 'name' => 'Purple' ],
					[ 'slug' => 'custom-teal', 'color' => '#008080', 'name' => 'Teal' ],
				]
			);

		$result = AutoDetect::detect();

		$this->assertSame( '#2563eb', $result['brand_color'] );
	}

	/**
	 * Test FSE theme: invalid hex color in palette is skipped.
	 *
	 * @return void
	 */
	public function test_brand_color_invalid_hex_in_palette_skipped(): void {
		Functions\expect( 'get_bloginfo' )->andReturn( 'Test' );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\when( 'get_theme_mod' )->justReturn( '' );
		Functions\expect( 'get_site_icon_url' )->andReturn( '' );
		Functions\expect( 'wp_get_global_settings' )
			->once()
			->with( [ 'color', 'palette', 'theme' ] )
			->andReturn(
				[
					[ 'slug' => 'primary', 'color' => 'not-a-hex', 'name' => 'Primary' ],
				]
			);
		Functions\expect( 'sanitize_hex_color' )
			->once()
			->with( 'not-a-hex' )
			->andReturn( null );

		$result = AutoDetect::detect();

		$this->assertSame( '#2563eb', $result['brand_color'] );
	}

	/**
	 * Test that detect() returns logo URL when custom_logo is set.
	 *
	 * @return void
	 */
	public function test_detect_logo_url(): void {
		Functions\expect( 'get_bloginfo' )->andReturn( 'Test' );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\when( 'get_theme_mod' )->alias(
			function ( string $name, $default = false ) {
				if ( 'custom_logo' === $name ) {
					return 42;
				}
				return $default;
			}
		);
		Functions\expect( 'wp_get_attachment_image_url' )
			->once()
			->with( 42, 'full' )
			->andReturn( 'https://example.com/logo.png' );
		Functions\expect( 'get_site_icon_url' )->andReturn( '' );
		Functions\when( 'wp_get_global_settings' )->justReturn( [] );

		$result = AutoDetect::detect();

		$this->assertSame( 'https://example.com/logo.png', $result['logo_url'] );
	}

	/**
	 * Test that detect() returns icon URL when site icon is set.
	 *
	 * @return void
	 */
	public function test_detect_icon_url(): void {
		Functions\expect( 'get_bloginfo' )->andReturn( 'Test' );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\expect( 'get_theme_mod' )->andReturn( '' );
		Functions\expect( 'get_site_icon_url' )
			->once()
			->andReturn( 'https://example.com/favicon.png' );
		Functions\when( 'wp_get_global_settings' )->justReturn( [] );

		$result = AutoDetect::detect();

		$this->assertSame( 'https://example.com/favicon.png', $result['icon_url'] );
	}

	/**
	 * Test that detect() returns empty logo URL when no logo is set.
	 *
	 * @return void
	 */
	public function test_empty_logo_when_no_custom_logo(): void {
		Functions\expect( 'get_bloginfo' )->andReturn( 'Test' );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\when( 'get_theme_mod' )->justReturn( '' );
		Functions\expect( 'get_site_icon_url' )->andReturn( '' );
		Functions\when( 'wp_get_global_settings' )->justReturn( [] );

		$result = AutoDetect::detect();

		$this->assertSame( '', $result['logo_url'] );
	}

	/**
	 * Test fallback when sanitize_hex_color returns null for invalid color.
	 *
	 * @return void
	 */
	public function test_invalid_color_falls_back_to_default(): void {
		Functions\expect( 'get_bloginfo' )->andReturn( 'Test' );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\when( 'get_theme_mod' )->alias(
			function ( string $name, $default = false ) {
				if ( 'primary_color' === $name ) {
					return 'not-a-color';
				}
				return $default;
			}
		);
		Functions\expect( 'sanitize_hex_color' )
			->once()
			->with( 'not-a-color' )
			->andReturn( null );
		Functions\expect( 'get_site_icon_url' )->andReturn( '' );
		Functions\when( 'wp_get_global_settings' )->justReturn( [] );

		$result = AutoDetect::detect();

		$this->assertSame( '#2563eb', $result['brand_color'] );
	}

	/**
	 * Test that detect() returns empty logo when attachment URL lookup fails.
	 *
	 * @return void
	 */
	public function test_logo_empty_when_attachment_url_fails(): void {
		Functions\expect( 'get_bloginfo' )->andReturn( 'Test' );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\when( 'get_theme_mod' )->alias(
			function ( string $name, $default = false ) {
				if ( 'custom_logo' === $name ) {
					return 99;
				}
				return $default;
			}
		);
		Functions\expect( 'wp_get_attachment_image_url' )
			->once()
			->with( 99, 'full' )
			->andReturn( false );
		Functions\expect( 'get_site_icon_url' )->andReturn( '' );
		Functions\when( 'wp_get_global_settings' )->justReturn( [] );

		$result = AutoDetect::detect();

		$this->assertSame( '', $result['logo_url'] );
	}

	/**
	 * Test theme.json color wins over Customizer color.
	 *
	 * @return void
	 */
	public function test_theme_json_color_wins_over_customizer(): void {
		Functions\expect( 'get_bloginfo' )->andReturn( 'Test' );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\when( 'get_theme_mod' )->alias(
			function ( string $name, $default = false ) {
				if ( 'primary_color' === $name ) {
					return '#customizer';
				}
				return $default;
			}
		);
		Functions\expect( 'get_site_icon_url' )->andReturn( '' );
		Functions\expect( 'wp_get_global_settings' )
			->once()
			->with( [ 'color', 'palette', 'theme' ] )
			->andReturn(
				[
					[ 'slug' => 'primary', 'color' => '#111111', 'name' => 'Primary' ],
				]
			);
		Functions\expect( 'sanitize_hex_color' )
			->once()
			->with( '#111111' )
			->andReturn( '#111111' );

		$result = AutoDetect::detect();

		$this->assertSame( '#111111', $result['brand_color'] );
	}

	/**
	 * Test wp_get_global_settings returning non-array falls back to default.
	 *
	 * @return void
	 */
	public function test_brand_color_non_array_palette_falls_back(): void {
		Functions\expect( 'get_bloginfo' )->andReturn( 'Test' );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\when( 'get_theme_mod' )->justReturn( '' );
		Functions\expect( 'get_site_icon_url' )->andReturn( '' );
		Functions\expect( 'wp_get_global_settings' )
			->once()
			->with( [ 'color', 'palette', 'theme' ] )
			->andReturn( null );

		$result = AutoDetect::detect();

		$this->assertSame( '#2563eb', $result['brand_color'] );
	}

	/**
	 * Test malformed palette entries are skipped gracefully.
	 *
	 * @return void
	 */
	public function test_brand_color_malformed_palette_entries_skipped(): void {
		Functions\expect( 'get_bloginfo' )->andReturn( 'Test' );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\when( 'get_theme_mod' )->justReturn( '' );
		Functions\expect( 'get_site_icon_url' )->andReturn( '' );
		Functions\expect( 'wp_get_global_settings' )
			->once()
			->with( [ 'color', 'palette', 'theme' ] )
			->andReturn(
				[
					'not-an-array',
					[ 'name' => 'Missing slug and color' ],
					[ 'slug' => 'primary' ],
					[ 'slug' => 'accent', 'color' => '#abcdef', 'name' => 'Accent' ],
				]
			);
		Functions\expect( 'sanitize_hex_color' )
			->once()
			->with( '#abcdef' )
			->andReturn( '#abcdef' );

		$result = AutoDetect::detect();

		$this->assertSame( '#abcdef', $result['brand_color'] );
	}
}
