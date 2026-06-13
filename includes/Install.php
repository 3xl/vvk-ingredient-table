<?php
declare( strict_types=1 );

namespace VVKit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activation / upgrade routines.
 */
final class Install {

	public static function activate(): void {
		if ( ! is_blog_installed() ) {
			return;
		}

		self::create_tables();
		self::add_default_options();

		update_option( 'vvkit_version', VVKIT_VERSION );
	}

	/**
	 * Runs on every load: re-syncs schema and options when the stored
	 * version differs (covers the v1 -> v2 upgrade, where activation
	 * hooks do not fire again).
	 */
	public static function maybe_upgrade(): void {
		if ( VVKIT_VERSION === get_option( 'vvkit_version' ) ) {
			return;
		}

		self::create_tables();
		self::add_default_options();

		update_option( 'vvkit_version', VVKIT_VERSION );
	}

	private static function create_tables(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( Schema::get_schema() as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * add_option() is a no-op when the option exists, so values coming
	 * from a v1 install are preserved untouched.
	 */
	private static function add_default_options(): void {
		add_option( 'vvkit_default_title', 'Ingredients' );
		add_option( 'vvkit_default_title_tagname', 'h2' );
		add_option( 'vvkit_css_classes', 'table' );
		add_option( 'vvkit_fractions', '1' );
		add_option( 'vvkit_post_type', [ 'post' ] );
		add_option( 'vvkit_jsonld', '1' );
		add_option( 'vvkit_display_defaults', \VVKit\Support\Options::DISPLAY_FEATURES );
		add_option( 'vvkit_delete_data_on_uninstall', '0' );
	}
}
