<?php
declare( strict_types=1 );

namespace VVKit\Repository;

use VVKit\Support\Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access for vvkit_ingredients.
 */
class IngredientRepository {

	private \wpdb $db;

	public function __construct() {
		global $wpdb;

		$this->db = $wpdb;
	}

	private function table(): string {
		return $this->db->prefix . 'vvkit_ingredients';
	}

	/**
	 * All ingredients with the number of table rows using each one.
	 *
	 * @return object[]
	 */
	public function all_with_usage(): array {
		$cached = Cache::get( 'ingredients_all' );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$results = $this->db->get_results(
			"SELECT i.*, COUNT(it.id) AS usage_count
			FROM {$this->db->prefix}vvkit_ingredients i
			LEFT JOIN {$this->db->prefix}vvkit_ingredient_table it ON it.ingredient_id = i.id
			GROUP BY i.id
			ORDER BY i.name ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- no user input.
		);

		Cache::set( 'ingredients_all', $results );

		return $results;
	}

	public function find( int $id ): ?object {
		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->db->prefix}vvkit_ingredients WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return $row ?: null;
	}

	public function find_by_name( string $name ): ?object {
		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->db->prefix}vvkit_ingredients WHERE name = %s", $name ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return $row ?: null;
	}

	public function insert( string $name ): ?object {
		$inserted = $this->db->insert( $this->table(), [ 'name' => $name ], [ '%s' ] );

		if ( ! $inserted ) {
			return null;
		}

		Cache::invalidate();

		return $this->find( (int) $this->db->insert_id );
	}

	public function update( int $id, string $name ): bool {
		$updated = $this->db->update( $this->table(), [ 'name' => $name ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );

		Cache::invalidate();

		return false !== $updated;
	}

	/**
	 * Deletes the ingredient, every table row referencing it and its
	 * satellite data (nutrition facts, tags).
	 */
	public function delete( int $id ): bool {
		( new RowRepository() )->delete_for_ingredient( $id );
		( new NutritionRepository() )->delete( $id );
		( new TagRepository() )->delete_for_ingredient( $id );

		$deleted = $this->db->delete( $this->table(), [ 'id' => $id ], [ '%d' ] );

		Cache::invalidate();

		return false !== $deleted && $deleted > 0;
	}
}
