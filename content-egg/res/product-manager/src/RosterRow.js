import { DropdownMenu, MenuItem, Tooltip } from '@wordpress/components';
import { dateI18n, getSettings as getDateSettings } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';
import { badgeStyle } from './badge';
import { insertActionLabel } from './insertProductBlock';
import { formatPrice, formatOldPrice } from './formatPrice';
import HoverPreview from './HoverPreview';
import useHoverPreview from './useHoverPreview';

export default function RosterRow( {
	product,
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
	// Show the merchant (domain as fallback), which is more meaningful than the
	// internal module name. The link tooltip is the destination domain — it
	// describes where the link goes and reveals the region/TLD the merchant name
	// hides (e.g. "amazon.co.uk"). The module name lives in the Manager's Module
	// column, so it isn't needed here.
	const merchant = product?.merchant || product?.domain || moduleLabel;
	const domain = product?.domain || '';
	const price = formatPrice( product );
	// Only show the old price when it's genuinely a higher "was" price.
	const discounted =
		Number( product?.priceOld ) > Number( product?.price ) &&
		Number( product?.price ) > 0;
	const oldPrice = discounted ? formatOldPrice( product ) : '';
	// Merchant links straight to the product; orig_url is the raw merchant URL,
	// url the (possibly cloaked) affiliate link as fallback.
	const productUrl = product?.orig_url || product?.url || '';
	const outOfStock = Number( product?.stock_status ) === -1;
	const badgeColor = product?.badge
		? badgeStyle( product.badge_color ).background
		: '';
	// last_update is a unix timestamp of the last price/data refresh; show it as
	// the price tooltip, formatted in the site's date format and timezone.
	const updatedAt = Number( product?.last_update );
	const priceTitle =
		updatedAt > 0
			? sprintf(
					/* translators: %s: date/time the price was last updated */
					__( 'Updated: %s', 'content-egg' ),
					dateI18n(
						getDateSettings().formats.datetime,
						updatedAt * 1000
					)
			  )
			: undefined;

	// Enlarged image preview on thumbnail hover, to help scan the list. Anchored
	// to the LEFT of the sidebar (which hugs the screen's right edge) so it never
	// runs off-screen.
	const { thumbRef, position, show, hide } = useHoverPreview( {
		enabled: !! product?.img,
		side: 'left',
	} );

	return (
		// The row is a mouse-oriented drag + selection surface, not a control —
		// editing is on the ⋮ menu (keyboard-accessible) and double-click.
		/* eslint-disable-next-line jsx-a11y/no-static-element-interactions, jsx-a11y/click-events-have-key-events */
		<div
			className={ `cegg-pm-row${ isDropTarget ? ' is-drop-target' : '' }${
				outOfStock ? ' is-oos' : ''
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
				// Double-click the row to edit — but let the merchant link and
				// the ⋮ menu keep their own behavior.
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
				{ product?.img ? <img src={ product.img } alt="" /> : null }
				{ product?.badge && (
					<span
						className="cegg-pm-row__badge-dot"
						style={ { background: badgeColor } }
					/>
				) }
			</span>
			<HoverPreview
				position={ position }
				img={ product?.img }
				badge={ product?.badge }
				badgeColor={ product?.badge_color }
			/>
			<span className="cegg-pm-row__body">
				<div className="cegg-pm-row__title" title={ product?.title }>
					{ product?.title || product?.unique_id }
				</div>
				<div className="cegg-pm-row__meta">
					{ productUrl ? (
						<a
							className="cegg-pm-row__merchant"
							href={ productUrl }
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
					{ price ? (
						<>
							{ ' · ' }
							{ priceTitle ? (
								<Tooltip text={ priceTitle }>
									<span className="cegg-pm-row__price">
										{ price }
									</span>
								</Tooltip>
							) : (
								<span className="cegg-pm-row__price">
									{ price }
								</span>
							) }
							{ oldPrice ? (
								<span className="cegg-pm-row__price-old">
									{ oldPrice }
								</span>
							) : null }
						</>
					) : null }
					{ outOfStock &&
						( priceTitle ? (
							<Tooltip text={ priceTitle }>
								<span className="cegg-pm-row__oos">
									{ __( 'Out of stock', 'content-egg' ) }
								</span>
							</Tooltip>
						) : (
							<span className="cegg-pm-row__oos">
								{ __( 'Out of stock', 'content-egg' ) }
							</span>
						) ) }
				</div>
			</span>
			<DropdownMenu
				icon="ellipsis"
				label={ __( 'Product actions', 'content-egg' ) }
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
