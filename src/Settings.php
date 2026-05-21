<?php
/**
 * Admin settings page: registration, rendering, and asset enqueueing.
 *
 * @package GracefulErrorPages
 */

declare( strict_types=1 );

namespace GracefulErrorPages;

use GracefulErrorPages\Helpers\Assets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the admin settings page under Settings > Error Pages.
 *
 * Uses the WordPress Settings API for all option persistence.
 * Page is organized into three tabs: Design, Content, Behavior.
 * Each tab has its own settings group to prevent data loss on save.
 */
class Settings {

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'gcep-settings';

	/**
	 * Capability required to manage settings.
	 *
	 * @var string
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * Valid tab slugs.
	 *
	 * @var array<string>
	 */
	private const TAB_SLUGS = [ 'design', 'content', 'behavior' ];

	/**
	 * Per-tab settings group names.
	 *
	 * @var array<string, string>
	 */
	private const TAB_GROUPS = [
		'design'   => 'gcep_design',
		'content'  => 'gcep_content',
		'behavior' => 'gcep_behavior',
	];

	/**
	 * Option definitions grouped by tab.
	 *
	 * Each entry: option_name => [type, sanitize callback name, default].
	 *
	 * @var array<string, array<string, array{type: string, sanitize: string, default: mixed}>>
	 */
	private const TAB_OPTIONS = [
		'design'   => [
			'gcep_template'    => [
				'type'     => 'string',
				'sanitize' => 'template',
				'default'  => 'minimal',
			],
			'gcep_logo_url'    => [
				'type'     => 'string',
				'sanitize' => 'url',
				'default'  => '',
			],
			'gcep_icon_url'    => [
				'type'     => 'string',
				'sanitize' => 'url',
				'default'  => '',
			],
			'gcep_brand_color' => [
				'type'     => 'string',
				'sanitize' => 'hex_color',
				'default'  => '#2563eb',
			],
			'gcep_bg_color'    => [
				'type'     => 'string',
				'sanitize' => 'hex_color',
				'default'  => '',
			],
			'gcep_text_color'  => [
				'type'     => 'string',
				'sanitize' => 'hex_color',
				'default'  => '',
			],
			'gcep_dark_mode'   => [
				'type'     => 'string',
				'sanitize' => 'dark_mode',
				'default'  => 'auto',
			],
		],
		'content'  => [
			'gcep_site_name'          => [
				'type'     => 'string',
				'sanitize' => 'text',
				'default'  => '',
			],
			'gcep_error_title'        => [
				'type'     => 'string',
				'sanitize' => 'text',
				'default'  => '',
			],
			'gcep_error_message'      => [
				'type'     => 'string',
				'sanitize' => 'kses',
				'default'  => '',
			],
			'gcep_primary_btn_text'   => [
				'type'     => 'string',
				'sanitize' => 'text',
				'default'  => '',
			],
			'gcep_primary_btn_url'    => [
				'type'     => 'string',
				'sanitize' => 'url',
				'default'  => '',
			],
			'gcep_secondary_btn_text' => [
				'type'     => 'string',
				'sanitize' => 'text',
				'default'  => '',
			],
			'gcep_secondary_btn_url'  => [
				'type'     => 'string',
				'sanitize' => 'url',
				'default'  => '',
			],
			'gcep_support_link'       => [
				'type'     => 'string',
				'sanitize' => 'url',
				'default'  => '',
			],
			'gcep_copyright'          => [
				'type'     => 'string',
				'sanitize' => 'text',
				'default'  => '',
			],
		],
		'behavior' => [
			'gcep_scope'        => [
				'type'     => 'string',
				'sanitize' => 'scope',
				'default'  => 'frontend',
			],
			'gcep_fatal_errors' => [
				'type'     => 'integer',
				'sanitize' => 'boolean',
				'default'  => 1,
			],
			'gcep_show_debug'   => [
				'type'     => 'integer',
				'sanitize' => 'boolean',
				'default'  => 1,
			],
			'gcep_admin_bypass' => [
				'type'     => 'integer',
				'sanitize' => 'boolean',
				'default'  => 1,
			],
		],
	];

	/**
	 * The hook suffix returned by add_options_page().
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Register hooks for the admin settings.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( GCEP_FILE ), [ $this, 'add_settings_link' ] );
	}

	/**
	 * Add the settings page to the admin menu.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		$this->hook_suffix = (string) add_options_page(
			__( 'Error Pages', 'graceful-error-pages' ),
			__( 'Error Pages', 'graceful-error-pages' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);

		if ( '' !== $this->hook_suffix ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		}
	}

	/**
	 * Register all settings with the Settings API.
	 *
	 * Each tab's options are registered under a separate group so that
	 * saving one tab does not wipe options from other tabs.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		foreach ( self::TAB_OPTIONS as $tab => $options ) {
			$group = self::TAB_GROUPS[ $tab ];

			foreach ( $options as $option_name => $def ) {
				register_setting(
					$group,
					$option_name,
					[
						'type'              => $def['type'],
						'sanitize_callback' => [ Sanitizer::class, $def['sanitize'] ],
						'default'           => $def['default'],
					]
				);
			}
		}
	}

	/**
	 * Add a "Settings" link to the Plugins page.
	 *
	 * @param array<string> $links Existing action links.
	 * @return array<string>
	 */
	public function add_settings_link( array $links ): array {
		$url  = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'graceful-error-pages' ) . '</a>';

		array_unshift( $links, $link );

		return $links;
	}

	/**
	 * Enqueue admin assets only on the settings page.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();

		Assets::enqueue_style( 'admin', 'css/admin.css' );

		Assets::enqueue_script( 'admin', [ 'wp-color-picker' ] );

		wp_localize_script(
			'gcep-admin',
			'gcepAdmin',
			[
				'previewNonce'  => wp_create_nonce( Preview::NONCE_ACTION ),
				'previewAction' => Preview::ACTION,
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'mediaTitle'    => __( 'Select Logo', 'graceful-error-pages' ),
				'mediaButton'   => __( 'Use This Image', 'graceful-error-pages' ),
				'closeLabel'    => __( 'Close preview', 'graceful-error-pages' ),
				'previewTitle'  => __( 'Error page preview', 'graceful-error-pages' ),
			]
		);

		Assets::enqueue_script( 'merge-tags' );

		$tag_defs = [];
		foreach ( TemplateEngine::MERGE_TAGS as $tag => $key ) {
			$tag_defs[] = [
				'tag'   => $tag,
				'label' => $this->get_merge_tag_label( $key ),
			];
		}

		wp_localize_script( 'gcep-merge-tags', 'gcepMergeTags', $tag_defs );
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$active_tab = $this->get_active_tab();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';

		echo '<div class="gcep-header-bar">';
		$this->render_tabs( $active_tab );
		echo '<button type="button" class="button gcep-preview-btn">' . esc_html__( 'Preview Error Page', 'graceful-error-pages' ) . '</button>';
		echo '</div>';

		echo '<form method="post" action="options.php" id="gcep-settings-form">';
		settings_fields( self::TAB_GROUPS[ $active_tab ] );

		switch ( $active_tab ) {
			case 'content':
				$this->render_content_tab();
				break;

			case 'behavior':
				$this->render_behavior_tab();
				break;

			default:
				$this->render_design_tab();
				break;
		}

		submit_button();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Get the settings group for a given tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public static function get_group_for_tab( string $tab ): string {
		return self::TAB_GROUPS[ $tab ] ?? self::TAB_GROUPS['design'];
	}

	/**
	 * Get all option definitions across all tabs.
	 *
	 * @return array<string, array{type: string, sanitize: string, default: mixed}>
	 */
	public static function get_all_option_defs(): array {
		$all = [];
		foreach ( self::TAB_OPTIONS as $options ) {
			$all = array_merge( $all, $options );
		}
		return $all;
	}

	/**
	 * Get translated tab labels.
	 *
	 * @return array<string, string> Slug => translated label.
	 */
	private function get_tab_labels(): array {
		return [
			'design'   => __( 'Design', 'graceful-error-pages' ),
			'content'  => __( 'Content', 'graceful-error-pages' ),
			'behavior' => __( 'Behavior', 'graceful-error-pages' ),
		];
	}

	/**
	 * Get the active tab slug from the URL.
	 *
	 * @return string
	 */
	private function get_active_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation, no data mutation.
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'design';

		return in_array( $tab, self::TAB_SLUGS, true ) ? $tab : 'design';
	}

	/**
	 * Render the tab navigation.
	 *
	 * @param string $active_tab The currently active tab slug.
	 * @return void
	 */
	private function render_tabs( string $active_tab ): void {
		echo '<nav class="nav-tab-wrapper">';

		foreach ( $this->get_tab_labels() as $slug => $label ) {
			$url   = add_query_arg( 'tab', $slug, admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) );
			$class = ( $active_tab === $slug ) ? ' nav-tab-active' : '';

			echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . esc_attr( $class ) . '">';
			echo esc_html( $label );
			echo '</a>';
		}

		echo '</nav>';
	}

	/**
	 * Render the Design tab fields.
	 *
	 * @return void
	 */
	private function render_design_tab(): void {
		$current_template = get_option( 'gcep_template', 'minimal' );
		$templates        = TemplateEngine::get_available_templates();

		echo '<table class="form-table" role="presentation">';

		// Template picker.
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Template', 'graceful-error-pages' ) . '</th>';
		echo '<td>';
		echo '<fieldset>';
		echo '<legend class="screen-reader-text"><span>' . esc_html__( 'Template', 'graceful-error-pages' ) . '</span></legend>';
		echo '<div class="gcep-template-picker">';

		foreach ( $templates as $slug => $label ) {
			$checked = checked( $current_template, $slug, false );
			$id      = 'gcep-template-' . esc_attr( $slug );

			echo '<label class="gcep-template-option" for="' . esc_attr( $id ) . '">';
			echo '<input type="radio" id="' . esc_attr( $id ) . '" name="gcep_template" value="' . esc_attr( $slug ) . '"' . $checked . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- checked() returns escaped HTML attribute.
			echo '<span class="gcep-template-preview gcep-template-preview--' . esc_attr( $slug ) . '"></span>';
			echo '<span class="gcep-template-label">' . esc_html( $label ) . '</span>';
			echo '</label>';
		}

		echo '</div>';
		echo '</fieldset>';
		echo '</td>';
		echo '</tr>';

		// Logo URL.
		$this->render_media_field(
			'gcep_logo_url',
			__( 'Logo URL', 'graceful-error-pages' ),
			__( 'Logo displayed on the error page. Leave blank to use the site icon.', 'graceful-error-pages' )
		);

		// Site Icon URL.
		$this->render_media_field(
			'gcep_icon_url',
			__( 'Site Icon URL', 'graceful-error-pages' ),
			__( 'Fallback icon when no logo is set. Auto-detected on activation.', 'graceful-error-pages' )
		);

		// Brand color.
		$this->render_color_field(
			'gcep_brand_color',
			__( 'Brand Color', 'graceful-error-pages' )
		);

		// Background color.
		$this->render_color_field(
			'gcep_bg_color',
			__( 'Background Color', 'graceful-error-pages' )
		);

		// Text color.
		$this->render_color_field(
			'gcep_text_color',
			__( 'Text Color', 'graceful-error-pages' )
		);

		// Dark mode.
		$dark_mode = get_option( 'gcep_dark_mode', 'auto' );
		$modes     = [
			'auto'     => __( 'Auto (follow system preference)', 'graceful-error-pages' ),
			'on'       => __( 'Always dark', 'graceful-error-pages' ),
			'off'      => __( 'Always light', 'graceful-error-pages' ),
			'disabled' => __( 'Disabled', 'graceful-error-pages' ),
		];

		echo '<tr>';
		echo '<th scope="row"><label for="gcep-dark-mode">' . esc_html__( 'Dark Mode', 'graceful-error-pages' ) . '</label></th>';
		echo '<td>';
		echo '<select id="gcep-dark-mode" name="gcep_dark_mode">';

		foreach ( $modes as $mode_value => $mode_label ) {
			echo '<option value="' . esc_attr( $mode_value ) . '"' . selected( $dark_mode, $mode_value, false ) . '>';
			echo esc_html( $mode_label );
			echo '</option>';
		}

		echo '</select>';
		echo '</td>';
		echo '</tr>';

		echo '</table>';
	}

	/**
	 * Render the Content tab fields.
	 *
	 * @return void
	 */
	private function render_content_tab(): void {
		echo '<table class="form-table" role="presentation">';

		// Site name override.
		$site_name = get_option( 'gcep_site_name', '' );

		echo '<tr>';
		echo '<th scope="row"><label for="gcep-site-name">' . esc_html__( 'Site Name', 'graceful-error-pages' ) . '</label></th>';
		echo '<td>';
		echo '<input type="text" id="gcep-site-name" name="gcep_site_name" value="' . esc_attr( $site_name ) . '" class="regular-text">';
		echo '<p class="description">' . esc_html__( 'Override the site name shown on error pages. Leave blank to use Settings > General.', 'graceful-error-pages' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Error title.
		$error_title = get_option( 'gcep_error_title', '' );

		echo '<tr>';
		echo '<th scope="row"><label for="gcep-error-title">' . esc_html__( 'Error Title', 'graceful-error-pages' ) . '</label></th>';
		echo '<td>';
		echo '<input type="text" id="gcep-error-title" name="gcep_error_title" value="' . esc_attr( $error_title ) . '" class="regular-text gcep-merge-input">';
		echo '<p class="description">' . esc_html__( 'Leave blank to use the default or auto-detected title. Type { to insert a merge tag.', 'graceful-error-pages' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Error message.
		$error_message = get_option( 'gcep_error_message', '' );

		echo '<tr>';
		echo '<th scope="row"><label for="gcep-error-message">' . esc_html__( 'Error Message', 'graceful-error-pages' ) . '</label></th>';
		echo '<td>';
		echo '<textarea id="gcep-error-message" name="gcep_error_message" rows="4" class="large-text gcep-merge-input">' . esc_textarea( $error_message ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Leave blank to use the default message. Basic HTML allowed.', 'graceful-error-pages' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Primary button text.
		$primary_text = get_option( 'gcep_primary_btn_text', '' );

		echo '<tr>';
		echo '<th scope="row"><label for="gcep-primary-btn-text">' . esc_html__( 'Primary Button Text', 'graceful-error-pages' ) . '</label></th>';
		echo '<td>';
		echo '<input type="text" id="gcep-primary-btn-text" name="gcep_primary_btn_text" value="' . esc_attr( $primary_text ) . '" class="regular-text gcep-merge-input">';
		echo '<p class="description">';
		/* translators: %s: default button text */
		echo esc_html( sprintf( __( 'Default: "%s"', 'graceful-error-pages' ), __( 'Go to Homepage', 'graceful-error-pages' ) ) );
		echo '</p>';
		echo '</td>';
		echo '</tr>';

		// Primary button URL.
		$primary_url = get_option( 'gcep_primary_btn_url', '' );

		echo '<tr>';
		echo '<th scope="row"><label for="gcep-primary-btn-url">' . esc_html__( 'Primary Button URL', 'graceful-error-pages' ) . '</label></th>';
		echo '<td>';
		echo '<input type="url" id="gcep-primary-btn-url" name="gcep_primary_btn_url" value="' . esc_url( $primary_url ) . '" class="regular-text">';
		echo '<p class="description">' . esc_html__( 'Default: homepage URL.', 'graceful-error-pages' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Secondary button text.
		$secondary_text = get_option( 'gcep_secondary_btn_text', '' );

		echo '<tr>';
		echo '<th scope="row"><label for="gcep-secondary-btn-text">' . esc_html__( 'Secondary Button Text', 'graceful-error-pages' ) . '</label></th>';
		echo '<td>';
		echo '<input type="text" id="gcep-secondary-btn-text" name="gcep_secondary_btn_text" value="' . esc_attr( $secondary_text ) . '" class="regular-text gcep-merge-input">';
		echo '<p class="description">';
		/* translators: %s: default button text */
		echo esc_html( sprintf( __( 'Default: "%s"', 'graceful-error-pages' ), __( 'Go Back', 'graceful-error-pages' ) ) );
		echo '</p>';
		echo '</td>';
		echo '</tr>';

		// Secondary button URL.
		$secondary_url = get_option( 'gcep_secondary_btn_url', '' );

		echo '<tr>';
		echo '<th scope="row"><label for="gcep-secondary-btn-url">' . esc_html__( 'Secondary Button URL', 'graceful-error-pages' ) . '</label></th>';
		echo '<td>';
		echo '<input type="url" id="gcep-secondary-btn-url" name="gcep_secondary_btn_url" value="' . esc_url( $secondary_url ) . '" class="regular-text">';
		echo '<p class="description">' . esc_html__( 'Leave blank to use browser back.', 'graceful-error-pages' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Support link.
		$support_link = get_option( 'gcep_support_link', '' );

		echo '<tr>';
		echo '<th scope="row"><label for="gcep-support-link">' . esc_html__( 'Support Link', 'graceful-error-pages' ) . '</label></th>';
		echo '<td>';
		echo '<input type="url" id="gcep-support-link" name="gcep_support_link" value="' . esc_url( $support_link ) . '" class="regular-text">';
		echo '<p class="description">' . esc_html__( 'Optional link to a support or contact page.', 'graceful-error-pages' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Copyright text.
		$copyright = get_option( 'gcep_copyright', '' );

		echo '<tr>';
		echo '<th scope="row"><label for="gcep-copyright">' . esc_html__( 'Copyright Text', 'graceful-error-pages' ) . '</label></th>';
		echo '<td>';
		echo '<input type="text" id="gcep-copyright" name="gcep_copyright" value="' . esc_attr( $copyright ) . '" class="regular-text gcep-merge-input">';
		echo '<p class="description">';
		/* translators: %s: example merge tag usage */
		echo esc_html( sprintf( __( 'Default: "&copy; %s". Type { to insert a merge tag.', 'graceful-error-pages' ), '{year} {site_name}' ) );
		echo '</p>';
		echo '</td>';
		echo '</tr>';

		echo '</table>';
	}

	/**
	 * Render the Behavior tab fields.
	 *
	 * @return void
	 */
	private function render_behavior_tab(): void {
		echo '<table class="form-table" role="presentation">';

		// Scope.
		$scope  = get_option( 'gcep_scope', 'frontend' );
		$scopes = [
			'frontend'   => __( 'Frontend only', 'graceful-error-pages' ),
			'admin'      => __( 'Admin only', 'graceful-error-pages' ),
			'everywhere' => __( 'Everywhere (Frontend + Admin)', 'graceful-error-pages' ),
		];

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Scope', 'graceful-error-pages' ) . '</th>';
		echo '<td>';
		echo '<fieldset>';
		echo '<legend class="screen-reader-text"><span>' . esc_html__( 'Scope', 'graceful-error-pages' ) . '</span></legend>';

		foreach ( $scopes as $scope_value => $scope_label ) {
			echo '<label>';
			echo '<input type="radio" name="gcep_scope" value="' . esc_attr( $scope_value ) . '"' . checked( $scope, $scope_value, false ) . '> '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- checked() returns escaped HTML attribute.
			echo esc_html( $scope_label );
			echo '</label><br>';
		}

		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'Where to display custom error pages.', 'graceful-error-pages' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Fatal errors toggle.
		$this->render_checkbox_field(
			'gcep_fatal_errors',
			__( 'Fatal Error Handler', 'graceful-error-pages' ),
			__( 'Replace PHP fatal error screens with branded pages', 'graceful-error-pages' )
		);

		// Debug info toggle.
		$this->render_checkbox_field(
			'gcep_show_debug',
			__( 'Debug Info', 'graceful-error-pages' ),
			__( 'Show debug details on error pages when WP_DEBUG is enabled', 'graceful-error-pages' )
		);

		// Admin bypass toggle.
		$this->render_checkbox_field(
			'gcep_admin_bypass',
			__( 'Admin Bypass', 'graceful-error-pages' ),
			__( 'Show default WordPress error pages to logged-in administrators', 'graceful-error-pages' )
		);

		echo '</table>';
	}

	/**
	 * Render a media uploader field row (for logo/icon URLs).
	 *
	 * @param string $option_name The option name.
	 * @param string $label       The field label.
	 * @param string $description The field description.
	 * @return void
	 */
	private function render_media_field( string $option_name, string $label, string $description ): void {
		$value = get_option( $option_name, '' );
		$id    = str_replace( '_', '-', $option_name );

		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td>';
		echo '<div class="gcep-media-field">';
		echo '<input type="url" id="' . esc_attr( $id ) . '" name="' . esc_attr( $option_name ) . '" value="' . esc_url( $value ) . '" class="regular-text">';
		echo ' <button type="button" class="button gcep-media-select" data-target="#' . esc_attr( $id ) . '">' . esc_html__( 'Select Image', 'graceful-error-pages' ) . '</button>';

		if ( '' !== $value ) {
			echo ' <button type="button" class="button gcep-media-remove" data-target="#' . esc_attr( $id ) . '">' . esc_html__( 'Remove', 'graceful-error-pages' ) . '</button>';
		}

		echo '</div>';

		if ( '' !== $value ) {
			echo '<div class="gcep-media-preview"><img src="' . esc_url( $value ) . '" alt=""></div>';
		}

		echo '<p class="description">' . esc_html( $description ) . '</p>';
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Render a color picker field row.
	 *
	 * Reads the default value from TAB_OPTIONS so defaults stay DRY.
	 *
	 * @param string $option_name The option name.
	 * @param string $label       The field label.
	 * @return void
	 */
	private function render_color_field( string $option_name, string $label ): void {
		$def_color = self::TAB_OPTIONS['design'][ $option_name ]['default'] ?? '';
		$value     = get_option( $option_name, $def_color );
		$id        = str_replace( '_', '-', $option_name );

		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td>';
		echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $option_name ) . '" value="' . esc_attr( $value ) . '" class="gcep-color-picker" data-default-color="' . esc_attr( $def_color ) . '">';
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Get a human-readable label for a merge tag context key.
	 *
	 * @param string $key The context key (e.g. 'site_name').
	 * @return string
	 */
	private function get_merge_tag_label( string $key ): string {
		$labels = [
			'site_name' => __( 'Your site name', 'graceful-error-pages' ),
			'year'      => __( 'Current year', 'graceful-error-pages' ),
			'home_url'  => __( 'Your site homepage URL', 'graceful-error-pages' ),
			'back_url'  => __( 'The URL to go back to', 'graceful-error-pages' ),
		];

		return $labels[ $key ] ?? $key;
	}

	/**
	 * Render a checkbox/toggle field row.
	 *
	 * @param string $option_name The option name.
	 * @param string $label       The field label.
	 * @param string $description The checkbox description text.
	 * @return void
	 */
	private function render_checkbox_field( string $option_name, string $label, string $description ): void {
		$def_val = self::TAB_OPTIONS['behavior'][ $option_name ]['default'] ?? 1;
		$value   = (int) get_option( $option_name, $def_val );
		$id      = str_replace( '_', '-', $option_name );

		echo '<tr>';
		echo '<th scope="row">' . esc_html( $label ) . '</th>';
		echo '<td>';
		echo '<label for="' . esc_attr( $id ) . '">';
		echo '<input type="hidden" name="' . esc_attr( $option_name ) . '" value="0">';
		echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $option_name ) . '" value="1"' . checked( $value, 1, false ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- checked() returns escaped HTML attribute.
		echo ' ' . esc_html( $description );
		echo '</label>';
		echo '</td>';
		echo '</tr>';
	}
}
