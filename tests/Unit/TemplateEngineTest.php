<?php
/**
 * Tests for the TemplateEngine class.
 *
 * @package GracefulErrorPages\Tests\Unit
 */

declare( strict_types=1 );

namespace GracefulErrorPages\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use GracefulErrorPages\TemplateEngine;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * TemplateEngine unit tests.
 */
class TemplateEngineTest extends TestCase {

	/**
	 * The engine instance.
	 *
	 * @var TemplateEngine
	 */
	private TemplateEngine $engine;

	/**
	 * Set up Brain\Monkey and the engine before each test.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();

		$this->engine = new TemplateEngine();
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
	 * Stub the WP functions called inside build_context().
	 *
	 * @return void
	 */
	private function stub_wp_context_functions(): void {
		Functions\when( 'get_option' )->alias(
			function ( string $key, $default = false ) {
				return $default;
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'Test Site' );
		Functions\when( 'home_url' )->justReturn( 'https://example.com/' );
		Functions\when( 'is_rtl' )->justReturn( false );
		Functions\when( '__' )->returnArg();
	}

	/**
	 * Test that has_template returns true for valid templates.
	 *
	 * @return void
	 */
	public function test_has_template_returns_true_for_valid(): void {
		$this->assertTrue( $this->engine->has_template( 'minimal' ) );
		$this->assertTrue( $this->engine->has_template( 'corporate' ) );
	}

	/**
	 * Test that has_template returns false for invalid templates.
	 *
	 * @return void
	 */
	public function test_has_template_returns_false_for_invalid(): void {
		$this->assertFalse( $this->engine->has_template( '' ) );
		$this->assertFalse( $this->engine->has_template( '../wp-config' ) );
		$this->assertFalse( $this->engine->has_template( 'nonexistent' ) );
	}

	/**
	 * Test that display outputs template directly without buffering.
	 *
	 * @return void
	 */
	public function test_display_outputs_template(): void {
		$this->stub_render_functions();

		$this->expectOutputRegex( '/<!DOCTYPE html>/' );

		$this->engine->display( 'minimal', [
			'error_title'   => 'Display Test',
			'error_message' => 'Direct output.',
		] );
	}

	/**
	 * Test that display with invalid template produces no output.
	 *
	 * @return void
	 */
	public function test_display_invalid_template_no_output(): void {
		$this->stub_wp_context_functions();

		$this->expectOutputString( '' );

		$this->engine->display( 'nonexistent', [] );
	}

	/**
	 * Test that path traversal in template name is rejected.
	 *
	 * @return void
	 */
	public function test_path_traversal_rejected(): void {
		$this->stub_wp_context_functions();

		$result = $this->engine->render( '../wp-config', [] );

		$this->assertSame( '', $result );
	}

	/**
	 * Test that forward slashes in template name are rejected.
	 *
	 * @return void
	 */
	public function test_slash_in_template_rejected(): void {
		$this->stub_wp_context_functions();

		$result = $this->engine->render( 'sub/template', [] );

		$this->assertSame( '', $result );
	}

	/**
	 * Test that backslashes in template name are rejected.
	 *
	 * @return void
	 */
	public function test_backslash_in_template_rejected(): void {
		$this->stub_wp_context_functions();

		$result = $this->engine->render( 'sub\\template', [] );

		$this->assertSame( '', $result );
	}

	/**
	 * Test that null bytes in template name are rejected.
	 *
	 * @return void
	 */
	public function test_null_byte_in_template_rejected(): void {
		$this->stub_wp_context_functions();

		$result = $this->engine->render( "minimal\0.php", [] );

		$this->assertSame( '', $result );
	}

	/**
	 * Test that empty template name returns empty string.
	 *
	 * @return void
	 */
	public function test_empty_template_returns_empty(): void {
		$this->stub_wp_context_functions();

		$result = $this->engine->render( '', [] );

		$this->assertSame( '', $result );
	}

	/**
	 * Test that nonexistent template returns empty string.
	 *
	 * @return void
	 */
	public function test_nonexistent_template_returns_empty(): void {
		$this->stub_wp_context_functions();

		$result = $this->engine->render( 'nonexistent-template-xyz', [] );

		$this->assertSame( '', $result );
	}

	/**
	 * Test that merge tags are replaced in output.
	 *
	 * @return void
	 */
	public function test_merge_tags_replaced(): void {
		$this->stub_wp_context_functions();
		Functions\when( 'esc_html' )->returnArg();

		$context = [
			'site_name' => 'Acme Corp',
			'year'      => '2026',
			'home_url'  => 'https://example.com/',
			'back_url'  => '/go-back',
		];

		$output = '{site_name} - {year} - {home_url} - {back_url}';

		$engine  = new TemplateEngine();
		$replace = new \ReflectionMethod( $engine, 'replace_merge_tags' );
		$replace->setAccessible( true );

		$result = $replace->invoke( $engine, $output, $context );

		$this->assertSame( 'Acme Corp - 2026 - https://example.com/ - /go-back', $result );
	}

	/**
	 * Test that get_available_templates returns the expected slugs.
	 *
	 * @return void
	 */
	public function test_get_available_templates(): void {
		Functions\when( '__' )->returnArg();

		$templates = TemplateEngine::get_available_templates();

		$this->assertArrayHasKey( 'minimal', $templates );
		$this->assertArrayHasKey( 'corporate', $templates );
		$this->assertArrayHasKey( 'friendly', $templates );
		$this->assertArrayHasKey( 'dark', $templates );
		$this->assertArrayHasKey( 'starter', $templates );
		$this->assertCount( 5, $templates );
	}

	/**
	 * Test that context defaults are applied when no overrides given.
	 *
	 * @return void
	 */
	public function test_context_defaults_applied(): void {
		Functions\when( 'get_option' )->alias(
			function ( string $key, $default = false ) {
				return $default;
			}
		);
		Functions\when( 'get_bloginfo' )->alias(
			function ( string $show ) {
				if ( $show === 'name' ) {
					return 'Default Site';
				}
				if ( $show === 'charset' ) {
					return 'UTF-8';
				}
				return '';
			}
		);
		Functions\when( 'home_url' )->justReturn( 'https://example.com/' );
		Functions\when( 'is_rtl' )->justReturn( false );
		Functions\when( '__' )->returnArg();

		$build = new \ReflectionMethod( $this->engine, 'build_context' );
		$build->setAccessible( true );

		$context = $build->invoke( $this->engine, [] );

		$this->assertSame( 'Default Site', $context['site_name'] );
		$this->assertSame( 'UTF-8', $context['charset'] );
		$this->assertSame( 'ltr', $context['text_direction'] );
		$this->assertSame( 500, $context['response_code'] );
		$this->assertFalse( $context['back_link'] );
		$this->assertSame( '', $context['bg_color'] );
		$this->assertSame( '', $context['text_color'] );
		$this->assertSame( 'auto', $context['dark_mode'] );
		$this->assertSame( '', $context['copyright'] );
		$this->assertSame( '', $context['support_link'] );
	}

	/**
	 * Test that context overrides take precedence over defaults.
	 *
	 * @return void
	 */
	public function test_context_overrides(): void {
		$this->stub_wp_context_functions();

		$build = new \ReflectionMethod( $this->engine, 'build_context' );
		$build->setAccessible( true );

		$context = $build->invoke( $this->engine, [
			'error_title'   => 'Custom Title',
			'response_code' => 403,
			'back_link'     => true,
		] );

		$this->assertSame( 'Custom Title', $context['error_title'] );
		$this->assertSame( 403, $context['response_code'] );
		$this->assertTrue( $context['back_link'] );
	}

	/**
	 * Test that color and dark_mode overrides take effect.
	 *
	 * @return void
	 */
	public function test_context_color_and_dark_mode_overrides(): void {
		$this->stub_wp_context_functions();

		$build = new \ReflectionMethod( $this->engine, 'build_context' );
		$build->setAccessible( true );

		$context = $build->invoke( $this->engine, [
			'bg_color'   => '#000000',
			'text_color' => '#ffffff',
			'dark_mode'  => 'on',
		] );

		$this->assertSame( '#000000', $context['bg_color'] );
		$this->assertSame( '#ffffff', $context['text_color'] );
		$this->assertSame( 'on', $context['dark_mode'] );
	}

	/**
	 * Stub WP style enqueue functions called by enqueue_error_styles().
	 *
	 * @return void
	 */
	private function stub_style_functions(): void {
		Functions\when( 'wp_register_style' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->justReturn( true );
		Functions\when( 'wp_add_inline_style' )->justReturn( true );
		Functions\when( 'wp_print_styles' )->justReturn( '' );
	}

	/**
	 * Stub all WP functions needed for template rendering.
	 *
	 * @return void
	 */
	private function stub_render_functions(): void {
		$this->stub_wp_context_functions();
		$this->stub_style_functions();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'get_locale' )->justReturn( 'en_US' );
	}

	/**
	 * Test that rendering the minimal template produces a full HTML document.
	 *
	 * @return void
	 */
	public function test_render_minimal_outputs_html_document(): void {
		$this->stub_render_functions();

		$output = $this->engine->render( 'minimal', [
			'error_title'   => 'Test Error',
			'error_message' => 'Something broke.',
			'brand_color'   => '#ff0000',
		] );

		$this->assertStringContainsString( '<!DOCTYPE html>', $output );
		$this->assertStringContainsString( 'Test Error', $output );
		$this->assertStringContainsString( 'Something broke.', $output );
		$this->assertStringContainsString( 'gcep-template-minimal', $output );
		$this->assertStringContainsString( 'data-dark-mode=', $output );
	}

	/**
	 * Test that the dark template always outputs data-dark-mode="disabled".
	 *
	 * @return void
	 */
	public function test_render_dark_template_forces_disabled_dark_mode(): void {
		$this->stub_render_functions();

		$output = $this->engine->render( 'dark', [
			'dark_mode' => 'off',
		] );

		$this->assertStringContainsString( 'gcep-template-dark', $output );
		$this->assertStringContainsString( 'data-dark-mode="disabled"', $output );
	}

	/**
	 * Test corporate template shows text fallback when no logo or icon is set.
	 *
	 * @return void
	 */
	public function test_render_corporate_logo_text_fallback(): void {
		$this->stub_render_functions();

		$output = $this->engine->render( 'corporate', [
			'logo_url'  => '',
			'icon_url'  => '',
			'site_name' => 'Acme Corp',
		] );

		$this->assertStringContainsString( 'gcep-logo-text', $output );
		$this->assertStringContainsString( 'Acme Corp', $output );
	}

	/**
	 * Test that the friendly template renders with its SVG illustration.
	 *
	 * @return void
	 */
	public function test_render_friendly_has_illustration(): void {
		$this->stub_render_functions();

		$output = $this->engine->render( 'friendly', [
			'logo_url' => '',
			'icon_url' => '',
		] );

		$this->assertStringContainsString( 'gcep-template-friendly', $output );
		$this->assertStringContainsString( 'gcep-illustration', $output );
		$this->assertStringContainsString( '<svg', $output );
	}

	/**
	 * Test that the starter template renders without card decoration.
	 *
	 * @return void
	 */
	public function test_render_starter_outputs_minimal_markup(): void {
		$this->stub_render_functions();

		$output = $this->engine->render( 'starter', [
			'error_title' => 'Starter Test',
		] );

		$this->assertStringContainsString( 'gcep-template-starter', $output );
		$this->assertStringContainsString( 'Starter Test', $output );
		$this->assertStringContainsString( '<!DOCTYPE html>', $output );
	}

	/**
	 * Test that content settings (button text, copyright, support link) are in context.
	 *
	 * @return void
	 */
	public function test_context_includes_content_settings(): void {
		$this->stub_wp_context_functions();

		$build = new \ReflectionMethod( $this->engine, 'build_context' );
		$build->setAccessible( true );

		$context = $build->invoke( $this->engine, [] );

		$this->assertArrayHasKey( 'primary_btn_text', $context );
		$this->assertArrayHasKey( 'primary_btn_url', $context );
		$this->assertArrayHasKey( 'secondary_btn_text', $context );
		$this->assertArrayHasKey( 'secondary_btn_url', $context );
		$this->assertArrayHasKey( 'copyright', $context );
		$this->assertArrayHasKey( 'support_link', $context );
	}

	/**
	 * Test that custom button text from settings appears in template output.
	 *
	 * @return void
	 */
	public function test_render_uses_custom_button_text(): void {
		$this->stub_render_functions();

		$output = $this->engine->render( 'minimal', [
			'primary_btn_text' => 'Return Home',
		] );

		$this->assertStringContainsString( 'Return Home', $output );
	}

	/**
	 * Test that custom copyright from settings appears in footer.
	 *
	 * @return void
	 */
	public function test_render_uses_custom_copyright(): void {
		$this->stub_render_functions();

		$output = $this->engine->render( 'minimal', [
			'copyright' => 'Custom Corp 2026',
		] );

		$this->assertStringContainsString( 'Custom Corp 2026', $output );
	}

	/**
	 * Test that support link renders when set.
	 *
	 * @return void
	 */
	public function test_render_shows_support_link(): void {
		$this->stub_render_functions();

		$output = $this->engine->render( 'minimal', [
			'support_link' => 'https://example.com/support',
		] );

		$this->assertStringContainsString( 'gcep-support', $output );
		$this->assertStringContainsString( 'https://example.com/support', $output );
	}

	/**
	 * Test that templates output a lang attribute on the html element.
	 *
	 * @return void
	 */
	public function test_render_includes_lang_attribute(): void {
		$this->stub_wp_context_functions();
		$this->stub_style_functions();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'get_locale' )->justReturn( 'de_DE' );

		$output = $this->engine->render( 'minimal', [] );

		$this->assertMatchesRegularExpression( '/lang=["\']de-DE["\']/', $output );
	}

	/**
	 * Test that templates use semantic main element.
	 *
	 * @return void
	 */
	public function test_render_uses_semantic_main_element(): void {
		$this->stub_render_functions();

		$output = $this->engine->render( 'minimal', [] );

		$this->assertStringContainsString( '<main', $output );
		$this->assertStringContainsString( '</main>', $output );
	}

	/**
	 * Test that templates use h1 for the error title.
	 *
	 * @return void
	 */
	public function test_render_uses_h1_for_title(): void {
		$this->stub_render_functions();

		$output = $this->engine->render( 'minimal', [
			'error_title' => 'Not Found',
		] );

		$this->assertStringContainsString( '<h1', $output );
		$this->assertStringContainsString( 'Not Found', $output );
	}

	/**
	 * Test that templates include a noindex robots meta tag.
	 *
	 * @return void
	 */
	public function test_render_includes_noindex_robots(): void {
		$this->stub_render_functions();

		$output = $this->engine->render( 'minimal', [] );

		$this->assertStringContainsString( 'noindex', $output );
		$this->assertStringContainsString( 'nofollow', $output );
	}

	/**
	 * Test that the friendly template SVG has aria-hidden on decorative illustration.
	 *
	 * @return void
	 */
	public function test_friendly_svg_has_aria_hidden(): void {
		$this->stub_render_functions();

		$output = $this->engine->render( 'friendly', [] );

		$this->assertStringContainsString( 'aria-hidden="true"', $output );
	}

	/**
	 * Test that templates include a footer element.
	 *
	 * @return void
	 */
	public function test_render_includes_footer(): void {
		$this->stub_render_functions();

		$templates = [ 'minimal', 'corporate', 'dark', 'friendly', 'starter' ];
		foreach ( $templates as $template ) {
			$output = $this->engine->render( $template, [] );
			$this->assertStringContainsString( '<footer', $output, "Template '{$template}' missing <footer> element." );
			$this->assertStringContainsString( '</footer>', $output, "Template '{$template}' missing </footer> closing tag." );
		}
	}

	/**
	 * Test that resolve_context_tags replaces merge tags in context values.
	 *
	 * @return void
	 */
	public function test_resolve_context_tags_replaces_tags_in_values(): void {
		$resolve = new \ReflectionMethod( $this->engine, 'resolve_context_tags' );
		$resolve->setAccessible( true );

		$context = [
			'site_name' => 'Acme Corp',
			'year'      => '2026',
			'home_url'  => 'https://example.com/',
			'back_url'  => '/back',
			'copyright' => '© {year} {site_name}',
		];

		$result = $resolve->invoke( $this->engine, $context );

		$this->assertSame( '© 2026 Acme Corp', $result['copyright'] );
	}

	/**
	 * Test that resolve_context_tags does not modify merge tag source keys.
	 *
	 * @return void
	 */
	public function test_resolve_context_tags_skips_source_keys(): void {
		$resolve = new \ReflectionMethod( $this->engine, 'resolve_context_tags' );
		$resolve->setAccessible( true );

		$context = [
			'site_name' => 'Acme {year}',
			'year'      => '2026',
			'home_url'  => 'https://example.com/',
			'back_url'  => '/back',
		];

		$result = $resolve->invoke( $this->engine, $context );

		$this->assertSame( 'Acme {year}', $result['site_name'] );
		$this->assertSame( '2026', $result['year'] );
	}

	/**
	 * Test that resolve_context_tags leaves non-string values unchanged.
	 *
	 * @return void
	 */
	public function test_resolve_context_tags_skips_non_strings(): void {
		$resolve = new \ReflectionMethod( $this->engine, 'resolve_context_tags' );
		$resolve->setAccessible( true );

		$context = [
			'site_name'     => 'Acme',
			'year'          => '2026',
			'home_url'      => 'https://example.com/',
			'back_url'      => '/back',
			'response_code' => 500,
			'back_link'     => true,
		];

		$result = $resolve->invoke( $this->engine, $context );

		$this->assertSame( 500, $result['response_code'] );
		$this->assertTrue( $result['back_link'] );
	}

	/**
	 * Test that resolve_context_tags handles context with no merge tags.
	 *
	 * @return void
	 */
	public function test_resolve_context_tags_no_tags_present(): void {
		$resolve = new \ReflectionMethod( $this->engine, 'resolve_context_tags' );
		$resolve->setAccessible( true );

		$context = [
			'site_name' => 'Acme',
			'year'      => '2026',
			'home_url'  => 'https://example.com/',
			'back_url'  => '/back',
			'copyright' => 'Plain text copyright',
		];

		$result = $resolve->invoke( $this->engine, $context );

		$this->assertSame( 'Plain text copyright', $result['copyright'] );
	}

	/**
	 * Test that merge tags in copyright are resolved before template escaping.
	 *
	 * @return void
	 */
	public function test_render_resolves_tags_in_copyright_before_escaping(): void {
		$this->stub_wp_context_functions();
		$this->stub_style_functions();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'get_locale' )->justReturn( 'en_US' );

		$esc_html_calls = [];
		Functions\when( 'esc_html' )->alias(
			function ( $text ) use ( &$esc_html_calls ) {
				$esc_html_calls[] = $text;
				return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
			}
		);

		$output = $this->engine->render( 'minimal', [
			'site_name' => 'Acme <script>',
			'copyright' => '© {site_name}',
		] );

		$this->assertStringContainsString( '© Acme &lt;script&gt;', $output );
		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertContains( '© Acme <script>', $esc_html_calls, 'esc_html should receive the resolved value, not raw {site_name}.' );
	}

	/**
	 * Test that URL merge tags in context values are resolved.
	 *
	 * @return void
	 */
	public function test_resolve_context_tags_resolves_url_tags(): void {
		$resolve = new \ReflectionMethod( $this->engine, 'resolve_context_tags' );
		$resolve->setAccessible( true );

		$context = [
			'site_name'    => 'Acme',
			'year'         => '2026',
			'home_url'     => 'https://example.com/',
			'back_url'     => '/back',
			'support_link' => '{home_url}support',
		];

		$result = $resolve->invoke( $this->engine, $context );

		$this->assertSame( 'https://example.com/support', $result['support_link'] );
	}

	/**
	 * Test chained resolution: tag resolves to value containing another tag.
	 *
	 * PHP str_replace with arrays processes sequentially, so {year} inside
	 * an already-substituted value is expanded in the same pass. Source keys
	 * themselves are still skipped.
	 *
	 * @return void
	 */
	public function test_resolve_context_tags_chained_expansion(): void {
		$resolve = new \ReflectionMethod( $this->engine, 'resolve_context_tags' );
		$resolve->setAccessible( true );

		$context = [
			'site_name' => 'Acme {year}',
			'year'      => '2026',
			'home_url'  => 'https://example.com/',
			'back_url'  => '/back',
			'copyright' => '© {site_name}',
		];

		$result = $resolve->invoke( $this->engine, $context );

		$this->assertSame( '© Acme 2026', $result['copyright'] );
		$this->assertSame( 'Acme {year}', $result['site_name'] );
	}

	/**
	 * Test that merge tags in error_message are resolved before wp_kses_post.
	 *
	 * @return void
	 */
	public function test_render_resolves_tags_in_error_message(): void {
		$this->stub_wp_context_functions();
		$this->stub_style_functions();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_html' )->alias(
			function ( $text ) {
				return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
			}
		);
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'get_locale' )->justReturn( 'en_US' );

		$kses_calls = [];
		Functions\when( 'wp_kses_post' )->alias(
			function ( $text ) use ( &$kses_calls ) {
				$kses_calls[] = $text;
				return strip_tags( (string) $text, '<b><em><strong><a>' );
			}
		);

		$output = $this->engine->render( 'minimal', [
			'site_name'     => 'Acme <script>alert(1)</script>',
			'error_message' => 'Error on {site_name}',
		] );

		$this->assertContains( 'Error on Acme <script>alert(1)</script>', $kses_calls, 'wp_kses_post should receive the resolved value.' );
		$this->assertStringNotContainsString( '<script>', $output );
	}

	/**
	 * Test that empty replacement values produce correct output.
	 *
	 * @return void
	 */
	public function test_resolve_context_tags_empty_replacement_value(): void {
		$resolve = new \ReflectionMethod( $this->engine, 'resolve_context_tags' );
		$resolve->setAccessible( true );

		$context = [
			'site_name' => '',
			'year'      => '2026',
			'home_url'  => 'https://example.com/',
			'back_url'  => '/back',
			'copyright' => '© {site_name}',
		];

		$result = $resolve->invoke( $this->engine, $context );

		$this->assertSame( '© ', $result['copyright'] );
	}

	/**
	 * Test that a value consisting of only a merge tag resolves fully.
	 *
	 * @return void
	 */
	public function test_resolve_context_tags_value_is_only_a_tag(): void {
		$resolve = new \ReflectionMethod( $this->engine, 'resolve_context_tags' );
		$resolve->setAccessible( true );

		$context = [
			'site_name'       => 'Acme',
			'year'            => '2026',
			'home_url'        => 'https://example.com/',
			'back_url'        => '/back',
			'primary_btn_url' => '{home_url}',
		];

		$result = $resolve->invoke( $this->engine, $context );

		$this->assertSame( 'https://example.com/', $result['primary_btn_url'] );
	}

	/**
	 * Test that replace_merge_tags escapes values in post-render pass.
	 *
	 * @return void
	 */
	public function test_replace_merge_tags_escapes_values(): void {
		Functions\when( 'esc_html' )->alias(
			function ( $text ) {
				return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
			}
		);

		$replace = new \ReflectionMethod( $this->engine, 'replace_merge_tags' );
		$replace->setAccessible( true );

		$output  = '<p>Welcome to {site_name}</p>';
		$context = [
			'site_name' => '<script>alert(1)</script>',
			'year'      => '2026',
		];

		$result = $replace->invoke( $this->engine, $output, $context );

		$this->assertStringContainsString( '&lt;script&gt;', $result );
		$this->assertStringNotContainsString( '<script>', $result );
	}

	/**
	 * Test that build_css_custom_properties returns valid CSS for brand color.
	 *
	 * @return void
	 */
	public function test_build_css_custom_properties_with_brand_color(): void {
		$method = new \ReflectionMethod( $this->engine, 'build_css_custom_properties' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->engine, [ 'brand_color' => '#ff0000' ] );

		$this->assertStringContainsString( '--gcep-brand-color: #ff0000', $result );
		$this->assertStringContainsString( 'body.gcep-error-page[data-dark-mode]', $result );
	}

	/**
	 * Test that build_css_custom_properties includes all three color properties.
	 *
	 * @return void
	 */
	public function test_build_css_custom_properties_all_colors(): void {
		$method = new \ReflectionMethod( $this->engine, 'build_css_custom_properties' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->engine, [
			'brand_color' => '#3b82f6',
			'bg_color'    => '#000000',
			'text_color'  => '#ffffff',
		] );

		$this->assertStringContainsString( '--gcep-brand-color: #3b82f6', $result );
		$this->assertStringContainsString( '--gcep-bg-color: #000000', $result );
		$this->assertStringContainsString( '--gcep-text-color: #ffffff', $result );
	}

	/**
	 * Test that build_css_custom_properties returns empty string with no colors.
	 *
	 * @return void
	 */
	public function test_build_css_custom_properties_empty_without_colors(): void {
		$method = new \ReflectionMethod( $this->engine, 'build_css_custom_properties' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->engine, [] );

		$this->assertSame( '', $result );
	}

	/**
	 * Test that build_css_custom_properties rejects invalid hex values.
	 *
	 * @return void
	 */
	public function test_build_css_custom_properties_rejects_invalid_hex(): void {
		$method = new \ReflectionMethod( $this->engine, 'build_css_custom_properties' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->engine, [
			'brand_color' => 'red; background: url(evil)',
			'bg_color'    => 'not-a-hex',
			'text_color'  => '#xyz',
		] );

		$this->assertSame( '', $result );
	}

	/**
	 * Test that build_css_custom_properties accepts shorthand hex.
	 *
	 * @return void
	 */
	public function test_build_css_custom_properties_accepts_shorthand_hex(): void {
		$method = new \ReflectionMethod( $this->engine, 'build_css_custom_properties' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->engine, [ 'brand_color' => '#f00' ] );

		$this->assertStringContainsString( '--gcep-brand-color: #f00', $result );
	}

	/**
	 * Test that build_css_custom_properties skips empty color strings.
	 *
	 * @return void
	 */
	public function test_build_css_custom_properties_skips_empty_strings(): void {
		$method = new \ReflectionMethod( $this->engine, 'build_css_custom_properties' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->engine, [
			'brand_color' => '#3b82f6',
			'bg_color'    => '',
			'text_color'  => '',
		] );

		$this->assertStringContainsString( '--gcep-brand-color: #3b82f6', $result );
		$this->assertStringNotContainsString( '--gcep-bg-color', $result );
		$this->assertStringNotContainsString( '--gcep-text-color', $result );
	}

	/**
	 * Test that enqueue_error_styles registers with CSS URL when provided.
	 *
	 * @return void
	 */
	public function test_enqueue_error_styles_with_css_url(): void {
		$css_url         = 'https://example.com/error-page.css';
		$register_args   = [];
		$enqueue_args    = [];
		$inline_called   = false;

		Functions\when( 'wp_register_style' )->alias(
			function () use ( &$register_args ) {
				$register_args = func_get_args();
			}
		);
		Functions\when( 'wp_enqueue_style' )->alias(
			function ( string $handle ) use ( &$enqueue_args ) {
				$enqueue_args[] = $handle;
			}
		);
		Functions\when( 'wp_add_inline_style' )->alias(
			function () use ( &$inline_called ) {
				$inline_called = true;
			}
		);

		$this->engine->enqueue_error_styles( [ 'css_url' => $css_url ] );

		$this->assertSame( 'gcep-error-page', $register_args[0] );
		$this->assertSame( $css_url, $register_args[1] );
		$this->assertContains( 'gcep-error-page', $enqueue_args );
		$this->assertFalse( $inline_called );
	}

	/**
	 * Test that enqueue_error_styles registers virtual style when no CSS URL.
	 *
	 * @return void
	 */
	public function test_enqueue_error_styles_without_css_url(): void {
		$register_args = [];
		$inline_called = false;

		Functions\when( 'wp_register_style' )->alias(
			function () use ( &$register_args ) {
				$register_args = func_get_args();
			}
		);
		Functions\when( 'wp_enqueue_style' )->justReturn( true );
		Functions\when( 'wp_add_inline_style' )->alias(
			function () use ( &$inline_called ) {
				$inline_called = true;
			}
		);

		$this->engine->enqueue_error_styles( [] );

		$this->assertSame( 'gcep-error-page', $register_args[0] );
		$this->assertFalse( $register_args[1] );
		$this->assertFalse( $inline_called );
	}

	/**
	 * Test that enqueue_error_styles adds inline CSS for brand color.
	 *
	 * @return void
	 */
	public function test_enqueue_error_styles_adds_inline_css_for_colors(): void {
		$inline_handle = '';
		$inline_css    = '';

		Functions\when( 'wp_register_style' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->justReturn( true );
		Functions\when( 'wp_add_inline_style' )->alias(
			function ( string $handle, string $css ) use ( &$inline_handle, &$inline_css ) {
				$inline_handle = $handle;
				$inline_css    = $css;
			}
		);

		$this->engine->enqueue_error_styles( [ 'brand_color' => '#ff0000' ] );

		$this->assertSame( 'gcep-error-page', $inline_handle );
		$this->assertStringContainsString( '--gcep-brand-color: #ff0000', $inline_css );
	}

	/**
	 * Test that enqueue_error_styles handles CSS URL + brand color together.
	 *
	 * @return void
	 */
	public function test_enqueue_error_styles_css_url_and_colors(): void {
		$css_url       = 'https://example.com/error-page.css';
		$register_args = [];
		$inline_css    = '';

		Functions\when( 'wp_register_style' )->alias(
			function () use ( &$register_args ) {
				$register_args = func_get_args();
			}
		);
		Functions\when( 'wp_enqueue_style' )->justReturn( true );
		Functions\when( 'wp_add_inline_style' )->alias(
			function ( string $handle, string $css ) use ( &$inline_css ) {
				$inline_css = $css;
			}
		);

		$this->engine->enqueue_error_styles( [
			'css_url'     => $css_url,
			'brand_color' => '#3b82f6',
		] );

		$this->assertSame( $css_url, $register_args[1] );
		$this->assertStringContainsString( '--gcep-brand-color: #3b82f6', $inline_css );
	}

	/**
	 * Test that build_css_custom_properties rejects invalid-length hex values.
	 *
	 * @return void
	 */
	public function test_build_css_custom_properties_rejects_invalid_length_hex(): void {
		$method = new \ReflectionMethod( $this->engine, 'build_css_custom_properties' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->engine, [
			'brand_color' => '#12345',
			'bg_color'    => '#1234567',
		] );

		$this->assertSame( '', $result );
	}
}
