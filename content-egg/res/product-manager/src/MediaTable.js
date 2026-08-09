import { Button, CheckboxControl, SelectControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useMemo, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { STORE_NAME } from './store';
import { mediaOnly } from './familyFilter';
import { getKey } from './keys';
import {
	groupsFromProducts,
	groupSelectOptions,
	resolveGroupValue,
} from './groups';
import { buildOrderAfterMove } from './reorder';
import { insertMediaShortcode } from './insertProductBlock';
import { setItemAsFeatured } from './featuredImage';
import { blockEditorAvailable } from './blockEditor';
import useModules from './useModules';
import MediaRow from './MediaRow';

// The media Added tab — the image/video analogue of AttachedTable, deliberately
// leaner: media has no price/stock/clicks/AI/bridge and no manual entry, so the
// toolbar is just selection + remove + group assignment. Reuses the shared store
// dispatch (remove/update/reorder/bulk*) since media lives in the same store.
export default function MediaTable( {
	parserType = 'IMAGE',
	postId,
	onEdit,
	onGoToSearch,
	compact = false,
} ) {
	const isVideo = parserType === 'VIDEO';
	const isImage = parserType === 'IMAGE';
	const media = useSelect(
		( select ) =>
			mediaOnly( select( STORE_NAME ).getProducts(), parserType ),
		[ parserType ]
	);
	const groups = useMemo( () => groupsFromProducts( media ), [ media ] );
	const {
		removeProduct,
		updateProduct,
		reorder,
		bulkRemove: bulkRemoveProducts,
		bulkUpdate: bulkUpdateProducts,
	} = useDispatch( STORE_NAME );
	const { modules } = useModules( { types: parserType } );
	const labelFor = ( id ) =>
		( modules.find( ( m ) => m.id === id ) || {} ).label || id;
	const canInsert = blockEditorAvailable();

	const [ selected, setSelected ] = useState( [] );
	const [ dropIndex, setDropIndex ] = useState( null );
	const dragFrom = useRef( null );

	const allKeys = media.map( ( p ) => getKey( p.module_id, p.unique_id ) );
	const allKeySet = new Set( allKeys );
	const selectedKeys = selected.filter( ( k ) => allKeySet.has( k ) );
	const allSelected =
		media.length > 0 && selectedKeys.length === media.length;
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
		reorder( buildOrderAfterMove( media, fromIndex, toIndex ) );
	};

	const selectedTargets = () =>
		media
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

	const setRowGroup = ( item, value ) => {
		const group = resolveGroupValue( value );
		if ( group === null ) {
			return;
		}
		updateProduct( item.module_id, item.unique_id, { group } );
	};

	if ( media.length === 0 ) {
		return (
			<div className="cegg-pm-added cegg-pm-added--empty">
				<div className="cegg-pm-empty">
					<p className="cegg-pm-empty__title">
						{ isVideo
							? __( 'No videos added yet', 'content-egg' )
							: __( 'No images added yet', 'content-egg' ) }
					</p>
					<p className="cegg-pm-empty__hint">
						{ isVideo
							? __(
									'Search video sources and add clips to this post — they’ll show up here to group, reorder, and edit.',
									'content-egg'
							  )
							: __(
									'Search image sources and add photos to this post — they’ll show up here to group, reorder, and edit.',
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
								{ isVideo
									? __( 'Search videos', 'content-egg' )
									: __( 'Search images', 'content-egg' ) }
							</Button>
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
									/* translators: %d: number of selected items */
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
										'Select all',
										'content-egg'
									) }
								/>
							</th>
							<th>
								{ isVideo
									? __( 'Video', 'content-egg' )
									: __( 'Image', 'content-egg' ) }
							</th>
							{ ! compact && (
								<th className="cegg-pm-atable__module">
									{ __( 'Module', 'content-egg' ) }
								</th>
							) }
							<th className="cegg-pm-atable__type">
								{ __( 'Type', 'content-egg' ) }
							</th>
							<th className="cegg-pm-atable__group">
								{ __( 'Group', 'content-egg' ) }
							</th>
							<th className="cegg-pm-atable__actions"></th>
						</tr>
					</thead>
					<tbody>
						{ media.map( ( p, index ) => {
							const k = getKey( p.module_id, p.unique_id );
							return (
								<MediaRow
									key={ k }
									media={ p }
									parserType={ parserType }
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
											? () =>
													insertMediaShortcode(
														parserType,
														p
													)
											: undefined
									}
									onSetFeatured={
										isImage
											? () =>
													setItemAsFeatured(
														postId,
														p.module_id,
														p.unique_id
													)
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
