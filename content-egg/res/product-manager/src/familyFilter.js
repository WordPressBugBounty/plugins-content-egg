// Family (parser-type) partitioning for the shared store.
//
// The `content-egg/products` store holds items for every managed module on the
// post — products AND coupons — because the REST collector spans both families.
// Each family's UI shows only its own items, so we partition the flat roster by
// module id. The coupon module ids are localized synchronously by
// ProductManagerLoader (ceggPmConfig.couponModuleIds), so classification needs
// no async fetch and there is no first-paint flash.

function cfg() {
	return ( typeof window !== 'undefined' && window.ceggPmConfig ) || {};
}

// Set of module ids whose parser type is COUPON, from the localized config.
export function couponModuleIdSet() {
	return new Set( cfg().couponModuleIds || [] );
}

// Set of module ids whose parser type is IMAGE / VIDEO, from localized config.
export function imageModuleIdSet() {
	return new Set( cfg().imageModuleIds || [] );
}
export function videoModuleIdSet() {
	return new Set( cfg().videoModuleIds || [] );
}

// Items belonging to the given module-id set. A null/undefined set means "no
// classification available" → return the list unchanged (safe default).
export function filterByModuleIds( items, idSet ) {
	if ( ! idSet ) {
		return items || [];
	}
	return ( items || [] ).filter( ( p ) => idSet.has( p && p.module_id ) );
}

// Items NOT in the given module-id set (the complement) — used to keep coupons
// out of the product roster.
export function excludeModuleIds( items, idSet ) {
	if ( ! idSet ) {
		return items || [];
	}
	return ( items || [] ).filter( ( p ) => ! idSet.has( p && p.module_id ) );
}

// Product-family items from a flat roster (everything that isn't a coupon,
// image, or video).
// Filtering happens in the component — not as a store selector — because the
// `content-egg/products` store is registered first-wins across several block
// bundles, so a newly-added selector isn't guaranteed to exist at runtime.
export function productsOnly( items ) {
	const excluded = new Set( [
		...couponModuleIdSet(),
		...imageModuleIdSet(),
		...videoModuleIdSet(),
	] );
	return excludeModuleIds( items, excluded );
}

// Coupon-family items from a flat roster.
export function couponsOnly( items ) {
	return filterByModuleIds( items, couponModuleIdSet() );
}

// Image / video family items from a flat roster.
export function imagesOnly( items ) {
	return filterByModuleIds( items, imageModuleIdSet() );
}
export function videosOnly( items ) {
	return filterByModuleIds( items, videoModuleIdSet() );
}
// Media items for a given parser family — the clone-friendly single entry the
// Media* components call with their parserType prop.
export function mediaOnly( items, parserType ) {
	return parserType === 'VIDEO' ? videosOnly( items ) : imagesOnly( items );
}

// Split a flat roster into { products, coupons } by the coupon-module set.
export function partitionByFamily( items, couponIds = couponModuleIdSet() ) {
	const products = [];
	const coupons = [];
	( items || [] ).forEach( ( p ) => {
		if ( couponIds.has( p && p.module_id ) ) {
			coupons.push( p );
		} else {
			products.push( p );
		}
	} );
	return { products, coupons };
}
