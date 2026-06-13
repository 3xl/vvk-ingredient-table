<?php
declare( strict_types=1 );

namespace VVKit\Repository;

use VVKit\Support\Cache;
use VVKit\Support\Translator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access for vvkit_ingredient_tags: allergens and diet tags per
 * ingredient. Satellite table — the v1 ingredients table is untouched.
 */
class TagRepository {

	public const TYPES = [ 'allergen', 'diet' ];

	private \wpdb $db;

	public function __construct() {
		global $wpdb;

		$this->db = $wpdb;
	}

	private function table(): string {
		return $this->db->prefix . 'vvkit_ingredient_tags';
	}

	/**
	 * Whole table as ingredient_id-indexed map: id => [ 'allergen' => [...], 'diet' => [...] ].
	 *
	 * @return array<int,array<string,string[]>>
	 */
	public function all_map(): array {
		$cached = Cache::get( 'tags_map' );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$map = [];

		foreach ( $this->db->get_results( "SELECT * FROM {$this->db->prefix}vvkit_ingredient_tags ORDER BY tag ASC" ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$ingredient_id = (int) $row->ingredient_id;
			$type          = (string) $row->type;

			if ( ! in_array( $type, self::TYPES, true ) ) {
				continue;
			}

			$map[ $ingredient_id ] ??= [
				'allergen' => [],
				'diet'     => [],
			];

			$map[ $ingredient_id ][ $type ][] = (string) $row->tag;
		}

		Cache::set( 'tags_map', $map );

		return $map;
	}

	/**
	 * Distinct tag slugs grouped by type (used to register translatable
	 * strings — the badge labels shown on the frontend).
	 *
	 * @return array<string,string[]> [ 'allergen' => [...], 'diet' => [...] ]
	 */
	public function distinct(): array {
		$out = [
			'allergen' => [],
			'diet'     => [],
		];

		foreach ( $this->all_map() as $tags ) {
			foreach ( self::TYPES as $type ) {
				foreach ( $tags[ $type ] as $slug ) {
					$out[ $type ][ $slug ] = true;
				}
			}
		}

		return [
			'allergen' => array_keys( $out['allergen'] ),
			'diet'     => array_keys( $out['diet'] ),
		];
	}

	/**
	 * @return array<string,string[]> [ 'allergen' => [...], 'diet' => [...] ]
	 */
	public function for_ingredient( int $ingredient_id ): array {
		return $this->all_map()[ $ingredient_id ] ?? [
			'allergen' => [],
			'diet'     => [],
		];
	}

	/**
	 * Replaces all tags of the given type for an ingredient.
	 *
	 * @param string[] $tags
	 */
	public function set( int $ingredient_id, string $type, array $tags ): void {
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return;
		}

		$this->db->delete(
			$this->table(),
			[
				'ingredient_id' => $ingredient_id,
				'type'          => $type,
			],
			[ '%d', '%s' ]
		);

		foreach ( array_unique( $tags ) as $tag ) {
			$this->db->insert(
				$this->table(),
				[
					'ingredient_id' => $ingredient_id,
					'tag'           => $tag,
					'type'          => $type,
				],
				[ '%d', '%s', '%s' ]
			);

			Translator::register_tag( $type, (string) $tag );
		}

		Cache::invalidate();
	}

	public function delete_for_ingredient( int $ingredient_id ): void {
		$this->db->delete( $this->table(), [ 'ingredient_id' => $ingredient_id ], [ '%d' ] );

		Cache::invalidate();
	}
}
