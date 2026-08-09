import { DropdownMenu, MenuItem } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { badgeStyle } from './badge';
import HoverPreview from './HoverPreview';
import useHoverPreview from './useHoverPreview';
import {
	couponValidity,
	couponPillLabel,
	couponDiscount,
} from './couponFields';
import { insertActionLabel } from './insertProductBlock';

// Compact sidebar roster row for a coupon — the coupon analogue of RosterRow.
// Shows the code chip, discount, and validity (with an expired/upcoming pill)
// instead of price/stock, and greys out once past its end date. Editing is on
// the ⋮ menu (keyboard-accessible) and double-click; the row itself is a
// drag-reorder / shortcode-drag surface.
export default function CouponRosterRow( {
	coupon,
	moduleLabel,
	onEdit,
	onRemove,
	onInsert,
	onSelect,
	isSelected,
	onDragStart,
	onDragOver,
	onDragEnd,
	onDrop,
	isDropTarget,
} ) {
	// The merchant (domain as fallback) reads more meaningfully than the module
	// name; the module name lives in the Manager's Module column.
	const merchant = coupon?.merchant || coupon?.domain || moduleLabel;
	const domain = coupon?.domain || '';
	const couponUrl = coupon?.orig_url || coupon?.url || '';
	const code = String( coupon?.code || '' ).trim();
	const discount = couponDiscount( coupon );
	const { state, label: validityLabel } = couponValidity( coupon );
	const pill = couponPillLabel( state );
	const badgeColor = coupon?.badge
		? badgeStyle( coupon.badge_color ).background
		: '';

	// Enlarged image preview on thumbnail hover, anchored to the LEFT of the
	// sidebar (which hugs the screen's right edge) so it never runs off-screen.
	const { thumbRef, position, show, hide } = useHoverPreview( {
		enabled: !! coupon?.img,
		side: 'left',
	} );

	return (
		// The row is a mouse-oriented drag + selection surface, not a control —
		// editing is on the ⋮ menu (keyboard-accessible) and double-click.
		/* eslint-disable-next-line jsx-a11y/no-static-element-interactions, jsx-a11y/click-events-have-key-events */
		<div
			className={ `cegg-pm-row${ isDropTarget ? ' is-drop-target' : '' }${
				state === 'expired' ? ' is-expired' : ''
			}${ isSelected ? ' is-selected' : '' }` }
			draggable
			onClick={ ( event ) => {
				// Row selection (for multi-drag). Leave the merchant link and the
				// ⋮ menu to their own handlers.
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
				// Double-click the row to edit — but let the merchant link and the
				// ⋮ menu keep their own behavior.
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
				{ coupon?.img ? <img src={ coupon.img } alt="" /> : null }
				{ coupon?.badge && (
					<span
						className="cegg-pm-row__badge-dot"
						style={ { background: badgeColor } }
					/>
				) }
			</span>
			<HoverPreview
				position={ position }
				img={ coupon?.img }
				badge={ coupon?.badge }
				badgeColor={ coupon?.badge_color }
			/>
			<span className="cegg-pm-row__body">
				<div className="cegg-pm-row__title" title={ coupon?.title }>
					{ coupon?.title || coupon?.unique_id }
				</div>
				<div className="cegg-pm-row__meta">
					{ couponUrl ? (
						<a
							className="cegg-pm-row__merchant"
							href={ couponUrl }
							target="_blank"
							rel="noreferrer"
							title={ domain || undefined }
						>
							{ merchant }
						</a>
					) : (
						<span
							className="cegg-pm-row__merchant"
							title={ domain || undefined }
						>
							{ merchant }
						</span>
					) }
					{ code && (
						<>
							{ ' · ' }
							<span className="cegg-pm-coupon__code">
								{ code }
							</span>
						</>
					) }
					{ discount && (
						<>
							{ ' ' }
							<span className="cegg-pm-coupon__discount">
								{ discount }
							</span>
						</>
					) }
					{ validityLabel && (
						<>
							{ ' · ' }
							<span className="cegg-pm-coupon__validity">
								{ validityLabel }
							</span>
						</>
					) }
					{ pill && (
						<>
							{ ' ' }
							<span
								className={ `cegg-pm-coupon__pill cegg-pm-coupon__pill--${ state }` }
							>
								{ pill }
							</span>
						</>
					) }
				</div>
			</span>
			<DropdownMenu
				icon="ellipsis"
				label={ __( 'Coupon actions', 'content-egg' ) }
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
