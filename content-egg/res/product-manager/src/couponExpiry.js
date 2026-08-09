// Coupon validity state from its start/end window. Pure (no clock of its own —
// `now` is injected) so it unit-tests deterministically and can drive the row's
// expired/upcoming pill.
//
// Dates are stored as unix SECONDS (see ProductDataService's `date` sanitizer);
// a blank/zero bound means "no bound". `now` is also unix seconds and defaults
// to the current time for callers that don't inject one.

export function nowSeconds() {
	return Math.floor( Date.now() / 1000 );
}

// → 'expired' | 'upcoming' | 'active'
export function couponState( coupon = {}, now = nowSeconds() ) {
	const end = Number( coupon.endDate );
	if ( Number.isFinite( end ) && end > 0 && end < now ) {
		return 'expired';
	}
	const start = Number( coupon.startDate );
	if ( Number.isFinite( start ) && start > 0 && start > now ) {
		return 'upcoming';
	}
	return 'active';
}
