import {
	Button,
	CheckboxControl,
	DropdownMenu,
	MenuItem,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { groupSelectOptions } from './groups';
import HoverPreview from './HoverPreview';
import useHoverPreview from './useHoverPreview';
import {
	couponValidity,
	couponPillLabel,
	couponDiscount,
} from './couponFields';
import { insertActionLabel } from './insertProductBlock';

// One row of the coupon Added table — the coupon analogue of AttachedRow. Shows
// the code, discount, and validity (with an expired/upcoming pill) instead of
// price/stock. A past-end coupon greys out via the row's is-expired class.
export default function CouponRow( {
	coupon,
	moduleLabel,
	groups,
	selected,
	onToggleSelect,
	onSetGroup,
	onEdit,
	onInsert,
	onRemove,
	onDragStart,
	onDragOver,
	onDragEnd,
	onDrop,
	isDropTarget,
	compact,
} ) {
	const domain = coupon?.domain || '';
	const merchantName = coupon?.merchant || '';
	const sourceLabel = domain || merchantName || moduleLabel;
	const sourceTitle = merchantName || domain || moduleLabel;
	const couponUrl = coupon?.orig_url || coupon?.url || '';
	const code = String( coupon?.code || '' ).trim();
	const discount = couponDiscount( coupon );
	const { state, label: validityLabel } = couponValidity( coupon );
	const pill = couponPillLabel( state );

	const { thumbRef, position, show, hide } = useHoverPreview( {
		enabled: !! coupon?.img,
		side: 'right',
	} );

	return (
		<tr
			className={ `cegg-pm-arow cegg-pm-crow is-${ state }${
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
						/* translators: %s: coupon title */
						__( 'Select %s', 'content-egg' ),
						coupon?.title || coupon?.unique_id || ''
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
						{ coupon?.img ? (
							<img src={ coupon.img } alt="" />
						) : null }
					</span>
					<HoverPreview position={ position } img={ coupon?.img } />
					<span className="cegg-pm-arow__text">
						<span className="cegg-pm-arow__titlerow">
							<span
								className="cegg-pm-arow__title"
								title={ coupon?.title || coupon?.unique_id }
							>
								{ coupon?.title || coupon?.unique_id }
							</span>
							{ pill && (
								<span
									className={ `cegg-pm-coupon__pill cegg-pm-coupon__pill--${ state }` }
								>
									{ pill }
								</span>
							) }
						</span>
						<span className="cegg-pm-arow__source">
							{ couponUrl ? (
								<a
									className="cegg-pm-arow__merchant"
									href={ couponUrl }
									target="_blank"
									rel="noreferrer"
									title={ sourceTitle || undefined }
								>
									{ sourceLabel }
								</a>
							) : (
								<span
									className="cegg-pm-arow__merchant"
									title={ sourceTitle || undefined }
								>
									{ sourceLabel }
								</span>
							) }
							{ code && (
								<span className="cegg-pm-coupon__code">
									{ code }
								</span>
							) }
							{ discount && (
								<span className="cegg-pm-coupon__discount">
									{ discount }
								</span>
							) }
						</span>
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
			<td className="cegg-pm-crow__validity-cell">
				{ validityLabel ? (
					<span className="cegg-pm-coupon__validity">
						{ validityLabel }
					</span>
				) : (
					<span className="cegg-pm-arow__price-empty">—</span>
				) }
			</td>
			<td className="cegg-pm-arow__group-cell">
				<select
					className="cegg-pm-arow__group-select"
					value={ coupon?.group || '' }
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
					{ onInsert && (
						<DropdownMenu
							icon="ellipsis"
							label={ __( 'More actions', 'content-egg' ) }
							toggleProps={ { isSmall: true } }
						>
							{ ( { onClose } ) => (
								<MenuItem
									onClick={ () => {
										onClose();
										onInsert();
									} }
								>
									{ insertActionLabel() }
								</MenuItem>
							) }
						</DropdownMenu>
					) }
				</div>
			</td>
		</tr>
	);
}
