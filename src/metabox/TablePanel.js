import { useState } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import * as api from '../api';
import { parseQuantity } from '../utils';
import RowModal from './RowModal';

const TAG_OPTIONS = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ].map( ( tag ) => ( {
	label: tag,
	value: tag,
} ) );

const EMPTY_DRAFT = { quantity: '', unitId: '0', ingredientId: '', note: '' };

const DISPLAY_FEATURES = [
	{ key: 'servings_switcher', label: __( 'Servings switcher', 'vvkit' ) },
	{ key: 'units_toggle', label: __( 'Units toggle', 'vvkit' ) },
	{ key: 'allergens', label: __( 'Allergen badges', 'vvkit' ) },
	{ key: 'diets', label: __( 'Diet badges', 'vvkit' ) },
	{ key: 'nutrition', label: __( 'Nutrition facts', 'vvkit' ) },
	{ key: 'product_links', label: __( 'Product links', 'vvkit' ) },
];

const TRI_STATE_OPTIONS = [
	{ label: __( 'Default', 'vvkit' ), value: '' },
	{ label: __( 'Show', 'vvkit' ), value: 'show' },
	{ label: __( 'Hide', 'vvkit' ), value: 'hide' },
];

export default function TablePanel( {
	table,
	ingredients,
	units,
	onChange,
	onDelete,
	onError,
	notify,
} ) {
	const [ title, setTitle ] = useState( table.title );
	const [ tagname, setTagname ] = useState( table.title_tagname );
	const [ positions, setPositions ] = useState( table.positions );
	const [ servings, setServings ] = useState(
		table.servings === null ? '' : String( table.servings )
	);
	const [ display, setDisplay ] = useState( () => {
		const initial = {};

		DISPLAY_FEATURES.forEach( ( { key } ) => {
			const value = table.display_overrides?.[ key ];
			initial[ key ] = value === true ? 'show' : value === false ? 'hide' : '';
		} );

		return initial;
	} );
	const [ saving, setSaving ] = useState( false );
	const [ adding, setAdding ] = useState( false );
	const [ editingRow, setEditingRow ] = useState( null );
	const [ draft, setDraft ] = useState( EMPTY_DRAFT );

	const unitOptions = [
		{ label: '—', value: '0' },
		...units.map( ( unit ) => ( { label: unit.name, value: String( unit.id ) } ) ),
	];

	const ingredientOptions = [
		{ label: __( 'Select an ingredient…', 'vvkit' ), value: '' },
		...ingredients.map( ( ingredient ) => ( {
			label: ingredient.name,
			value: String( ingredient.id ),
		} ) ),
	];

	const saveTable = async ( override = {} ) => {
		setSaving( true );

		const parsedServings = parseInt( servings, 10 );

		try {
			const updated = await api.tables.update( table.id, {
				title,
				title_tagname: tagname,
				positions,
				servings:
					Number.isFinite( parsedServings ) && parsedServings > 0
						? parsedServings
						: null,
				display: Object.fromEntries(
					Object.entries( display )
						.filter( ( [ , value ] ) => value )
						.map( ( [ key, value ] ) => [ key, value === 'show' ] )
				),
				...override,
			} );
			onChange( updated );
			notify( __( 'Table saved.', 'vvkit' ) );
		} catch ( e ) {
			onError( e );
		} finally {
			setSaving( false );
		}
	};

	const togglePosition = ( value, checked ) => {
		const next = checked
			? [ ...positions.filter( ( p ) => p !== value ), value ]
			: positions.filter( ( p ) => p !== value );

		setPositions( next );
		saveTable( { positions: next } );
	};

	const addRow = async () => {
		const quantity = parseQuantity( draft.quantity );

		if ( quantity === undefined ) {
			onError( new Error( __( 'The quantity is not a valid number.', 'vvkit' ) ) );
			return;
		}

		if ( ! draft.ingredientId ) {
			onError( new Error( __( 'Select an ingredient first.', 'vvkit' ) ) );
			return;
		}

		setAdding( true );

		try {
			const row = await api.rows.create( table.id, {
				ingredient_id: Number( draft.ingredientId ),
				unit_id: Number( draft.unitId ) || null,
				quantity,
				note: draft.note,
				position: table.rows.length,
			} );
			onChange( { ...table, rows: [ ...table.rows, row ] } );
			setDraft( EMPTY_DRAFT );
			notify( __( 'Ingredient added.', 'vvkit' ) );
		} catch ( e ) {
			onError( e );
		} finally {
			setAdding( false );
		}
	};

	const deleteRow = async ( row ) => {
		if (
			! window.confirm( __( 'Remove this ingredient from the table?', 'vvkit' ) )
		) {
			return;
		}

		try {
			await api.rows.remove( row.id );
			onChange( {
				...table,
				rows: table.rows.filter( ( item ) => item.id !== row.id ),
			} );
			notify( __( 'Ingredient removed.', 'vvkit' ) );
		} catch ( e ) {
			onError( e );
		}
	};

	const moveRow = async ( index, delta ) => {
		const target = index + delta;

		if ( target < 0 || target >= table.rows.length ) {
			return;
		}

		const reordered = [ ...table.rows ];
		[ reordered[ index ], reordered[ target ] ] = [
			reordered[ target ],
			reordered[ index ],
		];

		onChange( { ...table, rows: reordered } );

		try {
			const fresh = await api.rows.reorder(
				table.id,
				reordered.map( ( row ) => row.id )
			);
			onChange( { ...table, rows: fresh } );
		} catch ( e ) {
			onError( e );
		}
	};

	const saveRowEdit = async ( row, payload ) => {
		try {
			const updated = await api.rows.update( row.id, payload );
			onChange( {
				...table,
				rows: table.rows.map( ( item ) =>
					item.id === updated.id ? updated : item
				),
			} );
			setEditingRow( null );
			notify( __( 'Ingredient updated.', 'vvkit' ) );
		} catch ( e ) {
			onError( e );
		}
	};

	const copyShortcode = async () => {
		try {
			await navigator.clipboard.writeText( table.shortcode );
			notify( __( 'Shortcode copied to the clipboard.', 'vvkit' ) );
		} catch {
			onError( new Error( __( 'Could not copy the shortcode.', 'vvkit' ) ) );
		}
	};

	return (
		<div className="vvkit-panel">
			<div className="vvkit-panel-settings">
				<TextControl
					label={ __( 'Title', 'vvkit' ) }
					value={ title }
					onChange={ setTitle }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Title tag', 'vvkit' ) }
					options={ TAG_OPTIONS }
					value={ tagname }
					onChange={ setTagname }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'Servings', 'vvkit' ) }
					type="number"
					min={ 1 }
					value={ servings }
					onChange={ setServings }
					className="vvkit-servings-field"
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<Button
					__next40pxDefaultSize
					variant="primary"
					isBusy={ saving }
					onClick={ () => saveTable() }
				>
					{ __( 'Save table', 'vvkit' ) }
				</Button>
				<Button
					__next40pxDefaultSize
					variant="secondary"
					isDestructive
					onClick={ onDelete }
				>
					{ __( 'Delete table', 'vvkit' ) }
				</Button>
			</div>

			<div className="vvkit-panel-positions">
				<span className="vvkit-label">{ __( 'Automatic placement:', 'vvkit' ) }</span>
				<CheckboxControl
					label={ __( 'Before the post content', 'vvkit' ) }
					checked={ positions.includes( -1 ) }
					onChange={ ( checked ) => togglePosition( -1, checked ) }
					__nextHasNoMarginBottom
				/>
				<CheckboxControl
					label={ __( 'After the post content', 'vvkit' ) }
					checked={ positions.includes( 1 ) }
					onChange={ ( checked ) => togglePosition( 1, checked ) }
					__nextHasNoMarginBottom
				/>
			</div>

			<div className="vvkit-panel-display">
				<span className="vvkit-label">{ __( 'Extras:', 'vvkit' ) }</span>
				<div className="vvkit-display-grid">
					{ DISPLAY_FEATURES.map( ( { key, label } ) => (
						<SelectControl
							key={ key }
							label={ label }
							options={ TRI_STATE_OPTIONS }
							value={ display[ key ] }
							onChange={ ( value ) =>
								setDisplay( { ...display, [ key ]: value } )
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					) ) }
				</div>
				<p className="description">
					{ __(
						'“Default” follows the plugin settings. Saved with the “Save table” button.',
						'vvkit'
					) }
				</p>
			</div>

			<div className="vvkit-add-row">
				<TextControl
					label={ __( 'Quantity', 'vvkit' ) }
					value={ draft.quantity }
					onChange={ ( quantity ) => setDraft( { ...draft, quantity } ) }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Unit', 'vvkit' ) }
					options={ unitOptions }
					value={ draft.unitId }
					onChange={ ( unitId ) => setDraft( { ...draft, unitId } ) }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Ingredient', 'vvkit' ) }
					options={ ingredientOptions }
					value={ draft.ingredientId }
					onChange={ ( ingredientId ) =>
						setDraft( { ...draft, ingredientId } )
					}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'Note', 'vvkit' ) }
					value={ draft.note }
					onChange={ ( note ) => setDraft( { ...draft, note } ) }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<Button
					__next40pxDefaultSize
					variant="primary"
					isBusy={ adding }
					onClick={ addRow }
				>
					{ __( 'Add', 'vvkit' ) }
				</Button>
			</div>

			<table className="widefat striped vvkit-rows">
				<thead>
					<tr>
						<th className="vvkit-col-order"></th>
						<th>{ __( 'Quantity', 'vvkit' ) }</th>
						<th>{ __( 'Unit', 'vvkit' ) }</th>
						<th>{ __( 'Ingredient', 'vvkit' ) }</th>
						<th>{ __( 'Note', 'vvkit' ) }</th>
						<th className="vvkit-col-actions"></th>
					</tr>
				</thead>
				<tbody>
					{ ! table.rows.length && (
						<tr>
							<td colSpan={ 6 }>
								<em>{ __( 'No ingredients in this table yet.', 'vvkit' ) }</em>
							</td>
						</tr>
					) }
					{ table.rows.map( ( row, index ) => (
						<tr key={ row.id }>
							<td className="vvkit-col-order">
								<Button
									icon="arrow-up-alt2"
									label={ __( 'Move up', 'vvkit' ) }
									disabled={ index === 0 }
									onClick={ () => moveRow( index, -1 ) }
									size="small"
								/>
								<Button
									icon="arrow-down-alt2"
									label={ __( 'Move down', 'vvkit' ) }
									disabled={ index === table.rows.length - 1 }
									onClick={ () => moveRow( index, 1 ) }
									size="small"
								/>
							</td>
							<td>{ row.quantity_display }</td>
							<td>{ row.unit.name }</td>
							<td>
								{ row.ingredient.name }
								{ row.product && (
									<span
										className="dashicons dashicons-cart vvkit-product-flag"
										title={ row.product.name }
									/>
								) }
							</td>
							<td>{ row.note }</td>
							<td className="vvkit-col-actions">
								<Button
									icon="edit"
									label={ __( 'Edit', 'vvkit' ) }
									onClick={ () => setEditingRow( row ) }
									size="small"
								/>
								<Button
									icon="trash"
									label={ __( 'Remove', 'vvkit' ) }
									isDestructive
									onClick={ () => deleteRow( row ) }
									size="small"
								/>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>

			<div className="vvkit-shortcode">
				<span className="vvkit-label">{ __( 'Shortcode:', 'vvkit' ) }</span>
				<code>{ table.shortcode }</code>
				<Button variant="secondary" size="small" onClick={ copyShortcode }>
					{ __( 'Copy', 'vvkit' ) }
				</Button>
			</div>

			{ editingRow && (
				<RowModal
					row={ editingRow }
					unitOptions={ unitOptions }
					ingredientOptions={ ingredientOptions }
					onSave={ ( payload ) => saveRowEdit( editingRow, payload ) }
					onClose={ () => setEditingRow( null ) }
					onError={ onError }
				/>
			) }
		</div>
	);
}
