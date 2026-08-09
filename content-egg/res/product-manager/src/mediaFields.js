// Minimal media field helpers. Images need nothing beyond title + img; videos
// carry a source we can badge. Source is derived from the item's `extra`:
//   extra.guid      → an embedded provider (YouTube)
//   extra.video_url → a direct file (Pexels etc.)
export function mediaSource( item ) {
	const extra = ( item && item.extra ) || {};
	if ( extra.guid ) {
		return { kind: 'embed', label: 'YouTube' };
	}
	if ( extra.video_url ) {
		return { kind: 'file', label: 'Video' };
	}
	return null;
}
