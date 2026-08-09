import {
	Button,
	CheckboxControl,
	SelectControl,
	Tooltip,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useMemo, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { STORE_NAME } from './store';
import { couponsOnly } from './familyFilter';
import { getKey } from './keys';
import {
	groupsFromProducts,
	groupSelectOptions,
	resolveGroupValue,
} from './groups';
import { buildOrderAfterMove } from './reorder';
import { insertCouponShortcode } from './insertProductBlock';
import { blockEditorAvailable } from './blockEditor';
import useModules from './useModules';
import CouponRow from './CouponRow';

// The coupon Added tab — the coupon analogue of AttachedTable, deliberately
// leaner: coupons have no price/stock/clicks/AI/bridge, so the toolbar is just
// selection + remove + group assignment + add-manually. Reuses the same store
// dispatch (remove/update/reorder/bulk*) since coupons live in the shared store.
export default function CouponTable( {
	onEdit,
	onGoToSearch,
	compact = false,
} ) {
	const coupons = useSelect(
		( select ) => couponsOnly( select( STORE_NAME ).getProducts() ),
		[]
	);
	const groups = useMemo( () => groupsFromProducts( coupons ), [ coupons ] );
	const {
		removeProduct,
		updateProduct,
		reorder,
		bulkRemove: bulkRemoveProducts,
		bulkUpdate: bulkUpdateProducts,
	} = useDispatch( STORE_NAME );
	const { modules } = useModules( { types: 'COUPON' } );
	const labelFor = ( id ) =>
		( modules.find( ( m ) => m.id === id ) || {} ).label || id;
	const canInsert = blockEditorAvailable();
	// The manual-entry coupon module (like Offer for products) — the "Add coupon"
	// target, from config so it shows even when the module is inactive.
	const manualModuleId =
		( typeof window !== 'undefined' &&
			window.ceggPmConfig?.manualCouponModuleId ) ||
		( modules.find( ( m ) => m.searchable === false ) || {} ).id;
	const addManual = () =>
		manualModuleId && onEdit( { create: true, moduleId: manualModuleId } );

	const [ selected, setSelected ] = useState( [] );
	const [ dropIndex, setDropIndex ] = useState( null );
	const dragFrom = useRef( null );

	const allKeys = coupons.map( ( p ) => getKey( p.module_id, p.unique_id ) );
	const allKeySet = new Set( allKeys );
	const selectedKeys = selected.filter( ( k ) => allKeySet.has( k ) );
	const allSelected =
		coupons.length > 0 && selectedKeys.length === coupons.length;
	const someSelected = selectedKeys.length > 0 && ! allSelected;

	const toggle = ( k ) =>
		setSelected( ( cur ) =>
			cur.includes( k ) ? cur.filter( ( x ) => x !== k ) : [ ...cur, k ]
		);
	const toggleAll = () => setSelected( allSelected ? [] : allKeys );

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

	const selectedTargets = () =>
		coupons
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

	const setRowGroup = ( coupon, value ) => {
		const group = resolveGroupValue( value );
		if ( group === null ) {
			return;
		}
		updateProduct( coupon.module_id, coupon.unique_id, { group } );
	};

	if ( coupons.length === 0 ) {
		return (
			<div className="cegg-pm-added cegg-pm-added--empty">
				<div className="cegg-pm-empty">
					<p className="cegg-pm-empty__title">
						{ __( 'No coupons added yet', 'content-egg' ) }
					</p>
					<p className="cegg-pm-empty__hint">
						{ __(
							'Search coupon networks and add vouchers to this post — they’ll show up here to group, reorder, and keep up to date.',
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
								{ __( 'Search coupons', 'content-egg' ) }
							</Button>
						) }
						{ manualModuleId && (
							<Tooltip
								text={ __(
									'Add a coupon manually — enter its title, code, URL, and validity dates yourself.',
									'content-egg'
								) }
							>
								<Button
									variant="secondary"
									onClick={ addManual }
								>
									{ __( 'Add coupon', 'content-egg' ) }
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
									/* translators: %d: number of selected coupons */
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
				</div>
				<div className="cegg-pm-added__global">
					{ manualModuleId && (
						<Tooltip
							text={ __(
								'Add a coupon manually — enter its title, code, URL, and validity dates yourself.',
								'content-egg'
							) }
						>
							<Button
								variant="secondary"
								size="compact"
								icon="plus"
								onClick={ addManual }
							>
								{ __( 'Add coupon', 'content-egg' ) }
							</Button>
						</Tooltip>
					) }
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
										'Select all coupons',
										'content-egg'
									) }
								/>
							</th>
							<th>{ __( 'Coupon', 'content-egg' ) }</th>
							{ ! compact && (
								<th className="cegg-pm-atable__module">
									{ __( 'Module', 'content-egg' ) }
								</th>
							) }
							<th className="cegg-pm-atable__validity">
								{ __( 'Validity', 'content-egg' ) }
							</th>
							<th className="cegg-pm-atable__group">
								{ __( 'Group', 'content-egg' ) }
							</th>
							<th className="cegg-pm-atable__actions"></th>
						</tr>
					</thead>
					<tbody>
						{ coupons.map( ( p, index ) => {
							const k = getKey( p.module_id, p.unique_id );
							return (
								<CouponRow
									key={ k }
									coupon={ p }
									moduleLabel={ labelFor( p.module_id ) }
									groups={ groups }
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
											? () => insertCouponShortcode( p )
											: undefined
									}
									onRemove={ () =>
										removeProduct(
											p.module_id,
											p.unique_id
										)
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
