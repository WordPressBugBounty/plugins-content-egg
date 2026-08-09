// Stable identity for a product within a module: "moduleId:uniqueId". Used to
// match search results against what's already attached, and to track
// added/busy state. Keep every producer of these keys on this one function so
// the formats can't drift apart.
export const getKey = ( moduleId, uniqueId ) =>
	`${ String( moduleId || '' ) }:${ String( uniqueId || '' ) }`;
