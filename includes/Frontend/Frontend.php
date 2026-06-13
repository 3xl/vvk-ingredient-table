<?php
declare( strict_types=1 );

namespace VVKit\Frontend;

use VVKit\Repository\RowRepository;
use VVKit\Repository\TableRepository;
use VVKit\Support\Options;
use VVKit\Support\Presenter;
use VVKit\Support\Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend rendering: [vvkit] shortcode, automatic placement before
 * and/or after the post content (also applied to REST `content.rendered`)
 * and schema.org Recipe JSON-LD.
 */
class Frontend {

	private TableRepository $tables;
	private RowRepository $rows;

	public function __construct() {
		$this->tables = new TableRepository();
		$this->rows   = new RowRepository();
	}

	public function register(): void {
		add_shortcode( 'vvkit', [ $this, 'shortcode' ] );
		add_filter( 'the_content', [ $this, 'inject_tables' ] );
		add_action( 'wp_head', [ $this, 'output_jsonld' ] );
	}

	/**
	 * Usage: [vvkit table_id='123']
	 */
	public function shortcode( array|string $atts ): string {
		$atts = shortcode_atts(
			[ 'table_id' => 0 ],
			array_change_key_case( (array) $atts, CASE_LOWER ),
			'vvkit'
		);

		$table = $this->tables->find( absint( $atts['table_id'] ) );

		return $table ? Renderer::table( $table ) : '';
	}

	public function inject_tables( string $content ): string {
		$post = get_post();

		if ( ! $post || ! in_array( $post->post_type, Options::post_types(), true ) ) {
			return $content;
		}

		$tables = $this->tables->for_post( (int) $post->ID );

		if ( ! $tables ) {
			return $content;
		}

		$before = '';
		$after  = '';

		foreach ( $tables as $table ) {
			$positions = explode( ',', (string) ( $table->positions ?? '' ) );

			if ( in_array( '-1', $positions, true ) ) {
				$before .= Renderer::table( $table );
			}

			if ( in_array( '1', $positions, true ) ) {
				$after .= Renderer::table( $table );
			}
		}

		return $before . $content . $after;
	}

	/**
	 * schema.org Recipe JSON-LD with recipeIngredient, recipeYield and
	 * NutritionInformation (when computable from the ingredient facts).
	 */
	public function output_jsonld(): void {
		if ( ! Options::jsonld_enabled() || ! is_singular( Options::post_types() ) ) {
			return;
		}

		$post = get_post();

		if ( ! $post ) {
			return;
		}

		$tables = $this->tables->for_post( (int) $post->ID );

		if ( ! $tables ) {
			return;
		}

		$ingredients = [];
		$servings    = null;
		$nutrition   = null;

		foreach ( $tables as $table ) {
			$data = Presenter::table( $table, $this->rows->for_table( (int) $table->id ) );

			foreach ( $data['rows'] as $row ) {
				$line = trim( $row['quantity_display'] . ' ' . $row['unit']['name'] );
				$line = trim( $line . ' ' . $row['ingredient']['name'] );

				if ( '' !== $row['note'] ) {
					$line .= ' (' . $row['note'] . ')';
				}

				$ingredients[] = $line;
			}

			if ( null === $servings && $data['servings'] ) {
				$servings = $data['servings'];
			}

			// Nutrition hidden on a table (per-table or global setting)
			// stays out of the structured data too.
			if ( null === $nutrition && $data['display']['nutrition'] && ! empty( $data['nutrition']['per_serving'] ) ) {
				$nutrition = $data['nutrition']['per_serving'];
			}
		}

		if ( ! $ingredients ) {
			return;
		}

		$jsonld = [
			'@context'         => 'https://schema.org',
			'@type'            => 'Recipe',
			'name'             => get_the_title( $post ),
			'datePublished'    => get_the_date( 'c', $post ),
			'recipeIngredient' => $ingredients,
		];

		$author = get_the_author_meta( 'display_name', (int) $post->post_author );

		if ( $author ) {
			$jsonld['author'] = [
				'@type' => 'Person',
				'name'  => $author,
			];
		}

		$image = get_the_post_thumbnail_url( $post, 'full' );

		if ( $image ) {
			$jsonld['image'] = $image;
		}

		if ( $servings ) {
			$jsonld['recipeYield'] = sprintf(
				/* translators: %d: number of servings. */
				_n( '%d serving', '%d servings', $servings, 'vvkit' ),
				$servings
			);
		}

		if ( $nutrition ) {
			$jsonld['nutrition'] = array_filter( [
				'@type'                 => 'NutritionInformation',
				'calories'              => isset( $nutrition['kcal'] ) ? $nutrition['kcal'] . ' calories' : null,
				'fatContent'            => isset( $nutrition['fat'] ) ? $nutrition['fat'] . ' g' : null,
				'saturatedFatContent'   => isset( $nutrition['saturated_fat'] ) ? $nutrition['saturated_fat'] . ' g' : null,
				'carbohydrateContent'   => isset( $nutrition['carbs'] ) ? $nutrition['carbs'] . ' g' : null,
				'sugarContent'          => isset( $nutrition['sugars'] ) ? $nutrition['sugars'] . ' g' : null,
				'proteinContent'        => isset( $nutrition['protein'] ) ? $nutrition['protein'] . ' g' : null,
				'fiberContent'          => isset( $nutrition['fiber'] ) ? $nutrition['fiber'] . ' g' : null,
				// schema.org expects sodium, not salt: salt(g) * 400 = sodium(mg).
				'sodiumContent'         => isset( $nutrition['salt'] ) ? round( $nutrition['salt'] * 400 ) . ' mg' : null,
			] );
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $jsonld ) . '</script>' . "\n";
	}
}
