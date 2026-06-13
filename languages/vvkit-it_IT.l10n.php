<?php
/**
 * Italian translations (PHP translation file format, WordPress >= 6.5).
 */

return [
	'project-id-version' => 'VVK Ingredients Table 2.1.0',
	'language'           => 'it_IT',
	'messages'           => [
		// Frontend.
		'Quantity'                     => 'Quantità',
		'Ingredient'                   => 'Ingrediente',
		'servings'                     => 'porzioni',
		'Fewer servings'               => 'Meno porzioni',
		'More servings'                => 'Più porzioni',
		'Units system'                 => 'Sistema di unità',
		'Original'                     => 'Originale',
		'Metric'                       => 'Metrico',
		'Imperial'                     => 'Imperiale',
		'Allergens:'                   => 'Allergeni:',
		'Nutrition per serving:'       => 'Valori nutrizionali per porzione:',
		'Nutrition (total):'           => 'Valori nutrizionali (totale):',
		'(estimate based on part of the ingredients)' => '(stima basata su parte degli ingredienti)',
		'kcal'                         => 'kcal',
		'fat'                          => 'grassi',
		'saturated fat'                => 'grassi saturi',
		'carbohydrates'                => 'carboidrati',
		'sugars'                       => 'zuccheri',
		'protein'                      => 'proteine',
		'fiber'                        => 'fibre',
		'salt'                         => 'sale',
		// Plural forms are NUL-joined (MO-style), not arrays.
		'%d serving'                   => "%d porzione\0%d porzioni",

		// Menu / pages.
		'Ingredient tables'            => 'Tabelle ingredienti',
		'Ingredients'                  => 'Ingredienti',
		'Units'                        => 'Unità',
		'Settings'                     => 'Impostazioni',

		// Settings sections and fields.
		'Content'                      => 'Contenuto',
		'Display'                      => 'Visualizzazione',
		'Advanced'                     => 'Avanzate',
		'Post types'                   => 'Tipi di contenuto',
		'Default table title'          => 'Titolo predefinito della tabella',
		'Default title tag'            => 'Tag predefinito del titolo',
		'CSS classes'                  => 'Classi CSS',
		'Fractions'                    => 'Frazioni',
		'Recipe JSON-LD'               => 'JSON-LD Ricetta',
		'Table extras'                 => 'Extra delle tabelle',
		'Servings switcher'            => 'Selettore porzioni',
		'Units toggle'                 => 'Cambio sistema di unità',
		'Allergen badges'              => 'Badge allergeni',
		'Diet badges'                  => 'Badge diete',
		'Nutrition facts'              => 'Valori nutrizionali',
		'Product links'                => 'Link ai prodotti',
		'Default visibility of the table extras on the frontend. Each table can override these from the post editor.' => 'Visibilità predefinita degli extra delle tabelle sul frontend. Ogni tabella può sovrascriverla dall\'editor del post.',
		'Uninstall'                    => 'Disinstallazione',
		'Post types where the ingredient tables metabox and the automatic placement are enabled.' => 'Tipi di contenuto in cui sono attivi il metabox delle tabelle ingredienti e l\'inserimento automatico.',
		'Title assigned to newly created tables.' => 'Titolo assegnato alle nuove tabelle.',
		'Heading tag used to render the table title on the frontend.' => 'Tag heading usato per il titolo della tabella sul frontend.',
		'Space-separated CSS classes added to the rendered table (the vvkit class is always present).' => 'Classi CSS, separate da spazio, aggiunte alla tabella renderizzata (la classe vvkit è sempre presente).',
		'Render quantities as kitchen fractions (e.g. 0.5 becomes ½, 1.5 becomes 1 ½).' => 'Mostra le quantità come frazioni (es. 0.5 diventa ½, 1.5 diventa 1 ½).',
		'Output schema.org Recipe structured data (recipeIngredient, servings, nutrition) on posts with tables.' => 'Genera i dati strutturati schema.org Recipe (recipeIngredient, porzioni, nutrizione) sui post con tabelle.',
		'Delete all plugin data (tables, ingredients, units, settings) when the plugin is uninstalled.' => 'Elimina tutti i dati del plugin (tabelle, ingredienti, unità, impostazioni) alla disinstallazione.',
		'Leave unchecked to keep your data for a future reinstall.' => 'Lascia deselezionato per conservare i dati in vista di una futura reinstallazione.',

		// REST errors.
		'Resource not found.'          => 'Risorsa non trovata.',
		'Sorry, you are not allowed to do that.' => 'Non disponi dei permessi necessari per questa operazione.',
		'The database operation failed.' => 'Operazione sul database non riuscita.',
		'The selected ingredient does not exist.' => 'L\'ingrediente selezionato non esiste.',
		'An ingredient with this name already exists.' => 'Esiste già un ingrediente con questo nome.',
		'A unit with this name already exists.' => 'Esiste già un\'unità con questo nome.',
		'The name cannot be empty.'    => 'Il nome non può essere vuoto.',
		'Ingredient tables attached to the post.' => 'Tabelle ingredienti associate al post.',
	],
];
