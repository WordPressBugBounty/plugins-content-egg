import apiFetch from '@wordpress/api-fetch';

export function fetchModules( types = 'PRODUCT' ) {
	const params = new URLSearchParams();
	if ( types ) {
		params.set( 'types', types );
	}
	// Pass the edited post so the content_egg_metabox_modules filter can hide
	// modules per-post (legacy $post parity).
	const postId = window.ceggPmConfig?.postId;
	if ( postId ) {
		params.set( 'post_id', String( postId ) );
	}
	const query = params.toString();
	return apiFetch( { path: `/cegg/v1/modules${ query ? `?${ query }` : '' }` } );
}

export function searchProducts( {
	moduleId,
	keyword,
	filters = {},
	postId = 0,
	signal,
} ) {
	return apiFetch( {
		path: '/cegg/v1/search',
		method: 'POST',
		data: {
			module_id: moduleId,
			keyword,
			filters,
			post_id: postId,
		},
		signal,
	} );
}

export function addProducts( { postId, moduleId, items, keyword = '' } ) {
	return apiFetch( {
		path: `/cegg/v1/posts/${ postId }/products`,
		method: 'POST',
		data: { module_id: moduleId, items, keyword },
	} );
}

export function getPostProducts( postId ) {
	return apiFetch( { path: `/cegg/v1/posts/${ postId }/products` } );
}

export function addProductsRequest( {
	postId,
	moduleId,
	items,
	revision = '',
} ) {
	return apiFetch( {
		path: `/cegg/v1/posts/${ postId }/products`,
		method: 'POST',
		data: { module_id: moduleId, items, revision },
	} );
}

export function updateProductRequest( {
	postId,
	moduleId,
	uniqueId,
	fields,
	revision = '',
} ) {
	return apiFetch( {
		path: `/cegg/v1/posts/${ postId }/products/${ moduleId }/${ encodeURIComponent(
			uniqueId
		) }`,
		method: 'PATCH',
		data: { fields, revision },
	} );
}

export function removeProductRequest( {
	postId,
	moduleId,
	uniqueId,
	revision = '',
} ) {
	return apiFetch( {
		path: `/cegg/v1/posts/${ postId }/products/${ moduleId }/${ encodeURIComponent(
			uniqueId
		) }`,
		method: 'DELETE',
		data: { revision },
	} );
}

export function bulkRemoveRequest( { postId, targets } ) {
	return apiFetch( {
		path: `/cegg/v1/posts/${ postId }/products/bulk`,
		method: 'DELETE',
		data: { targets },
	} );
}

export function bulkUpdateRequest( { postId, targets, fields } ) {
	return apiFetch( {
		path: `/cegg/v1/posts/${ postId }/products/bulk`,
		method: 'PATCH',
		data: { targets, fields },
	} );
}

export function reorderRequest( { postId, order } ) {
	return apiFetch( {
		path: `/cegg/v1/posts/${ postId }/products/order`,
		method: 'PUT',
		data: { order },
	} );
}

export function refreshRequest( { postId, type } ) {
	return apiFetch( {
		path: `/cegg/v1/posts/${ postId }/refresh`,
		method: 'POST',
		data: { type },
	} );
}

// Whether a listings (keyword) update would find a keyword to search — drives
// whether the "Update listings" action is enabled. → { available: boolean }
export function keywordStatusRequest( { postId } ) {
	return apiFetch( {
		path: `/cegg/v1/posts/${ postId }/products/keyword-status`,
	} );
}

export function aiRequest( {
	postId,
	moduleId,
	items,
	titleMethod = '',
	descriptionMethod = '',
} ) {
	return apiFetch( {
		path: `/cegg/v1/posts/${ postId }/ai`,
		method: 'POST',
		data: {
			module_id: moduleId,
			items,
			title_method: titleMethod,
			description_method: descriptionMethod,
		},
	} );
}

export function smartGroupsRequest( { postId, method, targets = [] } ) {
	return apiFetch( {
		path: `/cegg/v1/posts/${ postId }/smart-groups`,
		method: 'POST',
		data: { method, targets },
	} );
}

export function fetchUpdateSettings( postId, family ) {
	const q = family ? `?family=${ encodeURIComponent( family ) }` : '';
	return apiFetch( {
		path: `/cegg/v1/posts/${ postId }/update-settings${ q }`,
	} );
}

// Only PRODUCT sends global_keyword — the global keyword is product-scoped, and
// omitting the field leaves the stored value untouched for other families.
export function saveUpdateSettings( {
	postId,
	globalKeyword,
	modules,
	family,
} ) {
	const data = { modules, family };
	if ( family === 'PRODUCT' ) {
		data.global_keyword = globalKeyword;
	}
	return apiFetch( {
		path: `/cegg/v1/posts/${ postId }/update-settings`,
		method: 'PUT',
		data,
	} );
}

// Force a stored image item as the post's featured image. → { external,
// attachment_id, thumbnail_url }
export function setFeaturedImageRequest( { postId, moduleId, uniqueId } ) {
	return apiFetch( {
		path: `/cegg/v1/posts/${ postId }/featured-image`,
		method: 'POST',
		data: { module_id: moduleId, unique_id: uniqueId },
	} );
}

export function wooFlagRequest( { postId, moduleId, uniqueId, field, value } ) {
	return apiFetch( {
		path: `/cegg/v1/posts/${ postId }/woo-flag`,
		method: 'PUT',
		data: { module_id: moduleId, unique_id: uniqueId, field, value },
	} );
}
