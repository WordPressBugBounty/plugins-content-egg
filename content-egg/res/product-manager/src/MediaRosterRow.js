import { DropdownMenu, MenuItem } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import HoverPreview from './HoverPreview';
import useHoverPreview from './useHoverPreview';
import { mediaSource } from './mediaFields';
import { insertActionLabel } from './insertProductBlock';

// Compact sidebar roster row for a media item — the image/video analogue of
// RosterRow. Shows a thumbnail + title and (for video) a source badge. Editing
// is on the ⋮ menu (keyboard-accessible) and double-click; the row itself is a
// drag-reorder / shortcode-drag surface.
export default function MediaRosterRow( {
	media,
	moduleLabel,
	parserType,
	onEdit,
	onRemove,
	onInsert,
	onSetFeatured,
	onSelect,
	isSelected,
	onDragStart,
	onDragOver,
	onDragEnd,
	onDrop,
	isDropTarget,
} ) {
	const isVideo = parserType === 'VIDEO';
	const source = isVideo ? mediaSource( media ) : null;
	const label = source ? source.label : moduleLabel;
	const title = decodeEntities( media?.title || media?.unique_id || '' );

	// Enlarged image preview on thumbnail hover, anchored to the LEFT of the
	// sidebar (which hugs the screen's right edge) so it never runs off-screen.
	const { thumbRef, position, show, hide } = useHoverPreview( {
		enabled: !! media?.img,
		side: 'left',
	} );

	return (
		// The row is a mouse-oriented drag + selection surface, not a control —
		// editing is on the ⋮ menu (keyboard-accessible) and double-click.
		/* eslint-disable-next-line jsx-a11y/no-static-element-interactions, jsx-a11y/click-events-have-key-events */
		<div
			className={ `cegg-pm-row${ isDropTarget ? ' is-drop-target' : '' }${
				isSelected ? ' is-selected' : ''
			}` }
			draggable
			onClick={ ( event ) => {
				// Row selection (for multi-drag). Leave the ⋮ menu to its own handler.
				if ( event.target.closest( 'a, button' ) ) {
					return;
				}
				onSelect?.( event );
			} }
			onDragStart={ onDragStart }
			onDragOver={ onDragOver }
			onDragEnd={ onDragEnd }
			onDrop={ onDrop }
			onDoubleClick={ ( event ) => {
				// Double-click the row to edit — but let the ⋮ menu keep its behavior.
				if ( event.target.closest( 'a, button' ) ) {
					return;
				}
				event.preventDefault(); // suppress text selection
				onEdit();
			} }
		>
			<span className="cegg-pm-row__grip">⠿</span>
			<span
				ref={ thumbRef }
				className="cegg-pm-thumb cegg-pm-row__thumb"
				onMouseEnter={ show }
				onMouseLeave={ hide }
			>
				{ media?.img ? <img src={ media.img } alt="" /> : null }
				{ isVideo && (
					<span className="cegg-pm-row__play" aria-hidden="true">
						▶
					</span>
				) }
			</span>
			<HoverPreview position={ position } img={ media?.img } />
			<span className="cegg-pm-row__body">
				<div className="cegg-pm-row__title" title={ title }>
					{ title }
				</div>
				{ label && (
					<div className="cegg-pm-row__meta">
						<span className="cegg-pm-media__source">{ label }</span>
					</div>
				) }
			</span>
			<DropdownMenu
				icon="ellipsis"
				label={ __( 'Media actions', 'content-egg' ) }
				toggleProps={ { isSmall: true } }
			>
				{ ( { onClose } ) => (
					<>
						<MenuItem
							onClick={ () => {
								onClose();
								onEdit();
							} }
						>
							{ __( 'Edit', 'content-egg' ) }
						</MenuItem>
						{ onInsert && (
							<MenuItem
								onClick={ () => {
									onClose();
									onInsert();
								} }
							>
								{ insertActionLabel() }
							</MenuItem>
						) }
						{ onSetFeatured && (
							<MenuItem
								onClick={ () => {
									onClose();
									onSetFeatured();
								} }
							>
								{ __( 'Set as featured image', 'content-egg' ) }
							</MenuItem>
						) }
						<MenuItem
							isDestructive
							onClick={ () => {
								onClose();
								onRemove();
							} }
						>
							{ __( 'Remove', 'content-egg' ) }
						</MenuItem>
					</>
				) }
			</DropdownMenu>
		</div>
	);
}
