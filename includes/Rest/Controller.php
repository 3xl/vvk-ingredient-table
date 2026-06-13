<?php
declare( strict_types=1 );

namespace VVKit\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared plumbing for the plugin REST controllers.
 *
 * Authentication relies on the standard REST cookie + X-WP-Nonce flow
 * (handled transparently by @wordpress/api-fetch in the admin UI);
 * authorization is enforced per route via permission callbacks.
 */
abstract class Controller {

	protected const ROUTE_NAMESPACE = 'vvkit/v1';

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	abstract public function register_routes(): void;

	public function can_edit_posts(): bool|\WP_Error {
		return current_user_can( 'edit_posts' ) ? true : $this->forbidden();
	}

	public function can_manage_options(): bool|\WP_Error {
		return current_user_can( 'manage_options' ) ? true : $this->forbidden();
	}

	protected function not_found(): \WP_Error {
		return new \WP_Error( 'vvkit_not_found', __( 'Resource not found.', 'vvkit' ), [ 'status' => 404 ] );
	}

	protected function forbidden(): \WP_Error {
		return new \WP_Error(
			'vvkit_forbidden',
			__( 'Sorry, you are not allowed to do that.', 'vvkit' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}

	protected function db_error(): \WP_Error {
		return new \WP_Error( 'vvkit_db_error', __( 'The database operation failed.', 'vvkit' ), [ 'status' => 500 ] );
	}

	protected function duplicate( string $message ): \WP_Error {
		return new \WP_Error( 'vvkit_duplicate', $message, [ 'status' => 409 ] );
	}

	/**
	 * Arg definition for a non-empty, sanitized name parameter.
	 */
	protected function name_arg(): array {
		return [
			'type'              => 'string',
			'required'          => true,
			'validate_callback' => static fn( $value ): bool => is_string( $value ) && '' !== trim( $value ),
			'sanitize_callback' => 'sanitize_text_field',
		];
	}
}
