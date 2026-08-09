// Compose a [content-egg module=… template=…] shortcode string — the ONLY way
// coupon (and other non-product) modules render, since they have no block. Pure
// (no WP/DOM) so it unit-tests in isolation and drives both the classic-editor
// insert and the live preview/Copy. Attribute names match EggShortcode's
// allowed atts: module, template, groups (there is no `next`/limit att).
export function buildModuleShortcode( fields = {} ) {
	const parts = [ 'content-egg' ];

	const module = String( fields.module || '' ).trim();
	if ( module ) {
		parts.push( `module=${ module }` );
	}

	const template = String( fields.template || '' ).trim();
	if ( template ) {
		parts.push( `template=${ template }` );
	}

	const groups = ( fields.groups || [] )
		.map( ( v ) => String( v ).trim() )
		.filter( Boolean );
	if ( groups.length ) {
		parts.push( `groups="${ groups.join( ',' ) }"` );
	}

	return `[${ parts.join( ' ' ) }]`;
}
