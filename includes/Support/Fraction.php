<?php
declare( strict_types=1 );

namespace VVKit\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Human-friendly quantity formatting. With the "fractions" option on,
 * 0.5 becomes ½ and 1.5 becomes 1 ½; quantities that do not map to a
 * kitchen-friendly fraction (denominator > 16) fall back to decimals.
 */
final class Fraction {

	private const GLYPHS = [
		'1/2' => '½',
		'1/3' => '⅓',
		'2/3' => '⅔',
		'1/4' => '¼',
		'3/4' => '¾',
		'1/5' => '⅕',
		'2/5' => '⅖',
		'3/5' => '⅗',
		'4/5' => '⅘',
		'1/6' => '⅙',
		'5/6' => '⅚',
		'1/8' => '⅛',
		'3/8' => '⅜',
		'5/8' => '⅝',
		'7/8' => '⅞',
	];

	public static function format( ?float $quantity ): string {
		if ( null === $quantity || $quantity <= 0 ) {
			return '';
		}

		if ( ! Options::fractions_enabled() ) {
			return self::decimal( $quantity );
		}

		$whole = (int) floor( $quantity + 1e-9 );
		$rest  = $quantity - $whole;

		if ( $rest < 1e-6 ) {
			return (string) $whole;
		}

		$ratio = self::ratio( $rest );

		if ( null === $ratio ) {
			return self::decimal( $quantity );
		}

		[ $numerator, $denominator ] = $ratio;

		$key      = $numerator . '/' . $denominator;
		$fraction = self::GLYPHS[ $key ] ?? $key;

		return $whole > 0 ? $whole . ' ' . $fraction : $fraction;
	}

	private static function decimal( float $quantity ): string {
		return rtrim( rtrim( number_format( $quantity, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * Continued-fraction expansion of 0 < $value < 1.
	 *
	 * @return array{0:int,1:int}|null [numerator, denominator], or null
	 *                                 when no kitchen-friendly fraction exists.
	 */
	private static function ratio( float $value, float $tolerance = 1.0e-6 ): ?array {
		$h1 = 1.0;
		$h2 = 0.0;
		$k1 = 0.0;
		$k2 = 1.0;
		$b  = 1 / $value;
		$i  = 0;

		do {
			if ( ++$i > 32 ) {
				return null;
			}

			$b = 1 / $b;
			$a = floor( $b );

			[ $h1, $h2 ] = [ $a * $h1 + $h2, $h1 ];
			[ $k1, $k2 ] = [ $a * $k1 + $k2, $k1 ];

			$b -= $a;

			if ( 0.0 === $b ) {
				break;
			}
		} while ( abs( $value - $h1 / $k1 ) > $value * $tolerance );

		if ( $k1 > 16 ) {
			return null;
		}

		return [ (int) $h1, (int) $k1 ];
	}
}
