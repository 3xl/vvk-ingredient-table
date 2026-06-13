<?php
declare( strict_types=1 );

namespace VVKit\Repository;

use VVKit\Support\Cache;
use VVKit\Support\Translator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access for vvkit_units.
 *
 * Conversion model (v2.1): `value` is the amount of the base unit
 * (g for mass, ml for volume) contained in 1 unit; `dimension` is
 * 'mass' or 'volume'; `unit_system` is 'metric' or 'imperial'.
 */
class UnitRepository {

	public const DIMENSIONS = [ 'mass', 'volume' ];
	public const SYSTEMS    = [ 'metric', 'imperial' ];
	public const BASE_UNITS = [
		'mass'   => 'g',
		'volume' => 'ml',
	];

	private const FORMATS = [
		'name'        => '%s',
		'value'       => '%f',
		'dimension'   => '%s',
		'unit_system' => '%s',
	];

	private \wpdb $db;

	public function __construct() {
		global $wpdb;

		$this->db = $wpdb;
	}

	private function table(): string {
		return $this->db->prefix . 'vvkit_units';
	}

	/**
	 * All units with the number of table rows using each one.
	 *
	 * @return object[]
	 */
	public function all_with_usage(): array {
		$cached = Cache::get( 'units_all' );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$results = $this->db->get_results(
			"SELECT u.*, COUNT(it.id) AS usage_count
			FROM {$this->db->prefix}vvkit_units u
			LEFT JOIN {$this->db->prefix}vvkit_ingredient_table it ON it.unit_id = u.id
			GROUP BY u.id
			ORDER BY u.name ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- no user input.
		);

		Cache::set( 'units_all', $results );

		return $results;
	}

	public function find( int $id ): ?object {
		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->db->prefix}vvkit_units WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return $row ?: null;
	}

	public function find_by_name( string $name ): ?object {
		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->db->prefix}vvkit_units WHERE name = %s", $name ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return $row ?: null;
	}

	/**
	 * @param array<string,mixed> $data Whitelisted by self::FORMATS; null values store SQL NULL.
	 */
	public function insert( array $data ): ?object {
		$data = array_intersect_key( $data, self::FORMATS );

		$formats  = array_map( static fn( string $column ): string => self::FORMATS[ $column ], array_keys( $data ) );
		$inserted = $this->db->insert( $this->table(), $data, $formats );

		if ( ! $inserted ) {
			return null;
		}

		Cache::invalidate();

		$unit = $this->find( (int) $this->db->insert_id );

		if ( $unit && isset( $data['name'] ) ) {
			Translator::register_unit( (int) $unit->id, (string) $data['name'] );
		}

		return $unit;
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

		if ( isset( $data['name'] ) ) {
			Translator::register_unit( $id, (string) $data['name'] );
		}

		return false !== $updated;
	}

	/**
	 * Deletes the unit; rows using it keep the ingredient but lose the unit.
	 */
	public function delete( int $id ): bool {
		( new RowRepository() )->nullify_unit( $id );

		$deleted = $this->db->delete( $this->table(), [ 'id' => $id ], [ '%d' ] );

		Cache::invalidate();

		return false !== $deleted && $deleted > 0;
	}
}
