import {
	Button,
	FormTokenField,
	TextControl,
	// InputControl + its suffix wrapper are still exported under the
	// __experimental prefix in WP 6.8; both are present in the target install.
	/* eslint-disable @wordpress/no-unsafe-wp-apis */
	__experimentalInputControl as InputControl,
	__experimentalInputControlSuffixWrapper as InputControlSuffixWrapper,
	/* eslint-enable @wordpress/no-unsafe-wp-apis */
} from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from './store';
import { productsOnly } from './familyFilter';
import useModules from './useModules';
import { groupsFromProducts } from './groups';
import { buildBlockShortcode } from './buildBlockShortcode';

function cfg() {
	return ( typeof window !== 'undefined' && window.ceggPmConfig ) || {};
}

// Bootstrap Icons (inlined so they don't depend on the bi font being loaded in
// the block-editor bundle). clipboard → clipboard-check on copy; same 16×16 box,
// so the icon-only button never changes width.
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

const clipboardCheckIcon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		width="16"
		height="16"
		fill="currentColor"
		viewBox="0 0 16 16"
		aria-hidden="true"
	>
		<path
			fillRule="evenodd"
			d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0"
		/>
		<path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1z" />
		<path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0z" />
	</svg>
);

// Most-recently-used templates float to the top. Shared with the block's own
// template picker via the same localStorage key, so a template used in either
// place surfaces in both. All accesses are guarded (private mode / storage off).
const TEMPLATE_USAGE_KEY = 'cegg_global_template_usage';

function readTemplateUsage() {
	try {
		const raw = window.localStorage.getItem( TEMPLATE_USAGE_KEY );
		return raw ? JSON.parse( raw ) : {};
	} catch ( e ) {
		return {};
	}
}

function recordTemplateUsage( id ) {
	if ( ! id ) {
		return;
	}
	try {
		const usage = readTemplateUsage();
		usage[ id ] = Date.now();
		window.localStorage.setItem(
			TEMPLATE_USAGE_KEY,
			JSON.stringify( usage )
		);
	} catch ( e ) {
		// storage unavailable — ordering just falls back to server order
	}
}

// The "Shortcode" tab: compose a [content-egg-block …] shortcode from a focused
// field set (template + scope + limit) and copy it, to paste anywhere in the
// content. Available on both editors — on the block editor it is the only way to
// use custom/theme templates, which the native block can't render.
export default function BuilderPanel() {
	const previewBase = cfg().templatePreviewBaseUrl || '';

	// Sort once per mount (most-recently-used first, server order as tiebreak) so
	// the list stays stable while browsing; a fresh copy reorders it next open.
	const templates = useMemo( () => {
		const list = cfg().blockTemplates || [];
		const usage = readTemplateUsage();
		return list
			.map( ( t, i ) => ( { t, i } ) )
			.sort( ( a, b ) => {
				const diff =
					( usage[ b.t.id ] || 0 ) - ( usage[ a.t.id ] || 0 );
				return diff !== 0 ? diff : a.i - b.i;
			} )
			.map( ( x ) => x.t );
	}, [] );

	const { modules } = useModules();
	const moduleLabelById = useMemo( () => {
		const map = {};
		( modules || [] ).forEach( ( m ) => {
			map[ m.id ] = m.label || m.id;
		} );
		return map;
	}, [ modules ] );
	const moduleIdByLabel = useMemo( () => {
		const map = {};
		( modules || [] ).forEach( ( m ) => {
			map[ m.label || m.id ] = m.id;
		} );
		return map;
	}, [ modules ] );
	const moduleLabels = useMemo(
		() => ( modules || [] ).map( ( m ) => m.label || m.id ),
		[ modules ]
	);

	const groupSuggestions = useSelect(
		( select ) =>
			groupsFromProducts(
				productsOnly( select( STORE_NAME ).getProducts() || [] )
			),
		[]
	);

	const [ template, setTemplate ] = useState(
		templates.length ? templates[ 0 ].id : ''
	);
	const [ groups, setGroups ] = useState( [] );
	const [ modulesSel, setModulesSel ] = useState( [] );
	const [ excludeSel, setExcludeSel ] = useState( [] );
	const [ next, setNext ] = useState( '' );
	const [ copied, setCopied ] = useState( false );

	const fields = {
		template,
		groups,
		modules: modulesSel,
		exclude_modules: excludeSel,
		next,
	};
	const shortcode = buildBlockShortcode( fields );

	// FormTokenField holds module *labels*; map to/from module ids for state.
	const labelsToIds = ( labels ) =>
		labels.map( ( l ) => moduleIdByLabel[ l ] || l ).filter( Boolean );
	const idsToLabels = ( ids ) =>
		ids.map( ( id ) => moduleLabelById[ id ] || id );

	// WP's clipboard hook (robust across browsers) returns a ref for the copy
	// button. Feedback shows on the button itself (a brief "Copied") — not a
	// snackbar, whose host is mounted in more than one place and would stack.
	const copyRef = useCopyToClipboard( shortcode, () => {
		setCopied( true );
		window.setTimeout( () => setCopied( false ), 1500 );
		// Mark the copied template as used so it floats to the top next open.
		recordTemplateUsage( template );
	} );

	return (
		<div className="cegg-pm-builder cegg-pm-builder--wide">
			<div className="cegg-pm-builder__bar">
				<InputControl
					className="cegg-pm-builder__code"
					value={ shortcode }
					readOnly
					label={ __( 'Shortcode', 'content-egg' ) }
					hideLabelFromVision
					__next40pxDefaultSize
					onFocus={ ( e ) => e.target.select() }
					onClick={ ( e ) => e.target.select() }
					suffix={
						<InputControlSuffixWrapper variant="control">
							<Button
								ref={ copyRef }
								className={
									'cegg-pm-builder__copy' +
									( copied ? ' is-copied' : '' )
								}
								icon={
									copied ? clipboardCheckIcon : clipboardIcon
								}
								iconSize={ 16 }
								label={
									copied
										? __( 'Copied', 'content-egg' )
										: __( 'Copy shortcode', 'content-egg' )
								}
								showTooltip
								size="small"
								disabled={ ! template }
							/>
						</InputControlSuffixWrapper>
					}
				/>
			</div>

			<div className="cegg-pm-builder__cols">
				<div className="cegg-pm-builder__inputs">
					<div className="cegg-pm-builder__field">
						<FormTokenField
							label={ __( 'Product groups', 'content-egg' ) }
							value={ groups }
							suggestions={ groupSuggestions }
							onChange={ setGroups }
							__experimentalExpandOnFocus
							__experimentalShowHowTo={ false }
						/>
					</div>

					<div className="cegg-pm-builder__field">
						<FormTokenField
							label={ __( 'Modules', 'content-egg' ) }
							value={ idsToLabels( modulesSel ) }
							suggestions={ moduleLabels }
							onChange={ ( labels ) =>
								setModulesSel( labelsToIds( labels ) )
							}
							__experimentalExpandOnFocus
							__experimentalShowHowTo={ false }
						/>
					</div>

					<div className="cegg-pm-builder__field">
						<FormTokenField
							label={ __( 'Exclude modules', 'content-egg' ) }
							value={ idsToLabels( excludeSel ) }
							suggestions={ moduleLabels }
							onChange={ ( labels ) =>
								setExcludeSel( labelsToIds( labels ) )
							}
							__experimentalExpandOnFocus
							__experimentalShowHowTo={ false }
						/>
					</div>

					<div className="cegg-pm-builder__field cegg-pm-builder__field--narrow">
						<TextControl
							label={ __( 'Limit', 'content-egg' ) }
							type="number"
							min="0"
							value={ next }
							onChange={ setNext }
							help={ __( '0 = all products', 'content-egg' ) }
							__nextHasNoMarginBottom
						/>
					</div>
				</div>

				<div className="cegg-pm-builder__templates-col">
					<span className="cegg-pm-builder__label">
						{ __( 'Template', 'content-egg' ) }
					</span>
					<div className="cegg-pm-builder__templates">
						{ templates.map( ( t ) => {
							const selected = t.id === template;
							return (
								<button
									type="button"
									key={ t.id }
									className={
										'cegg-pm-builder__tpl' +
										( selected ? ' is-selected' : '' )
									}
									onClick={ () => setTemplate( t.id ) }
									aria-pressed={ selected }
								>
									<span className="cegg-pm-builder__tpl-thumb">
										{ t.preview ? (
											<img
												src={ previewBase + t.preview }
												alt=""
												loading="lazy"
											/>
										) : (
											<span className="cegg-pm-builder__tpl-noimg" />
										) }
									</span>
									<span className="cegg-pm-builder__tpl-name">
										{ t.name }
										{ t.isCustom && (
											<em className="cegg-pm-builder__tpl-custom">
												{ __(
													'Custom',
													'content-egg'
												) }
											</em>
										) }
									</span>
									{ selected && (
										<span
											className="cegg-pm-builder__tpl-check"
											aria-hidden="true"
										>
											✓
										</span>
									) }
								</button>
							);
						} ) }
					</div>
				</div>
			</div>
		</div>
	);
}
