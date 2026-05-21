<?php
/**
 * Tests for the Settings class.
 *
 * @package GracefulErrorPages\Tests\Unit
 */

declare( strict_types=1 );

namespace GracefulErrorPages\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use GracefulErrorPages\Settings;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Settings unit tests.
 */
class SettingsTest extends TestCase {

	/**
	 * The settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Set up Brain\Monkey and Settings before each test.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();

		$this->settings = new Settings();
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
	 * Test register hooks are added.
	 *
	 * @return void
	 */
	public function test_register_adds_hooks(): void {
		$menu_added     = false;
		$init_added     = false;
		$filter_added   = false;

		Functions\when( 'add_action' )->alias(
			function ( string $hook ) use ( &$menu_added, &$init_added ) {
				if ( 'admin_menu' === $hook ) {
					$menu_added = true;
				}
				if ( 'admin_init' === $hook ) {
					$init_added = true;
				}
			}
		);

		Functions\when( 'add_filter' )->alias(
			function () use ( &$filter_added ) {
				$filter_added = true;
			}
		);

		Functions\when( 'plugin_basename' )->returnArg();

		$this->settings->register();

		$this->assertTrue( $menu_added, 'admin_menu hook should be added' );
		$this->assertTrue( $init_added, 'admin_init hook should be added' );
		$this->assertTrue( $filter_added, 'plugin_action_links filter should be added' );
	}

	/**
	 * Test register_settings registers all options per tab group.
	 *
	 * @return void
	 */
	public function test_register_settings_registers_all_options(): void {
		$registered = [];

		Functions\when( 'register_setting' )->alias(
			function ( string $group, string $option_name ) use ( &$registered ) {
				$registered[] = [
					'group'  => $group,
					'option' => $option_name,
				];
			}
		);

		$this->settings->register_settings();

		$option_names = array_column( $registered, 'option' );

		$this->assertContains( 'gcep_template', $option_names );
		$this->assertContains( 'gcep_logo_url', $option_names );
		$this->assertContains( 'gcep_icon_url', $option_names );
		$this->assertContains( 'gcep_brand_color', $option_names );
		$this->assertContains( 'gcep_bg_color', $option_names );
		$this->assertContains( 'gcep_text_color', $option_names );
		$this->assertContains( 'gcep_dark_mode', $option_names );
		$this->assertContains( 'gcep_site_name', $option_names );
		$this->assertContains( 'gcep_error_title', $option_names );
		$this->assertContains( 'gcep_error_message', $option_names );
		$this->assertContains( 'gcep_scope', $option_names );
		$this->assertContains( 'gcep_fatal_errors', $option_names );
		$this->assertContains( 'gcep_show_debug', $option_names );
		$this->assertContains( 'gcep_admin_bypass', $option_names );
	}

	/**
	 * Test design options are registered under gcep_design group.
	 *
	 * @return void
	 */
	public function test_design_options_in_design_group(): void {
		$registered = [];

		Functions\when( 'register_setting' )->alias(
			function ( string $group, string $option_name ) use ( &$registered ) {
				$registered[ $option_name ] = $group;
			}
		);

		$this->settings->register_settings();

		$this->assertSame( 'gcep_design', $registered['gcep_template'] );
		$this->assertSame( 'gcep_design', $registered['gcep_brand_color'] );
		$this->assertSame( 'gcep_design', $registered['gcep_dark_mode'] );
	}

	/**
	 * Test content options are registered under gcep_content group.
	 *
	 * @return void
	 */
	public function test_content_options_in_content_group(): void {
		$registered = [];

		Functions\when( 'register_setting' )->alias(
			function ( string $group, string $option_name ) use ( &$registered ) {
				$registered[ $option_name ] = $group;
			}
		);

		$this->settings->register_settings();

		$this->assertSame( 'gcep_content', $registered['gcep_site_name'] );
		$this->assertSame( 'gcep_content', $registered['gcep_error_title'] );
		$this->assertSame( 'gcep_content', $registered['gcep_copyright'] );
	}

	/**
	 * Test behavior options are registered under gcep_behavior group.
	 *
	 * @return void
	 */
	public function test_behavior_options_in_behavior_group(): void {
		$registered = [];

		Functions\when( 'register_setting' )->alias(
			function ( string $group, string $option_name ) use ( &$registered ) {
				$registered[ $option_name ] = $group;
			}
		);

		$this->settings->register_settings();

		$this->assertSame( 'gcep_behavior', $registered['gcep_scope'] );
		$this->assertSame( 'gcep_behavior', $registered['gcep_fatal_errors'] );
		$this->assertSame( 'gcep_behavior', $registered['gcep_admin_bypass'] );
	}

	/**
	 * Test add_settings_link prepends a link.
	 *
	 * @return void
	 */
	public function test_add_settings_link_prepends(): void {
		Functions\when( 'admin_url' )->justReturn( 'https://example.com/wp-admin/options-general.php?page=gcep-settings' );
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();

		$existing = [ '<a href="#">Deactivate</a>' ];
		$result   = $this->settings->add_settings_link( $existing );

		$this->assertCount( 2, $result );
		$this->assertStringContainsString( 'gcep-settings', $result[0] );
		$this->assertStringContainsString( 'Settings', $result[0] );
	}

	/**
	 * Test get_group_for_tab returns correct group names.
	 *
	 * @return void
	 */
	public function test_get_group_for_tab(): void {
		$this->assertSame( 'gcep_design', Settings::get_group_for_tab( 'design' ) );
		$this->assertSame( 'gcep_content', Settings::get_group_for_tab( 'content' ) );
		$this->assertSame( 'gcep_behavior', Settings::get_group_for_tab( 'behavior' ) );
		$this->assertSame( 'gcep_design', Settings::get_group_for_tab( 'invalid' ) );
	}

	/**
	 * Test get_all_option_defs returns all options.
	 *
	 * @return void
	 */
	public function test_get_all_option_defs_has_all_options(): void {
		$defs = Settings::get_all_option_defs();

		$this->assertArrayHasKey( 'gcep_template', $defs );
		$this->assertArrayHasKey( 'gcep_site_name', $defs );
		$this->assertArrayHasKey( 'gcep_scope', $defs );
		$this->assertArrayHasKey( 'gcep_fatal_errors', $defs );

		$this->assertGreaterThanOrEqual( 20, count( $defs ) );
	}

	/**
	 * Test each option def has required keys.
	 *
	 * @return void
	 */
	public function test_option_defs_structure(): void {
		$defs = Settings::get_all_option_defs();

		foreach ( $defs as $name => $def ) {
			$this->assertArrayHasKey( 'type', $def, "Option {$name} missing 'type' key" );
			$this->assertArrayHasKey( 'sanitize', $def, "Option {$name} missing 'sanitize' key" );
			$this->assertArrayHasKey( 'default', $def, "Option {$name} missing 'default' key" );
		}
	}
}
