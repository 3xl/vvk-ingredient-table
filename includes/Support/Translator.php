<?php
declare( strict_types=1 );

namespace VVKit\Support;

use VVKit\Repository\IngredientRepository;
use VVKit\Repository\RowRepository;
use VVKit\Repository\TableRepository;
use VVKit\Repository\TagRepository;
use VVKit\Repository\UnitRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translation layer for the table CONTENT stored in the custom DB tables
 * (ingredient names, unit names, table titles, row notes, allergen/diet
 * tags). WordPress only translates posts and taxonomies out of the box,
 * so this data is invisible to WPML / Polylang unless registered with
 * their String Translation APIs — which is exactly what this class does,
 * behind a single backend-agnostic facade.
 *
 * Resolution of the active language (used by the frontend and, crucially,
 * by the headless REST consumers):
 *   1. an explicit per-request override (the REST `?lang=` parameter);
 *   2. the active language of the multilingual plugin in use;
 *   3. the WordPress locale (single-language fallback — returns the source
 *      string unchanged, so installs without WPML/Polylang behave exactly
 *      as before).
 *
 * The UI strings of the plugin itself (labels, buttons) are NOT handled
 * here — those go through the `vvkit` text domain (.l10n.php files).
 */
final class Translator {

	public const NONE     = 0;
	public const POLYLANG = 1;
	public const WPML     = 2;

	/** WPML string-translation context / Polylang group label. */
	private const DOMAIN = 'VVK Ingredients Table';

	/** Option storing the signature of the last registered catalog. */
	private const SIGNATURE_OPTION = 'vvkit_i18n_signature';

	private static ?int $backend = null;

	/** Per-request language override (set from the REST `?lang=` param). */
	private static ?string $request_language = null;

	/**
	 * Detects which multilingual plugin is active. Polylang is checked
	 * first; WPML second. Result is memoized for the request.
	 */
	public static function backend(): int {
		if ( null !== self::$backend ) {
			return self::$backend;
		}

		if ( function_exists( 'pll_register_string' ) && function_exists( 'pll_translate_string' ) ) {
			self::$backend = self::POLYLANG;
		} elseif ( defined( 'ICL_SITEPRESS_VERSION' ) || has_filter( 'wpml_translate_single_string' ) ) {
			self::$backend = self::WPML;
		} else {
			self::$backend = self::NONE;
		}

		return self::$backend;
	}

	public static function is_active(): bool {
		return self::NONE !== self::backend();
	}

	/**
	 * Human-readable backend name for the settings screen.
	 */
	public static function backend_label(): string {
		return match ( self::backend() ) {
			self::POLYLANG => 'Polylang',
			self::WPML     => 'WPML',
			default        => __( 'none', 'vvkit' ),
		};
	}

	/**
	 * Sets the per-request target language (the REST `?lang=` parameter).
	 * Pass null to clear it.
	 */
	public static function set_request_language( ?string $lang ): void {
		$lang = is_string( $lang ) ? sanitize_key( $lang ) : '';

		self::$request_language = '' !== $lang ? $lang : null;
	}

	/**
	 * Resolves the active language slug (e.g. 'en', 'it') — the explicit
	 * per-request override if any, otherwise the ambient language. Used by
	 * the settings screen; translation itself goes through resolve_target().
	 */
	public static function current_language(): string {
		return self::$request_language ?? self::ambient_language();
	}

	/**
	 * The language of the surrounding context, ignoring any explicit
	 * override: the multilingual plugin's current language, falling back
	 * to the WordPress locale.
	 */
	private static function ambient_language(): string {
		switch ( self::backend() ) {
			case self::POLYLANG:
				$lang = pll_current_language( 'slug' );

				if ( is_string( $lang ) && '' !== $lang ) {
					return $lang;
				}
				break;

			case self::WPML:
				$lang = apply_filters( 'wpml_current_language', null );

				if ( is_string( $lang ) && '' !== $lang ) {
					return $lang;
				}
				break;
		}

		return substr( determine_locale(), 0, 2 );
	}

	/**
	 * Decides which language to translate content into for the current
	 * request, or null to keep the source string. The null case protects
	 * the admin editor: its REST reads (no explicit `?lang=`) must return
	 * the canonical/source text so saving never overwrites it with a
	 * translation.
	 *
	 *   - explicit `?lang=` override .................. translate to it
	 *   - REST request without override (admin editor)  keep source (null)
	 *   - everything else (frontend render, JSON-LD) .. translate to ambient
	 */
	private static function resolve_target(): ?string {
		if ( null !== self::$request_language ) {
			return self::$request_language;
		}

		if ( ( function_exists( 'wp_is_rest_request' ) && wp_is_rest_request() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return null;
		}

		return self::ambient_language();
	}

	/**
	 * Registers a single string with the active backend so it shows up in
	 * the plugin's String Translation UI. No-op without a backend.
	 *
	 * @param string $name      Stable identifier (used by WPML; Polylang keys by value).
	 * @param string $value     Source string (the canonical/default language).
	 * @param bool   $multiline Hint for Polylang's textarea editor.
	 */
	public static function register( string $name, string $value, bool $multiline = false ): void {
		if ( '' === trim( $value ) ) {
			return;
		}

		switch ( self::backend() ) {
			case self::POLYLANG:
				pll_register_string( $name, $value, self::DOMAIN, $multiline );
				break;

			case self::WPML:
				do_action( 'wpml_register_single_string', self::DOMAIN, $name, $value );
				break;
		}
	}

	/**
	 * Translates a string to the target language (or the current one).
	 * Falls back to the source string when there is no translation or no
	 * backend — so single-language installs are unaffected.
	 *
	 * @param string      $name  Stable identifier matching register().
	 * @param string      $value Source string.
	 * @param string|null $lang  Target language slug; null = current language.
	 */
	public static function translate( string $name, string $value, ?string $lang = null ): string {
		if ( '' === trim( $value ) || ! self::is_active() ) {
			return $value;
		}

		$lang = $lang ?? self::resolve_target();

		if ( null === $lang || '' === $lang ) {
			return $value;
		}

		switch ( self::backend() ) {
			case self::POLYLANG:
				// Polylang resolves by the source string, not the name.
				$translated = pll_translate_string( $value, $lang );

				return is_string( $translated ) && '' !== $translated ? $translated : $value;

			case self::WPML:
				$translated = apply_filters( 'wpml_translate_single_string', $value, self::DOMAIN, $name, $lang );

				return is_string( $translated ) && '' !== $translated ? $translated : $value;
		}

		return $value;
	}

	/* ---------------------------------------------------------------------
	 * Typed helpers — keep the name scheme in one place so register() and
	 * translate() always agree on the identifier for a given entity.
	 * ------------------------------------------------------------------- */

	public static function register_ingredient( int $id, string $name ): void {
		self::register( 'ingredient_' . $id, $name );
	}

	public static function ingredient( int $id, string $name, ?string $lang = null ): string {
		return self::translate( 'ingredient_' . $id, $name, $lang );
	}

	public static function register_unit( int $id, string $name ): void {
		self::register( 'unit_' . $id, $name );
	}

	public static function unit( int $id, string $name, ?string $lang = null ): string {
		return self::translate( 'unit_' . $id, $name, $lang );
	}

	public static function register_table_title( int $id, string $title ): void {
		self::register( 'table_' . $id . '_title', $title );
	}

	public static function table_title( int $id, string $title, ?string $lang = null ): string {
		return self::translate( 'table_' . $id . '_title', $title, $lang );
	}

	public static function register_note( int $row_id, string $note ): void {
		self::register( 'row_' . $row_id . '_note', $note, true );
	}

	public static function note( int $row_id, string $note, ?string $lang = null ): string {
		return self::translate( 'row_' . $row_id . '_note', $note, $lang );
	}

	public static function register_tag( string $type, string $slug ): void {
		self::register( $type . '_' . $slug, $slug );
	}

	public static function tag( string $type, string $slug, ?string $lang = null ): string {
		return self::translate( $type . '_' . $slug, $slug, $lang );
	}

	/* ---------------------------------------------------------------------
	 * Catalog registration
	 * ------------------------------------------------------------------- */

	/**
	 * Registers the whole content catalog, but only when it actually
	 * changed since the last run (cheap to call on every admin request).
	 * WPML persists registrations, while Polylang needs them present so
	 * the strings appear on its "Strings translations" screen.
	 *
	 * @param bool $force Re-register even if the signature is unchanged.
	 */
	public static function maybe_register_catalog( bool $force = false ): void {
		if ( ! self::is_active() ) {
			return;
		}

		$strings   = self::catalog_strings();
		$signature = md5( (string) wp_json_encode( wp_list_pluck( $strings, 'value' ) ) );

		if ( ! $force && get_option( self::SIGNATURE_OPTION ) === $signature ) {
			return;
		}

		foreach ( $strings as $string ) {
			self::register( $string['name'], $string['value'], $string['multiline'] ?? false );
		}

		update_option( self::SIGNATURE_OPTION, $signature, false );
	}

	/**
	 * Number of translatable content strings currently in the catalog.
	 */
	public static function catalog_count(): int {
		return count( self::catalog_strings() );
	}

	/**
	 * Builds the full list of translatable content strings from the DB.
	 * Every read is object-cached by its repository.
	 *
	 * @return array<int,array{name:string,value:string,multiline?:bool}>
	 */
	private static function catalog_strings(): array {
		$strings = [];

		foreach ( ( new IngredientRepository() )->all_with_usage() as $ingredient ) {
			$strings[] = [
				'name'  => 'ingredient_' . (int) $ingredient->id,
				'value' => (string) $ingredient->name,
			];
		}

		foreach ( ( new UnitRepository() )->all_with_usage() as $unit ) {
			$strings[] = [
				'name'  => 'unit_' . (int) $unit->id,
				'value' => (string) ( $unit->name ?? '' ),
			];
		}

		foreach ( ( new TableRepository() )->all() as $table ) {
			$strings[] = [
				'name'  => 'table_' . (int) $table->id . '_title',
				'value' => (string) ( $table->title ?? '' ),
			];
		}

		foreach ( ( new RowRepository() )->all_with_notes() as $row ) {
			$strings[] = [
				'name'      => 'row_' . (int) $row->id . '_note',
				'value'     => (string) ( $row->note ?? '' ),
				'multiline' => true,
			];
		}

		$tags = ( new TagRepository() )->distinct();

		foreach ( $tags as $type => $slugs ) {
			foreach ( $slugs as $slug ) {
				$strings[] = [
					'name'  => $type . '_' . $slug,
					'value' => (string) $slug,
				];
			}
		}

		return array_values( array_filter(
			$strings,
			static fn( array $string ): bool => '' !== trim( $string['value'] )
		) );
	}
}
