<?php
/**
 * Uninstall handler.
 *
 * Cleans up all plugin data when the plugin is deleted via wp-admin.
 *
 * @package GracefulErrorPages
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete all plugin options.
$gcep_options = [
	'gcep_template',
	'gcep_logo_url',
	'gcep_icon_url',
	'gcep_brand_color',
	'gcep_bg_color',
	'gcep_text_color',
	'gcep_dark_mode',
	'gcep_error_title',
	'gcep_error_message',
	'gcep_primary_btn_text',
	'gcep_primary_btn_url',
	'gcep_secondary_btn_text',
	'gcep_secondary_btn_url',
	'gcep_support_link',
	'gcep_copyright',
	'gcep_scope',
	'gcep_fatal_errors',
	'gcep_show_debug',
	'gcep_admin_bypass',
	'gcep_site_name',
];

foreach ( $gcep_options as $gcep_option ) {
	delete_option( $gcep_option );
}
