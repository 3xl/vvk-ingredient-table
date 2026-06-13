<?php
declare( strict_types=1 );

namespace VVKit\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Typed read access to the plugin options. Option names are unchanged
 * from v1 so existing installs keep their configuration.
 */
final class Options {

	public const ALLOWED_TITLE_TAGS = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ];

	/**
	 * Frontend "extras" whose visibility is configurable: globally from
	 * the settings page and per table (the table override wins).
	 */
	public const DISPLAY_FEATURES = [
		'servings_switcher',
		'units_toggle',
		'allergens',
		'diets',
		'nutrition',
		'product_links',
	];

	/**
	 * Post types the metabox / auto-insert applies to.
	 *
	 * v1 stored a single post type as a plain string ('' meaning 'post');
	 * v2 stores an array. Both shapes are accepted.
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		$value = get_option( 'vvkit_post_type', [ 'post' ] );

		if ( is_string( $value ) ) {
			$value = '' === trim( $value ) ? [ 'post' ] : array_map( 'trim', explode( ',', $value ) );
		}

		$value = array_values( array_filter( (array) $value, 'post_type_exists' ) );

		return $value ?: [ 'post' ];
	}

	public static function default_title(): string {
		return (string) get_option( 'vvkit_default_title', 'Ingredients' );
	}

	public static function default_title_tagname(): string {
		return self::sanitize_tagname( (string) get_option( 'vvkit_default_title_tagname', 'h2' ) );
	}

	public static function css_classes(): string {
		return (string) get_option( 'vvkit_css_classes', 'table' );
	}

	public static function fractions_enabled(): bool {
		return '1' === (string) get_option( 'vvkit_fractions', '1' );
	}

	/**
	 * Global default visibility of the table extras.
	 *
	 * @return array<string,bool> Keyed by self::DISPLAY_FEATURES.
	 */
	public static function display_defaults(): array {
		$enabled = get_option( 'vvkit_display_defaults', self::DISPLAY_FEATURES );
		$enabled = is_array( $enabled ) ? $enabled : [];

		$defaults = [];

		foreach ( self::DISPLAY_FEATURES as $feature ) {
			$defaults[ $feature ] = in_array( $feature, $enabled, true );
		}

		return $defaults;
	}

	public static function jsonld_enabled(): bool {
		return '1' === (string) get_option( 'vvkit_jsonld', '1' );
	}

	public static function delete_data_on_uninstall(): bool {
		return '1' === (string) get_option( 'vvkit_delete_data_on_uninstall', '0' );
	}

	public static function sanitize_tagname( string $tag ): string {
		$tag = strtolower( trim( $tag ) );

		return in_array( $tag, self::ALLOWED_TITLE_TAGS, true ) ? $tag : 'h2';
	}
}
