import { Button, Notice, Spinner } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from './store';
import { mediaOnly } from './familyFilter';
import { getKey } from './keys';
import { buildOrderAfterMove } from './reorder';
import {
	insertMediaShortcode,
	setMediaBlockDragData,
	setMediaShortcodeDragData,
} from './insertProductBlock';
import { blockEditorAvailable } from './blockEditor';
import { setItemAsFeatured } from './featuredImage';
import useModules from './useModules';
import MediaRosterRow from './MediaRosterRow';
import MediaEditDrawer from './MediaEditDrawer';
import ManagerModal from './ManagerModal';

// The sidebar Images/Videos panel — a full roster (the media analogue of
// ProductManagerPanel/CouponsPanel), parameterized by parserType ('IMAGE' /
// 'VIDEO'). Media lives in the shared store, so it reuses the same dispatch
// (remove/reorder) and the media components. "Search & add" and "Manage" open
// the shared manager scoped to this family. Media has no manual entry (all media
// modules are searchable), so there is no "Add manually" button — search only.
export default function MediaPanel( { parserType = 'IMAGE' } ) {
	const isVideo = parserType === 'VIDEO';
	const { media, error, isLoading, postId } = useSelect(
		( select ) => {
			const store = select( STORE_NAME );
			return {
				media: mediaOnly( store.getProducts() || [], parserType ),
				error: store.getError(),
				postId: store.getPostId(),
				// The GET resolver runs on getProducts; until it finishes, an
				// empty list is "not loaded yet", not "no media" — show a spinner
				// so we never flash the empty state on a post that has media.
				isLoading: ! store.hasFinishedResolution( 'getProducts', [] ),
			};
		},
		[ parserType ]
	);
	// "Set as featured image" is images-only.
	const isImage = parserType === 'IMAGE';
	const { removeProduct, reorder, setError } = useDispatch( STORE_NAME );
	const { modules } = useModules( { types: parserType } );
	const labelFor = ( id ) =>
		( modules.find( ( m ) => m.id === id ) || {} ).label || id;

	// insertMediaShortcode dispatches to core/block-editor (block screens) or the
	// classic editor; both need an editor present — hidden on Woo/CPT screens.
	const canInsert = blockEditorAvailable();
	// Authoritative block-vs-classic flag: rows carry a block drag payload only on
	// the block editor (drop → a content-egg/images|videos block); classic gets
	// shortcode text.
	const isBlockEditor =
		typeof window !== 'undefined' && window.ceggPmConfig?.isBlockEditor;

	// null when closed; otherwise the tab the manager opens on.
	const [ modalTab, setModalTab ] = useState( null );
	// null when closed; otherwise { moduleId, uniqueId } to edit.
	const [ editing, setEditing ] = useState( null );
	const [ dropIndex, setDropIndex ] = useState( null );
	const dragFrom = useRef( null );

	// Multi-select (visual) for the roster. Plain click selects one (and anchors);
	// Cmd/Ctrl toggles; Shift ranges. Keys are "moduleId:uniqueId".
	const [ selected, setSelected ] = useState( [] );
	const anchorRef = useRef( null );
	const keyAt = ( i ) => getKey( media[ i ].module_id, media[ i ].unique_id );
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
		reorder( buildOrderAfterMove( media, fromIndex, toIndex ) );
	};

	return (
		<>
			<div className="cegg-pm cegg-pm-sidebar cegg-pm-sidebar--media">
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
						{ isVideo
							? __( 'Search & add videos…', 'content-egg' )
							: __( 'Search & add images…', 'content-egg' ) }
					</Button>
					{ media.length > 0 && (
						<Button
							variant="secondary"
							className="cegg-pm-sidebar__manage"
							icon="list-view"
							label={
								isVideo
									? __( 'Manage videos', 'content-egg' )
									: __( 'Manage images', 'content-egg' )
							}
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
					{ ! isLoading && media.length === 0 && (
						<div className="cegg-pm-empty">
							<p className="cegg-pm-empty__title">
								{ isVideo
									? __( 'No videos yet', 'content-egg' )
									: __( 'No images yet', 'content-egg' ) }
							</p>
							<p className="cegg-pm-empty__hint">
								{ isVideo
									? __(
											'Use “Search & add videos” above to find and add clips.',
											'content-egg'
									  )
									: __(
											'Use “Search & add images” above to find and add photos.',
											'content-egg'
									  ) }
							</p>
						</div>
					) }
					{ ! isLoading &&
						media.map( ( item, index ) => (
							<MediaRosterRow
								key={ `${ item.module_id }:${ item.unique_id }` }
								media={ item }
								parserType={ parserType }
								moduleLabel={ labelFor( item.module_id ) }
								onEdit={ () =>
									setEditing( {
										moduleId: item.module_id,
										uniqueId: item.unique_id,
									} )
								}
								onRemove={ () =>
									removeProduct(
										item.module_id,
										item.unique_id
									)
								}
								onInsert={
									canInsert
										? () =>
												insertMediaShortcode(
													parserType,
													item
												)
										: undefined
								}
								onSetFeatured={
									isImage
										? () =>
												setItemAsFeatured(
													postId,
													item.module_id,
													item.unique_id
												)
										: undefined
								}
								isSelected={ selected.includes(
									getKey( item.module_id, item.unique_id )
								) }
								onSelect={ ( event ) =>
									handleSelect( index, event )
								}
								onDragStart={ ( event ) => {
									dragFrom.current = index;
									// The same drag also carries an insert payload
									// (block on the block editor, shortcode text on
									// classic); reorder uses dragFrom, not
									// dataTransfer, so the two never conflict. Drag
									// the whole selection when the grabbed row is part
									// of a multi-selection; the inserted block binds
									// to exactly those items (products="module:uid,…").
									const key = getKey(
										item.module_id,
										item.unique_id
									);
									const dragList =
										selected.includes( key ) &&
										selected.length > 1
											? media.filter( ( m ) =>
													selected.includes(
														getKey(
															m.module_id,
															m.unique_id
														)
													)
											  )
											: [ item ];
									if ( isBlockEditor ) {
										setMediaBlockDragData(
											event,
											parserType,
											dragList
										);
									} else {
										setMediaShortcodeDragData(
											event,
											parserType,
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
					family={ parserType }
					initialTab={ modalTab }
					onClose={ () => setModalTab( null ) }
				/>
			) }
			{ editing && (
				<MediaEditDrawer
					moduleId={ editing.moduleId }
					uniqueId={ editing.uniqueId }
					parserType={ parserType }
					onClose={ () => setEditing( null ) }
				/>
			) }
		</>
	);
}
