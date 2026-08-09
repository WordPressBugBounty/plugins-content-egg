import { Button, Notice, Spinner, Tooltip } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from './store';
import { couponsOnly } from './familyFilter';
import { getKey } from './keys';
import { buildOrderAfterMove } from './reorder';
import {
	insertCouponShortcode,
	setCouponBlockDragData,
	setCouponShortcodeDragData,
} from './insertProductBlock';
import { blockEditorAvailable } from './blockEditor';
import useModules from './useModules';
import CouponRosterRow from './CouponRosterRow';
import CouponEditDrawer from './CouponEditDrawer';
import ManagerModal from './ManagerModal';

// The sidebar Coupons panel — now a full roster (the coupon analogue of
// ProductManagerPanel), replacing the earlier count-only summary. Coupons live
// in the shared store, so it reuses the same dispatch (remove/reorder) and the
// coupon components (row, edit drawer). "Search & add" and "Manage" open the
// shared manager scoped to COUPON; "Add coupon" creates one manually via the
// Coupon module (the Offer counterpart), like the Manager's Add-coupon button.
export default function CouponsPanel() {
	const { coupons, error, isLoading } = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			coupons: couponsOnly( store.getProducts() || [] ),
			error: store.getError(),
			// The GET resolver runs on getProducts; until it finishes, an empty
			// list is "not loaded yet", not "no coupons" — show a spinner so we
			// never flash the empty state on a post that has coupons.
			isLoading: ! store.hasFinishedResolution( 'getProducts', [] ),
		};
	}, [] );
	const { removeProduct, reorder, setError } = useDispatch( STORE_NAME );
	const { modules } = useModules( { types: 'COUPON' } );
	const labelFor = ( id ) =>
		( modules.find( ( m ) => m.id === id ) || {} ).label || id;

	// The manual-entry coupon module (like Offer for products) — the "Add coupon"
	// target, from config so it resolves even when the module is inactive.
	const manualModuleId =
		( typeof window !== 'undefined' &&
			window.ceggPmConfig?.manualCouponModuleId ) ||
		( modules.find( ( m ) => m.searchable === false ) || {} ).id;

	// insertCouponShortcode dispatches to core/block-editor (block screens) or the
	// classic editor; both need an editor present — hidden on Woo/CPT screens.
	const canInsert = blockEditorAvailable();
	// Authoritative block-vs-classic flag (see ProductManagerLoader): rows carry a
	// block drag payload only on the block editor, so dropping one on the canvas
	// inserts a content-egg/coupons block; classic gets shortcode text.
	const isBlockEditor =
		typeof window !== 'undefined' && window.ceggPmConfig?.isBlockEditor;

	// null when closed; otherwise the tab the coupon manager opens on.
	const [ modalTab, setModalTab ] = useState( null );
	// null when closed; otherwise { moduleId, uniqueId } to edit or
	// { moduleId, create: true } to add a coupon manually.
	const [ editing, setEditing ] = useState( null );
	const [ dropIndex, setDropIndex ] = useState( null );
	const dragFrom = useRef( null );

	// Multi-select for drag-inserting several coupons as one bound block. Plain
	// click selects one (and anchors); Cmd/Ctrl toggles; Shift ranges from the
	// anchor. Keys are "moduleId:uniqueId" (getKey).
	const [ selected, setSelected ] = useState( [] );
	const anchorRef = useRef( null );
	const keyAt = ( i ) =>
		getKey( coupons[ i ].module_id, coupons[ i ].unique_id );
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
		reorder( buildOrderAfterMove( coupons, fromIndex, toIndex ) );
	};

	return (
		<>
			<div className="cegg-pm cegg-pm-sidebar cegg-pm-sidebar--coupons">
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
						{ __( 'Search & add coupons…', 'content-egg' ) }
					</Button>
					{ manualModuleId && (
						<Tooltip
							text={ __(
								'Add a coupon manually — enter its title, code, URL, and validity dates yourself.',
								'content-egg'
							) }
						>
							<Button
								variant="secondary"
								className="cegg-pm-sidebar__add-manual"
								icon="plus"
								label={ __( 'Add coupon', 'content-egg' ) }
								showTooltip={ false }
								onClick={ () =>
									setEditing( {
										moduleId: manualModuleId,
										create: true,
									} )
								}
							/>
						</Tooltip>
					) }
					{ coupons.length > 0 && (
						<Button
							variant="secondary"
							className="cegg-pm-sidebar__manage"
							icon="list-view"
							label={ __( 'Manage coupons', 'content-egg' ) }
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
					{ ! isLoading && coupons.length === 0 && (
						<div className="cegg-pm-empty">
							<p className="cegg-pm-empty__title">
								{ __( 'No coupons yet', 'content-egg' ) }
							</p>
							<p className="cegg-pm-empty__hint">
								{ __(
									'Use “Search & add coupons” above, or “Add coupon” to enter a voucher by hand.',
									'content-egg'
								) }
							</p>
						</div>
					) }
					{ ! isLoading &&
						coupons.map( ( coupon, index ) => (
							<CouponRosterRow
								key={ `${ coupon.module_id }:${ coupon.unique_id }` }
								coupon={ coupon }
								moduleLabel={ labelFor( coupon.module_id ) }
								onEdit={ () =>
									setEditing( {
										moduleId: coupon.module_id,
										uniqueId: coupon.unique_id,
									} )
								}
								onRemove={ () =>
									removeProduct(
										coupon.module_id,
										coupon.unique_id
									)
								}
								onInsert={
									canInsert
										? () => insertCouponShortcode( coupon )
										: undefined
								}
								isSelected={ selected.includes(
									getKey( coupon.module_id, coupon.unique_id )
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
										coupon.module_id,
										coupon.unique_id
									);
									// Drag the whole selection when the grabbed row
									// is part of a multi-selection; otherwise just
									// this coupon.
									const dragList =
										selected.includes( key ) &&
										selected.length > 1
											? coupons.filter( ( c ) =>
													selected.includes(
														getKey(
															c.module_id,
															c.unique_id
														)
													)
											  )
											: [ coupon ];
									if ( isBlockEditor ) {
										setCouponBlockDragData(
											event,
											dragList
										);
									} else {
										setCouponShortcodeDragData(
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
			</div>

			{ modalTab && (
				<ManagerModal
					family="COUPON"
					initialTab={ modalTab }
					onClose={ () => setModalTab( null ) }
				/>
			) }
			{ editing && (
				<CouponEditDrawer
					moduleId={ editing.moduleId }
					uniqueId={ editing.uniqueId }
					create={ !! editing.create }
					onClose={ () => setEditing( null ) }
				/>
			) }
		</>
	);
}
