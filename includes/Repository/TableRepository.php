<?php
declare( strict_types=1 );

namespace VVKit\Repository;

use VVKit\Support\Cache;
use VVKit\Support\Translator;

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
	 * All tables that have a title (used to register translatable strings).
	 *
	 * @return object[]
	 */
	public function all(): array {
		$cached = Cache::get( 'tables_all' );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$results = $this->db->get_results(
			"SELECT id, title FROM {$this->db->prefix}vvkit_tables WHERE title IS NOT NULL AND title <> '' ORDER BY id ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- no user input.
		);

		Cache::set( 'tables_all', $results );

		return $results;
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

		$table = $this->find( (int) $this->db->insert_id );

		if ( $table && '' !== trim( $title ) ) {
			Translator::register_table_title( (int) $table->id, $title );
		}

		return $table;
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

		if ( isset( $data['title'] ) && '' !== trim( (string) $data['title'] ) ) {
			Translator::register_table_title( $id, (string) $data['title'] );
		}

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
