<?php
declare( strict_types=1 );

namespace VVKit\Support;

use VVKit\Repository\RowRepository;
use VVKit\Repository\UnitRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a table to frontend HTML. Shared by the shortcode, the
 * automatic content placement and the Gutenberg block.
 */
final class Renderer {

	public static function table( object $table ): string {
		$rows = ( new RowRepository() )->for_table( (int) $table->id );
		$data = Presenter::table( $table, $rows );

		/**
		 * Filters the table data before rendering.
		 *
		 * @param array  $data  Presented table data.
		 * @param object $table Raw DB row.
		 */
		$data = apply_filters( 'vvkit_table_data', $data, $table );

		self::enqueue_assets();

		return View::render( 'ingredients-table', [
			'table'        => $data,
			'unit_catalog' => self::unit_catalog(),
		] );
	}

	/**
	 * Units usable for client-side conversion (dimension + factor set),
	 * embedded in the markup for the progressive-enhancement script.
	 */
	private static function unit_catalog(): array {
		$units = ( new UnitRepository() )->all_with_usage();

		$catalog = array_map(
			static fn( object $unit ): array => [
				'id'        => (int) $unit->id,
				'name'      => Translator::unit( (int) $unit->id, (string) $unit->name ),
				'dimension' => ( $unit->dimension ?? null ) ?: null,
				'system'    => ( $unit->unit_system ?? null ) ?: null,
				'factor'    => null === ( $unit->value ?? null ) ? null : (float) $unit->value,
			],
			$units
		);

		return array_values( array_filter(
			$catalog,
			static fn( array $unit ): bool => null !== $unit['dimension'] && null !== $unit['factor'] && $unit['factor'] > 0
		) );
	}

	private static function enqueue_assets(): void {
		wp_enqueue_style( 'vvkit-public', VVKIT_PLUGIN_URL . 'assets/css/public.css', [], VVKIT_VERSION );
		wp_enqueue_script( 'vvkit-public', VVKIT_PLUGIN_URL . 'assets/js/public.js', [], VVKIT_VERSION, true );
	}
}
