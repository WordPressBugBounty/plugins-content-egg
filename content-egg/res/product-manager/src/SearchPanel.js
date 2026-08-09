import { Button, Notice, SearchControl, Spinner } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { searchProducts } from './api';
import { STORE_NAME } from './store';
import FilterFields from './FilterFields';
import ModuleChips from './ModuleChips';
import ResultRow from './ResultRow';
import CouponResultRow from './CouponResultRow';
import MediaResultRow from './MediaResultRow';
import { setItemAsFeatured } from './featuredImage';
import useModules from './useModules';
import { getKey } from './keys';

// Adapt the input placeholder to what the SELECTED modules actually accept.
// With several selected, advertise only the capabilities they ALL share (the
// narrowest set), so we never suggest pasting a URL/EAN a module can't use.
const searchPlaceholder = ( selectedModules ) => {
	// Legacy per-module override (content_egg_keyword_input_placeholder): honor it
	// only when exactly one module is targeted — the shared box can't show a
	// per-module hint for a mixed selection.
	if (
		selectedModules.length === 1 &&
		selectedModules[ 0 ].keyword_placeholder
	) {
		return selectedModules[ 0 ].keyword_placeholder;
	}
	if ( ! selectedModules.length ) {
		return __( 'Keyword or product URL…', 'content-egg' );
	}
	const url = selectedModules.every( ( module ) => module.url_search );
	const gtin = selectedModules.every( ( module ) => module.gtin_search );
	if ( url && gtin ) {
		return __( 'Keyword, product URL, or EAN…', 'content-egg' );
	}
	if ( url ) {
		return __( 'Keyword or product URL…', 'content-egg' );
	}
	if ( gtin ) {
		return __( 'Keyword or EAN…', 'content-egg' );
	}
	return __( 'Keyword…', 'content-egg' );
};

const PRICE_KEYS = [ 'minimum_price', 'maximum_price' ];

// Which filter fields to show for the current selection:
// - one module  → its full filters, minus price if it can't actually filter it;
// - many modules → only the price filter, and only when ALL support it (locale
//   is module-specific, so it can't be shared);
// - none         → nothing.
const filtersToShow = ( selectedModules ) => {
	if ( selectedModules.length === 0 ) {
		return [];
	}
	if ( selectedModules.length === 1 ) {
		const only = selectedModules[ 0 ];
		return ( only.search_filters || [] ).filter( ( filter ) =>
			PRICE_KEYS.includes( filter.key ) ? only.price_filter : true
		);
	}
	if ( ! selectedModules.every( ( module ) => module.price_filter ) ) {
		return [];
	}
	return ( selectedModules[ 0 ].search_filters || [] ).filter( ( filter ) =>
		PRICE_KEYS.includes( filter.key )
	);
};

// Poll every 5s while a background feed import fills the catalog
// (5s * 60 = 5 minutes before giving up), mirroring the Angular metabox.
const FEED_IMPORT_RETRY_DELAY = 5000;
const FEED_IMPORT_MAX_RETRIES = 60;

export default function SearchPanel( {
	postId,
	existingKeys = [],
	onAdded,
	allowAddAll = true,
	initialCompareEan = '',
	family = 'PRODUCT',
} ) {
	const isCoupon = family === 'COUPON';
	const isMedia = family === 'IMAGE' || family === 'VIDEO';
	const byFamily = ( c, m, p ) => {
		if ( isCoupon ) {
			return c;
		}
		if ( isMedia ) {
			return m;
		}
		return p;
	};
	const {
		modules: allModules,
		isLoading: modulesLoading,
		error: modulesError,
	} = useModules( { types: family } );
	// The Search tab only lists modules that can actually search — manual-entry
	// modules (Offer) are added via "Add product", not searched. Memoized so the
	// reference stays stable (several effects below depend on `modules`).
	const modules = useMemo(
		() => allModules.filter( ( module ) => module.searchable !== false ),
		[ allModules ]
	);
	const [ keyword, setKeyword ] = useState( '' );
	const [ selectedIds, setSelectedIds ] = useState( [] );
	const [ preselected, setPreselected ] = useState( false );
	const [ filterValues, setFilterValues ] = useState( {} );
	const [ results, setResults ] = useState( {} );
	const [ addedKeys, setAddedKeys ] = useState( [] );
	const [ busyKeys, setBusyKeys ] = useState( [] );
	const searchController = useRef( null );
	const { addProducts } = useDispatch( STORE_NAME );

	// Search only the explicitly selected modules — never fall back to "all",
	// which would fire a request per module on large lists.
	const targetModules = modules.filter( ( module ) =>
		selectedIds.includes( module.id )
	);
	const singleModule =
		selectedIds.length === 1
			? modules.find( ( module ) => module.id === selectedIds[ 0 ] )
			: null;
	const shownFilters = filtersToShow( targetModules );
	const canPersist = !! postId;
	const hasEanModules = modules.some( ( module ) => module.gtin_search );

	// A search is in progress while any selected module's result bucket is still
	// loading (includes feed-import retries). Used to show the Search button as
	// busy — not to disable it: re-running aborts the in-flight search first.
	const isSearching = targetModules.some(
		( module ) => results[ module.id ]?.status === 'loading'
	);

	const singleModuleId = singleModule ? singleModule.id : null;
	useEffect( () => {
		// A new (or no) single-module context: start from that module's
		// descriptor defaults instead of the previous module's leftovers.
		setFilterValues( {} );
	}, [ singleModuleId ] );

	useEffect( () => {
		return () => {
			if ( searchController.current ) {
				searchController.current.abort();
			}
		};
	}, [] );

	// Preselect the highest-priority module (first, since the list is
	// priority-sorted) so a search works immediately without picking a chip.
	useEffect( () => {
		// Skip the default single-module preselect when the panel is opened
		// straight into an EAN compare: that path (compareByEan) selects every
		// GTIN module itself, and both effects fire on this fresh mount — without
		// this guard the preselect races in and overwrites the compare's
		// selection back to the first module (chips look unselected while the
		// search still fans out to all GTIN modules).
		if ( String( initialCompareEan || '' ).trim() ) {
			return;
		}
		if ( ! preselected && modules.length > 0 ) {
			setSelectedIds( [ modules[ 0 ].id ] );
			setPreselected( true );
		}
	}, [ modules, preselected, initialCompareEan ] );

	const isAdded = ( moduleId, uniqueId ) => {
		const key = getKey( moduleId, uniqueId );
		return addedKeys.includes( key ) || existingKeys.includes( key );
	};

	const toggleModule = ( moduleId ) => {
		setSelectedIds( ( current ) =>
			current.includes( moduleId )
				? current.filter( ( id ) => id !== moduleId )
				: [ ...current, moduleId ]
		);
	};

	const selectAllModules = () =>
		setSelectedIds( modules.map( ( module ) => module.id ) );
	const clearModules = () => setSelectedIds( [] );

	const searchModule = (
		module,
		controller,
		attempt = 0,
		override = null
	) => {
		const trimmed = override
			? String( override.keyword || '' ).trim()
			: keyword.trim();

		setResults( ( current ) => ( {
			...current,
			[ module.id ]: {
				status: 'loading',
				items: [],
				error: '',
				notice:
					attempt > 0
						? __(
								'Importing feed, still searching…',
								'content-egg'
						  )
						: '',
			},
		} ) );

		// An override (e.g. EAN compare) carries its own filters. Otherwise:
		// single module → its filters only to it; multiple → shared price filter.
		let filters = {};
		if ( override ) {
			filters = override.filters || {};
		} else if ( shownFilters.length ) {
			if ( singleModule ) {
				if ( singleModule.id === module.id ) {
					filters = filterValues;
				}
			} else {
				filters = filterValues;
			}
		}

		return searchProducts( {
			moduleId: module.id,
			keyword: trimmed,
			filters,
			postId,
			signal: controller.signal,
		} )
			.then( ( response ) => {
				if (
					response?.feed_importing &&
					attempt < FEED_IMPORT_MAX_RETRIES &&
					! controller.signal.aborted
				) {
					setTimeout( () => {
						if ( ! controller.signal.aborted ) {
							searchModule(
								module,
								controller,
								attempt + 1,
								override
							);
						}
					}, FEED_IMPORT_RETRY_DELAY );
					return;
				}

				setResults( ( current ) => ( {
					...current,
					[ module.id ]: {
						status: 'done',
						items: Array.isArray( response?.results )
							? response.results
							: [],
						error: String( response?.error || '' ),
						notice: String( response?.notice || '' ),
					},
				} ) );
			} )
			.catch( ( error ) => {
				// A newer search aborted this one — leave its result bucket
				// for the new search to overwrite; do not render an error.
				if (
					error?.name === 'AbortError' ||
					controller.signal.aborted
				) {
					return;
				}
				setResults( ( current ) => ( {
					...current,
					[ module.id ]: {
						status: 'done',
						items: [],
						error:
							error?.message ||
							__( 'Search failed.', 'content-egg' ),
						notice: '',
					},
				} ) );
			} );
	};

	// Abort any in-flight search and fan a fresh one out across moduleList. The
	// override (or null) is threaded to every module and its feed-import retries.
	const startSearch = ( moduleList, override = null ) => {
		if ( searchController.current ) {
			searchController.current.abort();
		}
		const controller = new AbortController();
		searchController.current = controller;

		moduleList.forEach( ( module ) =>
			searchModule( module, controller, 0, override )
		);
	};

	const runSearch = () => {
		if ( ! keyword.trim() || targetModules.length === 0 ) {
			return;
		}
		startSearch( targetModules );
	};

	// Click an EAN to build a price-comparison list: search that EAN across every
	// EAN-capable module at once. Reflect it in the UI (keyword + selected chips +
	// cleared filters) and fire immediately with explicit params, since the state
	// updates above only take effect on the next render.
	const compareByEan = ( ean ) => {
		const value = String( ean || '' ).trim();
		const eanModules = modules.filter( ( module ) => module.gtin_search );
		if ( ! value || eanModules.length === 0 ) {
			return;
		}
		setKeyword( value );
		setSelectedIds( eanModules.map( ( module ) => module.id ) );
		setFilterValues( {} );
		startSearch( eanModules, { keyword: value, filters: {} } );
	};

	// When opened from an EAN click in the Added tab, run the compare once —
	// after modules have loaded (compareByEan needs them to pick EAN modules).
	// This panel is remounted per open, so the ref resets each time.
	const didCompareRef = useRef( false );
	useEffect( () => {
		if ( didCompareRef.current ) {
			return undefined;
		}
		const value = String( initialCompareEan || '' ).trim();
		if ( ! value || ! modules.length ) {
			return undefined;
		}
		didCompareRef.current = true;
		compareByEan( value );
		return () => {
			// If this effect is torn down and re-created — React 18 StrictMode's
			// dev-only mount→unmount→mount, or a genuine remount — the panel's
			// unmount cleanup has already aborted the in-flight compare search.
			// Clear the guard so the next setup re-issues it (otherwise the
			// spinners hang on an aborted request).
			didCompareRef.current = false;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ initialCompareEan, modules.length ] );

	const persistItems = ( moduleId, items ) => {
		const keys = items.map( ( item ) =>
			getKey( moduleId, item.unique_id )
		);
		// Optimistically mark the products added the moment they're clicked; keep
		// them busy while the save is in flight, and roll back below if the server
		// rejects (or doesn't confirm) any of them.
		setAddedKeys( ( current ) => [ ...current, ...keys ] );
		setBusyKeys( ( current ) => [ ...current, ...keys ] );

		return addProducts( moduleId, items )
			.then( ( response ) => {
				const savedItems = Array.isArray( response?.items )
					? response.items
					: [];
				const confirmedIds = savedItems.map( ( item ) =>
					String( item.unique_id )
				);

				// Roll back any requested item the server did not actually save.
				const rejectedKeys = items
					.filter(
						( item ) =>
							! confirmedIds.includes( String( item.unique_id ) )
					)
					.map( ( item ) => getKey( moduleId, item.unique_id ) );
				if ( rejectedKeys.length ) {
					setAddedKeys( ( current ) =>
						current.filter(
							( key ) => ! rejectedKeys.includes( key )
						)
					);
				}

				if ( onAdded ) {
					const requestedIds = items.map( ( item ) =>
						String( item.unique_id )
					);
					savedItems
						.filter( ( item ) =>
							requestedIds.includes( String( item.unique_id ) )
						)
						.forEach( ( item ) =>
							onAdded( {
								...item,
								module_id: moduleId,
							} )
						);
				}
			} )
			.catch( ( error ) => {
				// Roll back the optimistic add on failure.
				setAddedKeys( ( current ) =>
					current.filter( ( key ) => ! keys.includes( key ) )
				);
				setResults( ( current ) => ( {
					...current,
					[ moduleId ]: {
						...( current[ moduleId ] || {
							items: [],
							notice: '',
						} ),
						status: 'done',
						error:
							error?.message ||
							__( 'Could not save the product.', 'content-egg' ),
					},
				} ) );
			} )
			.finally( () => {
				setBusyKeys( ( current ) =>
					current.filter( ( key ) => ! keys.includes( key ) )
				);
			} );
	};

	if ( modulesLoading ) {
		return <Spinner />;
	}

	if ( modulesError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ __( 'Could not load Content Egg modules.', 'content-egg' ) }
			</Notice>
		);
	}

	return (
		<div className="cegg-pm cegg-pm-search">
			{ ! canPersist && (
				<Notice status="warning" isDismissible={ false }>
					{ byFamily(
						__(
							'Save the post before adding coupons.',
							'content-egg'
						),
						__(
							'Save the post before adding media.',
							'content-egg'
						),
						__(
							'Save the post before adding products.',
							'content-egg'
						)
					) }
				</Notice>
			) }

			<div className="cegg-pm-search__bar">
				<div className="cegg-pm-search__bar-field">
					<SearchControl
						label={ byFamily(
							__( 'Search coupons', 'content-egg' ),
							__( 'Search media', 'content-egg' ),
							__( 'Search products', 'content-egg' )
						) }
						value={ keyword }
						onChange={ setKeyword }
						placeholder={ searchPlaceholder( targetModules ) }
						size="compact"
						onKeyDown={ ( event ) => {
							if ( event.key === 'Enter' ) {
								event.preventDefault();
								event.stopPropagation();
								runSearch();
							}
						} }
						__nextHasNoMarginBottom
					/>
				</div>
				{ shownFilters.length > 0 && (
					<FilterFields
						filters={ shownFilters }
						values={ filterValues }
						onChange={ setFilterValues }
					/>
				) }
				<Button
					className="cegg-pm-search__submit"
					variant="primary"
					size="compact"
					onClick={ runSearch }
					isBusy={ isSearching }
					disabled={ ! keyword.trim() }
				>
					{ isSearching
						? __( 'Searching…', 'content-egg' )
						: __( 'Search', 'content-egg' ) }
				</Button>
			</div>

			<ModuleChips
				modules={ modules }
				selectedIds={ selectedIds }
				onToggle={ toggleModule }
				onSelectAll={ selectAllModules }
				onClear={ clearModules }
			/>

			{ selectedIds.length === 0 && (
				<p className="cegg-pm-search__hint">
					{ __(
						'Select at least one module to search.',
						'content-egg'
					) }
				</p>
			) }

			<div className="cegg-pm-search__results">
				{ targetModules.map( ( module ) => {
					const moduleResults = results[ module.id ];
					if ( ! moduleResults ) {
						return null;
					}

					return (
						<div key={ module.id }>
							<div className="cegg-pm-search__module">
								{ module.label }
							</div>

							{ moduleResults.status === 'loading' && (
								<Spinner />
							) }

							{ moduleResults.error && (
								<Notice status="error" isDismissible={ false }>
									{ moduleResults.error }
								</Notice>
							) }
							{ moduleResults.notice && ! moduleResults.error && (
								<Notice status="info" isDismissible={ false }>
									{ moduleResults.notice }
								</Notice>
							) }

							{ moduleResults.status === 'done' &&
								! moduleResults.error &&
								moduleResults.items.length === 0 && (
									<p className="cegg-pm-search__empty">
										{ __( 'No results.', 'content-egg' ) }
									</p>
								) }

							<div
								className={
									isMedia
										? 'cegg-pm-media-grid'
										: 'cegg-pm-result-list'
								}
							>
								{ moduleResults.items.map( ( item ) => {
									const rowProps = {
										product: item,
										isAdded: isAdded(
											module.id,
											item.unique_id
										),
										isBusy: busyKeys.includes(
											getKey( module.id, item.unique_id )
										),
										disabled: ! canPersist,
										onAdd: () =>
											persistItems( module.id, [ item ] ),
									};
									if ( isCoupon ) {
										return (
											<CouponResultRow
												key={ getKey(
													module.id,
													item.unique_id
												) }
												{ ...rowProps }
											/>
										);
									}
									if ( isMedia ) {
										return (
											<MediaResultRow
												key={ getKey(
													module.id,
													item.unique_id
												) }
												{ ...rowProps }
												parserType={ family }
												onSetFeatured={
													family === 'IMAGE'
														? async () => {
																if (
																	! isAdded(
																		module.id,
																		item.unique_id
																	)
																) {
																	await persistItems(
																		module.id,
																		[ item ]
																	);
																}
																await setItemAsFeatured(
																	postId,
																	module.id,
																	item.unique_id
																);
														  }
														: undefined
												}
											/>
										);
									}
									return (
										<ResultRow
											key={ getKey(
												module.id,
												item.unique_id
											) }
											{ ...rowProps }
											onCompareEan={
												hasEanModules
													? compareByEan
													: undefined
											}
										/>
									);
								} ) }
							</div>

							{ allowAddAll && moduleResults.items.length > 1 && (
								<div className="cegg-pm-search__addall">
									<Button
										variant="secondary"
										size="small"
										disabled={
											! canPersist ||
											moduleResults.items.every(
												( item ) =>
													isAdded(
														module.id,
														item.unique_id
													)
											) ||
											moduleResults.items.some(
												( item ) =>
													busyKeys.includes(
														getKey(
															module.id,
															item.unique_id
														)
													)
											)
										}
										onClick={ () =>
											persistItems(
												module.id,
												moduleResults.items.filter(
													( item ) =>
														! isAdded(
															module.id,
															item.unique_id
														)
												)
											)
										}
									>
										{ __( 'Add all', 'content-egg' ) }
									</Button>
								</div>
							) }
						</div>
					);
				} ) }
			</div>
		</div>
	);
}
