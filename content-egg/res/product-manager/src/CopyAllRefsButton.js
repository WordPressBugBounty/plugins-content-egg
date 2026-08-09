import { Button } from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

// Bootstrap clipboard → check (inlined so they don't depend on the bi font).
const clipboardIcon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		width="16"
		height="16"
		fill="currentColor"
		viewBox="0 0 16 16"
		aria-hidden="true"
	>
		<path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1z" />
		<path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0z" />
	</svg>
);

const checkIcon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		width="16"
		height="16"
		fill="currentColor"
		viewBox="0 0 16 16"
		aria-hidden="true"
	>
		<path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z" />
	</svg>
);

// Copy every post product as a JSON array of references — { title, orig_url,
// product_ref: { module_id, unique_id } } — matching the legacy metabox's
// "Copy all product references" action, for pasting into another post's picker.
export default function CopyAllRefsButton( { products } ) {
	const [ copied, setCopied ] = useState( false );

	const text = JSON.stringify(
		( products || [] ).map( ( p ) => ( {
			title: p?.title || '',
			orig_url: p?.orig_url || '',
			product_ref: {
				module_id: p?.module_id || '',
				unique_id: p?.unique_id || '',
			},
		} ) ),
		null,
		2
	);

	const copyRef = useCopyToClipboard( text, () => {
		setCopied( true );
		window.setTimeout( () => setCopied( false ), 1000 );
	} );

	return (
		<Button
			ref={ copyRef }
			className="cegg-pm-copy-all"
			icon={ copied ? checkIcon : clipboardIcon }
			iconSize={ 16 }
			label={ __( 'Copy all product references', 'content-egg' ) }
			showTooltip
			size="compact"
			variant="secondary"
		/>
	);
}
