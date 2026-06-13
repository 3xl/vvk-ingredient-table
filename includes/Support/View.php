<?php
declare( strict_types=1 );

namespace VVKit\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tiny template renderer. Replaces the vendored league/plates engine:
 * plain PHP templates, escaping done at the template level, and themes
 * can override any template by placing it in <theme>/vvkit/<name>.php.
 */
final class View {

	public static function render( string $template, array $data = [] ): string {
		$file = locate_template( 'vvkit/' . $template . '.php' );

		if ( ! $file ) {
			$file = VVKIT_PLUGIN_DIR . 'templates/' . $template . '.php';
		}

		if ( ! is_readable( $file ) ) {
			return '';
		}

		extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled, plugin-internal data only.

		ob_start();
		include $file;

		return (string) ob_get_clean();
	}
}
