<?php
declare( strict_types=1 );

namespace VVKit\Rest;

use VVKit\Repository\IngredientRepository;
use VVKit\Repository\NutritionRepository;
use VVKit\Repository\TagRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for the shared ingredients catalog, including nutrition facts
 * (per 100 g) and allergen/diet tags.
 *
 * Reading and creating is open to editors/authors; renaming, deleting
 * and editing catalog metadata require manage_options.
 */
class IngredientsController extends Controller {

	private IngredientRepository $ingredients;
	private NutritionRepository $nutrition;
	private TagRepository $tags;

	public function __construct() {
		$this->ingredients = new IngredientRepository();
		$this->nutrition   = new NutritionRepository();
		$this->tags        = new TagRepository();
	}

	public function register_routes(): void {
		register_rest_route( self::ROUTE_NAMESPACE, '/ingredients', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => [ $this, 'can_edit_posts' ],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create' ],
				'permission_callback' => [ $this, 'can_edit_posts' ],
				'args'                => [ 'name' => $this->name_arg() ],
			],
		] );

		register_rest_route( self::ROUTE_NAMESPACE, '/ingredients/(?P<id>\d+)', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'show' ],
				'permission_callback' => [ $this, 'can_edit_posts' ],
			],
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update' ],
				'permission_callback' => [ $this, 'can_manage_options' ],
				'args'                => $this->update_args(),
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete' ],
				'permission_callback' => [ $this, 'can_manage_options' ],
			],
		] );
	}

	public function index(): \WP_REST_Response {
		$tags_map      = $this->tags->all_map();
		$nutrition_ids = array_keys( $this->nutrition->map() );

		return rest_ensure_response( array_map(
			static function ( object $ingredient ) use ( $tags_map, $nutrition_ids ): array {
				$id   = (int) $ingredient->id;
				$tags = $tags_map[ $id ] ?? [
					'allergen' => [],
					'diet'     => [],
				];

				return [
					'id'            => $id,
					'name'          => (string) $ingredient->name,
					'usage'         => (int) ( $ingredient->usage_count ?? 0 ),
					'allergens'     => $tags['allergen'],
					'diets'         => $tags['diet'],
					'has_nutrition' => in_array( $id, $nutrition_ids, true ),
				];
			},
			$this->ingredients->all_with_usage()
		) );
	}

	public function show( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id         = absint( $request['id'] );
		$ingredient = $this->ingredients->find( $id );

		if ( ! $ingredient ) {
			return $this->not_found();
		}

		return rest_ensure_response( $this->present_full( $ingredient ) );
	}

	public function create( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$name = trim( (string) $request['name'] );

		if ( $this->ingredients->find_by_name( $name ) ) {
			return $this->duplicate( __( 'An ingredient with this name already exists.', 'vvkit' ) );
		}

		$ingredient = $this->ingredients->insert( $name );

		if ( ! $ingredient ) {
			return $this->db_error();
		}

		return rest_ensure_response( [
			'id'            => (int) $ingredient->id,
			'name'          => (string) $ingredient->name,
			'usage'         => 0,
			'allergens'     => [],
			'diets'         => [],
			'has_nutrition' => false,
		] );
	}

	public function update( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id         = absint( $request['id'] );
		$ingredient = $this->ingredients->find( $id );

		if ( ! $ingredient ) {
			return $this->not_found();
		}

		if ( $request->has_param( 'name' ) ) {
			$name = trim( (string) $request['name'] );

			if ( '' === $name ) {
				return new \WP_Error( 'vvkit_invalid_name', __( 'The name cannot be empty.', 'vvkit' ), [ 'status' => 400 ] );
			}

			$existing = $this->ingredients->find_by_name( $name );

			if ( $existing && (int) $existing->id !== $id ) {
				return $this->duplicate( __( 'An ingredient with this name already exists.', 'vvkit' ) );
			}

			if ( ! $this->ingredients->update( $id, $name ) ) {
				return $this->db_error();
			}
		}

		if ( $request->has_param( 'nutrition' ) ) {
			$values = $request['nutrition'];

			if ( null === $values || ! array_filter( (array) $values, static fn( $value ): bool => null !== $value && '' !== $value ) ) {
				$this->nutrition->delete( $id );
			} else {
				$this->nutrition->upsert( $id, $this->sanitize_nutrition( (array) $values ) );
			}
		}

		if ( $request->has_param( 'allergens' ) ) {
			$this->tags->set( $id, 'allergen', $this->sanitize_tags( $request['allergens'] ) );
		}

		if ( $request->has_param( 'diets' ) ) {
			$this->tags->set( $id, 'diet', $this->sanitize_tags( $request['diets'] ) );
		}

		return rest_ensure_response( $this->present_full( $this->ingredients->find( $id ) ) );
	}

	public function delete( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = absint( $request['id'] );

		if ( ! $this->ingredients->find( $id ) ) {
			return $this->not_found();
		}

		if ( ! $this->ingredients->delete( $id ) ) {
			return $this->db_error();
		}

		return rest_ensure_response( [ 'deleted' => true ] );
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------- */

	private function present_full( object $ingredient ): array {
		$id        = (int) $ingredient->id;
		$tags      = $this->tags->for_ingredient( $id );
		$facts     = $this->nutrition->get( $id );
		$nutrition = null;

		if ( $facts ) {
			$nutrition = [];

			foreach ( NutritionRepository::FIELDS as $field ) {
				$nutrition[ $field ] = null === $facts->$field ? null : (float) $facts->$field;
			}
		}

		return [
			'id'        => $id,
			'name'      => (string) $ingredient->name,
			'nutrition' => $nutrition,
			'allergens' => $tags['allergen'],
			'diets'     => $tags['diet'],
		];
	}

	private function update_args(): array {
		$nutrition_properties = [];

		foreach ( NutritionRepository::FIELDS as $field ) {
			$nutrition_properties[ $field ] = [
				'type'    => [ 'number', 'null' ],
				'minimum' => 0,
			];
		}

		return [
			'name'      => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'nutrition' => [
				'type'                 => [ 'object', 'null' ],
				'properties'           => $nutrition_properties,
				'additionalProperties' => false,
			],
			'allergens' => [
				'type'  => 'array',
				'items' => [ 'type' => 'string' ],
			],
			'diets'     => [
				'type'  => 'array',
				'items' => [ 'type' => 'string' ],
			],
		];
	}

	/**
	 * @return array<string,float|null>
	 */
	private function sanitize_nutrition( array $values ): array {
		$clean = [];

		foreach ( NutritionRepository::FIELDS as $field ) {
			$value           = $values[ $field ] ?? null;
			$clean[ $field ] = null === $value || '' === $value ? null : max( 0.0, (float) $value );
		}

		return $clean;
	}

	/**
	 * @return string[]
	 */
	private function sanitize_tags( mixed $tags ): array {
		$clean = array_map(
			static fn( $tag ): string => mb_substr( sanitize_text_field( (string) $tag ), 0, 32 ),
			(array) $tags
		);

		return array_values( array_unique( array_filter( $clean, static fn( string $tag ): bool => '' !== $tag ) ) );
	}
}
