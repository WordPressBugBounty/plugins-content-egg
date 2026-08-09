import {
	Button,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { fetchUpdateSettings, saveUpdateSettings } from './api';

const DOC_URL =
	'https://ce-docs.keywordrush.com/updating-products/updating-the-product-list';

// A module is "shown" as an override row when it already has a per-module
// keyword; the rest are offered in the Add dropdown. The full module list is
// always kept in state so save submits every module (hidden ones as blank),
// preserving the server's full-replace / delete-on-empty semantics.
function decorate( list ) {
	return ( Array.isArray( list ) ? list : [] ).map( ( m ) => ( {
		...m,
		keyword: m.keyword || '',
		params: m.params || { min: '', max: '' },
		shown: ( m.keyword || '' ) !== '',
	} ) );
}

export default function SettingsPanel( {
	postId,
	onSettingsSaved,
	family = 'PRODUCT',
} ) {
	// The global keyword is product-scoped — only the PRODUCT family shows and
	// writes it. Other families manage their own per-module keyword only.
	const isProduct = family === 'PRODUCT';
	const [ globalKeyword, setGlobalKeyword ] = useState( '' );
	const [ modules, setModules ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ dirty, setDirty ] = useState( false );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	// Dedupe the load: React 18 double-invokes the mount effect in the dev editor
	// (StrictMode), which would fire this GET twice. The ref persists across the
	// double-invoke and resets on a real remount / postId change, so we fetch
	// once per post.
	const loadedForPostId = useRef( null );
	useEffect( () => {
		if ( ! postId ) {
			setLoading( false );
			return;
		}
		if ( loadedForPostId.current === postId ) {
			return;
		}
		loadedForPostId.current = postId;
		setLoading( true );
		fetchUpdateSettings( postId, family )
			.then( ( data ) => {
				// Ignore a response superseded by a newer post; the current
				// post's own fetch owns the loading/data state.
				if ( loadedForPostId.current !== postId ) {
					return;
				}
				setGlobalKeyword( data.global_keyword || '' );
				setModules( decorate( data.modules ) );
				setDirty( false );
				setLoading( false );
			} )
			.catch( ( e ) => {
				if ( loadedForPostId.current !== postId ) {
					return;
				}
				// Allow a retry on a later mount if the load failed.
				loadedForPostId.current = null;
				setLoading( false );
				createErrorNotice(
					e?.message ||
						__( 'Could not load settings.', 'content-egg' ),
					{ type: 'snackbar', isDismissible: true }
				);
			} );
	}, [ postId, family, createErrorNotice ] );

	const patchModule = ( id, field, value ) => {
		setDirty( true );
		setModules( ( prev ) =>
			prev.map( ( m ) =>
				m.id === id
					? {
							...m,
							...( field === 'keyword'
								? { keyword: value }
								: {
										params: {
											...m.params,
											[ field ]: value,
										},
								  } ),
					  }
					: m
			)
		);
	};

	const addModule = ( id ) => {
		if ( ! id ) {
			return;
		}
		setDirty( true );
		setModules( ( prev ) =>
			prev.map( ( m ) => ( m.id === id ? { ...m, shown: true } : m ) )
		);
	};

	// Hiding an override clears its keyword/params so the next save deletes the
	// module's meta (the module then falls back to the global keyword).
	const removeModule = ( id ) => {
		setDirty( true );
		setModules( ( prev ) =>
			prev.map( ( m ) =>
				m.id === id
					? {
							...m,
							shown: false,
							keyword: '',
							params: { min: '', max: '' },
					  }
					: m
			)
		);
	};

	const onSave = async () => {
		setSaving( true );
		const payload = {};
		modules.forEach( ( m ) => {
			payload[ m.id ] = {
				keyword: m.shown ? m.keyword || '' : '',
				min: m.shown ? m.params?.min ?? '' : '',
				max: m.shown ? m.params?.max ?? '' : '',
			};
		} );
		try {
			const data = await saveUpdateSettings( {
				postId,
				globalKeyword,
				modules: payload,
				family,
			} );
			setGlobalKeyword( data.global_keyword || '' );
			setModules( decorate( data.modules ) );
			setDirty( false );
			createSuccessNotice( __( 'Settings saved', 'content-egg' ), {
				type: 'snackbar',
			} );
			onSettingsSaved?.();
		} catch ( e ) {
			createErrorNotice(
				e?.message || __( 'Could not save settings.', 'content-egg' ),
				{ type: 'snackbar', isDismissible: true }
			);
		} finally {
			setSaving( false );
		}
	};

	if ( ! postId ) {
		return (
			<div className="cegg-pm-settings">
				<p className="cegg-pm-settings__intro">
					{ __(
						'Save the post before configuring auto-update settings.',
						'content-egg'
					) }
				</p>
			</div>
		);
	}

	const shown = modules.filter( ( m ) => m.shown );
	const addable = modules.filter( ( m ) => ! m.shown );

	return (
		<div className="cegg-pm-settings">
			<p className="cegg-pm-settings__intro">
				{ isProduct
					? __(
							'Automatic updates periodically re-run these keyword searches and replace the post’s items with fresh results. A per-module keyword overrides the global keyword for that module. The number of items and the update frequency are set in each module’s own settings.',
							'content-egg'
					  )
					: __(
							'Automatic updates periodically re-run each module’s keyword search and replace its items with fresh results. These modules use their own per-module keyword only — the global keyword applies to product modules. The number of items and the update frequency are set in each module’s own settings.',
							'content-egg'
					  ) }{ ' ' }
				<a
					className="cegg-pm-settings__help"
					href={ DOC_URL }
					target="_blank"
					rel="noreferrer"
					aria-label={ __(
						'Learn more about updating products',
						'content-egg'
					) }
				>
					<span
						className="dashicons dashicons-info-outline"
						aria-hidden="true"
					/>
				</a>
			</p>

			{ isProduct && (
				<div className="cegg-pm-settings__global">
					<TextControl
						label={ __( 'Global update keyword', 'content-egg' ) }
						value={ globalKeyword }
						disabled={ loading }
						onChange={ ( v ) => {
							setDirty( true );
							setGlobalKeyword( v );
						} }
						__nextHasNoMarginBottom
					/>
				</div>
			) }

			<div className="cegg-pm-settings__overrides">
				<div className="cegg-pm-settings__overrides-title">
					{ isProduct
						? __( 'Per-module overrides', 'content-egg' )
						: __( 'Per-module keywords', 'content-egg' ) }
				</div>

				{ loading && (
					<div className="cegg-pm-settings__loading-inline">
						<Spinner />
					</div>
				) }
				{ ! loading &&
					( shown.length === 0 ? (
						<p className="cegg-pm-settings__empty">
							{ isProduct
								? __(
										'No overrides — every module uses the global keyword.',
										'content-egg'
								  )
								: __(
										'No keywords set — automatic updates are off for these modules.',
										'content-egg'
								  ) }
						</p>
					) : (
						<table className="cegg-pm-table cegg-pm-settings__table">
							<thead>
								<tr>
									<th>{ __( 'Module', 'content-egg' ) }</th>
									<th>{ __( 'Keyword', 'content-egg' ) }</th>
									<th>
										{ __( 'Min price', 'content-egg' ) }
									</th>
									<th>
										{ __( 'Max price', 'content-egg' ) }
									</th>
									<th className="cegg-pm-settings__remove-col" />
								</tr>
							</thead>
							<tbody>
								{ shown.map( ( m ) => (
									<tr key={ m.id }>
										<td className="cegg-pm-settings__module">
											{ m.label }
										</td>
										<td>
											<TextControl
												value={ m.keyword || '' }
												placeholder={
													isProduct
														? globalKeyword
														: ''
												}
												onChange={ ( v ) =>
													patchModule(
														m.id,
														'keyword',
														v
													)
												}
												__nextHasNoMarginBottom
												aria-label={ __(
													'Update keyword',
													'content-egg'
												) }
											/>
										</td>
										<td>
											{ m.price_filter ? (
												<TextControl
													type="number"
													value={
														m.params?.min ?? ''
													}
													onChange={ ( v ) =>
														patchModule(
															m.id,
															'min',
															v
														)
													}
													__nextHasNoMarginBottom
													aria-label={ __(
														'Min price',
														'content-egg'
													) }
												/>
											) : (
												<span className="cegg-pm-settings__na">
													–
												</span>
											) }
										</td>
										<td>
											{ m.price_filter ? (
												<TextControl
													type="number"
													value={
														m.params?.max ?? ''
													}
													onChange={ ( v ) =>
														patchModule(
															m.id,
															'max',
															v
														)
													}
													__nextHasNoMarginBottom
													aria-label={ __(
														'Max price',
														'content-egg'
													) }
												/>
											) : (
												<span className="cegg-pm-settings__na">
													–
												</span>
											) }
										</td>
										<td className="cegg-pm-settings__remove-col">
											<Button
												variant="tertiary"
												isDestructive
												icon="no-alt"
												onClick={ () =>
													removeModule( m.id )
												}
												label={ __(
													'Remove override',
													'content-egg'
												) }
												showTooltip
											/>
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) ) }

				{ addable.length > 0 && (
					<div className="cegg-pm-settings__add">
						<SelectControl
							__nextHasNoMarginBottom
							value=""
							onChange={ ( id ) => addModule( id ) }
							options={ [
								{
									label: isProduct
										? __(
												'Add module override…',
												'content-egg'
										  )
										: __(
												'Add module keyword…',
												'content-egg'
										  ),
									value: '',
								},
								...addable.map( ( m ) => ( {
									label: m.label,
									value: m.id,
								} ) ),
							] }
							aria-label={ __(
								'Add module override',
								'content-egg'
							) }
						/>
					</div>
				) }
			</div>

			<div className="cegg-pm-settings__footer">
				<Button
					variant="primary"
					onClick={ onSave }
					isBusy={ saving }
					disabled={ saving || ! dirty }
				>
					{ __( 'Save settings', 'content-egg' ) }
				</Button>
			</div>
		</div>
	);
}
