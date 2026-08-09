import { Button, Notice, Spinner } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from './store';
import { productsOnly } from './familyFilter';
import { getKey } from './keys';
import RosterRow from './RosterRow';
import RefreshButton from './RefreshButton';
import CopyAllRefsButton from './CopyAllRefsButton';
import { buildOrderAfterMove } from './reorder';
import useModules from './useModules';
import ManagerModal from './ManagerModal';
import QuickEditDrawer from './QuickEditDrawer';
import {
	insertProductBlock,
	setProductBlockDragData,
	setProductShortcodeDragData,
} from './insertProductBlock';
import { blockEditorAvailable } from './blockEditor';

// Shared inner UI for both shells. The Gutenberg sidebar (ProductsSidebar) and
// the classic metabox (ClassicApp) both render this — the store, REST, and every
// component are identical; only the mount point and per-row insert differ.
//
// `showGoPro` is opt-in rather than derived from the config: only the Gutenberg
// sidebar wants the free-build upsell in the footer. The classic metabox already
// carries a "Go PRO" link in its own postbox title (ProductManagerLoader), so it
// passes nothing and stays as-is.
export default function ProductManagerPanel( { showGoPro = false } ) {
	const goProUrl =
		( ( typeof window !== 'undefined' && window.ceggPmConfig ) || {} )
			.goProUrl || '';
	const { products, error, isLoading } = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			products: productsOnly( store.getProducts() ),
			error: store.getError(),
			// The GET resolver runs on getProducts; until it finishes, an empty
			// list is "not loaded yet", not "no products" — show a spinner so we
			// never flash the empty state on a post that has products.
			isLoading: ! store.hasFinishedResolution( 'getProducts', [] ),
		};
	}, [] );
	const { removeProduct, reorder, setError } = useDispatch( STORE_NAME );
	const { modules } = useModules();
	const labelFor = ( id ) =>
		( modules.find( ( m ) => m.id === id ) || {} ).label || id;

	// null when closed; otherwise the tab the manager modal opens on.
	const [ modalTab, setModalTab ] = useState( null );
	const [ editing, setEditing ] = useState( null );
	const [ dropIndex, setDropIndex ] = useState( null );
	const dragFrom = useRef( null );

	// Multi-select for drag-inserting several products as one offers_list block.
	// Plain click selects one (and anchors); Cmd/Ctrl toggles; Shift ranges from
	// the anchor. Keys are "moduleId:uniqueId" (getKey).
	const [ selected, setSelected ] = useState( [] );
	const anchorRef = useRef( null );
	const keyAt = ( i ) =>
		getKey( products[ i ].module_id, products[ i ].unique_id );
	const handleSelect = ( index, event ) => {
		const key = keyAt( index );
		if ( event.shiftKey && anchorRef.current !== null ) {
			event.preventDefault();
			const from = Math.min( anchorRef.current, index );
			const to = Math.max( anchorRef.current, index );
			const range = [];
			for ( let i = from; i <= to; i++ ) {
				range.push( keyAt( i ) );
			}
			setSelected( range );
		} else if ( event.metaKey || event.ctrlKey ) {
			event.preventDefault();
			setSelected( ( cur ) =>
				cur.includes( key )
					? cur.filter( ( k ) => k !== key )
					: [ ...cur, key ]
			);
			anchorRef.current = index;
		} else {
			setSelected( [ key ] );
			anchorRef.current = index;
		}
	};

	// insertProductBlock dispatches to core/block-editor, which exists only on
	// block-editor screens — so per-row insert is offered there and hidden on
	// classic / Woo / CPT screens.
	const canInsert = blockEditorAvailable();
	// Authoritative block-vs-classic flag (see ProductManagerLoader): rows carry a
	// block drag payload only on the block editor, so dropping one on the canvas
	// inserts a content-egg/products block.
	const isBlockEditor =
		typeof window !== 'undefined' && window.ceggPmConfig?.isBlockEditor;

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

	return (
		<>
			<div className="cegg-pm cegg-pm-sidebar">
				{ error && (
					<Notice
						status="error"
						className="cegg-pm-notice"
						onRemove={ () => setError( null ) }
					>
						{ error }
					</Notice>
				) }
				<div className="cegg-pm-sidebar__actions">
					<Button
						variant="primary"
						className="cegg-pm-sidebar__add"
						onClick={ () => setModalTab( 'search' ) }
					>
						{ __( 'Search & add products…', 'content-egg' ) }
					</Button>
					{ products.length > 0 && (
						<Button
							variant="secondary"
							className="cegg-pm-sidebar__manage"
							icon="list-view"
							label={ __( 'Manage products', 'content-egg' ) }
							showTooltip
							onClick={ () => setModalTab( 'added' ) }
						/>
					) }
				</div>

				<div className="cegg-pm-roster">
					{ isLoading && (
						<div className="cegg-pm-loading">
							<Spinner />
						</div>
					) }
					{ ! isLoading && products.length === 0 && (
						<div className="cegg-pm-empty">
							<p className="cegg-pm-empty__title">
								{ __( 'No products yet', 'content-egg' ) }
							</p>
							<p className="cegg-pm-empty__hint">
								{ __(
									'Use “Search & add products” above to attach affiliate products to this post.',
									'content-egg'
								) }
							</p>
						</div>
					) }
					{ ! isLoading &&
						products.map( ( product, index ) => (
							<RosterRow
								key={ `${ product.module_id }:${ product.unique_id }` }
								product={ product }
								moduleLabel={ labelFor( product.module_id ) }
								onEdit={ () =>
									setEditing( {
										moduleId: product.module_id,
										uniqueId: product.unique_id,
									} )
								}
								onRemove={ () =>
									removeProduct(
										product.module_id,
										product.unique_id
									)
								}
								onInsert={
									canInsert
										? () => insertProductBlock( product )
										: undefined
								}
								isSelected={ selected.includes(
									getKey(
										product.module_id,
										product.unique_id
									)
								) }
								onSelect={ ( event ) =>
									handleSelect( index, event )
								}
								onDragStart={ ( event ) => {
									dragFrom.current = index;
									// The same drag also carries an insert payload
									// (block on the block editor, shortcode text on
									// classic); reorder uses dragFrom, not
									// dataTransfer, so the two never conflict.
									const key = getKey(
										product.module_id,
										product.unique_id
									);
									// Drag the whole selection when the grabbed row
									// is part of a multi-selection; otherwise just
									// this product.
									const dragList =
										selected.includes( key ) &&
										selected.length > 1
											? products.filter( ( p ) =>
													selected.includes(
														getKey(
															p.module_id,
															p.unique_id
														)
													)
											  )
											: [ product ];
									if ( isBlockEditor ) {
										setProductBlockDragData(
											event,
											dragList
										);
									} else {
										setProductShortcodeDragData(
											event,
											dragList
										);
									}
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
						) ) }
				</div>

				<div className="cegg-pm-footer">
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
					{ products.length > 0 && (
						<CopyAllRefsButton products={ products } />
					) }
					{ showGoPro && goProUrl && (
						<a
							className="cegg-pm-footer__gopro"
							href={ goProUrl }
							target="_blank"
							rel="noreferrer"
						>
							{ __( 'Go PRO', 'content-egg' ) } →
						</a>
					) }
				</div>
			</div>

			{ modalTab && (
				<ManagerModal
					initialTab={ modalTab }
					onClose={ () => setModalTab( null ) }
				/>
			) }
			{ editing && (
				<QuickEditDrawer
					moduleId={ editing.moduleId }
					uniqueId={ editing.uniqueId }
					onClose={ () => setEditing( null ) }
				/>
			) }
		</>
	);
}
