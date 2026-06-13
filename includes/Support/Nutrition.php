<?php
declare( strict_types=1 );

namespace VVKit\Support;

use VVKit\Repository\NutritionRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes the nutrition facts of a table from the per-100g values of
 * its ingredients. Only rows whose quantity can be normalized to grams
 * (unit with dimension 'mass' and a conversion factor) contribute; the
 * coverage counters tell the consumer how complete the estimate is.
 */
final class Nutrition {

	/**
	 * @param array $rows     Presented rows (see Presenter::row()).
	 * @param ?int  $servings Servings of the table, for the per-serving breakdown.
	 */
	public static function for_rows( array $rows, ?int $servings ): ?array {
		$map        = ( new NutritionRepository() )->map();
		$total      = array_fill_keys( NutritionRepository::FIELDS, null );
		$computed   = 0;
		$quantified = 0;

		foreach ( $rows as $row ) {
			if ( null === $row['quantity'] ) {
				continue;
			}

			$quantified++;

			$base = $row['base'];

			if ( ! $base || 'g' !== $base['unit'] ) {
				continue;
			}

			$facts = $map[ $row['ingredient']['id'] ] ?? null;

			if ( ! $facts ) {
				continue;
			}

			$computed++;

			foreach ( NutritionRepository::FIELDS as $field ) {
				if ( null === $facts->$field ) {
					continue;
				}

				$total[ $field ] = ( $total[ $field ] ?? 0.0 ) + (float) $facts->$field * $base['quantity'] / 100;
			}
		}

		$total = array_filter( $total, static fn( ?float $value ): bool => null !== $value );

		if ( ! $computed || ! $total ) {
			return null;
		}

		$round = static fn( array $values ): array => array_map( static fn( float $value ): float => round( $value, 1 ), $values );

		return [
			'total'       => $round( $total ),
			'per_serving' => $servings ? $round( array_map( static fn( float $value ): float => $value / $servings, $total ) ) : null,
			'coverage'    => [
				'computed' => $computed,
				'rows'     => $quantified,
			],
		];
	}
}
