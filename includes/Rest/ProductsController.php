<?php
declare( strict_types=1 );

namespace VVKit\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only product lookup used to link ingredient rows to e-commerce
 * products. Works with any plugin registering a public 'product' post
 * type (WooCommerce); reports availability so the UI can hide the field
 * when no e-commerce stack is installed.
 */
class ProductsController extends Controller {

	public function register_routes(): void {
		register_rest_route( self::ROUTE_NAMESPACE, '/products', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => [ $this, 'can_edit_posts' ],
				'args'                => [
					'search' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
		] );
	}

	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! post_type_exists( 'product' ) ) {
			return rest_ensure_response( [
				'available' => false,
				'products'  => [],
			] );
		}

		$query = new \WP_Query( [
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => 200,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			's'                      => (string) ( $request['search'] ?? '' ),
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] );

		$products = array_map(
			static fn( \WP_Post $post ): array => [
				'id'   => (int) $post->ID,
				'name' => (string) $post->post_title,
			],
			$query->posts
		);

		return rest_ensure_response( [
			'available' => true,
			'products'  => $products,
		] );
	}
}
