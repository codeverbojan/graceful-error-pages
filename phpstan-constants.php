<?php
/**
 * Plugin constants for PHPStan static analysis.
 *
 * @package GracefulErrorPages
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GCEP_VERSION', '1.0.5' );
define( 'GCEP_FILE', __DIR__ . '/graceful-error-pages.php' );
define( 'GCEP_DIR', __DIR__ . '/' );
define( 'GCEP_URL', 'https://example.com/wp-content/plugins/graceful-error-pages/' );
