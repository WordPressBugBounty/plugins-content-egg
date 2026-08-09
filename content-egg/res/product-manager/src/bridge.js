/**
 * Editor products snapshot bridge.
 *
 * The AngularJS metabox owns window.ceggEditorProducts today. When the REST
 * API appends products, this module merges them into the snapshot immediately
 * (so React consumers see them synchronously, before any binding updates) and
 * notifies the Angular app so its in-memory model — serialized on post save —
 * stays consistent.
 */

// NOTE (Phase 1 hardening): this publisher intentionally writes only the
// editor window's snapshot. Parent-frame propagation (see res/app/app.js) and
// the eventual single-republisher ownership move to Phase 2's
// content-egg/products store subscriber. Do not add parent-frame writes here.

function toSnapshotProduct( item, moduleId ) {
	return {
		unique_id: item && item.unique_id ? item.unique_id : '',
		module_id: item && item.module_id ? item.module_id : moduleId,
		title: item && item.title ? item.title : '',
		subtitle: item && item.subtitle ? item.subtitle : '',
		merchant: item && item.merchant ? item.merchant : '',
		badge: item && item.badge ? item.badge : '',
		domain: item && item.domain ? item.domain : '',
		group: item && item.group ? item.group : '',
		img: item && item.img ? item.img : '',
		url: item && item.url ? item.url : '',
		price: item && item.price ? item.price : '',
		priceOld: item && item.priceOld ? item.priceOld : '',
		currencyCode: item && item.currencyCode ? item.currencyCode : '',
		// Carry the server-formatted price (canonical symbol + i18n separators)
		// so binding modals format prices exactly like the Manage products UI.
		_priceFormatted:
			item && item._priceFormatted ? item._priceFormatted : '',
		_priceOldFormatted:
			item && item._priceOldFormatted ? item._priceOldFormatted : '',
		ratingDecimal: item && item.ratingDecimal ? item.ratingDecimal : '',
		order_num: item && item.order_num ? item.order_num : '',
	};
}

// Republish the full editor snapshot from the store's byModule map, so the 25
// EggBlocks and the binding modals (which read window.ceggEditorProducts) stay
// in sync. The store is the single caller.
export function publishSnapshot( byModule, postId ) {
	const normalized = {};
	Object.keys( byModule || {} ).forEach( ( moduleId ) => {
		normalized[ moduleId ] = ( byModule[ moduleId ] || [] ).map( ( item ) =>
			toSnapshotProduct( item, moduleId )
		);
	} );

	const all = Object.keys( normalized ).reduce(
		( acc, id ) => acc.concat( normalized[ id ] ),
		[]
	);

	const snapshot = {
		postId: postId || 0,
		byModule: normalized,
		all,
		updatedAt: Date.now(),
	};

	window.ceggEditorProducts = snapshot;
	window.dispatchEvent(
		new CustomEvent( 'ceggEditorProductsUpdated', { detail: snapshot } )
	);
}

// Notify the (frozen) Angular metabox that REST added items, so its in-memory
// model — serialized on post save — includes them. Full items, not the display
// subset. (Phase 1 sync shim contract.)
export function notifyAngularAdd( moduleId, items ) {
	window.dispatchEvent(
		new CustomEvent( 'ceggProductsChangedExternally', {
			detail: { module_id: moduleId, items: items || [] },
		} )
	);
}
