<?php
declare( strict_types=1 );

namespace VVKit\Support;

use VVKit\Repository\TagRepository;
use VVKit\Repository\UnitRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shapes raw DB rows into the array structure shared by the REST API,
 * the admin UI and the frontend templates.
 */
final class Presenter {

	/**
	 * @param object $table Row from vvkit_tables.
	 * @param array  $rows  Joined rows from vvkit_ingredient_table.
	 */
	public static function table( object $table, array $rows ): array {
		$positions = array_values( array_map( 'intval', array_filter(
			explode( ',', (string) ( $table->positions ?? '' ) ),
			static fn( string $value ): bool => '' !== trim( $value )
		) ) );

		$servings       = null === ( $table->servings ?? null ) ? null : (int) $table->servings;
		$presented_rows = array_map( [ self::class, 'row' ], $rows );
		$overrides      = self::display_overrides( $table );

		[ $allergen_slugs, $diet_slugs ] = self::collect_tags( $presented_rows );

		// Tags are computed on stable slugs (union/intersection); only the
		// resulting labels are localized for display and headless output.
		$allergens = array_map(
			static fn( string $slug ): string => Translator::tag( 'allergen', $slug ),
			$allergen_slugs
		);
		$diets = array_map(
			static fn( string $slug ): string => Translator::tag( 'diet', $slug ),
			$diet_slugs
		);

		return [
			'id'                => (int) $table->id,
			'post_id'           => (int) $table->post_id,
			'title'             => Translator::table_title( (int) $table->id, (string) ( $table->title ?? '' ) ),
			'title_tagname'     => Options::sanitize_tagname( (string) ( $table->title_tagname ?? 'h2' ) ),
			'positions'         => $positions,
			'servings'          => $servings,
			'shortcode'         => sprintf( "[vvkit table_id='%d']", (int) $table->id ),
			'rows'              => $presented_rows,
			'allergens'         => $allergens,
			'diets'             => $diets,
			'nutrition'         => Nutrition::for_rows( $presented_rows, $servings ),
			// Resolved visibility of the extras: per-table override wins
			// over the plugin-wide defaults. Headless consumers should
			// honor these flags too.
			'display'           => array_merge( Options::display_defaults(), $overrides ),
			// Raw overrides (only the explicitly set keys), used by the
			// admin UI to show the Default/Show/Hide tri-state.
			'display_overrides' => (object) $overrides,
		];
	}

	/**
	 * @return array<string,bool> Only the explicitly overridden features.
	 */
	private static function display_overrides( object $table ): array {
		$decoded = json_decode( (string) ( $table->display_options ?? '' ), true );

		if ( ! is_array( $decoded ) ) {
			return [];
		}

		$overrides = [];

		foreach ( Options::DISPLAY_FEATURES as $feature ) {
			if ( isset( $decoded[ $feature ] ) && is_bool( $decoded[ $feature ] ) ) {
				$overrides[ $feature ] = $decoded[ $feature ];
			}
		}

		return $overrides;
	}

	/**
	 * @param object $row Joined row (includes ingredient/unit aliases from RowRepository).
	 */
	public static function row( object $row ): array {
		$quantity  = null === $row->quantity ? null : (float) $row->quantity;
		$factor    = null === ( $row->unit_factor ?? null ) ? null : (float) $row->unit_factor;
		$dimension = ( $row->unit_dimension ?? null ) ?: null;

		$base = null;

		if ( null !== $quantity && null !== $factor && $factor > 0 && $dimension && isset( UnitRepository::BASE_UNITS[ $dimension ] ) ) {
			$base = [
				'quantity' => round( $quantity * $factor, 3 ),
				'unit'     => UnitRepository::BASE_UNITS[ $dimension ],
			];
		}

		$row_id        = (int) $row->id;
		$unit_id       = (int) ( $row->unit_id ?? 0 );
		$ingredient_id = (int) $row->ingredient_id;

		return [
			'id'               => $row_id,
			'table_id'         => (int) $row->table_id,
			'position'         => (int) $row->position,
			'quantity'         => $quantity,
			'quantity_display' => Fraction::format( $quantity ),
			'unit'             => [
				'id'        => $unit_id,
				'name'      => Translator::unit( $unit_id, (string) ( $row->unit_name ?? '' ) ),
				'dimension' => $dimension,
				'system'    => ( $row->unit_system ?? null ) ?: null,
				'factor'    => $factor,
			],
			'base'             => $base,
			'ingredient'       => [
				'id'   => $ingredient_id,
				'name' => Translator::ingredient( $ingredient_id, (string) ( $row->ingredient_name ?? '' ) ),
			],
			'note'             => Translator::note( $row_id, (string) ( $row->note ?? '' ) ),
			'referral'         => (string) ( $row->referral ?? '' ),
			'product'          => self::product( (int) ( $row->product_id ?? 0 ) ),
		];
	}

	/**
	 * Resolves a linked e-commerce product (WooCommerce post type).
	 */
	private static function product( int $product_id ): ?array {
		if ( ! $product_id ) {
			return null;
		}

		$post = get_post( $product_id );

		if ( ! $post || 'product' !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		return [
			'id'   => $product_id,
			'name' => (string) $post->post_title,
			'url'  => (string) get_permalink( $post ),
		];
	}

	/**
	 * Allergens: union across ingredients. Diets: intersection — a recipe
	 * is e.g. vegan only when every ingredient is tagged vegan.
	 *
	 * @return array{0:string[],1:string[]}
	 */
	private static function collect_tags( array $presented_rows ): array {
		if ( ! $presented_rows ) {
			return [ [], [] ];
		}

		$map       = ( new TagRepository() )->all_map();
		$allergens = [];
		$diets     = null;

		foreach ( $presented_rows as $row ) {
			$tags = $map[ $row['ingredient']['id'] ] ?? [
				'allergen' => [],
				'diet'     => [],
			];

			$allergens = array_merge( $allergens, $tags['allergen'] );
			$diets     = null === $diets ? $tags['diet'] : array_intersect( $diets, $tags['diet'] );
		}

		sort( $allergens );

		return [ array_values( array_unique( $allergens ) ), array_values( $diets ?? [] ) ];
	}
}
