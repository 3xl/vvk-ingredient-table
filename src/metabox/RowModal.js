import { useEffect, useState } from '@wordpress/element';
import {
	Button,
	ComboboxControl,
	Modal,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { products as productsApi } from '../api';
import { parseQuantity } from '../utils';

export default function RowModal( {
	row,
	unitOptions,
	ingredientOptions,
	onSave,
	onClose,
	onError,
} ) {
	const [ quantity, setQuantity ] = useState(
		row.quantity === null ? '' : String( row.quantity )
	);
	const [ unitId, setUnitId ] = useState( String( row.unit.id ) );
	const [ ingredientId, setIngredientId ] = useState( String( row.ingredient.id ) );
	const [ note, setNote ] = useState( row.note );
	const [ referral, setReferral ] = useState( row.referral );
	const [ productId, setProductId ] = useState( String( row.product?.id ?? 0 ) );
	const [ catalog, setCatalog ] = useState( null );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		productsApi.list().then( setCatalog );
	}, [] );

	const productOptions = catalog?.available
		? [
				{ label: '—', value: '0' },
				...catalog.products.map( ( product ) => ( {
					label: product.name,
					value: String( product.id ),
				} ) ),
		  ]
		: null;

	const save = async () => {
		const parsedQuantity = parseQuantity( quantity );

		if ( parsedQuantity === undefined ) {
			onError( new Error( __( 'The quantity is not a valid number.', 'vvkit' ) ) );
			return;
		}

		if ( ! ingredientId ) {
			onError( new Error( __( 'Select an ingredient first.', 'vvkit' ) ) );
			return;
		}

		setSaving( true );

		await onSave( {
			quantity: parsedQuantity,
			unit_id: Number( unitId ) || null,
			ingredient_id: Number( ingredientId ),
			note,
			referral,
			product_id: Number( productId ) || 0,
		} );

		setSaving( false );
	};

	return (
		<Modal
			title={ __( 'Edit ingredient', 'vvkit' ) }
			onRequestClose={ onClose }
			className="vvkit-row-modal"
		>
			<TextControl
				label={ __( 'Quantity', 'vvkit' ) }
				value={ quantity }
				onChange={ setQuantity }
				__next40pxDefaultSize
				help={ __( 'Decimal number, e.g. 0.5 (or 0,5). Leave empty for no quantity.', 'vvkit' ) }
			/>
			<SelectControl
				label={ __( 'Unit', 'vvkit' ) }
				options={ unitOptions }
				value={ unitId }
				onChange={ setUnitId }
				__next40pxDefaultSize
			/>
			<SelectControl
				label={ __( 'Ingredient', 'vvkit' ) }
				options={ ingredientOptions }
				value={ ingredientId }
				onChange={ setIngredientId }
				__next40pxDefaultSize
			/>
			<TextControl
				label={ __( 'Note', 'vvkit' ) }
				value={ note }
				onChange={ setNote }
				__next40pxDefaultSize
			/>
			<TextControl
				label={ __( 'Link (referral)', 'vvkit' ) }
				value={ referral }
				onChange={ setReferral }
				type="url"
				__next40pxDefaultSize
				help={ __( 'Optional URL: the ingredient name links there on the frontend.', 'vvkit' ) }
			/>
			{ productOptions && (
				<ComboboxControl
					label={ __( 'Linked product', 'vvkit' ) }
					options={ productOptions }
					value={ productId }
					onChange={ ( value ) => setProductId( value || '0' ) }
					__next40pxDefaultSize
					help={ __( 'Shop product for this ingredient. Used as link when no referral URL is set.', 'vvkit' ) }
				/>
			) }
			<div className="vvkit-modal-actions">
				<Button __next40pxDefaultSize variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'vvkit' ) }
				</Button>
				<Button
					__next40pxDefaultSize
					variant="primary"
					isBusy={ saving }
					onClick={ save }
				>
					{ __( 'Save', 'vvkit' ) }
				</Button>
			</div>
		</Modal>
	);
}
