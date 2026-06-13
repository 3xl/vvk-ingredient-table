/**
 * Classic wp-admin notices (.notice.notice-*), rendered by React so they
 * blend with the rest of the admin instead of the Gutenberg snackbars.
 */
import { __ } from '@wordpress/i18n';

export default function Notices( { notices, onDismiss } ) {
	return notices.map( ( notice ) => (
		<div
			key={ notice.id }
			className={ `notice notice-${ notice.type } is-dismissible` }
		>
			<p>{ notice.message }</p>
			<button
				type="button"
				className="notice-dismiss"
				onClick={ () => onDismiss( notice.id ) }
			>
				<span className="screen-reader-text">
					{ __( 'Dismiss this notice.', 'vvkit' ) }
				</span>
			</button>
		</div>
	) );
}
