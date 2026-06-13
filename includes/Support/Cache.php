<?php
declare( strict_types=1 );

namespace VVKit\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper over the WP object cache (persistent when Redis Object
 * Cache is active). Uses the core "last_changed" versioned-key pattern:
 * any write bumps the version, atomically invalidating every plugin key.
 */
final class Cache {

	private const GROUP = 'vvkit';

	public static function get( string $key ): mixed {
		return wp_cache_get( self::versioned( $key ), self::GROUP );
	}

	public static function set( string $key, mixed $value ): void {
		wp_cache_set( self::versioned( $key ), $value, self::GROUP, HOUR_IN_SECONDS );
	}

	public static function invalidate(): void {
		wp_cache_set( 'last_changed', microtime(), self::GROUP );
	}

	private static function versioned( string $key ): string {
		$version = wp_cache_get( 'last_changed', self::GROUP );

		if ( ! $version ) {
			$version = microtime();
			wp_cache_set( 'last_changed', $version, self::GROUP );
		}

		return $key . ':' . $version;
	}
}
