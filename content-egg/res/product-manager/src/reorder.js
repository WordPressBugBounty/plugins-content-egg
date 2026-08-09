// Given the current flat product list and a move (fromIndex → toIndex),
// return the new global order as [{ module_id, unique_id }] for store.reorder.
export function buildOrderAfterMove( products, fromIndex, toIndex ) {
	const next = products.slice();
	const [ moved ] = next.splice( fromIndex, 1 );
	next.splice( toIndex, 0, moved );
	return next.map( ( p ) => ( {
		module_id: p.module_id,
		unique_id: p.unique_id,
	} ) );
}

// Rebuild the per-module map for an optimistic local update, stamping each
// item's order_num by its GLOBAL position in `order` (a flat
// [{ module_id, unique_id }] list) — mirroring the server's buildOrderPlan().
// getProducts() sorts by order_num, so stamping it here makes the row snap to
// its new global position instantly, before the REST write settles. Items
// missing from `order` (drift) are appended, continuing the counter.
export function applyOrderToModules( byModule, order ) {
	const result = {};
	const placed = {};
	let n = 1;

	order.forEach( ( entry ) => {
		const moduleId = entry.module_id;
		const uniqueId = String( entry.unique_id );
		const items = byModule[ moduleId ] || [];
		const item = items.find( ( p ) => String( p.unique_id ) === uniqueId );
		if ( ! item ) {
			return;
		}
		if ( ! result[ moduleId ] ) {
			result[ moduleId ] = [];
			placed[ moduleId ] = new Set();
		}
		result[ moduleId ].push( { ...item, order_num: n } );
		placed[ moduleId ].add( uniqueId );
		n++;
	} );

	Object.keys( byModule ).forEach( ( moduleId ) => {
		( byModule[ moduleId ] || [] ).forEach( ( item ) => {
			if (
				placed[ moduleId ] &&
				placed[ moduleId ].has( String( item.unique_id ) )
			) {
				return;
			}
			if ( ! result[ moduleId ] ) {
				result[ moduleId ] = [];
			}
			result[ moduleId ].push( { ...item, order_num: n } );
			n++;
		} );
	} );

	return result;
}
