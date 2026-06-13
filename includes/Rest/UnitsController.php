<?php
declare( strict_types=1 );

namespace VVKit\Rest;

use VVKit\Repository\UnitRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for measurement units, including the conversion metadata
 * (factor to the base unit, dimension, metric/imperial system).
 */
class UnitsController extends Controller {

	private UnitRepository $units;

	public function __construct() {
		$this->units = new UnitRepository();
	}

	public function register_routes(): void {
		register_rest_route( self::ROUTE_NAMESPACE, '/units', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => [ $this, 'can_edit_posts' ],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create' ],
				'permission_callback' => [ $this, 'can_edit_posts' ],
				'args'                => array_merge( [ 'name' => $this->name_arg() ], $this->conversion_args() ),
			],
		] );

		register_rest_route( self::ROUTE_NAMESPACE, '/units/(?P<id>\d+)', [
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update' ],
				'permission_callback' => [ $this, 'can_manage_options' ],
				'args'                => array_merge( [ 'name' => $this->name_arg() ], $this->conversion_args() ),
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete' ],
				'permission_callback' => [ $this, 'can_manage_options' ],
			],
		] );
	}

	public function index(): \WP_REST_Response {
		return rest_ensure_response( array_map(
			[ $this, 'present' ],
			$this->units->all_with_usage()
		) );
	}

	public function create( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$name = trim( (string) $request['name'] );

		if ( $this->units->find_by_name( $name ) ) {
			return $this->duplicate( __( 'A unit with this name already exists.', 'vvkit' ) );
		}

		$unit = $this->units->insert( array_merge( [ 'name' => $name ], $this->conversion_data( $request ) ) );

		if ( ! $unit ) {
			return $this->db_error();
		}

		return rest_ensure_response( array_merge( $this->present( $unit ), [ 'usage' => 0 ] ) );
	}

	public function update( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id   = absint( $request['id'] );
		$name = trim( (string) $request['name'] );

		if ( ! $this->units->find( $id ) ) {
			return $this->not_found();
		}

		$existing = $this->units->find_by_name( $name );

		if ( $existing && (int) $existing->id !== $id ) {
			return $this->duplicate( __( 'A unit with this name already exists.', 'vvkit' ) );
		}

		if ( ! $this->units->update( $id, array_merge( [ 'name' => $name ], $this->conversion_data( $request ) ) ) ) {
			return $this->db_error();
		}

		return rest_ensure_response( $this->present( $this->units->find( $id ) ) );
	}

	public function delete( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = absint( $request['id'] );

		if ( ! $this->units->find( $id ) ) {
			return $this->not_found();
		}

		if ( ! $this->units->delete( $id ) ) {
			return $this->db_error();
		}

		return rest_ensure_response( [ 'deleted' => true ] );
	}

	private function present( object $unit ): array {
		$data = [
			'id'        => (int) $unit->id,
			'name'      => (string) $unit->name,
			'factor'    => null === ( $unit->value ?? null ) ? null : (float) $unit->value,
			'dimension' => ( $unit->dimension ?? null ) ?: null,
			'system'    => ( $unit->unit_system ?? null ) ?: null,
		];

		// Only known when the row comes from all_with_usage(): omitting it
		// lets the admin UI keep the previous count after an update.
		if ( isset( $unit->usage_count ) ) {
			$data['usage'] = (int) $unit->usage_count;
		}

		return $data;
	}

	private function conversion_args(): array {
		return [
			'factor'    => [
				'type'             => [ 'number', 'null' ],
				'exclusiveMinimum' => 0,
			],
			'dimension' => [
				'type' => [ 'string', 'null' ],
				'enum' => array_merge( UnitRepository::DIMENSIONS, [ null, '' ] ),
			],
			'system'    => [
				'type' => [ 'string', 'null' ],
				'enum' => array_merge( UnitRepository::SYSTEMS, [ null, '' ] ),
			],
		];
	}

	/**
	 * Maps the REST payload to DB columns. Dimension and factor go hand
	 * in hand: clearing the dimension also clears factor and system.
	 */
	private function conversion_data( \WP_REST_Request $request ): array {
		$data = [];

		if ( $request->has_param( 'factor' ) ) {
			$factor         = $request['factor'];
			$data['value']  = null === $factor || '' === $factor ? null : (float) $factor;
		}

		if ( $request->has_param( 'dimension' ) ) {
			$dimension         = (string) ( $request['dimension'] ?? '' );
			$data['dimension'] = in_array( $dimension, UnitRepository::DIMENSIONS, true ) ? $dimension : null;
		}

		if ( $request->has_param( 'system' ) ) {
			$system              = (string) ( $request['system'] ?? '' );
			$data['unit_system'] = in_array( $system, UnitRepository::SYSTEMS, true ) ? $system : null;
		}

		if ( array_key_exists( 'dimension', $data ) && null === $data['dimension'] ) {
			$data['value']       = null;
			$data['unit_system'] = null;
		}

		return $data;
	}
}
