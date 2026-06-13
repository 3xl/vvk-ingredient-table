import { createRoot } from '@wordpress/element';

import MetaboxApp from './metabox/MetaboxApp';
import ManageApp from './manage/ManageApp';

const metaboxRoot = document.getElementById( 'vvkit-metabox-root' );

if ( metaboxRoot ) {
	const postId = Number( metaboxRoot.dataset.postId );

	if ( Number.isFinite( postId ) && postId > 0 ) {
		createRoot( metaboxRoot ).render( <MetaboxApp postId={ postId } /> );
	}
}

const manageRoot = document.getElementById( 'vvkit-manage' );

if ( manageRoot ) {
	createRoot( manageRoot ).render(
		<ManageApp resource={ manageRoot.dataset.resource } />
	);
}
