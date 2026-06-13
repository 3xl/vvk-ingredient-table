<?php
declare( strict_types=1 );

namespace VVKit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin bootstrap: wires every module to its hooks.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	public function boot(): void {
		add_action( 'init', [ $this, 'load_textdomain' ] );

		// Multilingual content (WPML / Polylang). The REST `?lang=` param
		// lets headless consumers request a specific language; the admin
		// catalog registration keeps the String Translation UI in sync.
		add_filter( 'rest_pre_dispatch', [ $this, 'capture_rest_language' ], 10, 3 );

		if ( is_admin() ) {
			add_action( 'init', [ Support\Translator::class, 'maybe_register_catalog' ] );
		}

		Install::maybe_upgrade();

		( new Rest\TablesController() )->register();
		( new Rest\IngredientsController() )->register();
		( new Rest\UnitsController() )->register();
		( new Rest\ProductsController() )->register();
		( new Rest\ShoppingListController() )->register();
		( new Rest\PostFields() )->register();

		( new Blocks() )->register();
		( new Frontend\Frontend() )->register();

		if ( is_admin() ) {
			( new Admin\Admin() )->register();
		}
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'vvkit', false, dirname( plugin_basename( VVKIT_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Reads the `?lang=` REST parameter (any plugin endpoint or the core
	 * post endpoint that exposes `vvkit_tables`) and sets it as the target
	 * language for content translation. Does not change the dispatch flow.
	 *
	 * @param mixed            $result  Short-circuit response (passed through untouched).
	 * @param \WP_REST_Server  $server  REST server instance.
	 * @param \WP_REST_Request $request Current request.
	 */
	public function capture_rest_language( mixed $result, \WP_REST_Server $server, \WP_REST_Request $request ): mixed {
		$lang = $request->get_param( 'lang' );

		if ( is_string( $lang ) && '' !== $lang ) {
			Support\Translator::set_request_language( $lang );
		}

		return $result;
	}
}
