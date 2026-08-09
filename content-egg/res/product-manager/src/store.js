import { createReduxStore, register, select, subscribe } from '@wordpress/data';
import {
	addProductsRequest,
	aiRequest,
	bulkRemoveRequest,
	bulkUpdateRequest,
	getPostProducts,
	refreshRequest,
	removeProductRequest,
	reorderRequest,
	smartGroupsRequest,
	updateProductRequest,
	wooFlagRequest,
} from './api';
import { notifyAngularAdd, publishSnapshot } from './bridge';
import { applyOrderToModules } from './reorder';

export const STORE_NAME = 'content-egg/products';

const DEFAULT_STATE = {
	postId: 0,
	byModule: {},
	groups: [],
	revisions: {},
	saving: false,
	error: null,
};

// Serialize writes: one in-flight mutation per post. Each task runs after the
// previous settles (success or failure), so optimistic states never interleave.
let writeQueue = Promise.resolve();
function enqueueWrite( task ) {
	const run = writeQueue.then( task, task );
	writeQueue = run.catch( () => {} );
	return run;
}

// Bucket a flat [{ module_id, unique_id }] target list into
// { moduleId: Set<string unique_id> } for optimistic per-module mutation.
function bucketTargets( targets ) {
	const wanted = {};
	( targets || [] ).forEach( ( t ) => {
		const moduleId = t.module_id;
		( wanted[ moduleId ] = wanted[ moduleId ] || new Set() ).add(
			String( t.unique_id )
		);
	} );
	return wanted;
}

// Resolve the current post id across shells. Gutenberg exposes it via
// core/editor; classic/Woo/CPT screens have no such store, so fall back to the
// id localized by ProductManagerLoader (window.ceggPmConfig.postId) and finally
// to the classic edit form's hidden #post_ID field.
export function currentPostId() {
	const editor = select( 'core/editor' );
	const fromEditor =
		editor && editor.getCurrentPostId && editor.getCurrentPostId();
	if ( fromEditor ) {
		return fromEditor;
	}
	const fromConfig =
		typeof window !== 'undefined' &&
		window.ceggPmConfig &&
		Number( window.ceggPmConfig.postId );
	if ( fromConfig ) {
		return fromConfig;
	}
	const field =
		typeof document !== 'undefined' && document.getElementById( 'post_ID' );
	return field ? Number( field.value ) || 0 : 0;
}

function reducer( state = DEFAULT_STATE, action ) {
	switch ( action.type ) {
		case 'RECEIVE_PRODUCTS':
			return {
				...state,
				postId: action.postId || state.postId,
				byModule:
					action.byModule && typeof action.byModule === 'object'
						? action.byModule
						: {},
				groups: Array.isArray( action.groups ) ? action.groups : [],
				revisions:
					action.revisions && typeof action.revisions === 'object'
						? action.revisions
						: {},
			};
		case 'SET_MODULE_ITEMS':
			return {
				...state,
				byModule: {
					...state.byModule,
					[ action.moduleId ]: action.items,
				},
				revisions: {
					...state.revisions,
					[ action.moduleId ]: action.revision,
				},
			};
		case 'SET_SAVING':
			return { ...state, saving: action.saving };
		case 'SET_ERROR':
			return { ...state, error: action.error };
		default:
			return state;
	}
}

const selectors = {
	getByModule( state ) {
		return state.byModule;
	},
	getModuleItems( state, moduleId ) {
		return Array.isArray( state.byModule[ moduleId ] )
			? state.byModule[ moduleId ]
			: [];
	},
	getProducts( state ) {
		const all = Object.keys( state.byModule ).reduce(
			( acc, id ) => acc.concat( state.byModule[ id ] || [] ),
			[]
		);
		// CE stores products per module, but order_num is the GLOBAL position
		// the front-end renders by. Sort the flat roster by it so the sidebar
		// reflects — and can express — a cross-module order. Unnumbered items
		// (never reordered) keep their concat position, after numbered ones.
		return all
			.map( ( item, i ) => ( { item, i } ) )
			.sort( ( a, b ) => {
				const na = Number( a.item.order_num );
				const nb = Number( b.item.order_num );
				const va = Number.isFinite( na ) && na > 0 ? na : Infinity;
				const vb = Number.isFinite( nb ) && nb > 0 ? nb : Infinity;
				return va === vb ? a.i - b.i : va - vb;
			} )
			.map( ( pair ) => pair.item );
	},
	getProductsByModule( state, moduleId ) {
		return selectors.getModuleItems( state, moduleId );
	},
	getGroups( state ) {
		return state.groups;
	},
	getRevision( state, moduleId ) {
		return state.revisions[ moduleId ] || '';
	},
	getPostId( state ) {
		return state.postId || currentPostId();
	},
	isSaving( state ) {
		return state.saving;
	},
	getError( state ) {
		return state.error;
	},
};

// Action creators used by the resolver here; the write thunks are added in the
// next task. Defined inline so this file registers a complete store.
const actions = {
	receiveProducts( payload ) {
		return {
			type: 'RECEIVE_PRODUCTS',
			byModule: payload.by_module || {},
			revisions: payload.revisions || {},
			groups: payload.groups || [],
			postId: payload.postId,
		};
	},
	setModuleItems( moduleId, items, revision ) {
		return { type: 'SET_MODULE_ITEMS', moduleId, items, revision };
	},
	setSaving( saving ) {
		return { type: 'SET_SAVING', saving };
	},
	setError( error ) {
		return { type: 'SET_ERROR', error };
	},

	addProducts( moduleId, items ) {
		return ( { dispatch, select: sel } ) =>
			enqueueWrite( async () => {
				const postId = sel.getPostId();
				dispatch.setSaving( true );
				dispatch.setError( null );
				try {
					const response = await addProductsRequest( {
						postId,
						moduleId,
						items,
						revision: sel.getRevision( moduleId ),
					} );
					dispatch.setModuleItems(
						moduleId,
						response.items || [],
						response.revision
					);
					notifyAngularAdd( moduleId, response.items || [] );
					return response;
				} catch ( error ) {
					dispatch.setError( error?.message || 'Save failed.' );
					throw error;
				} finally {
					dispatch.setSaving( false );
				}
			} );
	},

	removeProduct( moduleId, uniqueId ) {
		return ( { dispatch, select: sel } ) =>
			enqueueWrite( async () => {
				const postId = sel.getPostId();
				const revision = sel.getRevision( moduleId );
				const prev = sel.getModuleItems( moduleId );
				const next = prev.filter(
					( item ) => String( item.unique_id ) !== String( uniqueId )
				);
				dispatch.setModuleItems( moduleId, next, revision ); // optimistic
				dispatch.setSaving( true );
				try {
					const response = await removeProductRequest( {
						postId,
						moduleId,
						uniqueId,
						revision,
					} );
					dispatch.setModuleItems(
						moduleId,
						response.items || [],
						response.revision
					);
					return response;
				} catch ( error ) {
					dispatch.setModuleItems( moduleId, prev, revision ); // rollback
					dispatch.setError( error?.message || 'Remove failed.' );
					throw error;
				} finally {
					dispatch.setSaving( false );
				}
			} );
	},

	updateProduct( moduleId, uniqueId, fields ) {
		return ( { dispatch, select: sel } ) =>
			enqueueWrite( async () => {
				const postId = sel.getPostId();
				const revision = sel.getRevision( moduleId );
				const prev = sel.getModuleItems( moduleId );
				const next = prev.map( ( item ) =>
					String( item.unique_id ) === String( uniqueId )
						? { ...item, ...fields }
						: item
				);
				dispatch.setModuleItems( moduleId, next, revision ); // optimistic
				dispatch.setSaving( true );
				try {
					const response = await updateProductRequest( {
						postId,
						moduleId,
						uniqueId,
						fields,
						revision,
					} );
					dispatch.setModuleItems(
						moduleId,
						response.items || [],
						response.revision
					);
					return response;
				} catch ( error ) {
					dispatch.setModuleItems( moduleId, prev, revision ); // rollback
					dispatch.setError( error?.message || 'Update failed.' );
					throw error;
				} finally {
					dispatch.setSaving( false );
				}
			} );
	},

	// Set a woo_sync/woo_attr flag with whole-post exclusivity. Non-optimistic:
	// the server clears the flag on every other item across modules, so we take
	// the authoritative products payload it returns rather than guessing.
	setWooFlag( moduleId, uniqueId, field, value ) {
		return ( { dispatch, select: sel } ) =>
			enqueueWrite( async () => {
				const postId = sel.getPostId();
				dispatch.setSaving( true );
				dispatch.setError( null );
				try {
					const response = await wooFlagRequest( {
						postId,
						moduleId,
						uniqueId,
						field,
						value,
					} );
					dispatch.receiveProducts( { ...response, postId } );
					return response;
				} catch ( error ) {
					dispatch.setError(
						error?.message || 'WooCommerce update failed.'
					);
					throw error;
				} finally {
					dispatch.setSaving( false );
				}
			} );
	},

	reorder( order ) {
		return ( { dispatch, select: sel } ) =>
			enqueueWrite( async () => {
				const postId = sel.getPostId();
				const prev = sel.getByModule();
				// Optimistic: snap every touched module into the new order now,
				// so the row moves instantly instead of waiting on the REST write.
				const next = applyOrderToModules( prev, order );
				Object.keys( next ).forEach( ( moduleId ) =>
					dispatch.setModuleItems(
						moduleId,
						next[ moduleId ],
						sel.getRevision( moduleId )
					)
				);
				dispatch.setSaving( true );
				try {
					const response = await reorderRequest( { postId, order } );
					// Re-sync authoritative order/revisions after a global rewrite.
					const data = await getPostProducts( postId );
					dispatch.receiveProducts( { ...data, postId } );
					return response;
				} catch ( error ) {
					// Rollback to the pre-drag order on failure.
					Object.keys( prev ).forEach( ( moduleId ) =>
						dispatch.setModuleItems(
							moduleId,
							prev[ moduleId ],
							sel.getRevision( moduleId )
						)
					);
					dispatch.setError( error?.message || 'Reorder failed.' );
					throw error;
				} finally {
					dispatch.setSaving( false );
				}
			} );
	},

	// Bucket a flat [{ module_id, unique_id }] target list into
	// { moduleId: Set<unique_id> } for optimistic per-module mutation.
	bulkRemove( targets ) {
		return ( { dispatch, select: sel } ) =>
			enqueueWrite( async () => {
				const postId = sel.getPostId();
				const prev = sel.getByModule();
				const wanted = bucketTargets( targets );
				// Optimistic: drop every targeted item from its module now, so
				// the rows disappear at once instead of one REST round-trip each.
				Object.keys( wanted ).forEach( ( moduleId ) => {
					const next = ( prev[ moduleId ] || [] ).filter(
						( item ) =>
							! wanted[ moduleId ].has( String( item.unique_id ) )
					);
					dispatch.setModuleItems(
						moduleId,
						next,
						sel.getRevision( moduleId )
					);
				} );
				dispatch.setSaving( true );
				try {
					const response = await bulkRemoveRequest( {
						postId,
						targets,
					} );
					// Re-sync authoritative items/revisions after the batch write.
					const data = await getPostProducts( postId );
					dispatch.receiveProducts( { ...data, postId } );
					return response;
				} catch ( error ) {
					Object.keys( prev ).forEach( ( moduleId ) =>
						dispatch.setModuleItems(
							moduleId,
							prev[ moduleId ],
							sel.getRevision( moduleId )
						)
					);
					dispatch.setError( error?.message || 'Remove failed.' );
					throw error;
				} finally {
					dispatch.setSaving( false );
				}
			} );
	},

	// Apply the same field patch to many products across modules in one request.
	bulkUpdate( targets, fields ) {
		return ( { dispatch, select: sel } ) =>
			enqueueWrite( async () => {
				const postId = sel.getPostId();
				const prev = sel.getByModule();
				const wanted = bucketTargets( targets );
				Object.keys( wanted ).forEach( ( moduleId ) => {
					const next = ( prev[ moduleId ] || [] ).map( ( item ) =>
						wanted[ moduleId ].has( String( item.unique_id ) )
							? { ...item, ...fields }
							: item
					);
					dispatch.setModuleItems(
						moduleId,
						next,
						sel.getRevision( moduleId )
					);
				} );
				dispatch.setSaving( true );
				try {
					const response = await bulkUpdateRequest( {
						postId,
						targets,
						fields,
					} );
					const data = await getPostProducts( postId );
					dispatch.receiveProducts( { ...data, postId } );
					return response;
				} catch ( error ) {
					Object.keys( prev ).forEach( ( moduleId ) =>
						dispatch.setModuleItems(
							moduleId,
							prev[ moduleId ],
							sel.getRevision( moduleId )
						)
					);
					dispatch.setError( error?.message || 'Update failed.' );
					throw error;
				} finally {
					dispatch.setSaving( false );
				}
			} );
	},

	refresh( type ) {
		return ( { dispatch, select: sel } ) =>
			enqueueWrite( async () => {
				const postId = sel.getPostId();
				dispatch.setSaving( true );
				try {
					const response = await refreshRequest( { postId, type } );
					const data = await getPostProducts( postId );
					dispatch.receiveProducts( { ...data, postId } );
					return response;
				} catch ( error ) {
					dispatch.setError( error?.message || 'Refresh failed.' );
					throw error;
				} finally {
					dispatch.setSaving( false );
				}
			} );
	},

	applyAi( moduleId, items, titleMethod, descriptionMethod ) {
		return ( { dispatch, select: sel } ) =>
			enqueueWrite( async () => {
				const postId = sel.getPostId();
				dispatch.setSaving( true );
				dispatch.setError( null );
				try {
					const response = await aiRequest( {
						postId,
						moduleId,
						items,
						titleMethod,
						descriptionMethod,
					} );
					if ( response.error ) {
						dispatch.setError( response.error );
						return { error: response.error };
					}
					dispatch.setModuleItems(
						moduleId,
						response.items || [],
						response.revision
					);
					return { previous: response.previous || [] };
				} catch ( error ) {
					dispatch.setError( error?.message || 'AI failed.' );
					throw error;
				} finally {
					dispatch.setSaving( false );
				}
			} );
	},

	applySmartGroups( method, targets = [] ) {
		return ( { dispatch, select: sel } ) =>
			enqueueWrite( async () => {
				const postId = sel.getPostId();
				dispatch.setSaving( true );
				dispatch.setError( null );
				try {
					const response = await smartGroupsRequest( {
						postId,
						method,
						targets,
					} );
					if ( response.error ) {
						dispatch.setError( response.error );
						return { error: response.error };
					}
					dispatch.receiveProducts( { ...response, postId } );
					return { previous: response.previous || [] };
				} catch ( error ) {
					dispatch.setError(
						error?.message || 'Smart groups failed.'
					);
					throw error;
				} finally {
					dispatch.setSaving( false );
				}
			} );
	},
};

const resolvers = {
	// Attached to getProducts (not getByModule) because that's the selector the
	// always-mounted sidebar reads on load; it's what triggers the initial GET.
	getProducts:
		() =>
		async ( { dispatch } ) => {
			const postId = currentPostId();
			if ( ! postId ) {
				return;
			}
			try {
				const data = await getPostProducts( postId );
				dispatch.receiveProducts( { ...data, postId } );
			} catch ( error ) {
				dispatch.setError(
					error?.message || 'Could not load products.'
				);
			}
		},
};

if ( ! window.__ceggProductsStoreRegistered ) {
	window.__ceggProductsStoreRegistered = true;
	register(
		createReduxStore( STORE_NAME, {
			reducer,
			actions,
			selectors,
			resolvers,
		} )
	);

	// Single publisher of the legacy snapshot: republish whenever byModule
	// changes so the 25 blocks + binding modals stay in sync without edits.
	let lastByModule = null;
	subscribe( () => {
		const s = select( STORE_NAME );
		if ( ! s ) {
			return;
		}
		const byModule = s.getByModule();
		if ( byModule === lastByModule ) {
			return;
		}
		lastByModule = byModule;
		publishSnapshot( byModule, s.getPostId() );
	}, STORE_NAME );
}
