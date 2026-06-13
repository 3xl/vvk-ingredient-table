<?php
declare( strict_types=1 );

namespace VVKit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal PSR-4 autoloader for the VVKit namespace (maps to includes/).
 */
final class Autoloader {

	public static function register(): void {
		spl_autoload_register( [ self::class, 'autoload' ] );
	}

	public static function autoload( string $class ): void {
		if ( ! str_starts_with( $class, __NAMESPACE__ . '\\' ) ) {
			return;
		}

		$relative = substr( $class, strlen( __NAMESPACE__ ) + 1 );
		$path     = VVKIT_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
