import { useEffect, useRef } from '@wordpress/element';

// The classic-editor JS API (wp.editor.initialize / .remove). WP aliases it to
// wp.oldEditor to avoid colliding with @wordpress/editor in the block editor, so
// prefer that and fall back. Returns null when the editor assets aren't loaded.
export function getClassicEditor() {
	const candidates = [ window.wp?.oldEditor, window.wp?.editor ];
	return (
		candidates.find( ( e ) => e && typeof e.initialize === 'function' ) ||
		null
	);
}

export function hasClassicEditor() {
	return !! getClassicEditor();
}

// A WYSIWYG description editor backed by WordPress's bundled TinyMCE. Mounts once
// on its own textarea and syncs the HTML back through onChange on every edit; the
// value prop is read only for the initial content (TinyMCE owns the DOM after).
export default function RichTextEditor( { value, onChange } ) {
	const idRef = useRef(
		'cegg-pm-rte-' + Math.random().toString( 36 ).slice( 2 )
	);
	const initialRef = useRef( value );
	const onChangeRef = useRef( onChange );
	onChangeRef.current = onChange;

	useEffect( () => {
		const id = idRef.current;
		const editorApi = getClassicEditor();
		if ( ! editorApi ) {
			return undefined;
		}

		editorApi.initialize( id, {
			tinymce: {
				toolbar1:
					'formatselect | bold italic | bullist numlist blockquote | link unlink | removeformat | undo redo',
				plugins: 'lists,link,paste',
				menubar: false,
				branding: false,
				statusbar: false,
				setup( editor ) {
					const sync = () =>
						onChangeRef.current( editor.getContent() );
					editor.on(
						'change keyup input SetContent Undo Redo',
						sync
					);
				},
			},
			quicktags: false,
			mediaButtons: false,
		} );

		return () => {
			try {
				editorApi.remove( id );
			} catch ( e ) {
				// editor already torn down
			}
		};
		// Mount once — TinyMCE owns the content after init.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	return (
		<textarea
			id={ idRef.current }
			className="cegg-pm-rte"
			defaultValue={ initialRef.current }
		/>
	);
}
