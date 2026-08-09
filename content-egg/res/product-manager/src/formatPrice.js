import { decodeEntities } from '@wordpress/html-entities';

// Shared price + currency formatter for the product manager UI.
//
// Prefers the server-formatted value (`_priceFormatted` / `_priceOldFormatted`),
// which the REST layer computes with the canonical PHP
// TemplateHelper::formatPriceCurrency() — the same formatter search results and
// front-end templates use — so the editor matches the site exactly (symbol
// placement + i18n separators). That value carries HTML entities for currency
// symbols (e.g. "&euro;"), which are meant for PHP templates that echo into
// HTML; decode them here since React renders the string as plain text. Falls
// back to a plain symbol/code format when the server value is absent (e.g. an
// item straight from an add response before the next GET). Returns '' for
// empty/zero prices so callers can hide the field.
function format( formattedValue, rawValue, product ) {
	const formatted = String( formattedValue ?? '' ).trim();
	if ( formatted ) {
		return decodeEntities( formatted );
	}

	const price = String( rawValue ?? '' ).trim();
	if ( ! price || price === '0' ) {
		return '';
	}

	// Decode the symbol too: `currency` can carry an HTML entity (e.g. "&euro;"),
	// which React would otherwise escape to "&amp;euro;" as plain text.
	const symbol = decodeEntities( String( product?.currency ?? '' ).trim() );
	if ( symbol ) {
		return `${ symbol }${ price }`;
	}

	const code = String( product?.currencyCode ?? '' ).trim();
	return code ? `${ price } ${ code }` : price;
}

export function formatPrice( product ) {
	return format( product?._priceFormatted, product?.price, product );
}

export function formatOldPrice( product ) {
	return format( product?._priceOldFormatted, product?.priceOld, product );
}
