import {
	Button,
	CheckboxControl,
	DropdownMenu,
	MenuItem,
	Tooltip,
} from '@wordpress/components';
import { dateI18n, getSettings as getDateSettings } from '@wordpress/date';
import { __, _n, sprintf } from '@wordpress/i18n';
import { badgeTintStyle } from './badge';
import { clicksLabel30 } from './clicksConfig';
import { formatPrice, formatOldPrice } from './formatPrice';
import { groupSelectOptions } from './groups';
import HoverPreview from './HoverPreview';
import useHoverPreview from './useHoverPreview';
import { isWooProduct, wooFlagOn } from './wooConfig';
import { insertActionLabel } from './insertProductBlock';

// One row of the Added (Manage products) table. Carries the same signals as the
// search-result and roster rows — merchant, stock, discount, badge, hover
// preview — because this is where the user decides what to keep, refresh, or
// drop, so it should show the most signal, not the least.
export default function AttachedRow( {
	product,
	moduleLabel,
	groups,
	selected,
	onToggleSelect,
	onSetGroup,
	onEdit,
	onInsert,
	onRemove,
	onCompareEan,
	onDragStart,
	onDragOver,
	onDragEnd,
	onDrop,
	isDropTarget,
	showClicks,
	compact,
} ) {
	// Show the domain by default — it's the most recognizable source label; fall
	// back to the merchant name, then the module label. The merchant name (fuller
	// than a bare domain) stays available as the link's hover title.
	const domain = product?.domain || '';
	const merchantName = product?.merchant || '';
	const sourceLabel = domain || merchantName || moduleLabel;
	const sourceTitle = merchantName || domain || moduleLabel;
	const productUrl = product?.orig_url || product?.url || '';
	const outOfStock = Number( product?.stock_status ) === -1;

	const price = formatPrice( product );
	// Only show the old price when it's genuinely a higher "was" price.
	const discounted =
		Number( product?.priceOld ) > Number( product?.price ) &&
		Number( product?.price ) > 0;
	const oldPrice = discounted ? formatOldPrice( product ) : '';

	const subtitle = String( product?.subtitle || '' ).trim();
	const promo = String( product?.promo || '' ).trim();
	const ean = String( product?.ean || '' ).trim();
	const attributeCount = Array.isArray( product?.features )
		? product.features.length
		: 0;

	const rating = product?.ratingDecimal || product?.rating || '';
	// reviewsCount may arrive as an int or a pre-formatted string ("1,203").
	const reviews = parseInt(
		String( product?.reviewsCount ?? '' ).replace( /[^\d]/g, '' ),
		10
	);
	const hasReviews = Number.isFinite( reviews ) && reviews > 0;

	// percentageSaved is CE's rounded discount percent.
	const percentSaved = parseInt( product?.percentageSaved, 10 );
	const hasDiscount = Number.isFinite( percentSaved ) && percentSaved > 0;

	// Free shipping only when shipping_cost is explicitly 0 (Amazon super-saver or
	// a feed's zero cost); an empty/absent cost means "unknown", not free.
	const shippingCost = product?.shipping_cost;
	const freeShipping =
		shippingCost !== '' &&
		shippingCost !== null &&
		shippingCost !== undefined &&
		Number( shippingCost ) === 0;

	// Amazon-specific signals carried in `extra` (mirrors the legacy metabox):
	// Prime deal access + a human availability message ("Only 2 left", etc.).
	const extra = product?.extra || {};
	const dealAccessType = String( extra?.DealAccessType || '' );
	const primeExclusive = dealAccessType === 'PRIME_EXCLUSIVE';
	const primeEarlyAccess = dealAccessType === 'PRIME_EARLY_ACCESS';
	const availabilityMessage = String(
		extra?.AvailabilityMessage || ''
	).trim();

	// last_update is a unix timestamp of the last price/data refresh; surface it
	// as the price tooltip so a stale price is discoverable in the manage view.
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

	// Preview opens to the RIGHT — the table sits in a left-aligned column with
	// open space to its right.
	const { thumbRef, position, show, hide } = useHoverPreview( {
		enabled: !! product?.img,
		side: 'right',
	} );

	const priceEl = priceTitle ? (
		<Tooltip text={ priceTitle }>
			<span className="cegg-pm-arow__price">{ price }</span>
		</Tooltip>
	) : (
		<span className="cegg-pm-arow__price">{ price }</span>
	);

	return (
		<tr
			className={ `cegg-pm-arow${
				isDropTarget ? ' is-drop-target' : ''
			}${ outOfStock ? ' is-oos' : '' }` }
			draggable
			onDragStart={ onDragStart }
			onDragOver={ onDragOver }
			onDragEnd={ onDragEnd }
			onDrop={ onDrop }
			onDoubleClick={ ( event ) => {
				// Double-click the row to edit (same as the sidebar roster) — but
				// let the merchant link, the ⋮ menu, and the inline checkbox /
				// group controls keep their own behavior.
				if (
					event.target.closest(
						'a, button, input, select, textarea, label'
					)
				) {
					return;
				}
				event.preventDefault(); // suppress text selection
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
						/* translators: %s: product title */
						__( 'Select %s', 'content-egg' ),
						product?.title || product?.unique_id || ''
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
						{ product?.img ? (
							<img src={ product.img } alt="" />
						) : null }
					</span>
					<HoverPreview
						position={ position }
						img={ product?.img }
						badge={ product?.badge }
						badgeColor={ product?.badge_color }
					/>
					<span className="cegg-pm-arow__text">
						{ /* Title leads every row (consistent scan line); the
						     badge trails it inline so it stays visible without
						     pushing the title down or into the merchant row. */ }
						<span className="cegg-pm-arow__titlerow">
							<span
								className="cegg-pm-arow__title"
								title={ product?.title || product?.unique_id }
							>
								{ product?.title || product?.unique_id }
							</span>
							{ product?.badge && (
								<span
									className="cegg-pm-arow__badge"
									style={ badgeTintStyle(
										product.badge_color
									) }
								>
									{ product.badge }
								</span>
							) }
						</span>
						{ subtitle && (
							<span
								className="cegg-pm-arow__subtitle"
								title={ subtitle }
							>
								{ subtitle }
							</span>
						) }
						{ promo && (
							<span className="cegg-pm-arow__meta">
								<span className="cegg-pm-arow__promo">
									{ promo }
								</span>
							</span>
						) }
						<span className="cegg-pm-arow__source">
							{ productUrl ? (
								<a
									className="cegg-pm-arow__merchant"
									href={ productUrl }
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
							{ rating && (
								<span className="cegg-pm-arow__rating">
									{ `★ ${ rating }` }
									{ hasReviews
										? ` (${ reviews.toLocaleString() })`
										: '' }
								</span>
							) }
							{ attributeCount > 0 && (
								<span className="cegg-pm-arow__attrs">
									{ sprintf(
										/* translators: %d: number of product attributes */
										_n(
											'%d attribute',
											'%d attributes',
											attributeCount,
											'content-egg'
										),
										attributeCount
									) }
								</span>
							) }
							{ isWooProduct() &&
								wooFlagOn( product, 'woo_sync' ) && (
									<span
										className="cegg-pm-arow__woo"
										title={ __(
											'WooCommerce sync product',
											'content-egg'
										) }
									>
										{ __( 'Woo sync', 'content-egg' ) }
									</span>
								) }
							{ isWooProduct() &&
								wooFlagOn( product, 'woo_attr' ) && (
									<span
										className="cegg-pm-arow__woo"
										title={ __(
											'WooCommerce attributes source',
											'content-egg'
										) }
									>
										{ __( 'Woo attrs', 'content-egg' ) }
									</span>
								) }
							{ ean &&
								( onCompareEan ? (
									<Tooltip
										text={ __(
											'Compare this EAN across all merchants',
											'content-egg'
										) }
									>
										<button
											type="button"
											className="cegg-pm-arow__ean cegg-pm-arow__ean--action"
											onClick={ () =>
												onCompareEan( ean )
											}
										>
											{ __( 'EAN', 'content-egg' ) }{ ' ' }
											{ ean }
										</button>
									</Tooltip>
								) : (
									<span className="cegg-pm-arow__ean">
										{ __( 'EAN', 'content-egg' ) } { ean }
									</span>
								) ) }
							{ freeShipping && (
								<span className="cegg-pm-arow__free-ship">
									{ __( 'Free shipping', 'content-egg' ) }
								</span>
							) }
							{ primeExclusive && (
								<span className="cegg-pm-arow__prime">
									{ __( 'Prime exclusive', 'content-egg' ) }
								</span>
							) }
							{ primeEarlyAccess && (
								<span className="cegg-pm-arow__prime">
									{ __(
										'Prime early access',
										'content-egg'
									) }
								</span>
							) }
							{ availabilityMessage && (
								<span
									className="cegg-pm-arow__availability"
									title={ availabilityMessage }
								>
									{ availabilityMessage }
								</span>
							) }
							{ outOfStock &&
								( priceTitle ? (
									<Tooltip text={ priceTitle }>
										<span className="cegg-pm-arow__oos">
											{ __(
												'Out of stock',
												'content-egg'
											) }
										</span>
									</Tooltip>
								) : (
									<span className="cegg-pm-arow__oos">
										{ __( 'Out of stock', 'content-egg' ) }
									</span>
								) ) }
						</span>
					</span>
				</div>
			</td>
			{ /* Module column is dropped in the compact (metabox) layout — the
			     source/merchant already shows in the product cell, and the
			     narrow metabox needs the width for the title. */ }
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
			<td className="cegg-pm-arow__price-cell">
				{ price ? (
					<>
						{ priceEl }
						{ oldPrice ? (
							<span className="cegg-pm-arow__price-old">
								{ oldPrice }
							</span>
						) : null }
						{ hasDiscount ? (
							<span className="cegg-pm-arow__discount">
								{ `−${ percentSaved }%` }
							</span>
						) : null }
					</>
				) : (
					<span className="cegg-pm-arow__price-empty">—</span>
				) }
			</td>
			{ showClicks && (
				<td className="cegg-pm-arow__clicks-cell">
					<ClicksCell
						d30={ product?._clicks_30d }
						total={ product?._clicks }
					/>
				</td>
			) }
			<td className="cegg-pm-arow__group-cell">
				{ /* Native <select> (not WP SelectControl) so we own the border:
				     the row control stays quiet until hover/focus. SelectControl
				     draws its border on a runtime-styled backdrop that can't be
				     reliably overridden from a stylesheet. */ }
				<select
					className="cegg-pm-arow__group-select"
					value={ product?.group || '' }
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
					{ /* The ⋮ menu currently holds only "Insert block", which
					     depends on core/block-editor — absent on classic screens.
					     Render it only when an insert handler is provided. */ }
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

function ClicksCell( { d30, total } ) {
	const d = Number( d30 ) || 0;
	const t = Number( total ) || 0;
	if ( ! d && ! t ) {
		return <span className="cegg-pm-clicks cegg-pm-clicks--empty">–</span>;
	}
	return (
		<span
			className="cegg-pm-clicks"
			title={ sprintf(
				// translators: 1: window label, 2: recent clicks, 3: total clicks
				__( '%1$s: %2$d · Total: %3$d', 'content-egg' ),
				clicksLabel30(),
				d,
				t
			) }
		>
			<span className="cegg-pm-clicks__primary">{ d }</span>
			<span className="cegg-pm-clicks__total">{ t }</span>
		</span>
	);
}
