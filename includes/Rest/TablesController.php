<?php
declare( strict_types=1 );

namespace VVKit\Rest;

use VVKit\Repository\IngredientRepository;
use VVKit\Repository\RowRepository;
use VVKit\Repository\TableRepository;
use VVKit\Repository\UnitRepository;
use VVKit\Support\Options;
use VVKit\Support\Presenter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for ingredient tables and their rows.
 *
 * Mutations are authorized against the post the table belongs to
 * (current_user_can( 'edit_post', $post_id )), not just a generic role.
 */
class TablesController extends Controller {

	private TableRepository $tables;
	private RowRepository $rows;

	public function __construct() {
		$this->tables = new TableRepository();
		$this->rows   = new RowRepository();
	}

	public function register_routes(): void {
		register_rest_route( self::ROUTE_NAMESPACE, '/tables', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => [ $this, 'can_edit_request_post' ],
				'args'                => [ 'post_id' => $this->post_id_arg() ],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create' ],
				'permission_callback' => [ $this, 'can_edit_request_post' ],
				'args'                => [ 'post_id' => $this->post_id_arg() ],
			],
		] );

		register_rest_route( self::ROUTE_NAMESPACE, '/tables/(?P<id>\d+)', [
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update' ],
				'permission_callback' => [ $this, 'can_edit_table' ],
				'args'                => [
					'title'         => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'title_tagname' => [
						'type' => 'string',
						'enum' => Options::ALLOWED_TITLE_TAGS,
					],
					'positions'     => [
						'type'  => 'array',
						'items' => [
							'type' => 'integer',
							'enum' => [ -1, 1 ],
						],
					],
					'servings'      => [
						'type'    => [ 'integer', 'null' ],
						'minimum' => 1,
					],
					'display'       => [
						'type'                 => [ 'object', 'null' ],
						'properties'           => array_fill_keys(
							Options::DISPLAY_FEATURES,
							[ 'type' => [ 'boolean', 'null' ] ]
						),
						'additionalProperties' => false,
					],
				],
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete' ],
				'permission_callback' => [ $this, 'can_edit_table' ],
			],
		] );

		register_rest_route( self::ROUTE_NAMESPACE, '/tables/(?P<id>\d+)/rows', [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_row' ],
				'permission_callback' => [ $this, 'can_edit_table' ],
				'args'                => $this->row_args( true ),
			],
		] );

		register_rest_route( self::ROUTE_NAMESPACE, '/tables/(?P<id>\d+)/rows/order', [
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'reorder_rows' ],
				'permission_callback' => [ $this, 'can_edit_table' ],
				'args'                => [
					'ids' => [
						'type'     => 'array',
						'required' => true,
						'items'    => [ 'type' => 'integer' ],
					],
				],
			],
		] );

		register_rest_route( self::ROUTE_NAMESPACE, '/rows/(?P<id>\d+)', [
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_row' ],
				'permission_callback' => [ $this, 'can_edit_row' ],
				'args'                => $this->row_args( false ),
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_row' ],
				'permission_callback' => [ $this, 'can_edit_row' ],
			],
		] );
	}

	/* ---------------------------------------------------------------------
	 * Permission callbacks
	 * ------------------------------------------------------------------- */

	public function can_edit_request_post( \WP_REST_Request $request ): bool|\WP_Error {
		$post_id = absint( $request['post_id'] );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return $this->not_found();
		}

		return current_user_can( 'edit_post', $post_id ) ? true : $this->forbidden();
	}

	public function can_edit_table( \WP_REST_Request $request ): bool|\WP_Error {
		$table = $this->tables->find( absint( $request['id'] ) );

		if ( ! $table ) {
			return $this->not_found();
		}

		return current_user_can( 'edit_post', (int) $table->post_id ) ? true : $this->forbidden();
	}

	public function can_edit_row( \WP_REST_Request $request ): bool|\WP_Error {
		$row = $this->rows->find( absint( $request['id'] ) );

		if ( ! $row ) {
			return $this->not_found();
		}

		$table = $this->tables->find( (int) $row->table_id );

		if ( ! $table ) {
			return $this->not_found();
		}

		return current_user_can( 'edit_post', (int) $table->post_id ) ? true : $this->forbidden();
	}

	/* ---------------------------------------------------------------------
	 * Table callbacks
	 * ------------------------------------------------------------------- */

	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$tables = $this->tables->for_post( absint( $request['post_id'] ) );

		return rest_ensure_response( array_map(
			fn( object $table ): array => Presenter::table( $table, $this->rows->for_table( (int) $table->id ) ),
			$tables
		) );
	}

	public function create( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$table = $this->tables->insert(
			absint( $request['post_id'] ),
			Options::default_title(),
			Options::default_title_tagname()
		);

		if ( ! $table ) {
			return $this->db_error();
		}

		return rest_ensure_response( Presenter::table( $table, [] ) );
	}

	public function update( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id   = absint( $request['id'] );
		$data = [];

		if ( $request->has_param( 'title' ) ) {
			$data['title'] = (string) $request['title'];
		}

		if ( $request->has_param( 'title_tagname' ) ) {
			$data['title_tagname'] = Options::sanitize_tagname( (string) $request['title_tagname'] );
		}

		if ( $request->has_param( 'positions' ) ) {
			$data['positions'] = implode( ',', array_map( 'strval', (array) $request['positions'] ) );
		}

		if ( $request->has_param( 'servings' ) ) {
			$servings         = $request['servings'];
			$data['servings'] = null === $servings ? null : absint( $servings );
		}

		// Replace semantics: the payload is the full set of overrides;
		// keys set to true/false override the plugin defaults, anything
		// else means "inherit".
		if ( $request->has_param( 'display' ) ) {
			$overrides = [];

			foreach ( (array) ( $request['display'] ?? [] ) as $feature => $value ) {
				if ( in_array( $feature, Options::DISPLAY_FEATURES, true ) && is_bool( $value ) ) {
					$overrides[ $feature ] = $value;
				}
			}

			$data['display_options'] = $overrides ? wp_json_encode( $overrides ) : null;
		}

		if ( $data && ! $this->tables->update( $id, $data ) ) {
			return $this->db_error();
		}

		$table = $this->tables->find( $id );

		return rest_ensure_response( Presenter::table( $table, $this->rows->for_table( $id ) ) );
	}

	public function delete( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! $this->tables->delete( absint( $request['id'] ) ) ) {
			return $this->db_error();
		}

		return rest_ensure_response( [ 'deleted' => true ] );
	}

	/* ---------------------------------------------------------------------
	 * Row callbacks
	 * ------------------------------------------------------------------- */

	public function create_row( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$table_id      = absint( $request['id'] );
		$ingredient_id = absint( $request['ingredient_id'] );

		if ( ! ( new IngredientRepository() )->find( $ingredient_id ) ) {
			return new \WP_Error( 'vvkit_invalid_ingredient', __( 'The selected ingredient does not exist.', 'vvkit' ), [ 'status' => 400 ] );
		}

		$row = $this->rows->insert( [
			'table_id'      => $table_id,
			'ingredient_id' => $ingredient_id,
			'unit_id'       => $this->normalize_unit_id( $request ),
			'quantity'      => $this->normalize_quantity( $request ),
			'note'          => (string) ( $request['note'] ?? '' ),
			'referral'      => (string) ( $request['referral'] ?? '' ),
			'product_id'    => absint( $request['product_id'] ?? 0 ),
			'position'      => $request->has_param( 'position' )
				? absint( $request['position'] )
				: count( $this->rows->for_table( $table_id ) ),
		] );

		if ( ! $row ) {
			return $this->db_error();
		}

		return rest_ensure_response( Presenter::row( $row ) );
	}

	public function update_row( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id   = absint( $request['id'] );
		$data = [];

		if ( $request->has_param( 'ingredient_id' ) ) {
			$ingredient_id = absint( $request['ingredient_id'] );

			if ( ! ( new IngredientRepository() )->find( $ingredient_id ) ) {
				return new \WP_Error( 'vvkit_invalid_ingredient', __( 'The selected ingredient does not exist.', 'vvkit' ), [ 'status' => 400 ] );
			}

			$data['ingredient_id'] = $ingredient_id;
		}

		if ( $request->has_param( 'unit_id' ) ) {
			$data['unit_id'] = $this->normalize_unit_id( $request );
		}

		if ( $request->has_param( 'quantity' ) ) {
			$data['quantity'] = $this->normalize_quantity( $request );
		}

		if ( $request->has_param( 'note' ) ) {
			$data['note'] = (string) $request['note'];
		}

		if ( $request->has_param( 'referral' ) ) {
			$data['referral'] = (string) $request['referral'];
		}

		if ( $request->has_param( 'product_id' ) ) {
			$data['product_id'] = absint( $request['product_id'] ?? 0 );
		}

		if ( $request->has_param( 'position' ) ) {
			$data['position'] = absint( $request['position'] );
		}

		if ( $data && ! $this->rows->update( $id, $data ) ) {
			return $this->db_error();
		}

		return rest_ensure_response( Presenter::row( $this->rows->find( $id ) ) );
	}

	public function delete_row( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! $this->rows->delete( absint( $request['id'] ) ) ) {
			return $this->db_error();
		}

		return rest_ensure_response( [ 'deleted' => true ] );
	}

	public function reorder_rows( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$table_id = absint( $request['id'] );

		if ( ! $this->rows->reorder( $table_id, array_map( 'absint', (array) $request['ids'] ) ) ) {
			return $this->db_error();
		}

		return rest_ensure_response( array_map( [ Presenter::class, 'row' ], $this->rows->for_table( $table_id ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------- */

	private function post_id_arg(): array {
		return [
			'type'              => 'integer',
			'required'          => true,
			'sanitize_callback' => 'absint',
		];
	}

	private function row_args( bool $require_ingredient ): array {
		return [
			'ingredient_id' => [
				'type'     => 'integer',
				'minimum'  => 1,
				'required' => $require_ingredient,
			],
			'unit_id'       => [
				'type' => [ 'integer', 'null' ],
			],
			'quantity'      => [
				'type'    => [ 'number', 'null' ],
				'minimum' => 0,
			],
			'note'          => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'referral'      => [
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			],
			'product_id'    => [
				'type'    => [ 'integer', 'null' ],
				'minimum' => 0,
			],
			'position'      => [
				'type'    => 'integer',
				'minimum' => 0,
			],
		];
	}

	private function normalize_unit_id( \WP_REST_Request $request ): ?int {
		$unit_id = absint( $request['unit_id'] ?? 0 );

		if ( ! $unit_id ) {
			return null;
		}

		return ( new UnitRepository() )->find( $unit_id ) ? $unit_id : null;
	}

	private function normalize_quantity( \WP_REST_Request $request ): ?float {
		$quantity = $request['quantity'];

		return ( null === $quantity || '' === $quantity ) ? null : (float) $quantity;
	}
}
