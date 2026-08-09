import { Button, Spinner, Tooltip } from '@wordpress/components';
import { dateI18n, getSettings as getDateSettings } from '@wordpress/date';
import { Fragment } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { badgeStyle } from './badge';
import { formatPrice, formatOldPrice } from './formatPrice';
import HoverPreview from './HoverPreview';
import useHoverPreview from './useHoverPreview';

// Render newline-separated text with real line breaks (a safe nl2br: React
// escapes each line, so nothing in the tag-stripped description is interpreted
// as HTML). htmlToText() already put each list item / paragraph on its own line.
function renderMultiline( text ) {
	return String( text )
		.split( '\n' )
		.map( ( line, i ) => (
			<Fragment key={ i }>
				{ i > 0 && <br /> }
				{ line }
			</Fragment>
		) );
}

export default function ResultRow( {
	product,
	isAdded,
	isBusy,
	disabled,
	onAdd,
	onCompareEan,
} ) {
	// Merchant (domain fallback) links to the product so it can be checked before
	// adding — more useful than the module name, which already labels the result
	// section above this row.
	const merchant = product?.merchant || product?.domain || '';
	const domain = product?.domain || '';
	const productUrl = product?.orig_url || product?.url || '';

	const price = formatPrice( product );
	// Only show the old price when it's genuinely a higher "was" price.
	const discounted =
		Number( product?.priceOld ) > Number( product?.price ) &&
		Number( product?.price ) > 0;
	const oldPrice = discounted ? formatOldPrice( product ) : '';
	const promo = String( product?.promo || '' ).trim();

	const rating = product?.ratingDecimal || product?.rating || '';
	// reviewsCount may arrive as an int or a pre-formatted string ("1,203").
	const reviews = parseInt(
		String( product?.reviewsCount ?? '' ).replace( /[^\d]/g, '' ),
		10
	);
	const hasReviews = Number.isFinite( reviews ) && reviews > 0;

	const ean = String( product?.ean || '' ).trim();
	const description = String( product?._descriptionText || '' ).trim();
	const outOfStock = Number( product?.stock_status ) === -1;
	// Free shipping only when shipping_cost is explicitly 0; empty/absent means
	// "unknown", not free.
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

	const badgeColor = product?.badge
		? badgeStyle( product.badge_color ).background
		: '';

	// last_update is a unix timestamp of the last price/data refresh; surface it
	// as the price tooltip so a stale price is discoverable.
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

	// Preview opens to the RIGHT — results sit in a left-aligned column.
	const { thumbRef, position, show, hide } = useHoverPreview( {
		enabled: !! product?.img,
		side: 'right',
	} );

	const priceEl = priceTitle ? (
		<Tooltip text={ priceTitle }>
			<span className="cegg-pm-result__price">{ price }</span>
		</Tooltip>
	) : (
		<span className="cegg-pm-result__price">{ price }</span>
	);

	const addable = ! isAdded && ! isBusy && ! disabled;

	// The whole row adds the product on click (matching the legacy metabox),
	// except clicks on the merchant link or the Add button, which keep their own
	// behavior. Interaction props are attached only when the row is addable, so
	// added / disabled rows are inert.
	const addFromRow = ( event ) => {
		if ( event.target.closest( 'a, button' ) ) {
			return;
		}
		onAdd();
	};
	const rowInteraction = addable
		? {
				role: 'button',
				tabIndex: 0,
				onClick: addFromRow,
				onKeyDown: ( event ) => {
					if ( event.key !== 'Enter' && event.key !== ' ' ) {
						return;
					}
					if ( event.target.closest( 'a, button' ) ) {
						return;
					}
					event.preventDefault();
					onAdd();
				},
				'aria-label': sprintf(
					/* translators: %s: product title */
					__( 'Add %s', 'content-egg' ),
					product?.title || product?.unique_id || ''
				),
		  }
		: {};

	return (
		<div
			className={ `cegg-pm-result${ outOfStock ? ' is-oos' : '' }${
				isAdded ? ' is-added' : ''
			}${ addable ? ' is-clickable' : '' }` }
			{ ...rowInteraction }
		>
			<span
				ref={ thumbRef }
				className="cegg-pm-thumb cegg-pm-result__thumb"
				onMouseEnter={ show }
				onMouseLeave={ hide }
			>
				{ product?.img ? <img src={ product.img } alt="" /> : null }
				{ product?.badge && (
					<span
						className="cegg-pm-result__badge-dot"
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

			<div className="cegg-pm-result__body">
				<div
					className="cegg-pm-result__title"
					title={ product?.title || product?.unique_id }
				>
					{ product?.title || product?.unique_id }
				</div>

				{ description && (
					<div className="cegg-pm-result__desc">
						{ renderMultiline( description ) }
					</div>
				) }

				<div className="cegg-pm-result__meta">
					{ price && priceEl }
					{ oldPrice && (
						<span className="cegg-pm-result__price-old">
							{ oldPrice }
						</span>
					) }
					{ promo && (
						<span className="cegg-pm-result__promo">{ promo }</span>
					) }
					{ rating && (
						<span className="cegg-pm-result__rating">
							{ `★ ${ rating }` }
							{ hasReviews
								? ` (${ reviews.toLocaleString() })`
								: '' }
						</span>
					) }
				</div>

				<div className="cegg-pm-result__source">
					{ merchant &&
						( productUrl ? (
							<a
								className="cegg-pm-result__merchant"
								href={ productUrl }
								target="_blank"
								rel="noreferrer"
								title={ domain || undefined }
							>
								{ merchant }
							</a>
						) : (
							<span
								className="cegg-pm-result__merchant"
								title={ domain || undefined }
							>
								{ merchant }
							</span>
						) ) }
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
									className="cegg-pm-result__ean cegg-pm-result__ean--action"
									onClick={ () => onCompareEan( ean ) }
								>
									{ __( 'EAN', 'content-egg' ) } { ean }
								</button>
							</Tooltip>
						) : (
							<span className="cegg-pm-result__ean">
								{ __( 'EAN', 'content-egg' ) } { ean }
							</span>
						) ) }
					{ freeShipping && (
						<span className="cegg-pm-result__free-ship">
							{ __( 'Free shipping', 'content-egg' ) }
						</span>
					) }
					{ primeExclusive && (
						<span className="cegg-pm-result__prime">
							{ __( 'Prime exclusive', 'content-egg' ) }
						</span>
					) }
					{ primeEarlyAccess && (
						<span className="cegg-pm-result__prime">
							{ __( 'Prime early access', 'content-egg' ) }
						</span>
					) }
					{ availabilityMessage && (
						<span
							className="cegg-pm-result__availability"
							title={ availabilityMessage }
						>
							{ availabilityMessage }
						</span>
					) }
					{ outOfStock &&
						( priceTitle ? (
							<Tooltip text={ priceTitle }>
								<span className="cegg-pm-result__oos">
									{ __( 'Out of stock', 'content-egg' ) }
								</span>
							</Tooltip>
						) : (
							<span className="cegg-pm-result__oos">
								{ __( 'Out of stock', 'content-egg' ) }
							</span>
						) ) }
				</div>
			</div>

			{ isBusy ? (
				// A real, visible spinner while the save is in flight. (Button's
				// own isBusy is a background shimmer that a disabled/tertiary
				// "Added" button would hide.)
				<span className="cegg-pm-result__add cegg-pm-result__saving">
					<Spinner />
				</span>
			) : (
				<Button
					className="cegg-pm-result__add"
					variant={ isAdded ? 'tertiary' : 'secondary' }
					size="compact"
					disabled={ disabled || isAdded }
					onClick={ onAdd }
				>
					{ isAdded
						? __( 'Added', 'content-egg' )
						: __( 'Add', 'content-egg' ) }
				</Button>
			) }
		</div>
	);
}
