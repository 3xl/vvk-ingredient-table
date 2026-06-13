import { useCallback, useEffect, useState } from '@wordpress/element';
import { Button, Notice, SnackbarList, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import * as api from '../api';
import TablePanel from './TablePanel';

let snackbarId = 0;

export default function MetaboxApp( { postId } ) {
	const [ tables, setTables ] = useState( null );
	const [ ingredients, setIngredients ] = useState( [] );
	const [ units, setUnits ] = useState( [] );
	const [ activeId, setActiveId ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ snackbars, setSnackbars ] = useState( [] );

	const notify = useCallback( ( content ) => {
		const id = `vvkit-snackbar-${ snackbarId++ }`;
		setSnackbars( ( current ) => [ ...current, { id, content } ] );
	}, [] );

	const onError = useCallback(
		( e ) => setError( e?.message || __( 'Request failed.', 'vvkit' ) ),
		[]
	);

	useEffect( () => {
		Promise.all( [
			api.tables.list( postId ),
			api.items.list( 'ingredients' ),
			api.items.list( 'units' ),
		] )
			.then( ( [ tablesData, ingredientsData, unitsData ] ) => {
				setTables( tablesData );
				setIngredients( ingredientsData );
				setUnits( unitsData );
				setActiveId( tablesData.length ? tablesData[ 0 ].id : null );
			} )
			.catch( onError );
	}, [ postId, onError ] );

	if ( tables === null ) {
		return error ? (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		) : (
			<div className="vvkit-loading">
				<Spinner />
			</div>
		);
	}

	const active = tables.find( ( table ) => table.id === activeId ) || null;

	const replaceTable = ( updated ) =>
		setTables( ( current ) =>
			current.map( ( table ) => ( table.id === updated.id ? updated : table ) )
		);

	const addTable = async () => {
		try {
			const table = await api.tables.create( postId );
			setTables( [ ...tables, table ] );
			setActiveId( table.id );
			notify( __( 'Table created.', 'vvkit' ) );
		} catch ( e ) {
			onError( e );
		}
	};

	const removeTable = async ( table ) => {
		if (
			! window.confirm(
				__( 'Delete this table and all of its ingredient rows?', 'vvkit' )
			)
		) {
			return;
		}

		try {
			await api.tables.remove( table.id );
			const next = tables.filter( ( item ) => item.id !== table.id );
			setTables( next );
			setActiveId( next.length ? next[ 0 ].id : null );
			notify( __( 'Table deleted.', 'vvkit' ) );
		} catch ( e ) {
			onError( e );
		}
	};

	return (
		<div className="vvkit-app">
			{ error && (
				<Notice status="error" onRemove={ () => setError( null ) }>
					{ error }
				</Notice>
			) }

			<div className="vvkit-tabs">
				{ tables.map( ( table ) => (
					<Button
						key={ table.id }
						variant={ table.id === activeId ? 'primary' : 'secondary' }
						onClick={ () => setActiveId( table.id ) }
					>
						{ table.title || __( '(untitled)', 'vvkit' ) }
					</Button>
				) ) }
				<Button variant="tertiary" icon="plus" onClick={ addTable }>
					{ __( 'New table', 'vvkit' ) }
				</Button>
			</div>

			{ ! tables.length && (
				<p className="description">
					{ __(
						'No ingredient tables yet. Create the first one to list the recipe ingredients.',
						'vvkit'
					) }
				</p>
			) }

			{ active && (
				<TablePanel
					key={ active.id }
					table={ active }
					ingredients={ ingredients }
					units={ units }
					onChange={ replaceTable }
					onDelete={ () => removeTable( active ) }
					onError={ onError }
					notify={ notify }
				/>
			) }

			<SnackbarList
				className="vvkit-snackbars"
				notices={ snackbars }
				onRemove={ ( id ) =>
					setSnackbars( ( current ) => current.filter( ( n ) => n.id !== id ) )
				}
			/>
		</div>
	);
}
