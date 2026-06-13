<?php
declare( strict_types=1 );

namespace VVKit;

use VVKit\Support\Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-off catalog seeder: fills the units and ingredients tables with a
 * broad, cooking-blog-oriented starter set (English source strings; other
 * languages are added afterwards as WPML / Polylang translations).
 *
 * Safe by design: each table is seeded ONLY when it is empty, so an
 * existing (v1-migrated or hand-curated) catalog is never touched. It is
 * triggered manually from Settings -> Catalog and is deliberately NOT
 * wired into activation/upgrade.
 *
 * Conversion model mirrors UnitRepository: `value` is the amount of the
 * base unit (g for mass, ml for volume) in one unit; count/descriptive
 * units (piece, pinch, clove...) carry no dimension/factor.
 */
final class Seeder {

	/**
	 * Seeds both tables (each only when empty) and clears the cache when
	 * anything was inserted.
	 *
	 * @return array{units:array{inserted:int,skipped:bool},ingredients:array{inserted:int,skipped:bool}}
	 */
	public static function run(): array {
		$result = [
			'units'       => self::seed_units(),
			'ingredients' => self::seed_ingredients(),
		];

		if ( $result['units']['inserted'] || $result['ingredients']['inserted'] ) {
			Cache::invalidate();
		}

		return $result;
	}

	public static function units_count(): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}vvkit_units" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	public static function ingredients_count(): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}vvkit_ingredients" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * @return array{inserted:int,skipped:bool}
	 */
	private static function seed_units(): array {
		global $wpdb;

		if ( self::units_count() > 0 ) {
			return [
				'inserted' => 0,
				'skipped'  => true,
			];
		}

		$table    = $wpdb->prefix . 'vvkit_units';
		$inserted = 0;

		foreach ( self::units() as $unit ) {
			// $wpdb->insert renders null values as SQL NULL regardless of
			// the format hint, so count/descriptive units stay unconverted.
			$ok = $wpdb->insert(
				$table,
				[
					'name'        => $unit['name'],
					'value'       => $unit['value'],
					'dimension'   => $unit['dimension'],
					'unit_system' => $unit['system'],
				],
				[ '%s', '%f', '%s', '%s' ]
			);

			if ( $ok ) {
				++$inserted;
			}
		}

		return [
			'inserted' => $inserted,
			'skipped'  => false,
		];
	}

	/**
	 * @return array{inserted:int,skipped:bool}
	 */
	private static function seed_ingredients(): array {
		global $wpdb;

		if ( self::ingredients_count() > 0 ) {
			return [
				'inserted' => 0,
				'skipped'  => true,
			];
		}

		$table    = $wpdb->prefix . 'vvkit_ingredients';
		$names    = array_values( array_unique( array_filter( array_map( 'trim', self::ingredients() ) ) ) );
		$inserted = 0;

		// Multi-row inserts in chunks: names are escaped via prepare (all %s,
		// no nullable columns), the placeholder count is fixed per chunk.
		foreach ( array_chunk( $names, 100 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '(%s)' ) );
			$sql          = "INSERT INTO {$table} (name) VALUES {$placeholders}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are fixed (%s) tuples.
			$affected     = $wpdb->query( $wpdb->prepare( $sql, $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

			if ( false !== $affected ) {
				$inserted += (int) $affected;
			}
		}

		return [
			'inserted' => $inserted,
			'skipped'  => false,
		];
	}

	/**
	 * Starter units with conversion to the base unit (g for mass, ml for
	 * volume). Count/descriptive units have null dimension/value.
	 *
	 * @return array<int,array{name:string,value:float|null,dimension:string|null,system:string|null}>
	 */
	private static function units(): array {
		return [
			// Mass — metric (base: g).
			[ 'name' => 'mg', 'value' => 0.001, 'dimension' => 'mass', 'system' => 'metric' ],
			[ 'name' => 'g', 'value' => 1.0, 'dimension' => 'mass', 'system' => 'metric' ],
			[ 'name' => 'kg', 'value' => 1000.0, 'dimension' => 'mass', 'system' => 'metric' ],
			// Mass — imperial / US.
			[ 'name' => 'oz', 'value' => 28.3495, 'dimension' => 'mass', 'system' => 'imperial' ],
			[ 'name' => 'lb', 'value' => 453.592, 'dimension' => 'mass', 'system' => 'imperial' ],

			// Volume — metric (base: ml).
			[ 'name' => 'ml', 'value' => 1.0, 'dimension' => 'volume', 'system' => 'metric' ],
			[ 'name' => 'cl', 'value' => 10.0, 'dimension' => 'volume', 'system' => 'metric' ],
			[ 'name' => 'dl', 'value' => 100.0, 'dimension' => 'volume', 'system' => 'metric' ],
			[ 'name' => 'l', 'value' => 1000.0, 'dimension' => 'volume', 'system' => 'metric' ],
			// Volume — imperial / US cooking (rounded kitchen standards).
			[ 'name' => 'tsp', 'value' => 5.0, 'dimension' => 'volume', 'system' => 'imperial' ],
			[ 'name' => 'tbsp', 'value' => 15.0, 'dimension' => 'volume', 'system' => 'imperial' ],
			[ 'name' => 'fl oz', 'value' => 29.5735, 'dimension' => 'volume', 'system' => 'imperial' ],
			[ 'name' => 'cup', 'value' => 240.0, 'dimension' => 'volume', 'system' => 'imperial' ],
			[ 'name' => 'pint', 'value' => 473.176, 'dimension' => 'volume', 'system' => 'imperial' ],
			[ 'name' => 'quart', 'value' => 946.353, 'dimension' => 'volume', 'system' => 'imperial' ],
			[ 'name' => 'gallon', 'value' => 3785.41, 'dimension' => 'volume', 'system' => 'imperial' ],

			// Count / descriptive — no conversion.
			[ 'name' => 'pcs', 'value' => null, 'dimension' => null, 'system' => null ],
			[ 'name' => 'clove', 'value' => null, 'dimension' => null, 'system' => null ],
			[ 'name' => 'slice', 'value' => null, 'dimension' => null, 'system' => null ],
			[ 'name' => 'pinch', 'value' => null, 'dimension' => null, 'system' => null ],
			[ 'name' => 'bunch', 'value' => null, 'dimension' => null, 'system' => null ],
			[ 'name' => 'can', 'value' => null, 'dimension' => null, 'system' => null ],
			[ 'name' => 'jar', 'value' => null, 'dimension' => null, 'system' => null ],
			[ 'name' => 'package', 'value' => null, 'dimension' => null, 'system' => null ],
			[ 'name' => 'stick', 'value' => null, 'dimension' => null, 'system' => null ],
			[ 'name' => 'sprig', 'value' => null, 'dimension' => null, 'system' => null ],
			[ 'name' => 'handful', 'value' => null, 'dimension' => null, 'system' => null ],
			[ 'name' => 'drop', 'value' => null, 'dimension' => null, 'system' => null ],
			[ 'name' => 'dash', 'value' => null, 'dimension' => null, 'system' => null ],
			[ 'name' => 'knob', 'value' => null, 'dimension' => null, 'system' => null ],
			[ 'name' => 'head', 'value' => null, 'dimension' => null, 'system' => null ],
			[ 'name' => 'stalk', 'value' => null, 'dimension' => null, 'system' => null ],
			[ 'name' => 'sheet', 'value' => null, 'dimension' => null, 'system' => null ],
			[ 'name' => 'to taste', 'value' => null, 'dimension' => null, 'system' => null ],
		];
	}

	/**
	 * Broad starter ingredient list compiled from the items most commonly
	 * used across major cooking blogs, grouped by category.
	 *
	 * @return string[]
	 */
	private static function ingredients(): array {
		return [
			// Flours & baking.
			'All-purpose flour', 'Bread flour', 'Whole wheat flour', 'Cake flour', 'Self-rising flour',
			'Almond flour', 'Cornmeal', 'Cornstarch', 'Semolina', 'Rye flour', 'Baking powder',
			'Baking soda', 'Active dry yeast', 'Instant yeast', 'Cream of tartar', 'Cocoa powder',
			'Vanilla extract', 'Vanilla bean', 'Almond extract', 'Food coloring',

			// Sugars & sweeteners.
			'Granulated sugar', 'Brown sugar', 'Powdered sugar', 'Caster sugar', 'Honey', 'Maple syrup',
			'Corn syrup', 'Molasses', 'Agave syrup', 'Coconut sugar',

			// Dairy & eggs.
			'Milk', 'Whole milk', 'Skim milk', 'Heavy cream', 'Whipping cream', 'Half-and-half',
			'Buttermilk', 'Sour cream', 'Yogurt', 'Greek yogurt', 'Butter', 'Unsalted butter',
			'Margarine', 'Cream cheese', 'Mascarpone', 'Ricotta', 'Cottage cheese', 'Eggs',
			'Egg yolk', 'Egg white', 'Condensed milk', 'Evaporated milk',

			// Cheeses.
			'Parmesan', 'Mozzarella', 'Cheddar', 'Gruyere', 'Feta', 'Goat cheese', 'Blue cheese',
			'Provolone', 'Pecorino', 'Emmental', 'Gorgonzola', 'Brie',

			// Oils & fats.
			'Olive oil', 'Extra virgin olive oil', 'Vegetable oil', 'Sunflower oil', 'Canola oil',
			'Coconut oil', 'Sesame oil', 'Peanut oil', 'Lard', 'Shortening', 'Ghee',

			// Vegetables.
			'Onion', 'Red onion', 'Yellow onion', 'Spring onion', 'Shallot', 'Garlic', 'Leek', 'Carrot',
			'Celery', 'Potato', 'Sweet potato', 'Tomato', 'Cherry tomato', 'Bell pepper', 'Red bell pepper',
			'Chili pepper', 'Jalapeno', 'Cucumber', 'Zucchini', 'Eggplant', 'Mushroom', 'Spinach', 'Kale',
			'Lettuce', 'Arugula', 'Broccoli', 'Cauliflower', 'Cabbage', 'Brussels sprouts', 'Green beans',
			'Peas', 'Corn', 'Asparagus', 'Beetroot', 'Radish', 'Pumpkin', 'Butternut squash', 'Fennel',
			'Artichoke', 'Ginger', 'Avocado', 'Olives', 'Capers', 'Sun-dried tomatoes', 'Scallion',

			// Fruits.
			'Apple', 'Banana', 'Lemon', 'Lime', 'Orange', 'Grapefruit', 'Strawberry', 'Blueberry',
			'Raspberry', 'Blackberry', 'Grape', 'Peach', 'Pear', 'Plum', 'Cherry', 'Pineapple', 'Mango',
			'Kiwi', 'Watermelon', 'Cantaloupe', 'Pomegranate', 'Fig', 'Apricot', 'Cranberry', 'Coconut',
			'Raisins', 'Dates', 'Lemon zest', 'Orange zest',

			// Herbs & spices.
			'Salt', 'Sea salt', 'Black pepper', 'White pepper', 'Basil', 'Oregano', 'Thyme', 'Rosemary',
			'Sage', 'Mint', 'Dill', 'Bay leaf', 'Parsley', 'Cilantro', 'Cinnamon', 'Nutmeg', 'Cloves',
			'Cardamom', 'Cumin', 'Coriander', 'Paprika', 'Smoked paprika', 'Cayenne pepper', 'Chili powder',
			'Curry powder', 'Garam masala', 'Ginger powder', 'Garlic powder', 'Onion powder', 'Saffron',
			'Star anise', 'Fennel seeds', 'Mustard seeds', 'Red pepper flakes', 'Allspice', 'Turmeric',
			'Italian seasoning', 'Herbs de Provence',

			// Proteins — meat & fish.
			'Chicken breast', 'Chicken thigh', 'Whole chicken', 'Ground beef', 'Beef steak', 'Beef chuck',
			'Pork chop', 'Pork belly', 'Ground pork', 'Bacon', 'Ham', 'Sausage', 'Prosciutto', 'Salami',
			'Pancetta', 'Turkey', 'Lamb', 'Veal', 'Salmon', 'Tuna', 'Cod', 'Shrimp', 'Prawns', 'Mussels',
			'Clams', 'Squid', 'Anchovies', 'Sardines', 'Crab', 'Scallops', 'Tofu', 'Tempeh', 'Seitan',

			// Grains, pasta & bread.
			'Rice', 'White rice', 'Brown rice', 'Basmati rice', 'Arborio rice', 'Jasmine rice', 'Pasta',
			'Spaghetti', 'Penne', 'Macaroni', 'Lasagna sheets', 'Noodles', 'Couscous', 'Quinoa', 'Bulgur',
			'Oats', 'Rolled oats', 'Barley', 'Bread', 'Breadcrumbs', 'Panko breadcrumbs', 'Tortilla',
			'Pita bread', 'Polenta', 'Gnocchi',

			// Legumes, nuts & seeds.
			'Chickpeas', 'Black beans', 'Kidney beans', 'White beans', 'Lentils', 'Red lentils',
			'Split peas', 'Edamame', 'Almonds', 'Walnuts', 'Cashews', 'Pecans', 'Pistachios', 'Hazelnuts',
			'Peanuts', 'Pine nuts', 'Macadamia nuts', 'Sesame seeds', 'Sunflower seeds', 'Pumpkin seeds',
			'Chia seeds', 'Flax seeds', 'Poppy seeds', 'Peanut butter', 'Almond butter', 'Tahini',

			// Condiments & sauces.
			'Soy sauce', 'Worcestershire sauce', 'Fish sauce', 'Hot sauce', 'Sriracha', 'Ketchup',
			'Mustard', 'Dijon mustard', 'Mayonnaise', 'Vinegar', 'Balsamic vinegar', 'White wine vinegar',
			'Red wine vinegar', 'Apple cider vinegar', 'Rice vinegar', 'Tomato paste', 'Tomato sauce',
			'Crushed tomatoes', 'Passata', 'Pesto', 'Hoisin sauce', 'Oyster sauce', 'Barbecue sauce',
			'Coconut milk', 'Chicken stock', 'Vegetable stock', 'Beef stock', 'Bouillon cube',

			// Liquids & misc.
			'Water', 'White wine', 'Red wine', 'Beer', 'Rum', 'Brandy', 'Vodka', 'Vermouth', 'Coffee',
			'Espresso', 'Lemon juice', 'Lime juice', 'Orange juice',

			// Chocolate & sweets.
			'Dark chocolate', 'Milk chocolate', 'White chocolate', 'Chocolate chips', 'Gelatin',
			'Marshmallows', 'Caramel',
		];
	}
}
