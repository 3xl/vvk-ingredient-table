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
}
