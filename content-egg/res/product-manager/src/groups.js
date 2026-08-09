import { __ } from '@wordpress/i18n';

// Sentinel value for the "create a new group" option in a group <select>.
export const NEW_GROUP = '__new__';

// Distinct, non-empty group names across the current products, in first-seen
// order. Derived live from the product list (not a stored snapshot) so the
// options never go stale: a product's assigned group is always a valid option,
// and a newly-created group shows up immediately. Stale options were why a saved
// group could render as "—" (a <select> value with no matching <option> falls
// back to the first option).
export function groupsFromProducts( products ) {
	const seen = new Set();
	const out = [];
	( products || [] ).forEach( ( product ) => {
		const group = String( product?.group || '' ).trim();
		if ( group && ! seen.has( group ) ) {
			seen.add( group );
			out.push( group );
		}
	} );
	return out;
}

// Options for a group <select>: a "none" entry, the existing groups, then a
// "+ New group…" entry (resolve the chosen value with resolveGroupValue).
export function groupSelectOptions( groups, { noneLabel = '—' } = {} ) {
	return [
		{ label: noneLabel, value: '' },
		...( groups || [] ).map( ( group ) => ( {
			label: group,
			value: group,
		} ) ),
		{ label: __( '＋ New group…', 'content-egg' ), value: NEW_GROUP },
	];
}

// A group name can later be used as a shortcode parameter — notably the
// comma-separated `groups="…"` attribute — so strip characters that break
// shortcode parsing (brackets, quotes, angle brackets, '&', '=') and the comma
// that separates the group list, then collapse whitespace. Spaces are kept
// (quote the value in the shortcode). Mirrors ProductDataService::sanitizeGroup().
export function sanitizeGroupName( raw ) {
	return String( raw || '' )
		.replace( /[[\]<>"',&=]/g, '' )
		.replace( /\s+/g, ' ' )
		.trim();
}

// Resolve a value picked from a group <select>. For the "+ New group…" sentinel,
// prompt for a name and sanitize it; returns the clean name, or null to cancel
// (empty/dismissed prompt, or nothing left after sanitizing) so the caller leaves
// the current group untouched. Any other value (an existing group, or '' to
// clear) is returned as-is.
export function resolveGroupValue( value ) {
	if ( value !== NEW_GROUP ) {
		return value;
	}
	// eslint-disable-next-line no-alert
	const raw = window.prompt( __( 'New group name', 'content-egg' ) );
	return sanitizeGroupName( raw ) || null;
}
