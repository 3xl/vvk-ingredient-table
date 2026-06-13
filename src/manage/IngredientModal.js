import { useEffect, useState } from '@wordpress/element';
import { Modal, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import * as api from '../api';
import { parseQuantity } from '../utils';

const NUTRITION_FIELDS = [
	{ key: 'kcal', label: __( 'Energy (kcal)', 'vvkit' ) },
	{ key: 'fat', label: __( 'Fat (g)', 'vvkit' ) },
	{ key: 'saturated_fat', label: __( 'Saturated fat (g)', 'vvkit' ) },
	{ key: 'carbs', label: __( 'Carbohydrates (g)', 'vvkit' ) },
	{ key: 'sugars', label: __( 'Sugars (g)', 'vvkit' ) },
	{ key: 'protein', label: __( 'Protein (g)', 'vvkit' ) },
	{ key: 'fiber', label: __( 'Fiber (g)', 'vvkit' ) },
	{ key: 'salt', label: __( 'Salt (g)', 'vvkit' ) },
];

const parseTags = ( value ) => [
	...new Set(
		value
			.split( ',' )
			.map( ( tag ) => tag.trim() )
			.filter( Boolean )
	),
];

/**
 * Edit modal for an ingredient. Classic wp-admin form markup
 * (form-wrap / form-field) so it matches the rest of the admin; tags are
 * comma-separated like in the core "Tags" box.
 */
export default function IngredientModal( { ingredient, onSave, onClose, onError, busy } ) {
	const [ detail, setDetail ] = useState( null );
	const [ name, setName ] = useState( ingredient.name );
	const [ nutrition, setNutrition ] = useState( {} );
	const [ allergens, setAllergens ] = useState( '' );
	const [ diets, setDiets ] = useState( '' );

	useEffect( () => {
		api.items
			.get( 'ingredients', ingredient.id )
			.then( ( data ) => {
				setDetail( data );
				setName( data.name );
				setAllergens( data.allergens.join( ', ' ) );
				setDiets( data.diets.join( ', ' ) );

				const values = {};

				NUTRITION_FIELDS.forEach( ( { key } ) => {
					const value = data.nutrition?.[ key ];
					values[ key ] = value === null || value === undefined ? '' : String( value );
				} );

				setNutrition( values );
			} )
			.catch( ( e ) => {
				onError( e );
				onClose();
			} );
	}, [ ingredient.id ] );

	const save = () => {
		const parsed = {};

		for ( const { key, label } of NUTRITION_FIELDS ) {
			const value = parseQuantity( nutrition[ key ] );

			if ( value === undefined ) {
				onError( new Error( __( 'Invalid value for:', 'vvkit' ) + ' ' + label ) );
				return;
			}

			parsed[ key ] = value;
		}

		const hasNutrition = Object.values( parsed ).some( ( value ) => value !== null );

		onSave( {
			name: name.trim(),
			nutrition: hasNutrition ? parsed : null,
			allergens: parseTags( allergens ),
			diets: parseTags( diets ),
		} );
	};

	return (
		<Modal
			title={ __( 'Edit Ingredient', 'vvkit' ) }
			onRequestClose={ onClose }
			className="vvkit-modal vvkit-ingredient-modal"
		>
			{ ! detail ? (
				<Spinner />
			) : (
				<div className="form-wrap vvkit-modal-form">
					<div className="form-field form-required">
						<label htmlFor="vvkit-ingredient-name">{ __( 'Name', 'vvkit' ) }</label>
						<input
							id="vvkit-ingredient-name"
							type="text"
							value={ name }
							aria-required="true"
							onChange={ ( event ) => setName( event.target.value ) }
						/>
					</div>

					<fieldset className="vvkit-nutrition-fieldset">
						<legend>{ __( 'Nutrition facts per 100 g', 'vvkit' ) }</legend>
						<div className="vvkit-nutrition-grid">
							{ NUTRITION_FIELDS.map( ( { key, label } ) => (
								<div className="form-field" key={ key }>
									<label htmlFor={ `vvkit-nutrition-${ key }` }>{ label }</label>
									<input
										id={ `vvkit-nutrition-${ key }` }
										type="text"
										value={ nutrition[ key ] ?? '' }
										onChange={ ( event ) =>
											setNutrition( {
												...nutrition,
												[ key ]: event.target.value,
											} )
										}
									/>
								</div>
							) ) }
						</div>
					</fieldset>

					<div className="form-field">
						<label htmlFor="vvkit-allergens">{ __( 'Allergens', 'vvkit' ) }</label>
						<input
							id="vvkit-allergens"
							type="text"
							value={ allergens }
							onChange={ ( event ) => setAllergens( event.target.value ) }
						/>
						<p>{ __( 'Separate allergens with commas (e.g. gluten, milk, eggs).', 'vvkit' ) }</p>
					</div>

					<div className="form-field">
						<label htmlFor="vvkit-diets">{ __( 'Diet tags', 'vvkit' ) }</label>
						<input
							id="vvkit-diets"
							type="text"
							value={ diets }
							onChange={ ( event ) => setDiets( event.target.value ) }
						/>
						<p>{ __( 'Separate tags with commas (e.g. vegan, gluten-free). A recipe gets a diet tag only when every ingredient has it.', 'vvkit' ) }</p>
					</div>

					<p className="submit">
						<button
							type="button"
							className="button button-primary"
							disabled={ busy || ! name.trim() }
							onClick={ save }
						>
							{ __( 'Save Changes', 'vvkit' ) }
						</button>{ ' ' }
						<button type="button" className="button" onClick={ onClose }>
							{ __( 'Cancel', 'vvkit' ) }
						</button>
					</p>
				</div>
			) }
		</Modal>
	);
}
