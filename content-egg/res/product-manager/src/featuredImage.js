import { dispatch, select } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { setFeaturedImageRequest } from './api';

// Set a stored image item as the post's featured image, then confirm with a
// snackbar toast and refresh the editor's Featured Image panel.
export async function setItemAsFeatured( postId, moduleId, uniqueId ) {
	try {
		await setFeaturedImageRequest( { postId, moduleId, uniqueId } );
		refreshFeaturedImagePanel( postId );
		dispatch( 'core/notices' ).createSuccessNotice(
			__( 'Featured image set.', 'content-egg' ),
			{ type: 'snackbar' }
		);
	} catch ( e ) {
		dispatch( 'core/notices' ).createErrorNotice(
			e?.message ||
				__( 'Could not set the featured image.', 'content-egg' ),
			{ type: 'snackbar' }
		);
	}
}

// Refetch the post entity so the editor's Featured Image panel reflects the new
// thumbnail as the SAVED value — a real attachment (local mode) or Content Egg's
// external "fake" id (external mode), which is the same mechanism that shows an
// external featured image on reload. Using the saved value (not editPost) avoids
// a dirty edit — a fake external id set as an edit would break the next save.
function refreshFeaturedImagePanel( postId ) {
	try {
		const editor = select( 'core/editor' );
		const postType =
			editor &&
			typeof editor.getCurrentPostType === 'function' &&
			editor.getCurrentPostType();
		if ( postType && postId ) {
			dispatch( 'core' ).invalidateResolution( 'getEntityRecord', [
				'postType',
				postType,
				postId,
			] );
		}
	} catch ( e ) {
		// no block editor / core-data store — panel refreshes on reload
	}
}
