<?php
declare( strict_types=1 );

namespace VVKit\Repository;

use VVKit\Support\Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access for vvkit_ingredient_nutrition: nutrition facts per 100 g
 * of ingredient. Satellite table — the v1 ingredients table is untouched.
 */
class NutritionRepository {

	public const FIELDS = [ 'kcal', 'fat', 'saturated_fat', 'carbs', 'sugars', 'protein', 'fiber', 'salt' ];

	private \wpdb $db;

	public function __construct() {
		global $wpdb;

		$this->db = $wpdb;
	}

	private function table(): string {
		return $this->db->prefix . 'vvkit_ingredient_nutrition';
	}

	/**
	 * Whole table as ingredient_id-indexed map (the catalog is small and
	 * the result is object-cached).
	 *
	 * @return array<int,object>
	 */
	public function map(): array {
		$cached = Cache::get( 'nutrition_map' );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$map = [];

		foreach ( $this->db->get_results( "SELECT * FROM {$this->db->prefix}vvkit_ingredient_nutrition" ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$map[ (int) $row->ingredient_id ] = $row;
		}

		Cache::set( 'nutrition_map', $map );

		return $map;
	}

	public function get( int $ingredient_id ): ?object {
		return $this->map()[ $ingredient_id ] ?? null;
	}

	/**
	 * @param array<string,float|null> $values Keyed by self::FIELDS.
	 */
	public function upsert( int $ingredient_id, array $values ): bool {
		$data = [ 'ingredient_id' => $ingredient_id ];

		foreach ( self::FIELDS as $field ) {
			$data[ $field ] = array_key_exists( $field, $values ) && null !== $values[ $field ]
				? (float) $values[ $field ]
				: null;
		}

		$this->db->delete( $this->table(), [ 'ingredient_id' => $ingredient_id ], [ '%d' ] );

		$inserted = $this->db->insert( $this->table(), $data );

		Cache::invalidate();

		return false !== $inserted;
	}

	public function delete( int $ingredient_id ): void {
		$this->db->delete( $this->table(), [ 'ingredient_id' => $ingredient_id ], [ '%d' ] );

		Cache::invalidate();
	}
}
