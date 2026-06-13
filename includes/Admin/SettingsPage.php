<?php
declare( strict_types=1 );

namespace VVKit\Admin;

use VVKit\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin settings, implemented with the core Settings API: nonce,
 * capability checks and persistence are handled by options.php, each
 * option has an explicit sanitize callback.
 */
class SettingsPage {

	private const OPTION_GROUP = 'vvkit';
	private const PAGE_SLUG    = 'vvkit-settings';

	public function register(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function register_settings(): void {
		register_setting( self::OPTION_GROUP, 'vvkit_post_type', [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_post_types' ],
			'default'           => [ 'post' ],
		] );

		register_setting( self::OPTION_GROUP, 'vvkit_default_title', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'Ingredients',
		] );

		register_setting( self::OPTION_GROUP, 'vvkit_default_title_tagname', [
			'type'              => 'string',
			'sanitize_callback' => [ Options::class, 'sanitize_tagname' ],
			'default'           => 'h2',
		] );

		register_setting( self::OPTION_GROUP, 'vvkit_css_classes', [
			'type'              => 'string',
			'sanitize_callback' => [ $this, 'sanitize_css_classes' ],
			'default'           => 'table',
		] );

		register_setting( self::OPTION_GROUP, 'vvkit_fractions', [
			'type'              => 'string',
			'sanitize_callback' => [ $this, 'sanitize_checkbox' ],
			'default'           => '1',
		] );

		register_setting( self::OPTION_GROUP, 'vvkit_display_defaults', [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_display_defaults' ],
			'default'           => Options::DISPLAY_FEATURES,
		] );

		register_setting( self::OPTION_GROUP, 'vvkit_jsonld', [
			'type'              => 'string',
			'sanitize_callback' => [ $this, 'sanitize_checkbox' ],
			'default'           => '1',
		] );

		register_setting( self::OPTION_GROUP, 'vvkit_delete_data_on_uninstall', [
			'type'              => 'string',
			'sanitize_callback' => [ $this, 'sanitize_checkbox' ],
			'default'           => '0',
		] );

		add_settings_section( 'vvkit_content', __( 'Content', 'vvkit' ), '__return_null', self::PAGE_SLUG );
		add_settings_section( 'vvkit_display', __( 'Display', 'vvkit' ), '__return_null', self::PAGE_SLUG );
		add_settings_section( 'vvkit_advanced', __( 'Advanced', 'vvkit' ), '__return_null', self::PAGE_SLUG );

		add_settings_field( 'vvkit_post_type', __( 'Post types', 'vvkit' ), [ $this, 'field_post_types' ], self::PAGE_SLUG, 'vvkit_content' );
		add_settings_field( 'vvkit_default_title', __( 'Default table title', 'vvkit' ), [ $this, 'field_default_title' ], self::PAGE_SLUG, 'vvkit_content' );
		add_settings_field( 'vvkit_default_title_tagname', __( 'Default title tag', 'vvkit' ), [ $this, 'field_title_tagname' ], self::PAGE_SLUG, 'vvkit_content' );

		add_settings_field( 'vvkit_css_classes', __( 'CSS classes', 'vvkit' ), [ $this, 'field_css_classes' ], self::PAGE_SLUG, 'vvkit_display' );
		add_settings_field( 'vvkit_fractions', __( 'Fractions', 'vvkit' ), [ $this, 'field_fractions' ], self::PAGE_SLUG, 'vvkit_display' );
		add_settings_field( 'vvkit_display_defaults', __( 'Table extras', 'vvkit' ), [ $this, 'field_display_defaults' ], self::PAGE_SLUG, 'vvkit_display' );
		add_settings_field( 'vvkit_jsonld', __( 'Recipe JSON-LD', 'vvkit' ), [ $this, 'field_jsonld' ], self::PAGE_SLUG, 'vvkit_display' );

		add_settings_field( 'vvkit_delete_data_on_uninstall', __( 'Uninstall', 'vvkit' ), [ $this, 'field_delete_data' ], self::PAGE_SLUG, 'vvkit_advanced' );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Field renderers
	 * ------------------------------------------------------------------- */

	public function field_post_types(): void {
		$selected   = Options::post_types();
		$post_types = get_post_types( [ 'public' => true ], 'objects' );

		unset( $post_types['attachment'] );

		echo '<fieldset>';

		foreach ( $post_types as $post_type ) {
			printf(
				'<label style="display:block;margin-bottom:4px"><input type="checkbox" name="vvkit_post_type[]" value="%s" %s> %s <code>%s</code></label>',
				esc_attr( $post_type->name ),
				checked( in_array( $post_type->name, $selected, true ), true, false ),
				esc_html( $post_type->labels->singular_name ),
				esc_html( $post_type->name )
			);
		}

		echo '</fieldset>';
		printf( '<p class="description">%s</p>', esc_html__( 'Post types where the ingredient tables metabox and the automatic placement are enabled.', 'vvkit' ) );
	}

	public function field_default_title(): void {
		printf(
			'<input type="text" class="regular-text" name="vvkit_default_title" value="%s">',
			esc_attr( Options::default_title() )
		);
		printf( '<p class="description">%s</p>', esc_html__( 'Title assigned to newly created tables.', 'vvkit' ) );
	}

	public function field_title_tagname(): void {
		echo '<select name="vvkit_default_title_tagname">';

		foreach ( Options::ALLOWED_TITLE_TAGS as $tag ) {
			printf(
				'<option value="%1$s" %2$s>%1$s</option>',
				esc_attr( $tag ),
				selected( Options::default_title_tagname(), $tag, false )
			);
		}

		echo '</select>';
		printf( '<p class="description">%s</p>', esc_html__( 'Heading tag used to render the table title on the frontend.', 'vvkit' ) );
	}

	public function field_css_classes(): void {
		printf(
			'<input type="text" class="regular-text" name="vvkit_css_classes" value="%s">',
			esc_attr( Options::css_classes() )
		);
		printf( '<p class="description">%s</p>', esc_html__( 'Space-separated CSS classes added to the rendered table (the vvkit class is always present).', 'vvkit' ) );
	}

	public function field_fractions(): void {
		printf(
			'<label><input type="checkbox" name="vvkit_fractions" value="1" %s> %s</label>',
			checked( Options::fractions_enabled(), true, false ),
			esc_html__( 'Render quantities as kitchen fractions (e.g. 0.5 becomes ½, 1.5 becomes 1 ½).', 'vvkit' )
		);
	}

	public function field_display_defaults(): void {
		$defaults = Options::display_defaults();

		echo '<fieldset>';

		foreach ( self::display_feature_labels() as $feature => $label ) {
			printf(
				'<label style="display:block;margin-bottom:4px"><input type="checkbox" name="vvkit_display_defaults[]" value="%s" %s> %s</label>',
				esc_attr( $feature ),
				checked( $defaults[ $feature ], true, false ),
				esc_html( $label )
			);
		}

		echo '</fieldset>';
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Default visibility of the table extras on the frontend. Each table can override these from the post editor.', 'vvkit' )
		);
	}

	/**
	 * @return array<string,string> Feature key => translated label.
	 */
	public static function display_feature_labels(): array {
		return [
			'servings_switcher' => __( 'Servings switcher', 'vvkit' ),
			'units_toggle'      => __( 'Units toggle', 'vvkit' ),
			'allergens'         => __( 'Allergen badges', 'vvkit' ),
			'diets'             => __( 'Diet badges', 'vvkit' ),
			'nutrition'         => __( 'Nutrition facts', 'vvkit' ),
			'product_links'     => __( 'Product links', 'vvkit' ),
		];
	}

	public function field_jsonld(): void {
		printf(
			'<label><input type="checkbox" name="vvkit_jsonld" value="1" %s> %s</label>',
			checked( Options::jsonld_enabled(), true, false ),
			esc_html__( 'Output schema.org Recipe structured data (recipeIngredient, servings, nutrition) on posts with tables.', 'vvkit' )
		);
	}

	public function field_delete_data(): void {
		printf(
			'<label><input type="checkbox" name="vvkit_delete_data_on_uninstall" value="1" %s> %s</label>',
			checked( Options::delete_data_on_uninstall(), true, false ),
			esc_html__( 'Delete all plugin data (tables, ingredients, units, settings) when the plugin is uninstalled.', 'vvkit' )
		);
		printf( '<p class="description">%s</p>', esc_html__( 'Leave unchecked to keep your data for a future reinstall.', 'vvkit' ) );
	}

	/* ---------------------------------------------------------------------
	 * Sanitizers
	 * ------------------------------------------------------------------- */

	public function sanitize_post_types( mixed $value ): array {
		$public = get_post_types( [ 'public' => true ] );

		$value = array_values( array_intersect( array_map( 'sanitize_key', (array) $value ), $public ) );

		return $value ?: [ 'post' ];
	}

	public function sanitize_display_defaults( mixed $value ): array {
		return array_values( array_intersect( array_map( 'sanitize_key', (array) $value ), Options::DISPLAY_FEATURES ) );
	}

	public function sanitize_css_classes( mixed $value ): string {
		$classes = preg_split( '/\s+/', trim( (string) $value ) ) ?: [];
		$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );

		return implode( ' ', $classes );
	}

	public function sanitize_checkbox( mixed $value ): string {
		return $value ? '1' : '0';
	}
}
