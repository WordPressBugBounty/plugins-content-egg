// Group selected products by their module_id (the enqueue endpoint is
// single-module, so one request is fired per group). Pure — unit-tested.
export function groupByModule( products ) {
	const out = {};
	( products || [] ).forEach( ( p ) => {
		const moduleId = String( ( p && p.module_id ) || '' );
		if ( ! moduleId ) {
			return;
		}
		( out[ moduleId ] = out[ moduleId ] || [] ).push( p );
	} );
	return out;
}

// POST one module's products to the legacy admin-ajax enqueue endpoint. Each
// product becomes a bridge-page import job under the given preset. Resolves to
// the server's data envelope ({created_count, job_ids, skipped, ...}); throws
// on transport or application error.
export async function enqueueBridgeImport( {
	presetId,
	moduleId,
	products,
	sourcePostId,
} ) {
	const cfg = ( typeof window !== 'undefined' && window.ceggPmConfig ) || {};
	const url = cfg.ajaxUrl || window.ajaxurl;

	const body = new window.FormData();
	body.append( 'action', 'cegg_import_enqueue' );
	body.append( 'nonce', cfg.importNonce || '' );
	body.append( 'preset_id', String( presetId ) );
	body.append( 'module_id', String( moduleId ) );
	body.append( 'source_post_id', String( sourcePostId || 0 ) );
	body.append( 'payload', JSON.stringify( products || [] ) );

	const res = await window.fetch( url, {
		method: 'POST',
		credentials: 'same-origin',
		body,
	} );
	const json = await res.json();
	if ( ! json || ! json.success ) {
		throw new Error(
			( json && json.data && json.data.message ) || 'Import failed'
		);
	}
	return json.data;
}
