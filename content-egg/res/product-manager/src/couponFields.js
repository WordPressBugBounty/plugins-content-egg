// Shared presentation helpers for coupon rows (search result + Added table),
// so the code chip, discount badge, and validity line read identically in both.
import { dateI18n, getSettings as getDateSettings } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';
import { couponState } from './couponExpiry';

// Format a unix-seconds date in the site's date format/timezone; '' when unset.
export function formatCouponDate( seconds ) {
	const n = Number( seconds );
	if ( ! Number.isFinite( n ) || n <= 0 ) {
		return '';
	}
	return dateI18n( getDateSettings().formats.date, n * 1000 );
}

// A short human validity label + the state ('active'|'upcoming'|'expired') for
// the row pill. Returns { state, label } — label may be '' when no bounds.
export function couponValidity( coupon = {} ) {
	const state = couponState( coupon );
	const start = formatCouponDate( coupon.startDate );
	const end = formatCouponDate( coupon.endDate );

	let label = '';
	if ( end ) {
		label = sprintf(
			/* translators: %s: coupon expiry date */
			__( 'Expires %s', 'content-egg' ),
			end
		);
	} else if ( start ) {
		label = sprintf(
			/* translators: %s: coupon start date */
			__( 'Starts %s', 'content-egg' ),
			start
		);
	}
	return { state, label };
}

// The pill text for a non-active coupon ('' when active → no pill).
export function couponPillLabel( state ) {
	if ( state === 'expired' ) {
		return __( 'Expired', 'content-egg' );
	}
	if ( state === 'upcoming' ) {
		return __( 'Upcoming', 'content-egg' );
	}
	return '';
}

// The discount string carried in extra.discount (module-specific), trimmed.
export function couponDiscount( coupon = {} ) {
	return String( coupon?.extra?.discount || '' ).trim();
}
