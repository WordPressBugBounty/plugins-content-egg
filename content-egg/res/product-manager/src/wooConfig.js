// Gate: true on a WooCommerce product post (server-computed, cross-shell).
export function isWooProduct() {
	return !! (
		typeof window !== 'undefined' && window.ceggPmConfig?.isWooProduct
	);
}

// Read a stored woo flag as a boolean — the metabox stores the string 'true'.
export function wooFlagOn( item, field ) {
	const v = item?.[ field ];
	return v === true || v === 'true' || v === '1' || v === 1;
}
