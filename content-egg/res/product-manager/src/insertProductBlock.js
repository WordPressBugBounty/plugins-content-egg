import { createBlock } from '@wordpress/blocks';
import { dispatch, select } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { buildBlockShortcode } from './buildBlockShortcode';

// Insert one or more blocks at the editor's current insertion point (after the
// selected block, matching Gutenberg's own inserter) rather than appending to
// the end. Falls back to a plain insert (append) when no insertion point is
// resolvable — e.g. nothing selected or an older block-editor store.
function insertBlocksAtCursor( blocks ) {
	const be = select( 'core/block-editor' );
	const point =
		be && typeof be.getBlockInsertionPoint === 'function'
			? be.getBlockInsertionPoint()
			: null;
	if ( point ) {
		dispatch( 'core/block-editor' ).insertBlocks(
			blocks,
			point.index,
			point.rootClientId
		);
	} else {
		dispatch( 'core/block-editor' ).insertBlocks( blocks );
	}
}

// Confirm an insert with a snackbar toast. Inserting from the full-screen
// manager modal happens behind it, so a toast is the only visible feedback that
// it worked. Rendered by the workspace's SnackbarList (and the editor's own).
function notifyInserted( message ) {
	try {
		dispatch( 'core/notices' ).createSuccessNotice( message, {
			type: 'snackbar',
		} );
	} catch ( e ) {
		// notices store unavailable — insert still succeeded
	}
}

// Display template per count + the composite product ref both forms bind to
// (module_id:unique_id — the matcher ModuleViewer already understands). One
// product → a single-item card; several → an offers list.
const SINGLE_TEMPLATE = 'item_simple';
const MULTI_TEMPLATE = 'offers_list';

function productRef( product ) {
	return `${ String( product?.module_id || '' ) }:${ String(
		product?.unique_id || ''
	) }`;
}

// Build the content-egg/products block bound to one or more products via the
// block's Choose-products mode (not the products filter), so it opens bound +
// editable in the sidebar picker. Serializes to the same products="…" shortcode
// the classic branch emits. Template defaults by count; pass one to override.
export function createProductsBlock( products, template ) {
	const list = Array.isArray( products ) ? products : [ products ];
	const refs = list
		.map( productRef )
		.filter( ( r ) => r && r !== ':' )
		.join( ',' );
	const tpl =
		template || ( list.length > 1 ? MULTI_TEMPLATE : SINGLE_TEMPLATE );
	return createBlock( 'content-egg/products', {
		template: tpl,
		selection_mode: 'products',
		chosen_products: refs,
	} );
}

// Single-product block for the Insert action (always item_simple).
export function createProductBlock( product ) {
	return createProductsBlock( [ product ], SINGLE_TEMPLATE );
}

// Label for the per-row Insert action — it inserts a block on the block editor
// and a shortcode on classic, so the wording follows the context.
export function insertActionLabel() {
	const cfg = ( typeof window !== 'undefined' && window.ceggPmConfig ) || {};
	return cfg.isBlockEditor
		? __( 'Insert block', 'content-egg' )
		: __( 'Insert shortcode', 'content-egg' );
}

// The [content-egg-block …] shortcode equivalent of createProductsBlock — same
// template-by-count and products="module:uid,…" binding, for classic editors.
export function buildProductsShortcode( products ) {
	const list = Array.isArray( products ) ? products : [ products ];
	const refs = list
		.map( productRef )
		.filter( ( r ) => r && r !== ':' )
		.join( ',' );
	const tpl = list.length > 1 ? MULTI_TEMPLATE : SINGLE_TEMPLATE;
	return `[content-egg-block template="${ tpl }" products="${ refs }"]`;
}

// Attach the product block to a drag as Gutenberg's inserter payload, so dropping
// the roster row(s) on the block canvas inserts it (same format the block
// inserter's own drag uses). Accepts one product or an array. Guarded — a drag
// must never throw. Block-editor only.
export function setProductBlockDragData( event, products ) {
	try {
		event.dataTransfer.setData(
			'wp-blocks',
			JSON.stringify( {
				type: 'inserter',
				blocks: [ createProductsBlock( products ) ],
			} )
		);
		event.dataTransfer.effectAllowed = 'copy';
	} catch ( e ) {
		// leave the native drag (reorder) intact
	}
}

// Attach the shortcode to a drag as plain text, so dropping the roster row(s)
// into a classic editor (TinyMCE body or the Text-mode textarea) inserts it.
// Accepts one product or an array. Guarded — a drag must never throw.
export function setProductShortcodeDragData( event, products ) {
	try {
		event.dataTransfer.setData(
			'text/plain',
			buildProductsShortcode( products )
		);
		event.dataTransfer.effectAllowed = 'copy';
	} catch ( e ) {
		// leave the native drag (reorder) intact
	}
}

// Insert a content-egg/products display bound to one product, at the cursor.
// - Block editor: a native content-egg/products block (template item_simple).
// - Classic / Woo / CPT: the equivalent [content-egg-block …] shortcode into the
//   active editor (TinyMCE visual, or the Text-mode textarea), clipboard fallback.
// Block-vs-classic comes from the authoritative server flag; select()ing
// core/block-editor is unreliable (that store registers on classic screens too).
export function insertProductBlock( product ) {
	const cfg = ( typeof window !== 'undefined' && window.ceggPmConfig ) || {};

	if ( cfg.isBlockEditor ) {
		insertBlocksAtCursor( createProductBlock( product ) );
		notifyInserted( __( 'Block inserted.', 'content-egg' ) );
		return;
	}

	const shortcode = buildProductsShortcode( [ product ] );
	if ( insertIntoClassicEditor( shortcode ) ) {
		notifyInserted( __( 'Shortcode inserted.', 'content-egg' ) );
	} else if ( window.navigator?.clipboard ) {
		window.navigator.clipboard.writeText( shortcode );
		notifyInserted( __( 'Shortcode copied to clipboard.', 'content-egg' ) );
	}
}

// The composite "module:uid" refs for one coupon or an array — the generic
// binding the [content-egg-block products=…] filter understands
// (ProductBindingFilter).
function couponRefs( coupons ) {
	const list = Array.isArray( coupons ) ? coupons : [ coupons ];
	return list
		.map(
			( c ) =>
				`${ String( c?.module_id || '' ) }:${ String(
					c?.unique_id || ''
				) }`
		)
		.filter( ( r ) => r && r !== ':' );
}

// A content-egg/coupons block bound (Choose mode) to one or more coupons — the
// coupon analogue of createProductsBlock. Serializes to the same
// [content-egg-block template=coupons_ticket products="…"] shortcode either way.
function createCouponsBlock( coupons ) {
	return createBlock( 'content-egg/coupons', {
		template: 'coupons_ticket',
		selection_mode: 'coupons',
		chosen_coupons: couponRefs( coupons ).join( ',' ),
	} );
}

// The [content-egg-block …] shortcode that renders exactly these coupon(s) via
// the coupon block template. Coupons are off module shortcodes — this is the
// modern equivalent, bound to the given coupons.
function buildCouponsShortcode( coupons ) {
	return buildBlockShortcode( {
		template: 'coupons_ticket',
		products: couponRefs( coupons ),
	} );
}

// Insert a coupon at the cursor, mirroring products:
// - Block editor: a content-egg/coupons block bound to this coupon
//   (Choose mode, template=coupons_ticket).
// - Classic / Woo / CPT: the equivalent [content-egg-block …] shortcode into the
//   active editor, clipboard fallback.
export function insertCouponShortcode( coupon ) {
	const cfg = ( typeof window !== 'undefined' && window.ceggPmConfig ) || {};

	if ( cfg.isBlockEditor ) {
		insertBlocksAtCursor( createCouponsBlock( [ coupon ] ) );
		notifyInserted( __( 'Block inserted.', 'content-egg' ) );
		return;
	}

	const shortcode = buildCouponsShortcode( [ coupon ] );
	if ( insertIntoClassicEditor( shortcode ) ) {
		notifyInserted( __( 'Shortcode inserted.', 'content-egg' ) );
	} else if ( window.navigator?.clipboard ) {
		window.navigator.clipboard.writeText( shortcode );
		notifyInserted( __( 'Shortcode copied to clipboard.', 'content-egg' ) );
	}
}

// Attach a content-egg/coupons block to a drag as Gutenberg's inserter payload,
// so dropping the roster row(s) on the block canvas inserts it (same format the
// block inserter's own drag uses). Accepts one coupon or an array. Guarded — a
// drag must never throw. Block-editor only.
export function setCouponBlockDragData( event, coupons ) {
	try {
		event.dataTransfer.setData(
			'wp-blocks',
			JSON.stringify( {
				type: 'inserter',
				blocks: [ createCouponsBlock( coupons ) ],
			} )
		);
		event.dataTransfer.effectAllowed = 'copy';
	} catch ( e ) {
		// leave the native drag (reorder) intact
	}
}

// Attach the coupon block shortcode to a drag as plain text (classic) — dropping
// the row(s) into a classic editor inserts it. Accepts one coupon or an array.
// Guarded; a drag must never throw.
export function setCouponShortcodeDragData( event, coupons ) {
	try {
		event.dataTransfer.setData(
			'text/plain',
			buildCouponsShortcode( coupons )
		);
		event.dataTransfer.effectAllowed = 'copy';
	} catch ( e ) {
		// leave the native drag (reorder) intact
	}
}

// --- Media (images / videos) ---
// Media blocks have no per-item Choose picker, but they DO accept a generic
// item-ID filter: the same composite "module:uid,…" `products=` binding coupons
// use (ProductBindingFilter is type-agnostic). So a dragged/inserted media item —
// or a whole multi-selection — produces a content-egg/images|videos block bound
// to exactly those items. Accepts one item or an array.
function mediaBlockName( parserType ) {
	return parserType === 'VIDEO' ? 'content-egg/videos' : 'content-egg/images';
}
function mediaTemplate( parserType ) {
	return parserType === 'VIDEO' ? 'videos_stacked' : 'images';
}
function mediaRefs( items ) {
	const list = Array.isArray( items ) ? items : [ items ];
	return list
		.map(
			( it ) =>
				`${ String( it?.module_id || '' ) }:${ String(
					it?.unique_id || ''
				) }`
		)
		.filter( ( r ) => r && r !== ':' );
}
function createMediaBlock( parserType, items ) {
	return createBlock( mediaBlockName( parserType ), {
		template: mediaTemplate( parserType ),
		products: mediaRefs( items ).join( ',' ),
	} );
}
function buildMediaShortcode( parserType, items ) {
	return buildBlockShortcode( {
		template: mediaTemplate( parserType ),
		products: mediaRefs( items ),
	} );
}

// Insert a media block bound to one or more items at the cursor:
// - Block editor: a content-egg/images|videos block with products="module:uid,…".
// - Classic / Woo / CPT: the equivalent [content-egg-block …] shortcode, clipboard
//   fallback.
export function insertMediaShortcode( parserType, items ) {
	const cfg = ( typeof window !== 'undefined' && window.ceggPmConfig ) || {};

	if ( cfg.isBlockEditor ) {
		insertBlocksAtCursor( createMediaBlock( parserType, items ) );
		notifyInserted( __( 'Block inserted.', 'content-egg' ) );
		return;
	}

	const shortcode = buildMediaShortcode( parserType, items );
	if ( insertIntoClassicEditor( shortcode ) ) {
		notifyInserted( __( 'Shortcode inserted.', 'content-egg' ) );
	} else if ( window.navigator?.clipboard ) {
		window.navigator.clipboard.writeText( shortcode );
		notifyInserted( __( 'Shortcode copied to clipboard.', 'content-egg' ) );
	}
}

// Attach a media block to a drag as Gutenberg's inserter payload (block editor).
// Accepts one item or an array. Guarded — a drag must never throw.
export function setMediaBlockDragData( event, parserType, items ) {
	try {
		event.dataTransfer.setData(
			'wp-blocks',
			JSON.stringify( {
				type: 'inserter',
				blocks: [ createMediaBlock( parserType, items ) ],
			} )
		);
		event.dataTransfer.effectAllowed = 'copy';
	} catch ( e ) {
		// leave the native drag (reorder) intact
	}
}

// Attach the media block shortcode to a drag as plain text (classic). Accepts
// one item or an array. Guarded.
export function setMediaShortcodeDragData( event, parserType, items ) {
	try {
		event.dataTransfer.setData(
			'text/plain',
			buildMediaShortcode( parserType, items )
		);
		event.dataTransfer.effectAllowed = 'copy';
	} catch ( e ) {
		// leave the native drag (reorder) intact
	}
}

// Insert text into the active classic editor at the cursor. TinyMCE visual
// editor first, then the Text-mode <textarea id="content">. Returns true if it
// inserted, false if no classic editor is available.
function insertIntoClassicEditor( text ) {
	const tinymce = window.tinymce;
	if (
		tinymce &&
		tinymce.activeEditor &&
		! tinymce.activeEditor.isHidden()
	) {
		tinymce.activeEditor.execCommand( 'mceInsertContent', false, text );
		return true;
	}

	const el = window.document.getElementById( 'content' );
	if ( el && typeof el.selectionStart === 'number' ) {
		const start = el.selectionStart;
		const end = el.selectionEnd;
		el.value = el.value.slice( 0, start ) + text + el.value.slice( end );
		const caret = start + text.length;
		el.selectionStart = caret;
		el.selectionEnd = caret;
		el.focus();
		return true;
	}

	return false;
}
