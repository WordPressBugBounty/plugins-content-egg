// CE stores badge_color as a Bootstrap contextual color name (default primary).
// This admin UI has no Bootstrap, so map the names to real colors + readable
// text. A raw "#rrggbb" value is used as-is (with white text).
export const BADGE_COLORS = {
	primary: { bg: '#0d6efd', fg: '#fff' },
	secondary: { bg: '#6c757d', fg: '#fff' },
	success: { bg: '#198754', fg: '#fff' },
	danger: { bg: '#dc3545', fg: '#fff' },
	warning: { bg: '#ffc107', fg: '#000' },
	info: { bg: '#0dcaf0', fg: '#000' },
	light: { bg: '#f8f9fa', fg: '#212529' },
	dark: { bg: '#212529', fg: '#fff' },
};

// The badge-color names the editor may pick, in legacy-metabox order. The
// renderer still accepts a raw "#rrggbb" for data that carries one, but the
// picker is limited to these named Bootstrap colors (as the legacy metabox is).
export const BADGE_COLOR_NAMES = Object.keys( BADGE_COLORS );

export function badgeStyle( color ) {
	const c = String( color || '' ).trim();
	if ( c.startsWith( '#' ) ) {
		return { background: c, color: '#fff' };
	}
	const map = BADGE_COLORS[ c ] || BADGE_COLORS.primary;
	return { background: map.bg, color: map.fg };
}

// Resolve a badge color (Bootstrap name or raw hex) to a hex value.
export function resolveBadgeHex( color ) {
	const c = String( color || '' ).trim();
	if ( c.startsWith( '#' ) ) {
		return c;
	}
	return ( BADGE_COLORS[ c ] || BADGE_COLORS.primary ).bg;
}

// A softer, "tinted" badge for the dense manage table: a light wash of the badge
// color as background with a darkened version of the color as text, instead of a
// solid fill. Reads as a calm status label next to the product title rather than
// a loud block. (The hover preview + front end keep the solid fill via
// badgeStyle, so this doesn't change how a badge looks where it's published.)
export function badgeTintStyle( color ) {
	const hex = resolveBadgeHex( color );
	return {
		background: `color-mix(in srgb, ${ hex } 14%, transparent)`,
		color: `color-mix(in srgb, ${ hex } 60%, #1e1e1e)`,
	};
}
