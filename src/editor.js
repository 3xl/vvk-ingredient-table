/**
 * Gutenberg block 'vvkit/ingredients-table': pick one of the post's
 * ingredient tables and preview it inline (server-side render).
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder, SelectControl, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';

registerBlockType( 'vvkit/ingredients-table', {
	apiVersion: 3,
	title: __( 'Ingredients table', 'vvkit' ),
	description: __( 'Renders one of the post ingredient tables inline in the content.', 'vvkit' ),
	icon: 'carrot',
	category: 'widgets',
	attributes: {
		tableId: {
			type: 'number',
			default: 0,
		},
	},

	edit: ( { attributes, setAttributes } ) => {
		const { tableId } = attributes;
		const postId = useSelect(
			( select ) => select( 'core/editor' )?.getCurrentPostId(),
			[]
		);
		const [ tables, setTables ] = useState( null );

		useEffect( () => {
			if ( ! postId ) {
				return;
			}

			apiFetch( { path: `/vvkit/v1/tables?post_id=${ postId }` } )
				.then( setTables )
				.catch( () => setTables( [] ) );
		}, [ postId ] );

		const blockProps = useBlockProps();

		if ( tables === null ) {
			return (
				<div { ...blockProps }>
					<Placeholder icon="carrot" label={ __( 'Ingredients table', 'vvkit' ) }>
						<Spinner />
					</Placeholder>
				</div>
			);
		}

		if ( ! tables.length ) {
			return (
				<div { ...blockProps }>
					<Placeholder
						icon="carrot"
						label={ __( 'Ingredients table', 'vvkit' ) }
						instructions={ __(
							'No ingredient tables on this post yet. Create one from the “Ingredient tables” box below the editor, then select it here.',
							'vvkit'
						) }
					/>
				</div>
			);
		}

		const options = [
			{ label: __( 'First table of the post', 'vvkit' ), value: 0 },
			...tables.map( ( table ) => ( {
				label: table.title || `#${ table.id }`,
				value: table.id,
			} ) ),
		];

		return (
			<div { ...blockProps }>
				<SelectControl
					label={ __( 'Table', 'vvkit' ) }
					value={ tableId }
					options={ options }
					onChange={ ( value ) => setAttributes( { tableId: Number( value ) } ) }
					__nextHasNoMarginBottom
				/>
				<ServerSideRender
					block="vvkit/ingredients-table"
					attributes={ { tableId } }
				/>
			</div>
		);
	},

	save: () => null,
} );
