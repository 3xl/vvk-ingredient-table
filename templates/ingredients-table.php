<?php
/**
 * Frontend template for a single ingredients table.
 *
 * Override it from a theme by creating: <theme>/vvkit/ingredients-table.php
 *
 * @var array $table        Presented table data (see \VVKit\Support\Presenter::table()).
 * @var array $unit_catalog Units usable for client-side conversion.
 */

use VVKit\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$vvkit_tag       = Options::sanitize_tagname( $table['title_tagname'] );
$vvkit_classes   = trim( Options::css_classes() . ' vvkit' );
$vvkit_display   = $table['display'];
$vvkit_nutrition = $table['display']['nutrition'] ? $table['nutrition'] : null;

$vvkit_show_servings = $table['servings'] && $vvkit_display['servings_switcher'];
$vvkit_show_units    = $vvkit_display['units_toggle'];

$vvkit_nutrition_labels = [
	'kcal'          => __( 'kcal', 'vvkit' ),
	'fat'           => __( 'fat', 'vvkit' ),
	'saturated_fat' => __( 'saturated fat', 'vvkit' ),
	'carbs'         => __( 'carbohydrates', 'vvkit' ),
	'sugars'        => __( 'sugars', 'vvkit' ),
	'protein'       => __( 'protein', 'vvkit' ),
	'fiber'         => __( 'fiber', 'vvkit' ),
	'salt'          => __( 'salt', 'vvkit' ),
];
?>
<div class="vvkit-wrap"
	data-fractions="<?php echo esc_attr( Options::fractions_enabled() ? '1' : '0' ); ?>"
	<?php if ( $vvkit_show_servings ) : ?>data-servings="<?php echo esc_attr( (string) $table['servings'] ); ?>"<?php endif; ?>
	data-units="<?php echo esc_attr( (string) wp_json_encode( $unit_catalog ?? [] ) ); ?>">

	<?php if ( '' !== $table['title'] ) : ?>
	<<?php echo $vvkit_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted h1-h6. ?> class="vvkit-title"><?php echo esc_html( $table['title'] ); ?></<?php echo $vvkit_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php endif; ?>

	<?php if ( $vvkit_show_servings || $vvkit_show_units ) : ?>
	<div class="vvkit-controls" hidden>
		<?php if ( $vvkit_show_servings ) : ?>
		<span class="vvkit-servings">
			<button type="button" class="vvkit-btn vvkit-servings-dec" aria-label="<?php esc_attr_e( 'Fewer servings', 'vvkit' ); ?>">&minus;</button>
			<span class="vvkit-servings-value"><?php echo esc_html( (string) ( $table['servings'] ?? '' ) ); ?></span>
			<?php esc_html_e( 'servings', 'vvkit' ); ?>
			<button type="button" class="vvkit-btn vvkit-servings-inc" aria-label="<?php esc_attr_e( 'More servings', 'vvkit' ); ?>">+</button>
		</span>
		<?php endif; ?>
		<?php if ( $vvkit_show_units ) : ?>
		<span class="vvkit-system" role="group" aria-label="<?php esc_attr_e( 'Units system', 'vvkit' ); ?>">
			<button type="button" class="vvkit-btn is-active" data-system=""><?php esc_html_e( 'Original', 'vvkit' ); ?></button>
			<button type="button" class="vvkit-btn" data-system="metric"><?php esc_html_e( 'Metric', 'vvkit' ); ?></button>
			<button type="button" class="vvkit-btn" data-system="imperial"><?php esc_html_e( 'Imperial', 'vvkit' ); ?></button>
		</span>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<table class="<?php echo esc_attr( $vvkit_classes ); ?>">
		<thead>
			<tr>
				<th class="vvkit-quantity-head"><?php esc_html_e( 'Quantity', 'vvkit' ); ?></th>
				<th class="vvkit-name-head"><?php esc_html_e( 'Ingredient', 'vvkit' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $table['rows'] as $vvkit_row ) : ?>
				<?php
				// Explicit referral link wins over the linked product URL.
				$vvkit_link = $vvkit_row['referral'];

				if ( '' === $vvkit_link && $vvkit_row['product'] && $vvkit_display['product_links'] ) {
					$vvkit_link = $vvkit_row['product']['url'];
				}
				?>
				<tr>
					<td class="vvkit-quantity-value">
						<span class="vvkit-qty"
							data-quantity="<?php echo esc_attr( null === $vvkit_row['quantity'] ? '' : (string) $vvkit_row['quantity'] ); ?>"
							data-unit-id="<?php echo esc_attr( (string) $vvkit_row['unit']['id'] ); ?>"><?php echo esc_html( $vvkit_row['quantity_display'] ); ?></span>
						<span class="vvkit-unit-name"><?php echo esc_html( $vvkit_row['unit']['name'] ); ?></span>
					</td>
					<td class="vvkit-name-value">
						<?php if ( '' !== $vvkit_link ) : ?>
							<a href="<?php echo esc_url( $vvkit_link ); ?>" <?php echo '' !== $vvkit_row['referral'] ? 'target="_blank" rel="noopener noreferrer"' : 'class="vvkit-product-link"'; ?>><?php echo esc_html( $vvkit_row['ingredient']['name'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $vvkit_row['ingredient']['name'] ); ?>
						<?php endif; ?>
						<?php if ( '' !== $vvkit_row['note'] ) : ?>
							<span class="vvkit-note"><?php echo esc_html( $vvkit_row['note'] ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $table['allergens'] && $vvkit_display['allergens'] ) : ?>
		<p class="vvkit-allergens">
			<strong><?php esc_html_e( 'Allergens:', 'vvkit' ); ?></strong>
			<?php foreach ( $table['allergens'] as $vvkit_allergen ) : ?>
				<span class="vvkit-badge vvkit-badge--allergen"><?php echo esc_html( $vvkit_allergen ); ?></span>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>

	<?php if ( $table['diets'] && $vvkit_display['diets'] ) : ?>
		<p class="vvkit-diets">
			<?php foreach ( $table['diets'] as $vvkit_diet ) : ?>
				<span class="vvkit-badge vvkit-badge--diet"><?php echo esc_html( $vvkit_diet ); ?></span>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>

	<?php if ( $vvkit_nutrition ) : ?>
		<?php
		$vvkit_values = $vvkit_nutrition['per_serving'] ?? $vvkit_nutrition['total'];
		$vvkit_parts  = [];

		foreach ( $vvkit_values as $vvkit_field => $vvkit_value ) {
			$vvkit_parts[] = 'kcal' === $vvkit_field
				? $vvkit_value . ' ' . $vvkit_nutrition_labels['kcal']
				: $vvkit_nutrition_labels[ $vvkit_field ] . ' ' . $vvkit_value . ' g';
		}
		?>
		<p class="vvkit-nutrition">
			<strong><?php echo esc_html( $vvkit_nutrition['per_serving'] ? __( 'Nutrition per serving:', 'vvkit' ) : __( 'Nutrition (total):', 'vvkit' ) ); ?></strong>
			<?php echo esc_html( implode( ' · ', $vvkit_parts ) ); ?>
			<?php if ( $vvkit_nutrition['coverage']['computed'] < $vvkit_nutrition['coverage']['rows'] ) : ?>
				<em class="vvkit-nutrition-partial"><?php esc_html_e( '(estimate based on part of the ingredients)', 'vvkit' ); ?></em>
			<?php endif; ?>
		</p>
	<?php endif; ?>
</div>
