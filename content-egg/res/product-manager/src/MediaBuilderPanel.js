import {
	Button,
	SelectControl,
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
import { mediaOnly } from './familyFilter';
import useModules from './useModules';
import { buildBlockShortcode } from './buildBlockShortcode';

function cfg() {
	return ( typeof window !== 'undefined' && window.ceggPmConfig ) || {};
}

// Bootstrap clipboard → clipboard-check (inlined so the bundle needs no font).
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

// The media "Shortcode" tab: compose a modern [content-egg-block
// template=images|videos …] shortcode. Pick the block template + an optional
// module filter ("all" aggregates every active module of this family). Filter
// mode only (media blocks have no per-item Choose). Copy-only.
export default function MediaBuilderPanel( { parserType = 'IMAGE' } ) {
	const isVideo = parserType === 'VIDEO';
	const { modules } = useModules( { types: parserType } );

	// Module ids that actually have media on this post — surfaced first in the
	// optional filter so the default choices are renderable.
	const presentIds = useSelect(
		( select ) => {
			const ids = mediaOnly(
				select( STORE_NAME ).getProducts() || [],
				parserType
			).map( ( c ) => c.module_id );
			return Array.from( new Set( ids ) );
		},
		[ parserType ]
	);

	const moduleOptions = useMemo( () => {
		const present = ( modules || [] ).filter( ( m ) =>
			presentIds.includes( m.id )
		);
		const rest = ( modules || [] ).filter(
			( m ) => ! presentIds.includes( m.id )
		);
		return [
			{
				value: '',
				label: isVideo
					? __( 'All video modules', 'content-egg' )
					: __( 'All image modules', 'content-egg' ),
			},
			...[ ...present, ...rest ].map( ( m ) => ( {
				value: m.id,
				label: m.label || m.id,
			} ) ),
		];
	}, [ modules, presentIds, isVideo ] );

	// Block templates (localized, screen-independent). Falls back to the single
	// default so the tab always works.
	const templateOptions = useMemo( () => {
		const list =
			( isVideo
				? cfg().videoBlockTemplates
				: cfg().imageBlockTemplates ) || [];
		if ( ! list.length ) {
			return [
				{
					value: isVideo ? 'videos_stacked' : 'images',
					label: isVideo
						? __( 'Videos', 'content-egg' )
						: __( 'Images', 'content-egg' ),
				},
			];
		}
		return list.map( ( t ) => ( {
			value: t.id,
			label: t.isCustom
				? `${ t.name } (${ __( 'custom', 'content-egg' ) })`
				: t.name,
		} ) );
	}, [ isVideo ] );

	const [ moduleId, setModuleId ] = useState( '' );
	const [ template, setTemplate ] = useState( '' );
	const [ copied, setCopied ] = useState( false );

	const activeTemplate = template || templateOptions[ 0 ].value;

	const shortcode = buildBlockShortcode( {
		template: activeTemplate,
		modules: moduleId ? [ moduleId ] : [],
	} );

	const copyRef = useCopyToClipboard( shortcode, () => {
		setCopied( true );
		window.setTimeout( () => setCopied( false ), 1500 );
	} );

	return (
		<div className="cegg-pm-builder">
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
							/>
						</InputControlSuffixWrapper>
					}
				/>
			</div>

			<div className="cegg-pm-builder__inputs">
				{ templateOptions.length > 1 && (
					<div className="cegg-pm-builder__field">
						<SelectControl
							label={ __( 'Template', 'content-egg' ) }
							value={ activeTemplate }
							options={ templateOptions }
							onChange={ setTemplate }
							__nextHasNoMarginBottom
						/>
					</div>
				) }
				<div className="cegg-pm-builder__field">
					<SelectControl
						label={ __( 'Limit to module', 'content-egg' ) }
						value={ moduleId }
						options={ moduleOptions }
						onChange={ setModuleId }
						__nextHasNoMarginBottom
					/>
				</div>
			</div>
		</div>
	);
}
