import {
	Button,
	CheckboxControl,
	DropdownMenu,
	SelectControl,
	Tooltip,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { STORE_NAME } from './store';
import { productsOnly } from './familyFilter';
import { getKey } from './keys';
import {
	groupsFromProducts,
	groupSelectOptions,
	resolveGroupValue,
} from './groups';
import { keywordStatusRequest } from './api';
import { clicksEnabled, clicksLabel30 } from './clicksConfig';
import { buildOrderAfterMove } from './reorder';
import { insertProductBlock } from './insertProductBlock';
import { groupByModule, enqueueBridgeImport } from './bridgeImport';
import { blockEditorAvailable } from './blockEditor';
import useModules from './useModules';
import AttachedRow from './AttachedRow';
import RefreshButton from './RefreshButton';
import AiMenu from './AiMenu';
import {
	aiEnabled,
	aiTitleMethods,
	aiDescriptionMethods,
	smartGroupMethods,
} from './aiConfig';
import { runAiUndo } from './aiUndo';

export default function AttachedTable( {
	onEdit,
	onGoToSearch,
	onCompareEan,
	postId,
	keywordStatusSeq,
	compact = false,
} ) {
	const products = useSelect(
		( select ) => productsOnly( select( STORE_NAME ).getProducts() ),
		[]
	);
	// Derive the group list from the current products so it's never stale — a
	// product's assigned group is always a valid option (the store's snapshot
	// only refreshed on a full re-sync, which made saved groups render as "—").
	const groups = useMemo(
		() => groupsFromProducts( products ),
		[ products ]
	);
	const {
		removeProduct,
		updateProduct,
		reorder,
		bulkRemove: bulkRemoveProducts,
		bulkUpdate: bulkUpdateProducts,
		applyAi,
		applySmartGroups,
	} = useDispatch( STORE_NAME );
	const { createErrorNotice, createSuccessNotice } =
		useDispatch( noticesStore );
	const { modules } = useModules();
	const labelFor = ( id ) =>
		( modules.find( ( m ) => m.id === id ) || {} ).label || id;
	// Only offer EAN compare when some module can search by GTIN/EAN.
	const hasEanModules = modules.some( ( m ) => m.gtin_search );
	// Click stats column — gated on the server's tracking config, mirroring the
	// AI gating above. Also dropped in the compact (metabox) layout to save width.
	const showClicks = ! compact && clicksEnabled();
	// Block insert is available only where core/block-editor exists (block
	// editor screens); classic / Woo / CPT screens have no such store.
	const canInsert = blockEditorAvailable();
	const clicksHeader = clicksLabel30();
	// The manual-entry module (Offer) — the target for "Add product". Sourced
	// from config so the button shows even when the module is inactive (the add
	// endpoint activates it on first use); falls back to the active module list.
	const manualModuleId =
		( typeof window !== 'undefined' &&
			window.ceggPmConfig?.manualModuleId ) ||
		( modules.find( ( m ) => m.searchable === false ) || {} ).id;
	const addManual = () =>
		manualModuleId && onEdit( { create: true, moduleId: manualModuleId } );

	const [ selected, setSelected ] = useState( [] );
	const [ dropIndex, setDropIndex ] = useState( null );
	const dragFrom = useRef( null );

	// A "Update listings" run re-searches each module's autoupdate keyword; with
	// no keyword anywhere it's a no-op, so we disable it. The keyword lives in
	// post meta (not the product data), so ask the server once when the tab opens.
	const [ listingsAvailable, setListingsAvailable ] = useState( false );
	useEffect( () => {
		if ( ! postId ) {
			return undefined;
		}
		let cancelled = false;
		keywordStatusRequest( { postId } )
			.then( ( res ) => {
				if ( ! cancelled ) {
					setListingsAvailable( !! res?.available );
				}
			} )
			.catch( () => {} );
		return () => {
			cancelled = true;
		};
	}, [ postId, keywordStatusSeq ] );

	// --- Selection -----------------------------------------------------------
	const allKeys = products.map( ( p ) => getKey( p.module_id, p.unique_id ) );
	const allKeySet = new Set( allKeys );
	// Ignore stale keys (a selected product that was since removed) so the
	// header checkbox + count stay honest.
	const selectedKeys = selected.filter( ( k ) => allKeySet.has( k ) );
	const allSelected =
		products.length > 0 && selectedKeys.length === products.length;
	const someSelected = selectedKeys.length > 0 && ! allSelected;

	const toggle = ( k ) =>
		setSelected( ( cur ) =>
			cur.includes( k ) ? cur.filter( ( x ) => x !== k ) : [ ...cur, k ]
		);
	const toggleAll = () => setSelected( allSelected ? [] : allKeys );

	// --- Drag reorder --------------------------------------------------------
	const endDrag = () => {
		dragFrom.current = null;
		setDropIndex( null );
	};
	const onDrop = ( toIndex ) => {
		const fromIndex = dragFrom.current;
		endDrag();
		if ( fromIndex === null || fromIndex === toIndex ) {
			return;
		}
		reorder( buildOrderAfterMove( products, fromIndex, toIndex ) );
	};

	// --- Bulk actions --------------------------------------------------------
	// Each bulk action is ONE batched REST request (grouped server-side by
	// module, one save per module) rather than a request per selected product.
	const selectedTargets = () =>
		products
			.filter( ( p ) =>
				selected.includes( getKey( p.module_id, p.unique_id ) )
			)
			.map( ( p ) => ( {
				module_id: p.module_id,
				unique_id: p.unique_id,
			} ) );

	const bulkRemove = () => {
		const targets = selectedTargets();
		if ( ! targets.length ) {
			return;
		}
		bulkRemoveProducts( targets );
		setSelected( [] );
	};

	const bulkGroup = ( value ) => {
		const group = resolveGroupValue( value );
		if ( group === null ) {
			return;
		}
		const targets = selectedTargets();
		if ( ! targets.length ) {
			return;
		}
		bulkUpdateProducts( targets, { group } );
	};

	// Per-row group change — resolves the "+ New group…" sentinel to a prompt.
	const setRowGroup = ( product, value ) => {
		const group = resolveGroupValue( value );
		if ( group === null ) {
			return;
		}
		updateProduct( product.module_id, product.unique_id, { group } );
	};

	// --- Bulk AI + Smart Groups ------------------------------------------------
	// Gated on a configured AI key (aiConfig mirrors the server-side gate).
	const aiOn = aiEnabled();
	// The active action key ('' when idle) — lets each AiMenu show its own
	// spinner instead of all three lighting up together.
	const [ aiBusy, setAiBusy ] = useState( '' );
	const [ aiUndoData, setAiUndoData ] = useState( null );
	const [ bridgeBusy, setBridgeBusy ] = useState( false );
	const importPresets =
		( typeof window !== 'undefined' &&
			window.ceggPmConfig?.importPresets ) ||
		[];

	// The currently-selected product objects (selectedKeys is already filtered
	// for staleness above), carrying each item's current title/subtitle/
	// description — the values the AI call rewrites from.
	const selectedProducts = () =>
		products.filter( ( p ) =>
			selectedKeys.includes( getKey( p.module_id, p.unique_id ) )
		);

	// Bulk title/description rewrite for the current selection — grouped by
	// module (one /ai request per module, mirroring the server's per-module
	// save), with the pre-change snapshots from every call merged into a
	// single undo entry.
	const runBulkAi = async ( kind, method ) => {
		if ( aiBusy || ! selectedKeys.length ) {
			return;
		}
		setAiBusy( kind );
		const byModule = {};
		selectedProducts().forEach( ( p ) => {
			( byModule[ p.module_id ] = byModule[ p.module_id ] || [] ).push( {
				unique_id: p.unique_id,
				title: p.title,
				subtitle: p.subtitle,
				description: p.description,
			} );
		} );
		const previous = [];
		try {
			for ( const moduleId of Object.keys( byModule ) ) {
				const args =
					kind === 'title'
						? [ moduleId, byModule[ moduleId ], method, '' ]
						: [ moduleId, byModule[ moduleId ], '', method ];
				// eslint-disable-next-line no-await-in-loop
				const result = await applyAi( ...args );
				if ( result?.error ) {
					// Soft failure: the store recorded the error, but the
					// fullscreen manager modal occludes the sidebar Notice
					// that would render it — surface it as a snackbar.
					createErrorNotice( result.error, {
						type: 'snackbar',
						isDismissible: true,
					} );
					return;
				}
				if ( result?.previous?.length ) {
					previous.push( ...result.previous );
				}
			}
			if ( previous.length ) {
				setAiUndoData( previous );
			}
			createSuccessNotice( __( 'AI applied', 'content-egg' ), {
				type: 'snackbar',
			} );
		} catch ( error ) {
			// Hard failure (thrown/rejected promise) — also catches this so an
			// unhandled rejection never escapes the fire-and-forget onClick.
			createErrorNotice(
				error?.message || __( 'AI request failed.', 'content-egg' ),
				{ type: 'snackbar', isDismissible: true }
			);
		} finally {
			setAiBusy( '' );
		}
	};

	// Grouping for the current selection — grouped-by-module the same way
	// applySmartGroups persists server-side, mirroring the bulk-AI gating
	// (disabled with nothing selected).
	const runSmartGroups = async ( method ) => {
		if ( aiBusy || ! selectedKeys.length ) {
			return;
		}
		setAiBusy( 'smartgroups' );
		try {
			const result = await applySmartGroups( method, selectedTargets() );
			if ( result?.error ) {
				// Soft failure: the store recorded the error, but the
				// fullscreen manager modal occludes the sidebar Notice that
				// would render it — surface it as a snackbar.
				createErrorNotice( result.error, {
					type: 'snackbar',
					isDismissible: true,
				} );
				return;
			}
			if ( result?.previous?.length ) {
				setAiUndoData( result.previous );
			}
			createSuccessNotice( __( 'Smart groups assigned', 'content-egg' ), {
				type: 'snackbar',
			} );
		} catch ( error ) {
			// Hard failure (thrown/rejected promise) — also catches this so an
			// unhandled rejection never escapes the fire-and-forget onClick.
			createErrorNotice(
				error?.message || __( 'AI request failed.', 'content-egg' ),
				{ type: 'snackbar', isDismissible: true }
			);
		} finally {
			setAiBusy( '' );
		}
	};

	// Enqueue one bridge-page import job per selected product, grouped into one
	// request per module. Preset-driven; source_post_id ties the jobs to this post.
	const onCreateBridgePages = async ( presetId ) => {
		if ( bridgeBusy || ! selectedKeys.length || ! postId ) {
			return;
		}
		const byModule = groupByModule( selectedProducts() );
		setBridgeBusy( true );
		try {
			const results = await Promise.all(
				Object.keys( byModule ).map( ( moduleId ) =>
					enqueueBridgeImport( {
						presetId,
						moduleId,
						products: byModule[ moduleId ],
						sourcePostId: postId,
					} )
				)
			);
			const created = results.reduce(
				( n, r ) => n + ( r?.created_count || 0 ),
				0
			);
			const skipped = results.reduce(
				( n, r ) => n + ( r?.skipped?.length || 0 ),
				0
			);
			if ( created > 0 ) {
				const message = skipped
					? sprintf(
							/* translators: 1: queued count, 2: skipped count */
							_n(
								'Queued %1$d bridge page. Skipped %2$d.',
								'Queued %1$d bridge pages. Skipped %2$d.',
								created,
								'content-egg'
							),
							created,
							skipped
					  )
					: sprintf(
							/* translators: %d: queued count */
							_n(
								'Queued %d bridge page.',
								'Queued %d bridge pages.',
								created,
								'content-egg'
							),
							created
					  );
				createSuccessNotice( message, { type: 'snackbar' } );
				setSelected( [] );
			} else {
				createErrorNotice(
					__(
						'No bridge pages were queued (duplicates or skipped).',
						'content-egg'
					),
					{ type: 'snackbar', isDismissible: true }
				);
			}
		} catch ( e ) {
			createErrorNotice(
				e?.message || __( 'Bridge page import failed.', 'content-egg' ),
				{ type: 'snackbar', isDismissible: true }
			);
		} finally {
			setBridgeBusy( false );
		}
	};

	// Tooltip/label for the bridge-pages toggle — states why it is disabled.
	let bridgeLabel = __(
		'Create a bridge page per selected product',
		'content-egg'
	);
	if ( ! postId ) {
		bridgeLabel = __( 'Save the post as a draft first', 'content-egg' );
	} else if ( ! selectedKeys.length ) {
		bridgeLabel = __( 'Select products first', 'content-egg' );
	}

	// Single-level undo for the last bulk-AI / Smart-Groups run.
	const undoAi = async () => {
		if ( ! aiUndoData ) {
			return;
		}
		await runAiUndo( aiUndoData, updateProduct );
		setAiUndoData( null );
		createSuccessNotice( __( 'Reverted', 'content-egg' ), {
			type: 'snackbar',
		} );
	};

	// --- Empty state ---------------------------------------------------------
	if ( products.length === 0 ) {
		return (
			<div className="cegg-pm-added cegg-pm-added--empty">
				<div className="cegg-pm-empty">
					<p className="cegg-pm-empty__title">
						{ __( 'No products added yet', 'content-egg' ) }
					</p>
					<p className="cegg-pm-empty__hint">
						{ __(
							'Search for affiliate products and add them to this post — they’ll show up here to group, reorder, and keep up to date.',
							'content-egg'
						) }
					</p>
					<div className="cegg-pm-empty__actions">
						{ onGoToSearch && (
							<Button
								variant="primary"
								className="cegg-pm-empty__cta"
								onClick={ onGoToSearch }
							>
								{ __( 'Search products', 'content-egg' ) }
							</Button>
						) }
						{ manualModuleId && (
							<Tooltip
								text={ __(
									'Add a product manually with the Offer module (no search) — enter its title, URL, price and details yourself.',
									'content-egg'
								) }
							>
								<Button
									variant="secondary"
									onClick={ addManual }
								>
									{ __( 'Add product', 'content-egg' ) }
								</Button>
							</Tooltip>
						) }
					</div>
				</div>
			</div>
		);
	}

	return (
		<div className="cegg-pm-added">
			<div className="cegg-pm-added__toolbar">
				<div className="cegg-pm-added__bulk">
					<span className="cegg-pm-added__count">
						{ selectedKeys.length > 0
							? sprintf(
									/* translators: %d: number of selected products */
									_n(
										'%d selected',
										'%d selected',
										selectedKeys.length,
										'content-egg'
									),
									selectedKeys.length
							  )
							: __( 'None selected', 'content-egg' ) }
					</span>
					<Button
						variant="secondary"
						isDestructive
						size="compact"
						disabled={ ! selectedKeys.length }
						onClick={ bulkRemove }
					>
						{ __( 'Remove', 'content-egg' ) }
					</Button>
					<SelectControl
						className="cegg-pm-added__group"
						disabled={ ! selectedKeys.length }
						value=""
						options={ groupSelectOptions( groups, {
							noneLabel: __( 'Assign group…', 'content-egg' ),
						} ) }
						onChange={ ( value ) => value && bulkGroup( value ) }
						__nextHasNoMarginBottom
					/>
					{ /* Smart groups sits next to "Assign group…" — both are
					     grouping actions on the current selection. */ }
					{ aiOn && (
						<AiMenu
							label={ __( 'Smart groups', 'content-egg' ) }
							icon="category"
							methods={ smartGroupMethods() }
							busy={ aiBusy === 'smartgroups' }
							disabled={ !! aiBusy || ! selectedKeys.length }
							onPick={ ( method ) => runSmartGroups( method ) }
						/>
					) }
					{ aiOn && (
						<AiMenu
							label={ __( 'AI title', 'content-egg' ) }
							methods={ aiTitleMethods() }
							busy={ aiBusy === 'title' }
							disabled={ !! aiBusy || ! selectedKeys.length }
							onPick={ ( method ) =>
								runBulkAi( 'title', method )
							}
						/>
					) }
					{ aiOn && (
						<AiMenu
							label={ __( 'AI description', 'content-egg' ) }
							methods={ aiDescriptionMethods() }
							busy={ aiBusy === 'description' }
							disabled={ !! aiBusy || ! selectedKeys.length }
							onPick={ ( method ) =>
								runBulkAi( 'description', method )
							}
						/>
					) }
					{ /* Undo AI extends the AI menu group rather than shifting
					     the global refresh actions when it appears. */ }
					{ aiOn && aiUndoData && (
						<Button variant="link" onClick={ undoAi }>
							{ __( 'Undo AI', 'content-egg' ) }
						</Button>
					) }
					{ importPresets.length > 0 && (
						<DropdownMenu
							className="cegg-pm-added__bridge"
							icon={ null }
							text={ __( 'Create bridge pages', 'content-egg' ) }
							label={ bridgeLabel }
							toggleProps={ {
								variant: 'secondary',
								size: 'compact',
								isBusy: bridgeBusy,
								disabled:
									bridgeBusy ||
									! selectedKeys.length ||
									! postId,
							} }
							controls={ importPresets.map( ( preset ) => ( {
								title: preset.title,
								onClick: () => onCreateBridgePages( preset.id ),
							} ) ) }
						/>
					) }
				</div>
				<div className="cegg-pm-added__global">
					{ manualModuleId && (
						<Tooltip
							text={ __(
								'Add a product manually with the Offer module (no search) — enter its title, URL, price and details yourself.',
								'content-egg'
							) }
						>
							<Button
								variant="secondary"
								size="compact"
								icon="plus"
								onClick={ addManual }
							>
								{ __( 'Add product', 'content-egg' ) }
							</Button>
						</Tooltip>
					) }
					<RefreshButton
						type="listings"
						idleLabel={ __( 'Update listings', 'content-egg' ) }
						doneLabel={ __( 'Listings updated', 'content-egg' ) }
						tooltip={ __(
							'Re-run the keyword search and replace products for each module that has an autoupdate keyword.',
							'content-egg'
						) }
						disabled={ ! listingsAvailable }
						disabledTooltip={ __(
							'No autoupdate keyword is set for this post.',
							'content-egg'
						) }
						variant="secondary"
						size="compact"
					/>
					<RefreshButton
						type="prices"
						idleLabel={ __( 'Update prices', 'content-egg' ) }
						doneLabel={ __( 'Prices updated', 'content-egg' ) }
						tooltip={ __(
							'Fetch the latest price and availability for every product on this post.',
							'content-egg'
						) }
						variant="secondary"
						size="compact"
					/>
				</div>
			</div>

			<div className="cegg-pm-added__scroll">
				<table className="cegg-pm-table cegg-pm-atable">
					<thead>
						<tr>
							<th className="cegg-pm-atable__grip"></th>
							<th className="cegg-pm-atable__select">
								<CheckboxControl
									checked={ allSelected }
									indeterminate={ someSelected }
									onChange={ toggleAll }
									__nextHasNoMarginBottom
									aria-label={ __(
										'Select all products',
										'content-egg'
									) }
								/>
							</th>
							<th>{ __( 'Product', 'content-egg' ) }</th>
							{ ! compact && (
								<th className="cegg-pm-atable__module">
									{ __( 'Module', 'content-egg' ) }
								</th>
							) }
							<th className="cegg-pm-atable__price">
								{ __( 'Price', 'content-egg' ) }
							</th>
							{ showClicks && (
								<th className="cegg-pm-atable__clicks">
									{ clicksHeader }
								</th>
							) }
							<th className="cegg-pm-atable__group">
								{ __( 'Group', 'content-egg' ) }
							</th>
							<th className="cegg-pm-atable__actions"></th>
						</tr>
					</thead>
					<tbody>
						{ products.map( ( p, index ) => {
							const k = getKey( p.module_id, p.unique_id );
							return (
								<AttachedRow
									key={ k }
									product={ p }
									moduleLabel={ labelFor( p.module_id ) }
									groups={ groups }
									showClicks={ showClicks }
									compact={ compact }
									selected={ selected.includes( k ) }
									onToggleSelect={ () => toggle( k ) }
									onSetGroup={ ( value ) =>
										setRowGroup( p, value )
									}
									onEdit={ () =>
										onEdit( {
											moduleId: p.module_id,
											uniqueId: p.unique_id,
										} )
									}
									onInsert={
										canInsert
											? () => insertProductBlock( p )
											: undefined
									}
									onRemove={ () =>
										removeProduct(
											p.module_id,
											p.unique_id
										)
									}
									onCompareEan={
										hasEanModules ? onCompareEan : undefined
									}
									onDragStart={ () => {
										dragFrom.current = index;
									} }
									onDragOver={ ( e ) => {
										e.preventDefault();
										if ( dropIndex !== index ) {
											setDropIndex( index );
										}
									} }
									onDragEnd={ endDrag }
									onDrop={ () => onDrop( index ) }
									isDropTarget={
										dropIndex === index &&
										dragFrom.current !== index
									}
								/>
							);
						} ) }
					</tbody>
				</table>
			</div>
		</div>
	);
}
