<?php
declare( strict_types=1 );

namespace VVKit\Repository;

use VVKit\Support\Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access for vvkit_tables. Every query goes through $wpdb->prepare.
 */
class TableRepository {

	private const FORMATS = [
		'post_id'       => '%d',
		'title'         => '%s',
		'title_tagname' => '%s',
		'positions'       => '%s',
		'servings'        => '%d',
		'display_options' => '%s',
	];

	private \wpdb $db;

	public function __construct() {
		global $wpdb;

		$this->db = $wpdb;
	}

	private function table(): string {
		return $this->db->prefix . 'vvkit_tables';
	}

	public function find( int $id ): ?object {
		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->db->prefix}vvkit_tables WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return $row ?: null;
	}

	/**
	 * @return object[]
	 */
	public function for_post( int $post_id ): array {
		$cache_key = 'tables_post_' . $post_id;
		$cached    = Cache::get( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$results = $this->db->get_results(
			$this->db->prepare( "SELECT * FROM {$this->db->prefix}vvkit_tables WHERE post_id = %d ORDER BY id ASC", $post_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		Cache::set( $cache_key, $results );

		return $results;
	}

	public function insert( int $post_id, string $title, string $title_tagname ): ?object {
		$inserted = $this->db->insert(
			$this->table(),
			[
				'post_id'       => $post_id,
				'title'         => $title,
				'title_tagname' => $title_tagname,
				'positions'     => '',
			],
			[ '%d', '%s', '%s', '%s' ]
		);

		if ( ! $inserted ) {
			return null;
		}

		Cache::invalidate();

		return $this->find( (int) $this->db->insert_id );
	}

	/**
	 * @param array<string,mixed> $data Whitelisted by self::FORMATS.
	 */
	public function update( int $id, array $data ): bool {
		$data = array_intersect_key( $data, self::FORMATS );

		if ( ! $data ) {
			return true;
		}

		$formats = array_map( static fn( string $column ): string => self::FORMATS[ $column ], array_keys( $data ) );
		$updated = $this->db->update( $this->table(), $data, [ 'id' => $id ], $formats, [ '%d' ] );

		Cache::invalidate();

		return false !== $updated;
	}

	/**
	 * Deletes the table and, first, all of its ingredient rows.
	 */
	public function delete( int $id ): bool {
		( new RowRepository() )->delete_for_table( $id );

		$deleted = $this->db->delete( $this->table(), [ 'id' => $id ], [ '%d' ] );

		Cache::invalidate();

		return false !== $deleted && $deleted > 0;
	}
}
