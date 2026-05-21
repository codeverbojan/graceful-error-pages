<?php
/**
 * Asset enqueue helpers.
 *
 * @package GracefulErrorPages
 */

declare( strict_types=1 );

namespace GracefulErrorPages\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues scripts and styles from the webpack build output.
 */
class Assets {

	/**
	 * Enqueue a built script from assets/build/.
	 *
	 * Reads the auto-generated .asset.php file for dependencies and
	 * content-hash versioning.
	 *
	 * @param string        $handle     Script handle (without prefix), e.g. 'admin'.
	 * @param array<string> $extra_deps Additional dependencies not in .asset.php (e.g. 'wp-color-picker').
	 * @return void
	 */
	public static function enqueue_script( string $handle, array $extra_deps = [] ): void {
		$asset = self::get_asset_data( $handle );
		$deps  = array_merge( $asset['dependencies'], $extra_deps );

		wp_enqueue_script(
			'gcep-' . $handle,
			GCEP_URL . 'assets/build/' . $handle . '.js',
			$deps,
			$asset['version'],
			true
		);
	}

	/**
	 * Enqueue a built stylesheet from assets/build/.
	 *
	 * @param string $handle  Style handle (without prefix), e.g. 'admin'.
	 * @param string $subpath Path relative to assets/build/. Defaults to {handle}.css.
	 * @return void
	 */
	public static function enqueue_style( string $handle, string $subpath = '' ): void {
		$path = '' !== $subpath ? $subpath : $handle . '.css';

		wp_enqueue_style(
			'gcep-' . $handle,
			GCEP_URL . 'assets/build/' . $path,
			[],
			GCEP_VERSION
		);
	}

	/**
	 * Read the .asset.php metadata for a given handle.
	 *
	 * @param string $handle Script handle (without prefix).
	 * @return array{dependencies: array<string>, version: string}
	 */
	private static function get_asset_data( string $handle ): array {
		$asset_file = GCEP_DIR . 'assets/build/' . $handle . '.asset.php';

		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;

			return [
				'dependencies' => $asset['dependencies'] ?? [],
				'version'      => $asset['version'] ?? GCEP_VERSION,
			];
		}

		return [
			'dependencies' => [],
			'version'      => GCEP_VERSION,
		];
	}
}
