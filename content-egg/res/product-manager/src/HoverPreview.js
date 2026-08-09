import { createPortal } from '@wordpress/element';
import { badgeStyle } from './badge';

// Enlarged image preview, portaled to <body> so it escapes panel/modal overflow
// and any transformed ancestor. `position` comes from useHoverPreview (null when
// not hovering). The badge, if any, overlays the preview's top-left corner.
export default function HoverPreview( { position, img, badge, badgeColor } ) {
	if ( ! position || ! img ) {
		return null;
	}
	return createPortal(
		// Include cegg-pm so the design tokens resolve — this is outside the
		// panel's .cegg-pm scope.
		<div className="cegg-pm cegg-pm-preview" style={ position }>
			<img src={ img } alt="" />
			{ badge && (
				<span
					className="cegg-pm-preview__badge"
					style={ badgeStyle( badgeColor ) }
				>
					{ badge }
				</span>
			) }
		</div>,
		document.body
	);
}
