<?php
/**
 * Plugin bootstrap.
 *
 * @package GracefulErrorPages
 */

declare( strict_types=1 );

namespace GracefulErrorPages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 *
 * Boots the plugin via a static factory method. Not a singleton — returns a new
 * instance on first boot. Subsequent calls return the same instance (boot guard).
 * The instance is stored in $GLOBALS['gcep_plugin'] by the main plugin file.
 */
class Plugin {

	/**
	 * Whether the plugin has been booted.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * The booted instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {
	}

	/**
	 * Boot the plugin.
	 *
	 * Returns the existing instance if already booted.
	 *
	 * @return self
	 */
	public static function boot(): self {
		if ( self::$booted && self::$instance instanceof self ) {
			return self::$instance;
		}

		self::$instance = new self();
		self::$instance->register_hooks();
		self::$booted = true;

		return self::$instance;
	}

	/**
	 * Register all plugin hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		register_activation_hook( GCEP_FILE, [ $this, 'activate' ] );
		register_deactivation_hook( GCEP_FILE, [ $this, 'deactivate' ] );

		add_action( 'init', [ $this, 'load_textdomain' ] );

		$template_engine = new TemplateEngine();

		$handler = new Handler( $template_engine );
		$handler->register();

		if ( is_admin() ) {
			$settings = new Settings();
			$settings->register();

			$preview = new Preview( $template_engine );
			$preview->register();
		}
	}

	/**
	 * Load plugin text domain for translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'graceful-error-pages', false, dirname( plugin_basename( GCEP_FILE ) ) . '/languages' );
	}

	/**
	 * Plugin activation callback.
	 *
	 * @return void
	 */
	public function activate(): void {
		$detected = AutoDetect::detect();

		add_option( 'gcep_site_name', $detected['site_name'] );
		add_option( 'gcep_logo_url', $detected['logo_url'] );
		add_option( 'gcep_icon_url', $detected['icon_url'] );
		add_option( 'gcep_brand_color', $detected['brand_color'] );
		add_option( 'gcep_template', 'minimal' );
		add_option( 'gcep_scope', 'frontend' );
		add_option( 'gcep_dark_mode', 'auto' );
		add_option( 'gcep_fatal_errors', 1 );
		add_option( 'gcep_show_debug', 1 );
		add_option( 'gcep_admin_bypass', 1 );
	}

	/**
	 * Plugin deactivation callback.
	 *
	 * @return void
	 */
	public function deactivate(): void {
	}

	/**
	 * Reset boot state.
	 *
	 * @internal Only for use in tests.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$booted   = false;
		self::$instance = null;
	}
}
