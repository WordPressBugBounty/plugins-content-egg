// Compose a [content-egg-block …] shortcode string from the builder's focused
// field set. Pure (no WP/DOM) so it unit-tests in isolation and drives both the
// classic-editor insert and the live preview/Copy — the preview is exactly what
// gets inserted. Param names match ShortcodeAtts/ProductBlock: template, groups,
// modules, exclude_modules, next, products.
export function buildBlockShortcode( fields = {} ) {
	const parts = [ 'content-egg-block' ];

	if ( fields.template ) {
		parts.push( `template=${ fields.template }` );
	}

	const list = ( key ) => {
		const values = ( fields[ key ] || [] )
			.map( ( v ) => String( v ).trim() )
			.filter( Boolean );
		if ( values.length ) {
			parts.push( `${ key }="${ values.join( ',' ) }"` );
		}
	};
	list( 'groups' );
	list( 'modules' );
	list( 'exclude_modules' );

	// `products` binds specific items via composite "module:uid" refs (the same
	// generic filter coupons reuse). Accept a string or an array.
	const products = Array.isArray( fields.products )
		? fields.products
				.map( ( v ) => String( v ).trim() )
				.filter( Boolean )
				.join( ',' )
		: String( fields.products || '' ).trim();
	if ( products ) {
		parts.push( `products="${ products }"` );
	}

	const next = parseInt( fields.next, 10 );
	if ( Number.isInteger( next ) && next > 0 ) {
		parts.push( `next=${ next }` );
	}

	return `[${ parts.join( ' ' ) }]`;
}
