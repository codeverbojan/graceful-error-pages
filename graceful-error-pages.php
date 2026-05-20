<?php
/**
 * Graceful Error Pages
 *
 * @package           GracefulErrorPages
 * @author            Codever
 * @copyright         2026 Codever
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Graceful Error Pages
 * Plugin URI:        https://bojanjosifoski.com/graceful-error-pages
 * Description:       Replace WordPress's ugly error screens with branded, professional pages — in one click.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Tested up to:      7.0
 * Author:            Codever
 * Author URI:        https://codever.io
 * Text Domain:       graceful-error-pages
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'GEP_VERSION' ) ) {
	define( 'GEP_VERSION', '1.0.0' );
}
if ( ! defined( 'GEP_FILE' ) ) {
	define( 'GEP_FILE', __FILE__ );
}
if ( ! defined( 'GEP_DIR' ) ) {
	define( 'GEP_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'GEP_URL' ) ) {
	define( 'GEP_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * PSR-4 autoloader for the GracefulErrorPages namespace.
 *
 * Maps GracefulErrorPages\ to the src/ directory. This replaces Composer's
 * autoloader in production so that no vendor/ directory needs to ship with
 * the plugin.
 *
 * @param string $class_name The fully-qualified class name.
 * @return void
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'GracefulErrorPages\\';
		$len    = strlen( $prefix );

		if ( strncmp( $class_name, $prefix, $len ) !== 0 ) {
			return;
		}

		$relative = substr( $class_name, $len );

		if ( str_contains( $relative, '..' )
			|| str_contains( $relative, "\0" )
			|| str_starts_with( $relative, '/' ) ) {
			return;
		}

		$file = GEP_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

$GLOBALS['gep_plugin'] = GracefulErrorPages\Plugin::boot();
