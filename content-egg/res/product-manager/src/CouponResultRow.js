import { Button, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import HoverPreview from './HoverPreview';
import useHoverPreview from './useHoverPreview';
import {
	couponValidity,
	couponPillLabel,
	couponDiscount,
} from './couponFields';

// A coupon search result — the coupon analogue of ResultRow. Shows the signals
// that matter for a voucher (code, discount, validity, merchant) rather than
// price/stock. Clicking the row adds it (except the merchant link / Add button).
export default function CouponResultRow( {
	product,
	isAdded,
	isBusy,
	disabled,
	onAdd,
} ) {
	const merchant = product?.merchant || product?.domain || '';
	const domain = product?.domain || '';
	const couponUrl = product?.orig_url || product?.url || '';
	const code = String( product?.code || '' ).trim();
	const discount = couponDiscount( product );
	const { state, label: validityLabel } = couponValidity( product );
	const pill = couponPillLabel( state );

	const { thumbRef, position, show, hide } = useHoverPreview( {
		enabled: !! product?.img,
		side: 'right',
	} );

	const addable = ! isAdded && ! isBusy && ! disabled;
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
					/* translators: %s: coupon title */
					__( 'Add %s', 'content-egg' ),
					product?.title || product?.unique_id || ''
				),
		  }
		: {};

	return (
		<div
			className={ `cegg-pm-result cegg-pm-coupon-result is-${ state }${
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
			</span>
			<HoverPreview position={ position } img={ product?.img } />

			<div className="cegg-pm-result__body">
				<div
					className="cegg-pm-result__title"
					title={ product?.title || product?.unique_id }
				>
					{ product?.title || product?.unique_id }
				</div>

				<div className="cegg-pm-result__meta">
					{ code && (
						<span className="cegg-pm-coupon__code">{ code }</span>
					) }
					{ discount && (
						<span className="cegg-pm-coupon__discount">
							{ discount }
						</span>
					) }
					{ validityLabel && (
						<span className="cegg-pm-coupon__validity">
							{ validityLabel }
						</span>
					) }
					{ pill && (
						<span
							className={ `cegg-pm-coupon__pill cegg-pm-coupon__pill--${ state }` }
						>
							{ pill }
						</span>
					) }
				</div>

				<div className="cegg-pm-result__source">
					{ merchant &&
						( couponUrl ? (
							<a
								className="cegg-pm-result__merchant"
								href={ couponUrl }
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
				</div>
			</div>

			{ isBusy ? (
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
