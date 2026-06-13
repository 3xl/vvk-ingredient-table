/**
 * Thin wrappers around the plugin REST API (vvkit/v1).
 *
 * apiFetch is preconfigured by WordPress with the REST root URL and the
 * X-WP-Nonce middleware, so every request is authenticated and
 * CSRF-protected out of the box.
 */
import apiFetch from '@wordpress/api-fetch';

const NS = '/vvkit/v1';

export const tables = {
	list: ( postId ) => apiFetch( { path: `${ NS }/tables?post_id=${ postId }` } ),
	create: ( postId ) =>
		apiFetch( { path: `${ NS }/tables`, method: 'POST', data: { post_id: postId } } ),
	update: ( id, data ) =>
		apiFetch( { path: `${ NS }/tables/${ id }`, method: 'PUT', data } ),
	remove: ( id ) => apiFetch( { path: `${ NS }/tables/${ id }`, method: 'DELETE' } ),
};

export const rows = {
	create: ( tableId, data ) =>
		apiFetch( { path: `${ NS }/tables/${ tableId }/rows`, method: 'POST', data } ),
	update: ( id, data ) => apiFetch( { path: `${ NS }/rows/${ id }`, method: 'PUT', data } ),
	remove: ( id ) => apiFetch( { path: `${ NS }/rows/${ id }`, method: 'DELETE' } ),
	reorder: ( tableId, ids ) =>
		apiFetch( { path: `${ NS }/tables/${ tableId }/rows/order`, method: 'PUT', data: { ids } } ),
};

export const items = {
	list: ( resource ) => apiFetch( { path: `${ NS }/${ resource }` } ),
	get: ( resource, id ) => apiFetch( { path: `${ NS }/${ resource }/${ id }` } ),
	create: ( resource, name ) =>
		apiFetch( { path: `${ NS }/${ resource }`, method: 'POST', data: { name } } ),
	update: ( resource, id, data ) =>
		apiFetch( { path: `${ NS }/${ resource }/${ id }`, method: 'PUT', data } ),
	remove: ( resource, id ) =>
		apiFetch( { path: `${ NS }/${ resource }/${ id }`, method: 'DELETE' } ),
};

let productsPromise = null;

export const products = {
	/**
	 * Loaded once and cached: the result is shared by every row modal.
	 * Resolves to { available: bool, products: [ { id, name } ] }.
	 */
	list: () => {
		if ( ! productsPromise ) {
			productsPromise = apiFetch( { path: `${ NS }/products` } ).catch( () => {
				productsPromise = null;

				return { available: false, products: [] };
			} );
		}

		return productsPromise;
	},
};
