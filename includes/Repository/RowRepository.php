<?php
declare( strict_types=1 );

namespace VVKit\Repository;

use VVKit\Support\Cache;
use VVKit\Support\Translator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access for vvkit_ingredient_table (the rows of each table),
 * always joined with ingredient and unit names.
 */
class RowRepository {

	private const FORMATS = [
		'ingredient_id' => '%d',
		'table_id'      => '%d',
		'quantity'      => '%f',
		'unit_id'       => '%d',
		'note'          => '%s',
		'position'      => '%d',
		'referral'      => '%s',
		'product_id'    => '%d',
	];

	private \wpdb $db;

	public function __construct() {
		global $wpdb;

		$this->db = $wpdb;
	}

	private function table(): string {
		return $this->db->prefix . 'vvkit_ingredient_table';
	}

	private function base_query(): string {
		return "SELECT it.*, i.name AS ingredient_name,
				u.name AS unit_name, u.value AS unit_factor, u.dimension AS unit_dimension, u.unit_system AS unit_system
			FROM {$this->db->prefix}vvkit_ingredient_table it
			LEFT JOIN {$this->db->prefix}vvkit_ingredients i ON it.ingredient_id = i.id
			LEFT JOIN {$this->db->prefix}vvkit_units u ON it.unit_id = u.id";
	}

	public function find( int $id ): ?object {
		$row = $this->db->get_row(
			$this->db->prepare( $this->base_query() . ' WHERE it.id = %d', $id ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		return $row ?: null;
	}

	/**
	 * All rows carrying a note (used to register translatable strings).
	 *
	 * @return object[]
	 */
	public function all_with_notes(): array {
		$cached = Cache::get( 'rows_with_notes' );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$results = $this->db->get_results(
			"SELECT id, note FROM {$this->db->prefix}vvkit_ingredient_table WHERE note IS NOT NULL AND note <> '' ORDER BY id ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- no user input.
		);

		Cache::set( 'rows_with_notes', $results );

		return $results;
	}

	/**
	 * @return object[]
	 */
	public function for_table( int $table_id ): array {
		$cache_key = 'rows_table_' . $table_id;
		$cached    = Cache::get( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$results = $this->db->get_results(
			$this->db->prepare( $this->base_query() . ' WHERE it.table_id = %d ORDER BY it.position ASC, it.id ASC', $table_id ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		Cache::set( $cache_key, $results );

		return $results;
	}

	/**
	 * @param array<string,mixed> $data Whitelisted by self::FORMATS; null values insert SQL NULL.
	 */
	public function insert( array $data ): ?object {
		$data = array_intersect_key( $data, self::FORMATS );

		$formats  = array_map( static fn( string $column ): string => self::FORMATS[ $column ], array_keys( $data ) );
		$inserted = $this->db->insert( $this->table(), $data, $formats );

		if ( ! $inserted ) {
			return null;
		}

		Cache::invalidate();

		$row = $this->find( (int) $this->db->insert_id );

		if ( $row && '' !== trim( (string) ( $data['note'] ?? '' ) ) ) {
			Translator::register_note( (int) $row->id, (string) $data['note'] );
		}

		return $row;
	}

	/**
	 * @param array<string,mixed> $data Whitelisted by self::FORMATS; null values store SQL NULL.
	 */
	public function update( int $id, array $data ): bool {
		$data = array_intersect_key( $data, self::FORMATS );

		if ( ! $data ) {
			return true;
		}

		$formats = array_map( static fn( string $column ): string => self::FORMATS[ $column ], array_keys( $data ) );
		$updated = $this->db->update( $this->table(), $data, [ 'id' => $id ], $formats, [ '%d' ] );

		Cache::invalidate();

		if ( isset( $data['note'] ) && '' !== trim( (string) $data['note'] ) ) {
			Translator::register_note( $id, (string) $data['note'] );
		}

		return false !== $updated;
	}

	public function delete( int $id ): bool {
		$deleted = $this->db->delete( $this->table(), [ 'id' => $id ], [ '%d' ] );

		Cache::invalidate();

		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Rewrites positions to match the given id order. Ids not belonging
	 * to the table are ignored.
	 *
	 * @param int[] $ids Row ids in the desired order.
	 */
	public function reorder( int $table_id, array $ids ): bool {
		$ok = true;

		foreach ( array_values( $ids ) as $position => $id ) {
			$updated = $this->db->update(
				$this->table(),
				[ 'position' => $position ],
				[
					'id'       => (int) $id,
					'table_id' => $table_id,
				],
				[ '%d' ],
				[ '%d', '%d' ]
			);

			$ok = $ok && false !== $updated;
		}

		Cache::invalidate();

		return $ok;
	}

	public function delete_for_table( int $table_id ): void {
		$this->db->delete( $this->table(), [ 'table_id' => $table_id ], [ '%d' ] );

		Cache::invalidate();
	}

	public function delete_for_ingredient( int $ingredient_id ): void {
		$this->db->delete( $this->table(), [ 'ingredient_id' => $ingredient_id ], [ '%d' ] );

		Cache::invalidate();
	}

	public function nullify_unit( int $unit_id ): void {
		$this->db->update( $this->table(), [ 'unit_id' => null ], [ 'unit_id' => $unit_id ], null, [ '%d' ] );

		Cache::invalidate();
	}
}
