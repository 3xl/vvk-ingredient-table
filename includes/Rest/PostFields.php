<?php
declare( strict_types=1 );

namespace VVKit\Rest;

use VVKit\Repository\RowRepository;
use VVKit\Repository\TableRepository;
use VVKit\Support\Presenter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes the ingredient tables as a read-only `vvkit_tables` field on
 * the REST responses of the configured post types, so headless clients
 * (the NestJS API / Next.js frontends) get structured data instead of
 * having to parse the rendered HTML.
 */
class PostFields {

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_fields' ] );
	}

	public function register_fields(): void {
		foreach ( \VVKit\Support\Options::post_types() as $post_type ) {
			register_rest_field( $post_type, 'vvkit_tables', [
				'get_callback' => [ $this, 'get_tables' ],
				'schema'       => [
					'description' => __( 'Ingredient tables attached to the post.', 'vvkit' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit', 'embed' ],
					'readonly'    => true,
				],
			] );
		}
	}

	/**
	 * @param array $post Prepared post data (REST shape).
	 */
	public function get_tables( array $post ): array {
		$tables = ( new TableRepository() )->for_post( (int) $post['id'] );
		$rows   = new RowRepository();

		return array_map(
			static fn( object $table ): array => Presenter::table( $table, $rows->for_table( (int) $table->id ) ),
			$tables
		);
	}
}
