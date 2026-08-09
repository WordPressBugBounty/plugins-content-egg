import { Button, Modal } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import RichTextEditor, { hasClassicEditor } from './RichTextEditor';

// The inline textarea grows with its content up to this height, then scrolls —
// so a short description stays compact in the narrow drawer and a long one gets
// room without a fixed oversized box.
const MAX_HEIGHT = 260;

// Strip anything executable from a fragment before it's shown in a preview. The
// server runs wp_kses_post on save; this mirrors that safety for the live
// (unsaved) draft so a preview can never run scripts or inline handlers.
function safeHtml( html ) {
	const doc = document.implementation.createHTMLDocument( '' );
	doc.body.innerHTML = String( html || '' );
	doc.body
		.querySelectorAll(
			'script, style, iframe, object, embed, link, meta, form'
		)
		.forEach( ( el ) => el.remove() );
	doc.body.querySelectorAll( '*' ).forEach( ( el ) => {
		Array.from( el.attributes ).forEach( ( attr ) => {
			const name = attr.name.toLowerCase();
			const value = attr.value.toLowerCase().replace( /\s+/g, '' );
			if (
				name.startsWith( 'on' ) ||
				( ( name === 'href' || name === 'src' ) &&
					value.startsWith( 'javascript:' ) )
			) {
				el.removeAttribute( attr.name );
			}
		} );
	} );
	return doc.body.innerHTML;
}

// Match how the description is stored: plain text gets its newlines turned into
// <br> (as ProductDataService::prepareItems does), HTML is left as authored.
function previewHtml( raw ) {
	const s = String( raw || '' );
	const hasTags = /<[a-z][\s\S]*>/i.test( s );
	const html = safeHtml( hasTags ? s : s.replace( /\n/g, '<br>' ) );
	return (
		html ||
		`<span class="cegg-pm-desc__empty">${ __(
			'Nothing to preview',
			'content-egg'
		) }</span>`
	);
}

function stopEnter( e ) {
	// Gutenberg intercepts Enter globally; keep it local to the textarea.
	if ( e.key === 'Enter' ) {
		e.stopPropagation();
	}
}

export default function DescriptionField( {
	label,
	value,
	onChange,
	actions,
} ) {
	const [ mode, setMode ] = useState( 'write' );
	const [ expanded, setExpanded ] = useState( false );
	const ref = useRef( null );

	const resize = () => {
		const el = ref.current;
		if ( ! el ) {
			return;
		}
		el.style.height = 'auto';
		el.style.height = Math.min( el.scrollHeight, MAX_HEIGHT ) + 'px';
	};

	// Re-fit when the value changes or the field returns to write mode.
	useEffect( () => {
		if ( mode === 'write' ) {
			resize();
		}
	}, [ value, mode ] );

	return (
		<div className="cegg-pm-desc">
			<div className="cegg-pm-desc__head">
				<span className="cegg-pm-desc__label">{ label }</span>
				<div className="cegg-pm-desc__tools">
					<div className="cegg-pm-desc__modes">
						<Button
							size="small"
							isPressed={ mode === 'write' }
							onClick={ () => setMode( 'write' ) }
						>
							{ __( 'Write', 'content-egg' ) }
						</Button>
						<Button
							size="small"
							isPressed={ mode === 'preview' }
							onClick={ () => setMode( 'preview' ) }
						>
							{ __( 'Preview', 'content-egg' ) }
						</Button>
					</div>
					{ actions }
					<Button
						size="small"
						icon="editor-expand"
						label={ __( 'Expand editor', 'content-egg' ) }
						showTooltip
						onClick={ () => setExpanded( true ) }
					/>
				</div>
			</div>

			{ mode === 'write' ? (
				<textarea
					ref={ ref }
					className="cegg-pm-desc__input"
					value={ value }
					rows={ 3 }
					onChange={ ( e ) => onChange( e.target.value ) }
					onInput={ resize }
					onKeyDown={ stopEnter }
				/>
			) : (
				<div
					className="cegg-pm-desc__preview"
					// eslint-disable-next-line react/no-danger
					dangerouslySetInnerHTML={ { __html: previewHtml( value ) } }
				/>
			) }

			{ expanded && (
				<Modal
					title={ __( 'Edit description', 'content-egg' ) }
					// The class lets the drawer's outside-click handler know a
					// click landed inside this editor and must NOT close the drawer.
					overlayClassName="cegg-pm-desc-modal"
					className="cegg-pm cegg-pm-desc-modal__frame"
					onRequestClose={ () => setExpanded( false ) }
					// TinyMCE's link dialog / dropdowns render in <body> outside
					// this modal — a click or Esc in them would otherwise be read
					// as dismissing us. Close only via the ✕ or Done.
					shouldCloseOnClickOutside={ false }
					shouldCloseOnEsc={ false }
				>
					{ hasClassicEditor() ? (
						// Visual editor (TinyMCE) — no HTML knowledge needed.
						<div className="cegg-pm-desc-modal__rte">
							<RichTextEditor
								value={ value }
								onChange={ onChange }
							/>
						</div>
					) : (
						// Fallback when the classic editor isn't available: a
						// roomy HTML pane with a live preview beside it.
						<div className="cegg-pm-desc-modal__grid">
							<div className="cegg-pm-desc-modal__pane">
								<span className="cegg-pm-desc-modal__pane-label">
									{ __( 'HTML', 'content-egg' ) }
								</span>
								<textarea
									className="cegg-pm-desc-modal__input"
									value={ value }
									onChange={ ( e ) =>
										onChange( e.target.value )
									}
									onKeyDown={ stopEnter }
								/>
							</div>
							<div className="cegg-pm-desc-modal__pane">
								<span className="cegg-pm-desc-modal__pane-label">
									{ __( 'Preview', 'content-egg' ) }
								</span>
								<div
									className="cegg-pm-desc-modal__preview"
									// eslint-disable-next-line react/no-danger
									dangerouslySetInnerHTML={ {
										__html: previewHtml( value ),
									} }
								/>
							</div>
						</div>
					) }
					<div className="cegg-pm-desc-modal__foot">
						<Button
							variant="primary"
							onClick={ () => setExpanded( false ) }
						>
							{ __( 'Done', 'content-egg' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
