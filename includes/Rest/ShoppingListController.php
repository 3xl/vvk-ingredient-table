<?php
declare( strict_types=1 );

namespace VVKit\Rest;

use VVKit\Repository\RowRepository;
use VVKit\Repository\TableRepository;
use VVKit\Support\Options;
use VVKit\Support\Presenter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aggregated shopping list across one or more published recipes.
 *
 * Public read-only endpoint (the same data is already rendered on the
 * public recipe pages); only published posts of the configured post
 * types are considered.
 *
 *   GET /vvkit/v1/shopping-list?posts=12,34,56
 */
class ShoppingListController extends Controller {

	private TableRepository $tables;
	private RowRepository $rows;

	public function __construct() {
		$this->tables = new TableRepository();
		$this->rows   = new RowRepository();
	}

	public function register_routes(): void {
		register_rest_route( self::ROUTE_NAMESPACE, '/shopping-list', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'posts' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
		] );
	}

	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$post_ids = array_filter( array_map( 'absint', explode( ',', (string) $request['posts'] ) ) );
		$post_ids = array_values( array_unique( array_filter( $post_ids, [ $this, 'is_listable_post' ] ) ) );

		$items = [];

		foreach ( $post_ids as $post_id ) {
			foreach ( $this->tables->for_post( $post_id ) as $table ) {
				$data = Presenter::table( $table, $this->rows->for_table( (int) $table->id ) );

				foreach ( $data['rows'] as $row ) {
					$this->accumulate( $items, $row, $post_id );
				}
			}
		}

		usort( $items, static fn( array $a, array $b ): int => strcasecmp( $a['ingredient']['name'], $b['ingredient']['name'] ) );

		return rest_ensure_response( [
			'posts' => $post_ids,
			'items' => array_map( [ $this, 'finalize_item' ], $items ),
		] );
	}

	public function is_listable_post( int $post_id ): bool {
		$post = get_post( $post_id );

		return $post
			&& 'publish' === $post->post_status
			&& in_array( $post->post_type, Options::post_types(), true );
	}

	/**
	 * Groups quantities per ingredient and per unit; sums the normalized
	 * base amount (g/ml) when every quantified entry provides one.
	 */
	private function accumulate( array &$items, array $row, int $post_id ): void {
		$ingredient_id = $row['ingredient']['id'];

		$items[ $ingredient_id ] ??= [
			'ingredient'   => $row['ingredient'],
			'entries'      => [],
			'base'         => [],
			'base_missing' => false,
			'no_quantity'  => false,
			'posts'        => [],
		];

		$item = &$items[ $ingredient_id ];

		if ( ! in_array( $post_id, $item['posts'], true ) ) {
			$item['posts'][] = $post_id;
		}

		if ( null === $row['quantity'] ) {
			$item['no_quantity'] = true;

			return;
		}

		$unit_key = $row['unit']['id'];

		$item['entries'][ $unit_key ] ??= [
			'quantity' => 0.0,
			'unit'     => $row['unit']['name'],
		];

		$item['entries'][ $unit_key ]['quantity'] += $row['quantity'];

		if ( $row['base'] ) {
			$item['base'][ $row['base']['unit'] ] = ( $item['base'][ $row['base']['unit'] ] ?? 0.0 ) + $row['base']['quantity'];
		} else {
			$item['base_missing'] = true;
		}
	}

	private function finalize_item( array $item ): array {
		$base_total = null;

		// A meaningful base total needs every quantified entry normalized
		// to the same base unit.
		if ( ! $item['base_missing'] && 1 === count( $item['base'] ) ) {
			$unit       = array_key_first( $item['base'] );
			$base_total = [
				'quantity' => round( $item['base'][ $unit ], 2 ),
				'unit'     => $unit,
			];
		}

		return [
			'ingredient'  => $item['ingredient'],
			'entries'     => array_values( array_map(
				static fn( array $entry ): array => [
					'quantity' => round( $entry['quantity'], 3 ),
					'unit'     => $entry['unit'],
				],
				$item['entries']
			) ),
			'base_total'  => $base_total,
			'no_quantity' => $item['no_quantity'],
			'posts'       => $item['posts'],
		];
	}
}
