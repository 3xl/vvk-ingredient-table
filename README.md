# VVK Ingredients Table

A **production-grade WordPress plugin** for managing structured ingredient tables in recipe posts. Combines a modern REST API, React admin UI, and progressive enhancement on the frontend to provide a complete ingredient management system with nutrition tracking, allergen information, and unit conversion.

**Version:** 2.2.2  
**Requirements:** WordPress 6.5+, PHP 8.1+

---

## Overview

VVK Ingredients Table transforms recipe posts with:

- **Structured ingredient data** — organize ingredients in tables with quantities, units, and linked products
- **Nutrition & allergens** — track nutritional values per ingredient, calculate recipe totals, and manage allergen/diet labels
- **REST API** (`/wp-json/vvkit/v1`) — full CRUD operations for tables, ingredients, units, and shopping lists
- **React admin UI** — metabox editor for recipes, management pages for ingredients/units, with live previews
- **Frontend flexibility** — shortcode `[vvkit]`, Gutenberg block, or automatic insertion; progressive enhancement for servings/unit conversion
- **Backwards compatible** — runs directly on v1 database schema with zero migration
- **Headless-ready** — REST field `vvkit_tables` on posts with pre-formatted quantities, nutrition, and allergen data

---

## Architecture

```
vvk-ingredients-table.php      Plugin bootstrap & activation
uninstall.php                  Cleanup & data deletion

includes/
├── Autoloader.php             PSR-4 namespace resolver
├── Plugin.php                 Hook wiring & module bootstrap
├── Install.php                Database schema & version upgrades
├── Schema.php                 Database table definitions
├── Repository/                Data access layer (prepared queries)
├── Rest/                       REST API endpoints & post field registration
├── Admin/                      WordPress admin UI, meta boxes, settings
├── Frontend/                   Shortcode, Gutenberg block, content insertion
└── Support/                    Options, Cache, Fraction parsing, Presenter, View

src/                            React admin components (built with @wordpress/scripts)
├── components/                 Reusable UI components (Block, Modal, Table, Filter, etc.)
├── hooks/                      Custom hooks for data & filtering
├── utils/                      Formatting, validation, API utilities
├── types/                      TypeScript type definitions
└── styles/                     Component & admin styling

build/                          Compiled JS/CSS (auto-generated, in .gitignore)
languages/                      Italian translations (.l10n.php format, WP 6.5+)
templates/                      Frontend template (overridable in theme)
assets/                         CSS & JavaScript for frontend & admin
```

---

## Key Features

### 1. Ingredient Management
- **Ingredients**: name, quantity, unit, product link, nutrition (per 100g), allergen/diet tags
- **Units**: name, abbreviation, dimension (mass/volume), system (metric/imperial), conversion factor to g/ml
- **Products**: WooCommerce integration (if available); ingredient names link to products

### 2. Nutrition & Allergens
- Per-ingredient nutrition values (calories, protein, fat, carbs, etc.)
- Allergen tags (gluten, dairy, nuts, etc.) and diet labels (vegan, keto, etc.)
- Automatic recipe nutrition calculation with coverage indicator
- Diet applicability: recipe is labeled as diet-compliant only if **all** ingredients carry the tag
- Nutrition output via REST and JSON-LD schema.org Recipe

### 3. Quantity & Unit Conversion
- Servings field per table; frontend selector recalculates quantities
- Unit system toggle (Original / Metric / Imperial) with conversion factors
- Fraction display mode: `0.5` → `½`, `1.5` → `1 ½`
- Progressive enhancement: works without JavaScript

### 4. REST API (`/wp-json/vvkit/v1`)
Full CRUD operations with cookie + nonce authentication:

| Endpoint | Methods | Permission |
|----------|---------|-----------|
| `/tables` | GET, POST | `edit_post` on recipe |
| `/tables/{id}` | GET, PUT, DELETE | `edit_post` |
| `/tables/{id}/rows` | POST | `edit_post` |
| `/rows/{id}` | PUT, DELETE | `edit_post` |
| `/ingredients` | GET, POST | `edit_posts` |
| `/ingredients/{id}` | GET | `edit_posts`; PUT/DELETE require `manage_options` |
| `/units` | GET, POST | `edit_posts` |
| `/units/{id}` | GET | `edit_posts`; PUT/DELETE require `manage_options` |
| `/products` | GET | `edit_posts` (requires WooCommerce) |
| `/shopping-list` | GET | Public (authenticated users only) |

**Post field**: Tables also appear as `vvkit_tables` in `/wp/v2/posts/{id}` REST responses with pre-formatted data (quantities, nutrition, allergens, display settings).

### 5. Admin Interface
- **Metabox** in post editor for creating/editing tables
- **Ingredients page** (`/admin.php?page=vvkit-ingredients`) for managing ingredient master data
- **Units page** (`/admin.php?page=vvkit-units`) for managing units
- **Settings** (`/admin.php?page=vvkit-settings`) for global configuration:
  - Post types where tables are enabled
  - Default table title and heading tag
  - CSS classes for rendered tables
  - Fraction display mode
  - JSON-LD schema output
  - Data deletion on uninstall
  - Table extras visibility (servings, unit conversion, nutrition, allergens, products)

### 6. Frontend
- **Shortcode** `[vvkit table_id="123"]` with position control (before/after content)
- **Gutenberg block** `vvkit/ingredients-table` with live preview
- **Automatic insertion** at configured position (disabled by default)
- **Progressive enhancement** for servings/unit conversion without page reload
- **Theme override** via `{theme}/vvkit/ingredients-table.php`

### 7. Backwards Compatibility
- Uses original v1 database schema (`vvkit_tables`, `vvkit_ingredients`, `vvkit_units`, `vvkit_ingredient_table`)
- Zero data migration required
- Supports both legacy string option `vvkit_post_type` and new array format
- Same shortcode syntax and semantics as v1

---

## Development

### Setup
```bash
# Clone repo and install dependencies
git clone <repo>
cd vvk-ingredients-table
pnpm install

# Build admin UI (required for metabox & management pages)
pnpm build

# Watch mode during development
pnpm dev

# Linting
pnpm lint
```

### Build System
- React components in `src/` built with `@wordpress/scripts`
- Output goes to `build/` (auto-generated, in .gitignore)
- Without build, the plugin still loads but admin metabox & pages are empty
- Part of pnpm monorepo: `pnpm --filter @vasavasa/wp-vvk-ingredients-table build`

### Code Standards
- PSR-4 namespacing: `VVKit\*`
- Type declarations: `declare(strict_types=1)`
- Prepared queries: all data access uses `$wpdb->prepare()`
- WordPress hooks & filters for extensibility
- PHPCS configuration: `.phpcs.xml.dist`

### Debugging
```php
// Enable debug mode in wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );

// Cache testing
define( 'WP_REDIS_HOST', 'redis' );  // if using Redis object cache
```

---

## Hooks for Extensions

### Filters
```php
// Modify table data before rendering
add_filter( 'vvkit_table_data', function( $data, $table ) {
    // $data: array of rows with quantities, nutrition, etc.
    // $table: WP_Post object of the recipe
    return $data;
}, 10, 2 );
```

### Template Override
Copy `templates/ingredients-table.php` to `<theme>/vvkit/ingredients-table.php`:
```php
<?php
// Your custom template receives:
// $table: table data (rows, nutrition, allergens, etc.)
// $table_post: WP_Post object of the recipe
// $args: shortcode/block arguments
?>
```

---

## Headless / API Consumers

The plugin exposes ingredient tables via REST in two ways:

1. **As a post field** (`vvkit_tables` in `/wp/v2/posts/{id}`):
   ```json
   {
     "vvkit_tables": [
       {
         "id": 1,
         "title": "Ingredients",
         "servings": 4,
         "rows": [
           {
             "ingredient": "Flour",
             "quantity": 250,
             "quantity_display": "250",
             "unit": "g",
             "base": 250,
             "product_id": null
           }
         ],
         "nutrition": {
           "calories": 1800,
           "protein": 45,
           ...
         },
         "allergens": ["gluten"],
         "diets": [],
         "display": {
           "servings": true,
           "units": true,
           "nutrition": true,
           "allergens": true,
           "products": false
         }
       }
     ]
   }
   ```

2. **Via REST API** (`/wp-json/vvkit/v1`):
   - Direct access for management interfaces
   - Detailed schema with all nutritional breakdowns
   - Requires authentication (cookie + nonce)

---

## Database Schema

### Core Tables (v1 compatible)

**vvkit_tables**
- `id` (PK)
- `post_id` (foreign key to `wp_posts`)
- `title` (varchar, e.g. "Ingredients", "Toppings")
- `title_tag` (varchar, e.g. "h3")
- `servings` (nullable INT)
- `positions` (INT: -1=before content, 0=none, 1=after)
- `display_options` (JSON, new in v2.2: overrides for unit visibility, nutrition, allergens, etc.)

**vvkit_ingredients**
- `id` (PK)
- `name` (varchar)

**vvkit_units**
- `id` (PK)
- `name` (varchar, e.g. "gram", "milliliter")
- `abbreviation` (varchar, e.g. "g", "ml")
- `dimension` (nullable: "mass" or "volume", new in v2.1)
- `unit_system` (nullable: "metric" or "imperial", new in v2.1)

**vvkit_ingredient_table** (join table)
- `id` (PK)
- `table_id` (FK)
- `ingredient_id` (FK)
- `quantity` (decimal)
- `unit_id` (FK)
- `product_id` (nullable FK to WooCommerce product)
- `sort` (order in table)

### Nutrition & Allergen Tables (new in v2.1)

**vvkit_ingredient_nutrition**
- `id` (PK)
- `ingredient_id` (FK)
- `calories`, `protein`, `carbs`, `fat`, `fiber`, `sugars` (all per 100g)

**vvkit_ingredient_tags** (join table)
- `id` (PK)
- `ingredient_id` (FK)
- `tag_id` (FK)
- `tag_type` ('allergen' or 'diet')
- `tag_name` (e.g. "gluten", "dairy", "vegan", "keto")

---

## Performance

- **Object cache** integration: uses WordPress object cache (Redis recommended for production)
- **Caching strategy**: table, ingredient, and unit data cached with invalidation on changes
- **Prepared queries**: all SQL uses `$wpdb->prepare()` to prevent injection
- **REST pagination**: ingredients/units paginated; tables eager-loaded where needed

---

## Localization

Translations in `languages/` use WP 6.5+ `.l10n.php` format (PHP instead of `.po` files). Currently Italian (`vvkit-it_IT.l10n.php`).

To add a language:
```bash
# Scan code for translatable strings
wp i18n make-pot . languages/vvkit.pot

# Generate .l10n.php for a new language
wp i18n generate-translations <pot-file> --format=php
```

---

## Version History

### v2.2.2 (Current)
- Fixed display options tri-state override behavior
- Improved nutrition calculation accuracy
- Enhanced error handling in REST API

### v2.2
- **Display options tri-state**: global defaults + per-table overrides for servings, units, nutrition, allergens, products
- JSON storage in `vvkit_tables.display_options`
- `display` & `display_overrides` in REST responses

### v2.1
- **Servings**: quantities recalculate on frontend
- **Unit conversion**: metric/imperial toggle with dimension & conversion factors
- **Nutrition tracking**: per-ingredient macros, recipe totals, diet labels
- **Shopping list**: public endpoint aggregating ingredients from multiple recipes
- **JSON-LD Recipe schema**: automatic output in `wp_head`
- **Nutrition tables** & **ingredient tags** tables added
- New REST endpoints for products, shopping list, nutrition

### v2.0
- Complete rewrite from v1
- React metabox & admin pages
- REST API redesign
- Database schema stays v1-compatible

---

## Known Limitations

- **Multiplayer editing**: not transaction-safe; concurrent edits may cause conflicts
- **WooCommerce products**: search limited to first 100 results; filters needed for larger catalogs
- **Nutrition data**: manual entry per ingredient; no auto-sync with USDA/Nutritionix
- **Display options**: tri-state applied at rendering time, not at edit time; changes visible after reload

---

## Support & Contributing

- **Bug reports**: GitHub Issues
- **Security issues**: contact author directly (security@3xlstudio.com)
- **Contributions**: PRs welcome; follow WordPress coding standards

---

## License

GPL-2.0-or-later. See LICENSE file for details.

---

## Author

**Claudio Geraci** ([3XL Studio](https://3xlstudio.com))

Built for [Vasava's Kitchen](https://vasavasakitchen.com/)
