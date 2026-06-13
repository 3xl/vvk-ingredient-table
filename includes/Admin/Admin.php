<?php
declare( strict_types=1 );

namespace VVKit\Admin;

use VVKit\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin wiring: menu pages, post metabox and asset enqueueing.
 * The actual UI is a React app (src/ -> build/) based on @wordpress/components.
 */
class Admin {

	private SettingsPage $settings;

	public function __construct() {
		$this->settings = new SettingsPage();
	}

	public function register(): void {
		$this->settings->register();

		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function add_meta_box(): void {
		add_meta_box(
			'vvkit-metabox',
			__( 'Ingredient tables', 'vvkit' ),
			[ $this, 'render_meta_box' ],
			Options::post_types(),
			'advanced',
			'high'
		);
	}

	public function render_meta_box( \WP_Post $post ): void {
		// NB: non riusare l'id del metabox ('vvkit-metabox'): WordPress lo assegna
		// già al wrapper .postbox e getElementById prenderebbe quello (post_id=NaN).
		printf( '<div id="vvkit-metabox-root" data-post-id="%s"></div>', esc_attr( (string) $post->ID ) );
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'Ingredient tables', 'vvkit' ),
			__( 'Ingredients', 'vvkit' ),
			'manage_options',
			'vvkit-settings',
			[ $this->settings, 'render' ],
			'dashicons-carrot',
			58
		);

		add_submenu_page(
			'vvkit-settings',
			__( 'Settings', 'vvkit' ),
			__( 'Settings', 'vvkit' ),
			'manage_options',
			'vvkit-settings',
			[ $this->settings, 'render' ]
		);

		add_submenu_page(
			'vvkit-settings',
			__( 'Ingredients', 'vvkit' ),
			__( 'Ingredients', 'vvkit' ),
			'manage_options',
			'vvkit-ingredients',
			fn() => $this->render_manage_page( 'ingredients', __( 'Ingredients', 'vvkit' ) )
		);

		add_submenu_page(
			'vvkit-settings',
			__( 'Units', 'vvkit' ),
			__( 'Units', 'vvkit' ),
			'manage_options',
			'vvkit-units',
			fn() => $this->render_manage_page( 'units', __( 'Units', 'vvkit' ) )
		);
	}

	private function render_manage_page( string $resource, string $title ): void {
		// Same chrome as the core taxonomy screens (edit-tags.php); the
		// React app fills in the col-container layout below the header.
		printf(
			'<div class="wrap"><h1 class="wp-heading-inline">%s</h1><hr class="wp-header-end"><div id="vvkit-manage" data-resource="%s"></div></div>',
			esc_html( $title ),
			esc_attr( $resource )
		);
	}

	public function enqueue_assets( string $hook ): void {
		$screen         = get_current_screen();
		$is_post_editor = $screen && 'post' === $screen->base && in_array( $screen->post_type, Options::post_types(), true );
		$is_plugin_page = false !== strpos( $hook, 'vvkit' );

		if ( ! $is_post_editor && ! $is_plugin_page ) {
			return;
		}

		$asset_file = VVKIT_PLUGIN_DIR . 'build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			// Build not run yet (pnpm --filter @vasavasa/wp-vvk-ingredients-table build).
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'vvkit-admin',
			VVKIT_PLUGIN_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'vvkit-admin', 'vvkit' );

		wp_enqueue_style(
			'vvkit-admin',
			VVKIT_PLUGIN_URL . 'assets/css/admin.css',
			[ 'wp-components' ],
			VVKIT_VERSION
		);
	}
}
