<?php
declare( strict_types=1 );

namespace VVKit;

use VVKit\Support\Cache;
use VVKit\Support\Translator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-off catalog seeder: fills the units and ingredients tables with a
 * broad, cooking-blog-oriented starter set (English source strings) and,
 * when WPML / Polylang is active, pre-loads IT/FR/DE/ES translations for
 * the seeded items.
 *
 * Safe by design: each table is seeded ONLY when it is empty, so an
 * existing (v1-migrated or hand-curated) catalog is never touched. It is
 * triggered manually from Settings -> Catalog and is deliberately NOT
 * wired into activation/upgrade.
 *
 * Conversion model mirrors UnitRepository: `value` is the amount of the
 * base unit (g for mass, ml for volume) in one unit; count/descriptive
 * units (piece, pinch, clove...) carry no dimension/factor.
 */
final class Seeder {

	/** Languages whose translations ship with the seed data. */
	private const LANGUAGES = [ 'it', 'fr', 'de', 'es' ];

	/**
	 * Seeds both tables (each only when empty), pre-loads translations for
	 * what was inserted, and clears the cache.
	 *
	 * @return array{units:array{inserted:int,skipped:bool},ingredients:array{inserted:int,skipped:bool},translations:int}
	 */
	public static function run(): array {
		$units       = self::seed_units();
		$ingredients = self::seed_ingredients();

		$items        = array_merge( $units['items'], $ingredients['items'] );
		$translations = 0;

		if ( $items ) {
			Cache::invalidate();

			// Register first (WPML needs the string to exist before it can be
			// translated; Polylang picks the source up from the value).
			foreach ( $items as $item ) {
				Translator::register( $item['name'], $item['source'] );
			}

			$translations = Translator::import_translations( $items, self::LANGUAGES );
		}

		return [
			'units'        => [
				'inserted' => $units['inserted'],
				'skipped'  => $units['skipped'],
			],
			'ingredients'  => [
				'inserted' => $ingredients['inserted'],
				'skipped'  => $ingredients['skipped'],
			],
			'translations' => $translations,
		];
	}

	public static function units_count(): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}vvkit_units" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	public static function ingredients_count(): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}vvkit_ingredients" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * @return array{inserted:int,skipped:bool,items:array<int,array{name:string,source:string,translations:array<string,string>}>}
	 */
	private static function seed_units(): array {
		global $wpdb;

		if ( self::units_count() > 0 ) {
			return [
				'inserted' => 0,
				'skipped'  => true,
				'items'    => [],
			];
		}

		$table    = $wpdb->prefix . 'vvkit_units';
		$inserted = 0;
		$items    = [];

		foreach ( self::data()['units'] as $unit ) {
			// $wpdb->insert renders null values as SQL NULL regardless of the
			// format hint, so count/descriptive units stay unconverted.
			$ok = $wpdb->insert(
				$table,
				[
					'name'        => $unit['name'],
					'value'       => $unit['value'],
					'dimension'   => $unit['dimension'],
					'unit_system' => $unit['system'],
				],
				[ '%s', '%f', '%s', '%s' ]
			);

			if ( ! $ok ) {
				continue;
			}

			++$inserted;
			$items[] = [
				'name'         => 'unit_' . (int) $wpdb->insert_id,
				'source'       => (string) $unit['name'],
				'translations' => (array) ( $unit['t'] ?? [] ),
			];
		}

		return [
			'inserted' => $inserted,
			'skipped'  => false,
			'items'    => $items,
		];
	}

	/**
	 * @return array{inserted:int,skipped:bool,items:array<int,array{name:string,source:string,translations:array<string,string>}>}
	 */
	private static function seed_ingredients(): array {
		global $wpdb;

		if ( self::ingredients_count() > 0 ) {
			return [
				'inserted' => 0,
				'skipped'  => true,
				'items'    => [],
			];
		}

		$table   = $wpdb->prefix . 'vvkit_ingredients';
		$entries = self::data()['ingredients'];

		// Source names, de-duplicated, preserving the catalog order.
		$names = [];
		foreach ( $entries as $entry ) {
			$name = trim( (string) $entry['en'] );

			if ( '' !== $name ) {
				$names[ $name ] = true;
			}
		}
		$names = array_keys( $names );

		$inserted = 0;

		// Multi-row inserts in chunks: names are escaped via prepare (all %s,
		// no nullable columns), the placeholder count is fixed per chunk.
		foreach ( array_chunk( $names, 100 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '(%s)' ) );
			$sql          = "INSERT INTO {$table} (name) VALUES {$placeholders}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are fixed (%s) tuples.
			$affected     = $wpdb->query( $wpdb->prepare( $sql, $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

			if ( false !== $affected ) {
				$inserted += (int) $affected;
			}
		}

		// Map the freshly inserted names back to their ids (the table was
		// empty, so every row here is one we just created).
		$id_by_name = [];
		foreach ( $wpdb->get_results( "SELECT id, name FROM {$table}" ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			$id_by_name[ (string) $row->name ] = (int) $row->id;
		}

		$items = [];
		foreach ( $entries as $entry ) {
			$name = trim( (string) $entry['en'] );
			$id   = $id_by_name[ $name ] ?? 0;

			if ( ! $id ) {
				continue;
			}

			$items[] = [
				'name'         => 'ingredient_' . $id,
				'source'       => $name,
				'translations' => [
					'it' => (string) ( $entry['it'] ?? '' ),
					'fr' => (string) ( $entry['fr'] ?? '' ),
					'de' => (string) ( $entry['de'] ?? '' ),
					'es' => (string) ( $entry['es'] ?? '' ),
				],
			];
		}

		return [
			'inserted' => $inserted,
			'skipped'  => false,
			'items'    => $items,
		];
	}

	/**
	 * Loads the seed dataset (units + ingredients with translations).
	 *
	 * @return array{units:array<int,array<string,mixed>>,ingredients:array<int,array<string,string>>}
	 */
	private static function data(): array {
		return require VVKIT_PLUGIN_DIR . 'includes/data/seed.php';
	}
}
