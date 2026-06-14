<?php
declare( strict_types=1 );

namespace VVKit\Admin;

use VVKit\Seeder;
use VVKit\Support\Options;
use VVKit\Support\Translator;

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
		add_action( 'admin_post_vvkit_i18n_sync', [ $this, 'handle_sync' ] );
		add_action( 'admin_post_vvkit_seed', [ $this, 'handle_seed' ] );
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
		add_settings_section( 'vvkit_i18n', __( 'Multilingual', 'vvkit' ), [ $this, 'section_i18n' ], self::PAGE_SLUG );
		add_settings_section( 'vvkit_catalog', __( 'Catalog', 'vvkit' ), [ $this, 'section_catalog' ], self::PAGE_SLUG );
		add_settings_section( 'vvkit_advanced', __( 'Advanced', 'vvkit' ), '__return_null', self::PAGE_SLUG );

		add_settings_field( 'vvkit_post_type', __( 'Post types', 'vvkit' ), [ $this, 'field_post_types' ], self::PAGE_SLUG, 'vvkit_content' );
		add_settings_field( 'vvkit_default_title', __( 'Default table title', 'vvkit' ), [ $this, 'field_default_title' ], self::PAGE_SLUG, 'vvkit_content' );
		add_settings_field( 'vvkit_default_title_tagname', __( 'Default title tag', 'vvkit' ), [ $this, 'field_title_tagname' ], self::PAGE_SLUG, 'vvkit_content' );

		add_settings_field( 'vvkit_css_classes', __( 'CSS classes', 'vvkit' ), [ $this, 'field_css_classes' ], self::PAGE_SLUG, 'vvkit_display' );
		add_settings_field( 'vvkit_fractions', __( 'Fractions', 'vvkit' ), [ $this, 'field_fractions' ], self::PAGE_SLUG, 'vvkit_display' );
		add_settings_field( 'vvkit_display_defaults', __( 'Table extras', 'vvkit' ), [ $this, 'field_display_defaults' ], self::PAGE_SLUG, 'vvkit_display' );
		add_settings_field( 'vvkit_jsonld', __( 'Recipe JSON-LD', 'vvkit' ), [ $this, 'field_jsonld' ], self::PAGE_SLUG, 'vvkit_display' );

		add_settings_field( 'vvkit_i18n_status', __( 'Translatable content', 'vvkit' ), [ $this, 'field_i18n_status' ], self::PAGE_SLUG, 'vvkit_i18n' );

		add_settings_field( 'vvkit_seed', __( 'Starter catalog', 'vvkit' ), [ $this, 'field_seed' ], self::PAGE_SLUG, 'vvkit_catalog' );

		add_settings_field( 'vvkit_delete_data_on_uninstall', __( 'Uninstall', 'vvkit' ), [ $this, 'field_delete_data' ], self::PAGE_SLUG, 'vvkit_advanced' );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php
			if ( isset( $_GET['vvkit_i18n'] ) && 'synced' === sanitize_key( wp_unslash( $_GET['vvkit_i18n'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					esc_html__( 'Translatable strings re-synced.', 'vvkit' )
				);
			}

			if ( isset( $_GET['vvkit_seeded_units'] ) || isset( $_GET['vvkit_seeded_ingredients'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
				$seeded_units       = isset( $_GET['vvkit_seeded_units'] ) ? absint( wp_unslash( $_GET['vvkit_seeded_units'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$seeded_ingredients = isset( $_GET['vvkit_seeded_ingredients'] ) ? absint( wp_unslash( $_GET['vvkit_seeded_ingredients'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

				$seeded_translations = isset( $_GET['vvkit_seeded_translations'] ) ? absint( wp_unslash( $_GET['vvkit_seeded_translations'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

				if ( $seeded_units || $seeded_ingredients ) {
					printf(
						'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
						esc_html( sprintf(
							/* translators: 1: number of units; 2: number of ingredients; 3: number of translation entries. */
							__( 'Seeded %1$d units and %2$d ingredients (%3$d translations pre-loaded).', 'vvkit' ),
							$seeded_units,
							$seeded_ingredients,
							$seeded_translations
						) )
					);
				} else {
					printf(
						'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
						esc_html__( 'Nothing seeded — the tables already contain data.', 'vvkit' )
					);
				}
			}
			?>
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

	public function section_i18n(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Translate the table content (ingredient and unit names, table titles, notes and allergen/diet labels) with WPML or Polylang. The plugin UI labels are translated separately through the language packs.', 'vvkit' )
		);
	}

	public function field_i18n_status(): void {
		$active = Translator::is_active();

		if ( ! $active ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'No multilingual plugin detected. Install and activate WPML or Polylang to translate the table content; without one the content is shown in its original language.', 'vvkit' )
			);

			return;
		}

		echo '<p>';
		printf(
			/* translators: 1: multilingual plugin name (WPML/Polylang); 2: active language code. */
			esc_html__( 'Detected: %1$s · current language: %2$s', 'vvkit' ),
			'<strong>' . esc_html( Translator::backend_label() ) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inline.
			'<code>' . esc_html( Translator::current_language() ) . '</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inline.
		);
		echo '</p>';

		$count = Translator::catalog_count();

		printf(
			'<p>%s</p>',
			sprintf(
				/* translators: %d: number of translatable strings. */
				esc_html( _n( '%d translatable string registered.', '%d translatable strings registered.', $count, 'vvkit' ) ),
				(int) $count
			)
		);

		$sync_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=vvkit_i18n_sync' ),
			'vvkit_i18n_sync'
		);

		printf(
			'<p><a href="%1$s" class="button button-secondary">%2$s</a></p>',
			esc_url( $sync_url ),
			esc_html__( 'Re-sync translatable strings', 'vvkit' )
		);
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Registers every current ingredient, unit, title, note and tag with the active multilingual plugin so they appear in its string translation screen. New and edited content is registered automatically.', 'vvkit' )
		);
	}

	/**
	 * Forces a full re-registration of the translatable content catalog.
	 */
	public function handle_sync(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'vvkit' ) );
		}

		check_admin_referer( 'vvkit_i18n_sync' );

		Translator::maybe_register_catalog( true );

		wp_safe_redirect( add_query_arg(
			[
				'page'       => self::PAGE_SLUG,
				'vvkit_i18n' => 'synced',
			],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	public function section_catalog(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Populate empty tables with a broad English starter set of units and ingredients (compiled from common cooking-blog usage), with Italian, French, German and Spanish translations pre-loaded into your multilingual plugin. Tables that already contain data are left untouched.', 'vvkit' )
		);
	}

	public function field_seed(): void {
		$units       = Seeder::units_count();
		$ingredients = Seeder::ingredients_count();

		echo '<p>';
		printf(
			/* translators: 1: number of units; 2: number of ingredients. */
			esc_html__( 'Current catalog: %1$d units, %2$d ingredients.', 'vvkit' ),
			(int) $units,
			(int) $ingredients
		);
		echo '</p>';

		$seed_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=vvkit_seed' ),
			'vvkit_seed'
		);

		printf(
			'<p><a href="%1$s" class="button button-secondary">%2$s</a></p>',
			esc_url( $seed_url ),
			esc_html__( 'Seed starter catalog', 'vvkit' )
		);

		if ( $units > 0 || $ingredients > 0 ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'A table is seeded only when empty, so the populated table(s) above will be skipped.', 'vvkit' )
			);
		} else {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'Both tables are empty and will be filled. IT/FR/DE/ES translations are pre-loaded for the languages configured in your multilingual plugin; refine them there afterwards.', 'vvkit' )
			);
		}
	}

	/**
	 * Seeds the empty catalog tables on demand.
	 */
	public function handle_seed(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'vvkit' ) );
		}

		check_admin_referer( 'vvkit_seed' );

		$result = Seeder::run();

		wp_safe_redirect( add_query_arg(
			[
				'page'                        => self::PAGE_SLUG,
				'vvkit_seeded_units'          => (int) $result['units']['inserted'],
				'vvkit_seeded_ingredients'    => (int) $result['ingredients']['inserted'],
				'vvkit_seeded_translations'   => (int) $result['translations'],
			],
			admin_url( 'admin.php' )
		) );
		exit;
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
