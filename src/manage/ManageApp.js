import { useEffect, useMemo, useState } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';

import * as api from '../api';
import IngredientModal from './IngredientModal';
import Notices from './Notices';
import UnitModal from './UnitModal';

let noticeId = 0;

const PER_PAGE = 20;

/**
 * Shared management screen for the ingredients and units catalogs.
 *
 * The markup mirrors the core taxonomy screens (edit-tags.php) so it
 * inherits the native admin styling: the search box sits in its own
 * .search-form above #col-container (which keeps both columns top-aligned),
 * the right column is a wp-list-table with a checkbox column, bulk actions,
 * native pagination, sortable headers, hover row actions and inline
 * Quick Edit. React only drives behavior; the look comes from core CSS.
 */
export default function ManageApp( { resource } ) {
	const isIngredients = resource === 'ingredients';

	const labels = isIngredients
		? {
				addTitle: __( 'Add New Ingredient', 'vvkit' ),
				addHelp: __( 'The name is how the ingredient appears in the recipe tables.', 'vvkit' ),
				searchLabel: __( 'Search Ingredients', 'vvkit' ),
				metaColumn: __( 'Tags & nutrition', 'vvkit' ),
				created: __( 'Ingredient created.', 'vvkit' ),
				updated: __( 'Ingredient updated.', 'vvkit' ),
				deleted: __( 'Ingredient deleted.', 'vvkit' ),
		  }
		: {
				addTitle: __( 'Add New Unit', 'vvkit' ),
				addHelp: __( 'Short name, e.g. gr, ml, tsp. Conversion details can be added with Edit.', 'vvkit' ),
				searchLabel: __( 'Search Units', 'vvkit' ),
				metaColumn: __( 'Conversion', 'vvkit' ),
				created: __( 'Unit created.', 'vvkit' ),
				updated: __( 'Unit updated.', 'vvkit' ),
				deleted: __( 'Unit deleted.', 'vvkit' ),
		  };

	const [ items, setItems ] = useState( null );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ sort, setSort ] = useState( { key: 'name', dir: 'asc' } );
	const [ newName, setNewName ] = useState( '' );
	const [ editing, setEditing ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ notices, setNotices ] = useState( [] );
	const [ selected, setSelected ] = useState( () => new Set() );
	const [ bulkAction, setBulkAction ] = useState( '-1' );
	const [ quickEdit, setQuickEdit ] = useState( null );

	const dismissNotice = ( id ) =>
		setNotices( ( current ) => current.filter( ( n ) => n.id !== id ) );

	const notify = ( type, message ) => {
		const id = `vvkit-notice-${ noticeId++ }`;
		setNotices( ( current ) => [ ...current, { id, type, message } ] );

		if ( 'success' === type ) {
			setTimeout( () => dismissNotice( id ), 4000 );
		}
	};

	const onError = ( e ) =>
		notify( 'error', e?.message || __( 'Request failed.', 'vvkit' ) );

	const reload = () =>
		api.items.list( resource ).then( setItems ).catch( onError );

	useEffect( () => {
		api.items.list( resource ).then( setItems ).catch( onError );
	}, [ resource ] );

	const filtered = useMemo( () => {
		if ( items === null ) {
			return [];
		}

		const query = search.trim().toLowerCase();
		const result = query
			? items.filter( ( item ) => item.name.toLowerCase().includes( query ) )
			: [ ...items ];

		const direction = sort.dir === 'asc' ? 1 : -1;

		result.sort( ( a, b ) =>
			sort.key === 'usage'
				? ( a.usage - b.usage ) * direction
				: a.name.localeCompare( b.name ) * direction
		);

		return result;
	}, [ items, search, sort ] );

	const totalPages = Math.max( 1, Math.ceil( filtered.length / PER_PAGE ) );
	const currentPage = Math.min( page, totalPages );
	const pageItems = filtered.slice(
		( currentPage - 1 ) * PER_PAGE,
		currentPage * PER_PAGE
	);

	if ( items === null ) {
		return (
			<>
				<Notices notices={ notices } onDismiss={ dismissNotice } />
				<Spinner />
			</>
		);
	}

	const sortByName = ( list ) =>
		[ ...list ].sort( ( a, b ) => a.name.localeCompare( b.name ) );

	// Navigation resets transient row state (selection / open quick edit),
	// matching the per-page behavior of the server-rendered core tables.
	const resetTransient = () => {
		setSelected( new Set() );
		setQuickEdit( null );
	};

	const goToPage = ( next ) => {
		setPage( Math.min( Math.max( 1, next ), totalPages ) );
		resetTransient();
	};

	const changeSearch = ( value ) => {
		setSearch( value );
		setPage( 1 );
		resetTransient();
	};

	const toggleSort = ( key ) => {
		setSort( ( current ) =>
			current.key === key
				? { key, dir: current.dir === 'asc' ? 'desc' : 'asc' }
				: { key, dir: key === 'usage' ? 'desc' : 'asc' }
		);
		setPage( 1 );
		resetTransient();
	};

	const add = async () => {
		const name = newName.trim();

		if ( ! name ) {
			return;
		}

		setBusy( true );

		try {
			const item = await api.items.create( resource, name );
			setItems( sortByName( [ ...items, item ] ) );
			setNewName( '' );
			notify( 'success', labels.created );
		} catch ( e ) {
			onError( e );
		} finally {
			setBusy( false );
		}
	};

	const mergeItem = ( updated ) =>
		setItems( ( current ) =>
			sortByName(
				current.map( ( item ) => {
					if ( item.id !== updated.id ) {
						return item;
					}

					const merged = { ...item, ...updated };

					if ( 'nutrition' in updated ) {
						merged.has_nutrition = !! updated.nutrition;
					}

					return merged;
				} )
			)
		);

	const saveEdit = async ( payload ) => {
		if ( ! editing ) {
			return;
		}

		setBusy( true );

		try {
			mergeItem( await api.items.update( resource, editing.id, payload ) );
			setEditing( null );
			notify( 'success', labels.updated );
		} catch ( e ) {
			onError( e );
		} finally {
			setBusy( false );
		}
	};

	const saveQuickEdit = async () => {
		const name = quickEdit.name.trim();

		if ( ! name ) {
			return;
		}

		setBusy( true );

		try {
			mergeItem( await api.items.update( resource, quickEdit.id, { name } ) );
			setQuickEdit( null );
			notify( 'success', labels.updated );
		} catch ( e ) {
			onError( e );
		} finally {
			setBusy( false );
		}
	};

	const confirmUsage = ( item ) => {
		const warning = isIngredients
			? sprintf(
					/* translators: 1: ingredient name, 2: number of recipe rows. */
					_n(
						'Delete "%1$s"? It will also be removed from %2$d recipe row.',
						'Delete "%1$s"? It will also be removed from %2$d recipe rows.',
						item.usage,
						'vvkit'
					),
					item.name,
					item.usage
			  )
			: sprintf(
					/* translators: 1: unit name, 2: number of recipe rows. */
					_n(
						'Delete "%1$s"? %2$d recipe row will lose its unit.',
						'Delete "%1$s"? %2$d recipe rows will lose their unit.',
						item.usage,
						'vvkit'
					),
					item.name,
					item.usage
			  );

		return item.usage
			? warning
			: sprintf(
					/* translators: %s: item name. */
					__( 'Delete "%s"?', 'vvkit' ),
					item.name
			  );
	};

	const remove = async ( item ) => {
		if ( ! window.confirm( confirmUsage( item ) ) ) {
			return;
		}

		try {
			await api.items.remove( resource, item.id );
			setItems( items.filter( ( current ) => current.id !== item.id ) );
			setSelected( ( current ) => {
				const next = new Set( current );
				next.delete( item.id );
				return next;
			} );
			notify( 'success', labels.deleted );
		} catch ( e ) {
			onError( e );
		}
	};

	const applyBulk = async () => {
		if ( bulkAction !== 'delete' || ! selected.size ) {
			return;
		}

		const ids = [ ...selected ];
		const chosen = items.filter( ( item ) => selected.has( item.id ) );
		const totalUsage = chosen.reduce( ( sum, item ) => sum + item.usage, 0 );

		let message = sprintf(
			/* translators: %d: number of selected items. */
			_n(
				'Delete the %d selected item?',
				'Delete the %d selected items?',
				ids.length,
				'vvkit'
			),
			ids.length
		);

		if ( totalUsage ) {
			message +=
				'\n' +
				( isIngredients
					? sprintf(
							/* translators: %d: number of recipe rows. */
							_n(
								'It will also be removed from %d recipe row.',
								'They will also be removed from %d recipe rows.',
								totalUsage,
								'vvkit'
							),
							totalUsage
					  )
					: sprintf(
							/* translators: %d: number of recipe rows. */
							_n(
								'%d recipe row will lose its unit.',
								'%d recipe rows will lose their unit.',
								totalUsage,
								'vvkit'
							),
							totalUsage
					  ) );
		}

		if ( ! window.confirm( message ) ) {
			return;
		}

		setBusy( true );

		try {
			await Promise.all( ids.map( ( id ) => api.items.remove( resource, id ) ) );
			setItems( items.filter( ( item ) => ! selected.has( item.id ) ) );
			setSelected( new Set() );
			setBulkAction( '-1' );
			notify(
				'success',
				sprintf(
					/* translators: %d: number of deleted items. */
					_n( '%d item deleted.', '%d items deleted.', ids.length, 'vvkit' ),
					ids.length
				)
			);
		} catch ( e ) {
			onError( e );
			reload();
			setSelected( new Set() );
		} finally {
			setBusy( false );
		}
	};

	const toggleOne = ( id, checked ) =>
		setSelected( ( current ) => {
			const next = new Set( current );

			if ( checked ) {
				next.add( id );
			} else {
				next.delete( id );
			}

			return next;
		} );

	const pageIds = pageItems.map( ( item ) => item.id );
	const allChecked = pageIds.length > 0 && pageIds.every( ( id ) => selected.has( id ) );

	const toggleAll = ( checked ) =>
		setSelected( ( current ) => {
			const next = new Set( current );

			pageIds.forEach( ( id ) => {
				if ( checked ) {
					next.add( id );
				} else {
					next.delete( id );
				}
			} );

			return next;
		} );

	const unitMeta = ( item ) => {
		if ( ! item.dimension ) {
			return '—';
		}

		const parts = [
			item.dimension === 'mass' ? __( 'mass', 'vvkit' ) : __( 'volume', 'vvkit' ),
		];

		if ( item.system ) {
			parts.push(
				item.system === 'metric' ? __( 'metric', 'vvkit' ) : __( 'imperial', 'vvkit' )
			);
		}

		if ( item.factor ) {
			parts.push( '= ' + item.factor + ' ' + ( item.dimension === 'mass' ? 'g' : 'ml' ) );
		}

		return parts.join( ' · ' );
	};

	const ingredientMeta = ( item ) => (
		<>
			{ item.allergens.map( ( tag ) => (
				<span key={ `a-${ tag }` } className="vvkit-chip vvkit-chip--allergen">
					{ tag }
				</span>
			) ) }
			{ item.diets.map( ( tag ) => (
				<span key={ `d-${ tag }` } className="vvkit-chip vvkit-chip--diet">
					{ tag }
				</span>
			) ) }
			{ item.has_nutrition && (
				<span
					className="dashicons dashicons-chart-pie vvkit-nutrition-flag"
					title={ __( 'Nutrition facts available', 'vvkit' ) }
				/>
			) }
		</>
	);

	const sortableHeader = ( key, label ) => {
		const isSorted = sort.key === key;
		const classes = [
			'manage-column',
			key === 'name' ? 'column-name column-primary' : 'column-posts num',
			isSorted ? `sorted ${ sort.dir }` : 'sortable asc',
		].join( ' ' );

		return (
			<th scope="col" className={ classes }>
				<a
					href={ `#sort-${ key }` }
					onClick={ ( event ) => {
						event.preventDefault();
						toggleSort( key );
					} }
				>
					<span>{ label }</span>
					<span className="sorting-indicators">
						<span className="sorting-indicator asc" aria-hidden="true"></span>
						<span className="sorting-indicator desc" aria-hidden="true"></span>
					</span>
				</a>
			</th>
		);
	};

	const bulkActions = ( which ) => (
		<div className="alignleft actions bulkactions">
			<label
				htmlFor={ `bulk-action-selector-${ which }` }
				className="screen-reader-text"
			>
				{ __( 'Select bulk action', 'vvkit' ) }
			</label>
			<select
				name={ which === 'top' ? 'action' : 'action2' }
				id={ `bulk-action-selector-${ which }` }
				value={ bulkAction }
				onChange={ ( event ) => setBulkAction( event.target.value ) }
			>
				<option value="-1">{ __( 'Bulk actions', 'vvkit' ) }</option>
				<option value="delete">{ __( 'Delete', 'vvkit' ) }</option>
			</select>
			<button
				type="button"
				className="button action"
				disabled={ busy || bulkAction !== 'delete' || ! selected.size }
				onClick={ applyBulk }
			>
				{ __( 'Apply', 'vvkit' ) }
			</button>
		</div>
	);

	const pageButton = ( { disabled, glyph, onClick, srLabel, navClass } ) =>
		disabled ? (
			<span className="tablenav-pages-navspan button disabled" aria-hidden="true">
				{ glyph }
			</span>
		) : (
			<button type="button" className={ `${ navClass } button` } onClick={ onClick }>
				<span className="screen-reader-text">{ srLabel }</span>
				<span aria-hidden="true">{ glyph }</span>
			</button>
		);

	const pagination = ( which ) => (
		<div className={ `tablenav-pages${ totalPages < 2 ? ' one-page' : '' }` }>
			<span className="displaying-num">
				{ sprintf(
					/* translators: %d: number of items. */
					_n( '%d item', '%d items', filtered.length, 'vvkit' ),
					filtered.length
				) }
			</span>
			<span className="pagination-links">
				{ pageButton( {
					disabled: currentPage === 1,
					glyph: '«',
					navClass: 'first-page',
					srLabel: __( 'First page', 'vvkit' ),
					onClick: () => goToPage( 1 ),
				} ) }
				{ pageButton( {
					disabled: currentPage === 1,
					glyph: '‹',
					navClass: 'prev-page',
					srLabel: __( 'Previous page', 'vvkit' ),
					onClick: () => goToPage( currentPage - 1 ),
				} ) }
				{ which === 'top' ? (
					<span className="paging-input">
						<label htmlFor="vvkit-current-page" className="screen-reader-text">
							{ __( 'Current Page', 'vvkit' ) }
						</label>
						<input
							key={ currentPage }
							className="current-page"
							id="vvkit-current-page"
							type="text"
							defaultValue={ currentPage }
							size={ String( totalPages ).length }
							aria-describedby="vvkit-table-paging"
							onKeyDown={ ( event ) => {
								if ( event.key === 'Enter' ) {
									event.preventDefault();
									goToPage( parseInt( event.target.value, 10 ) || 1 );
								}
							} }
							onBlur={ ( event ) =>
								goToPage( parseInt( event.target.value, 10 ) || 1 )
							}
						/>
						<span className="tablenav-paging-text">
							{ ' ' }
							{ __( 'of', 'vvkit' ) }{ ' ' }
							<span className="total-pages">{ totalPages }</span>
						</span>
					</span>
				) : (
					<span id="vvkit-table-paging" className="paging-input">
						<span className="tablenav-paging-text">
							{ currentPage } { __( 'of', 'vvkit' ) }{ ' ' }
							<span className="total-pages">{ totalPages }</span>
						</span>
					</span>
				) }
				{ pageButton( {
					disabled: currentPage === totalPages,
					glyph: '›',
					navClass: 'next-page',
					srLabel: __( 'Next page', 'vvkit' ),
					onClick: () => goToPage( currentPage + 1 ),
				} ) }
				{ pageButton( {
					disabled: currentPage === totalPages,
					glyph: '»',
					navClass: 'last-page',
					srLabel: __( 'Last page', 'vvkit' ),
					onClick: () => goToPage( totalPages ),
				} ) }
			</span>
		</div>
	);

	const tablenav = ( which ) => (
		<div className={ `tablenav ${ which }` }>
			{ bulkActions( which ) }
			{ pagination( which ) }
			<br className="clear" />
		</div>
	);

	const COLSPAN = 4;

	const renderRow = ( item ) => {
		if ( quickEdit && quickEdit.id === item.id ) {
			return (
				<tr key={ item.id } id={ `vvkit-edit-${ item.id }` } className="inline-edit-row inline-editor">
					<td colSpan={ COLSPAN } className="colspanchange">
						<div className="inline-edit-wrapper">
							<fieldset>
								<legend className="inline-edit-legend">
									{ __( 'Quick Edit', 'vvkit' ) }
								</legend>
								<div className="inline-edit-col">
									<label>
										<span className="title">{ __( 'Name', 'vvkit' ) }</span>
										<span className="input-text-wrap">
											<input
												type="text"
												name="name"
												className="ptitle"
												value={ quickEdit.name }
												autoFocus
												onChange={ ( event ) =>
													setQuickEdit( {
														...quickEdit,
														name: event.target.value,
													} )
												}
												onKeyDown={ ( event ) => {
													if ( event.key === 'Enter' ) {
														event.preventDefault();
														saveQuickEdit();
													}

													if ( event.key === 'Escape' ) {
														setQuickEdit( null );
													}
												} }
											/>
										</span>
									</label>
								</div>
							</fieldset>
							<div className="inline-edit-save submit">
								<button
									type="button"
									className="save button button-primary"
									disabled={ busy || ! quickEdit.name.trim() }
									onClick={ saveQuickEdit }
								>
									{ __( 'Update', 'vvkit' ) }
								</button>
								<button
									type="button"
									className="cancel button"
									onClick={ () => setQuickEdit( null ) }
								>
									{ __( 'Cancel', 'vvkit' ) }
								</button>
							</div>
						</div>
					</td>
				</tr>
			);
		}

		return (
			<tr key={ item.id }>
				<th scope="row" className="check-column">
					<input
						id={ `cb-select-${ item.id }` }
						type="checkbox"
						checked={ selected.has( item.id ) }
						onChange={ ( event ) => toggleOne( item.id, event.target.checked ) }
					/>
					<label htmlFor={ `cb-select-${ item.id }` }>
						<span className="screen-reader-text">
							{ sprintf(
								/* translators: %s: item name. */
								__( 'Select %s', 'vvkit' ),
								item.name
							) }
						</span>
					</label>
				</th>
				<td
					className="name column-name has-row-actions column-primary"
					data-colname={ __( 'Name', 'vvkit' ) }
				>
					<strong>{ item.name }</strong>
					<div className="row-actions">
						<span className="edit">
							<button
								type="button"
								className="button-link"
								onClick={ () => setEditing( item ) }
							>
								{ __( 'Edit', 'vvkit' ) }
							</button>
							{ ' | ' }
						</span>
						<span className="inline hide-if-no-js">
							<button
								type="button"
								className="button-link editinline"
								aria-label={ sprintf(
									/* translators: %s: item name. */
									__( 'Quick edit “%s” inline', 'vvkit' ),
									item.name
								) }
								onClick={ () =>
									setQuickEdit( { id: item.id, name: item.name } )
								}
							>
								{ __( 'Quick Edit', 'vvkit' ) }
							</button>
							{ ' | ' }
						</span>
						<span className="delete">
							<button
								type="button"
								className="button-link button-link-delete"
								onClick={ () => remove( item ) }
							>
								{ __( 'Delete', 'vvkit' ) }
							</button>
						</span>
					</div>
					<button type="button" className="toggle-row">
						<span className="screen-reader-text">
							{ __( 'Show more details', 'vvkit' ) }
						</span>
					</button>
				</td>
				<td data-colname={ labels.metaColumn }>
					{ isIngredients ? ingredientMeta( item ) : unitMeta( item ) }
				</td>
				<td className="posts column-posts num" data-colname={ __( 'Used in', 'vvkit' ) }>
					{ item.usage }
				</td>
			</tr>
		);
	};

	return (
		<>
			<Notices notices={ notices } onDismiss={ dismissNotice } />

			<form
				className="search-form wp-clearfix"
				onSubmit={ ( event ) => event.preventDefault() }
			>
				<p className="search-box">
					<label className="screen-reader-text" htmlFor="vvkit-search-input">
						{ labels.searchLabel }
					</label>
					<input
						type="search"
						id="vvkit-search-input"
						value={ search }
						onChange={ ( event ) => changeSearch( event.target.value ) }
					/>
					<button type="submit" className="button">
						{ labels.searchLabel }
					</button>
				</p>
			</form>

			<div id="col-container" className="wp-clearfix">
				<div id="col-left">
					<div className="col-wrap">
						<div className="form-wrap">
							<h2>{ labels.addTitle }</h2>
							<div className="form-field form-required">
								<label htmlFor="vvkit-add-name">{ __( 'Name', 'vvkit' ) }</label>
								<input
									id="vvkit-add-name"
									type="text"
									value={ newName }
									aria-required="true"
									onChange={ ( event ) => setNewName( event.target.value ) }
									onKeyDown={ ( event ) => event.key === 'Enter' && add() }
								/>
								<p>{ labels.addHelp }</p>
							</div>
							<p className="submit">
								<button
									type="button"
									className="button button-primary"
									disabled={ busy || ! newName.trim() }
									onClick={ add }
								>
									{ labels.addTitle }
								</button>
							</p>
						</div>
					</div>
				</div>

				<div id="col-right">
					<div className="col-wrap">
						{ tablenav( 'top' ) }

						<table className="wp-list-table widefat fixed striped table-view-list">
							<thead>
								<tr>
									<td id="cb" className="manage-column column-cb check-column">
										<label className="screen-reader-text" htmlFor="vvkit-cb-all-1">
											{ __( 'Select All', 'vvkit' ) }
										</label>
										<input
											id="vvkit-cb-all-1"
											type="checkbox"
											checked={ allChecked }
											onChange={ ( event ) => toggleAll( event.target.checked ) }
										/>
									</td>
									{ sortableHeader( 'name', __( 'Name', 'vvkit' ) ) }
									<th scope="col" className="manage-column">
										{ labels.metaColumn }
									</th>
									{ sortableHeader( 'usage', __( 'Used in', 'vvkit' ) ) }
								</tr>
							</thead>
							<tbody id="the-list">
								{ ! pageItems.length && (
									<tr className="no-items">
										<td className="colspanchange" colSpan={ COLSPAN }>
											{ __( 'Nothing found.', 'vvkit' ) }
										</td>
									</tr>
								) }
								{ pageItems.map( renderRow ) }
							</tbody>
							<tfoot>
								<tr>
									<td className="manage-column column-cb check-column">
										<label className="screen-reader-text" htmlFor="vvkit-cb-all-2">
											{ __( 'Select All', 'vvkit' ) }
										</label>
										<input
											id="vvkit-cb-all-2"
											type="checkbox"
											checked={ allChecked }
											onChange={ ( event ) => toggleAll( event.target.checked ) }
										/>
									</td>
									<th scope="col" className="manage-column column-name column-primary">
										{ __( 'Name', 'vvkit' ) }
									</th>
									<th scope="col" className="manage-column">
										{ labels.metaColumn }
									</th>
									<th scope="col" className="manage-column column-posts num">
										{ __( 'Used in', 'vvkit' ) }
									</th>
								</tr>
							</tfoot>
						</table>

						{ tablenav( 'bottom' ) }
					</div>
				</div>
			</div>

			{ editing && isIngredients && (
				<IngredientModal
					ingredient={ editing }
					busy={ busy }
					onSave={ saveEdit }
					onClose={ () => setEditing( null ) }
					onError={ onError }
				/>
			) }

			{ editing && ! isIngredients && (
				<UnitModal
					unit={ editing }
					busy={ busy }
					onSave={ saveEdit }
					onClose={ () => setEditing( null ) }
					onError={ onError }
				/>
			) }
		</>
	);
}
