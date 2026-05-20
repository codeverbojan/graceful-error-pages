<?php
/**
 * Uninstall handler.
 *
 * Cleans up all plugin data when the plugin is deleted via wp-admin.
 *
 * @package GracefulErrorPages
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete all plugin options.
$gep_options = [
	'gep_template',
	'gep_logo_url',
	'gep_icon_url',
	'gep_brand_color',
	'gep_bg_color',
	'gep_text_color',
	'gep_dark_mode',
	'gep_error_title',
	'gep_error_message',
	'gep_primary_btn_text',
	'gep_primary_btn_url',
	'gep_secondary_btn_text',
	'gep_secondary_btn_url',
	'gep_support_link',
	'gep_copyright',
	'gep_scope',
	'gep_fatal_errors',
	'gep_show_debug',
	'gep_admin_bypass',
	'gep_site_name',
];

foreach ( $gep_options as $gep_option ) {
	delete_option( $gep_option );
}
