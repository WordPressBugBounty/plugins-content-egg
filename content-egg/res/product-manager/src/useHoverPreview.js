import { useRef, useState } from '@wordpress/element';

// Positions an enlarged image preview beside the hovered thumbnail. The preview
// itself is portaled to <body> (see HoverPreview) so it escapes panel/modal
// overflow and any transformed ancestor; coordinates are therefore viewport-fixed.
//
// side='left'  → preview sits to the LEFT of the thumb. The sidebar hugs the
//                screen's right edge, so that's the only side with room.
// side='right' → preview sits to the RIGHT of the thumb. Modal results live in a
//                left-aligned column with open space to their right.
export default function useHoverPreview( {
	enabled = true,
	side = 'right',
	gap = 8,
} = {} ) {
	const thumbRef = useRef( null );
	const [ position, setPosition ] = useState( null );

	const show = () => {
		if ( ! enabled || ! thumbRef.current ) {
			return;
		}
		const rect = thumbRef.current.getBoundingClientRect();
		const pos = { top: rect.top + rect.height / 2 };
		if ( side === 'left' ) {
			pos.right = window.innerWidth - rect.left + gap;
		} else {
			pos.left = rect.right + gap;
		}
		setPosition( pos );
	};

	const hide = () => setPosition( null );

	return { thumbRef, position, show, hide };
}
