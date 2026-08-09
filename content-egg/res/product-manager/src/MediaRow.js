import {
	Button,
	CheckboxControl,
	DropdownMenu,
	MenuItem,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { groupSelectOptions } from './groups';
import HoverPreview from './HoverPreview';
import useHoverPreview from './useHoverPreview';
import { mediaSource } from './mediaFields';
import { insertActionLabel } from './insertProductBlock';

// One row of the media Added table — the image/video analogue of AttachedRow.
// Shows a thumbnail + title and a Type/source cell (video source or module),
// instead of price/stock.
export default function MediaRow( {
	media,
	moduleLabel,
	parserType,
	groups,
	selected,
	onToggleSelect,
	onSetGroup,
	onEdit,
	onInsert,
	onSetFeatured,
	onRemove,
	onDragStart,
	onDragOver,
	onDragEnd,
	onDrop,
	isDropTarget,
	compact,
} ) {
	const isVideo = parserType === 'VIDEO';
	const source = isVideo ? mediaSource( media ) : null;
	const mediaUrl = media?.orig_url || media?.url || '';
	const title = decodeEntities( media?.title || media?.unique_id || '' );
	let typeLabel = isVideo
		? __( 'Video', 'content-egg' )
		: __( 'Image', 'content-egg' );
	if ( source ) {
		typeLabel = source.label;
	}

	const { thumbRef, position, show, hide } = useHoverPreview( {
		enabled: !! media?.img,
		side: 'right',
	} );

	return (
		<tr
			className={ `cegg-pm-arow cegg-pm-mrow${
				isDropTarget ? ' is-drop-target' : ''
			}` }
			draggable
			onDragStart={ onDragStart }
			onDragOver={ onDragOver }
			onDragEnd={ onDragEnd }
			onDrop={ onDrop }
			onDoubleClick={ ( event ) => {
				if (
					event.target.closest(
						'a, button, input, select, textarea, label'
					)
				) {
					return;
				}
				event.preventDefault();
				onEdit();
			} }
		>
			<td className="cegg-pm-arow__grip-cell">
				<span className="cegg-pm-arow__grip" aria-hidden="true">
					⠿
				</span>
			</td>
			<td className="cegg-pm-arow__select">
				<CheckboxControl
					checked={ selected }
					onChange={ onToggleSelect }
					__nextHasNoMarginBottom
					aria-label={ sprintf(
						/* translators: %s: media title */
						__( 'Select %s', 'content-egg' ),
						title
					) }
				/>
			</td>
			<td className="cegg-pm-arow__product">
				<div className="cegg-pm-arow__cell">
					<span
						ref={ thumbRef }
						className="cegg-pm-thumb cegg-pm-arow__thumb"
						onMouseEnter={ show }
						onMouseLeave={ hide }
					>
						{ media?.img ? <img src={ media.img } alt="" /> : null }
					</span>
					<HoverPreview position={ position } img={ media?.img } />
					<span className="cegg-pm-arow__text">
						<span className="cegg-pm-arow__titlerow">
							<span
								className="cegg-pm-arow__title"
								title={ title }
							>
								{ title }
							</span>
						</span>
						{ mediaUrl && (
							<span className="cegg-pm-arow__source">
								<a
									className="cegg-pm-arow__merchant"
									href={ mediaUrl }
									target="_blank"
									rel="noreferrer"
								>
									{ mediaUrl }
								</a>
							</span>
						) }
					</span>
				</div>
			</td>
			{ ! compact && (
				<td className="cegg-pm-arow__module-cell">
					<span
						className="cegg-pm-arow__module"
						title={ moduleLabel }
					>
						{ moduleLabel }
					</span>
				</td>
			) }
			<td className="cegg-pm-mrow__type-cell">
				<span className="cegg-pm-media__source">{ typeLabel }</span>
			</td>
			<td className="cegg-pm-arow__group-cell">
				<select
					className="cegg-pm-arow__group-select"
					value={ media?.group || '' }
					onChange={ ( event ) => onSetGroup( event.target.value ) }
					aria-label={ __( 'Group', 'content-egg' ) }
				>
					{ groupSelectOptions( groups ).map( ( option ) => (
						<option key={ option.value } value={ option.value }>
							{ option.label }
						</option>
					) ) }
				</select>
			</td>
			<td className="cegg-pm-arow__actions">
				<div className="cegg-pm-arow__actions-row">
					<Button
						icon="edit"
						label={ __( 'Edit', 'content-egg' ) }
						showTooltip
						size="small"
						onClick={ onEdit }
					/>
					<Button
						icon="trash"
						label={ __( 'Remove', 'content-egg' ) }
						showTooltip
						size="small"
						onClick={ onRemove }
					/>
					{ ( onInsert || onSetFeatured ) && (
						<DropdownMenu
							icon="ellipsis"
							label={ __( 'More actions', 'content-egg' ) }
							toggleProps={ { isSmall: true } }
						>
							{ ( { onClose } ) => (
								<>
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
											{ __(
												'Set as featured image',
												'content-egg'
											) }
										</MenuItem>
									) }
								</>
							) }
						</DropdownMenu>
					) }
				</div>
			</td>
		</tr>
	);
}
