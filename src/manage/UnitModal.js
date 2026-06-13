import { useState } from '@wordpress/element';
import { Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { parseQuantity } from '../utils';

/**
 * Edit modal for a measurement unit. Classic wp-admin form markup
 * (form-wrap / form-field) so it matches the rest of the admin.
 */
export default function UnitModal( { unit, onSave, onClose, onError, busy } ) {
	const [ name, setName ] = useState( unit.name );
	const [ dimension, setDimension ] = useState( unit.dimension || '' );
	const [ system, setSystem ] = useState( unit.system || '' );
	const [ factor, setFactor ] = useState(
		unit.factor === null || unit.factor === undefined ? '' : String( unit.factor )
	);

	const save = () => {
		const parsedFactor = parseQuantity( factor );

		if ( parsedFactor === undefined ) {
			onError( new Error( __( 'The conversion factor is not a valid number.', 'vvkit' ) ) );
			return;
		}

		if ( dimension && ! parsedFactor ) {
			onError(
				new Error( __( 'A unit with a dimension needs a conversion factor.', 'vvkit' ) )
			);
			return;
		}

		onSave( {
			name: name.trim(),
			dimension: dimension || null,
			system: system || null,
			factor: parsedFactor,
		} );
	};

	return (
		<Modal
			title={ __( 'Edit Unit', 'vvkit' ) }
			onRequestClose={ onClose }
			className="vvkit-modal"
		>
			<div className="form-wrap vvkit-modal-form">
				<div className="form-field form-required">
					<label htmlFor="vvkit-unit-name">{ __( 'Name', 'vvkit' ) }</label>
					<input
						id="vvkit-unit-name"
						type="text"
						value={ name }
						aria-required="true"
						onChange={ ( event ) => setName( event.target.value ) }
					/>
				</div>

				<div className="form-field">
					<label htmlFor="vvkit-unit-dimension">{ __( 'Dimension', 'vvkit' ) }</label>
					<select
						id="vvkit-unit-dimension"
						value={ dimension }
						onChange={ ( event ) => setDimension( event.target.value ) }
					>
						<option value="">—</option>
						<option value="mass">{ __( 'Mass (base: g)', 'vvkit' ) }</option>
						<option value="volume">{ __( 'Volume (base: ml)', 'vvkit' ) }</option>
					</select>
					<p>{ __( 'What the unit measures. Leave empty for countable units (pieces, slices…).', 'vvkit' ) }</p>
				</div>

				<div className="form-field">
					<label htmlFor="vvkit-unit-system">{ __( 'System', 'vvkit' ) }</label>
					<select
						id="vvkit-unit-system"
						value={ system }
						onChange={ ( event ) => setSystem( event.target.value ) }
					>
						<option value="">—</option>
						<option value="metric">{ __( 'Metric', 'vvkit' ) }</option>
						<option value="imperial">{ __( 'Imperial', 'vvkit' ) }</option>
					</select>
				</div>

				<div className="form-field">
					<label htmlFor="vvkit-unit-factor">{ __( 'Conversion factor', 'vvkit' ) }</label>
					<input
						id="vvkit-unit-factor"
						type="text"
						className="small-text"
						value={ factor }
						onChange={ ( event ) => setFactor( event.target.value ) }
					/>
					<p>{ __( 'Grams (or ml) in 1 unit: kg = 1000, cup = 240, tbsp = 15…', 'vvkit' ) }</p>
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
		</Modal>
	);
}
