import {
	Button,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { select, useDispatch, useSelect } from '@wordpress/data';
import {
	createPortal,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import AiMenu from './AiMenu';
import { aiEnabled, aiTitleMethods, aiDescriptionMethods } from './aiConfig';
import { runAiUndo } from './aiUndo';
import { BADGE_COLOR_NAMES } from './badge';
import DescriptionField from './DescriptionField';
import { STORE_NAME } from './store';
import {
	groupsFromProducts,
	groupSelectOptions,
	resolveGroupValue,
} from './groups';
import { isWooProduct, wooFlagOn } from './wooConfig';

// Every field the drawer can edit — the client mirror of the server whitelist
// (ProductDataService::EDITABLE_FIELDS). Main fields show by default; the rest
// live behind the "More fields" disclosure. Anything not listed here (identity,
// cloaked links, computed values) is never editable.
const FIELDS = [
	// main
	'title',
	'subtitle',
	'url',
	'orig_url',
	'img',
	'price',
	'priceOld',
	'ratingDecimal',
	'rating', // derived from ratingDecimal server-side; kept for the diff snapshot
	'reviewsCount',
	'badge',
	'badge_color',
	'promo',
	'merchant',
	'domain',
	'description',
	// additional
	'stock_status',
	'availability',
	'group',
	'order_num',
	'manufacturer',
	'currencyCode',
	'shipping_cost',
	'ean',
	'upc',
	'sku',
	'isbn',
	'short_description',
	'logo', // merchant logo URL (Offer)
	'features', // list of {name, value} attribute rows
];

// The manual-entry module (Offer). Its products carry extra Offer-only fields
// (logo, custom XPath price selector, custom deeplink); those inputs show only
// when editing/creating a product for this module.
const MANUAL_MODULE_ID =
	( typeof window !== 'undefined' && window.ceggPmConfig?.manualModuleId ) ||
	'';

// Editable sub-keys of the product's `extra` array (mirrors the server's
// ProductDataService::EXTRA_EDITABLE). Everything else in `extra` is parser-set
// and left untouched.
const EXTRA_FIELDS = [ 'priceXpath', 'deeplink' ];

const BADGE_COLOR_OPTIONS = [
	{ value: '', label: __( '— Badge color —', 'content-egg' ) },
	...BADGE_COLOR_NAMES.map( ( name ) => ( { value: name, label: name } ) ),
];

// Supported currency codes come from the server (CurrencyHelper::getCurrenciesList,
// localized as window.ceggPmConfig.currencies).
const CURRENCY_CODES =
	( typeof window !== 'undefined' && window.ceggPmConfig?.currencies ) || [];
const CURRENCY_OPTIONS = [
	{ value: '', label: __( '— Currency —', 'content-egg' ) },
	...CURRENCY_CODES.map( ( code ) => ( { value: code, label: code } ) ),
];

const STOCK_OPTIONS = [
	{ value: '', label: '—' },
	{ value: '1', label: __( 'In stock', 'content-egg' ) },
	{ value: '-1', label: __( 'Out of stock', 'content-egg' ) },
	{ value: '0', label: __( 'Unknown', 'content-egg' ) },
];

// Remember whether the advanced / attributes sections were open so a second edit
// in the same session doesn't re-collapse them (module-scoped, resets on reload).
let advancedOpenMemo = false;
let attributesOpenMemo = false;

// Stable blank template for create mode (a fresh reference would rebuild the
// initial snapshot every render).
const EMPTY_ITEM = {};

// A simple, permissive URL check: it must parse and use http/https. Good enough
// to catch typos and bare domains without rejecting valid affiliate links.
function isValidUrl( value ) {
	const s = String( value || '' ).trim();
	if ( ! s ) {
		return false;
	}
	try {
		const u = new URL( s );
		return u.protocol === 'http:' || u.protocol === 'https:';
	} catch ( e ) {
		return false;
	}
}

// A field label with a required marker.
function requiredLabel( text ) {
	return (
		<>
			{ text }{ ' ' }
			<span className="cegg-pm-required" aria-hidden="true">
				*
			</span>
		</>
	);
}

export default function QuickEditDrawer( {
	moduleId,
	uniqueId,
	create = false,
	onClose,
} ) {
	const existing = useSelect(
		( sel ) =>
			sel( STORE_NAME )
				.getModuleItems( moduleId )
				.find( ( p ) => String( p.unique_id ) === String( uniqueId ) ),
		[ moduleId, uniqueId ]
	);
	// In create mode there's no stored product yet — edit a blank template and
	// persist only on save (Cancel discards, so no empty product is created).
	const item = create ? EMPTY_ITEM : existing;
	// Derive groups from the current products so the options never go stale.
	const products = useSelect(
		( sel ) => sel( STORE_NAME ).getProducts(),
		[]
	);
	const groups = useMemo(
		() => groupsFromProducts( products ),
		[ products ]
	);
	const { updateProduct, addProducts, applyAi, setWooFlag } =
		useDispatch( STORE_NAME );
	const isSaving = useSelect( ( sel ) => sel( STORE_NAME ).isSaving(), [] );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	// The write actions resolve when the save round-trips (they rethrow on
	// failure), so a snackbar can confirm success — or surface an error even in
	// the fullscreen manager where the sidebar's error notice isn't visible.
	const notifySaved = ( promise ) => {
		Promise.resolve( promise )
			.then( () =>
				createSuccessNotice(
					create
						? __( 'Product added.', 'content-egg' )
						: __( 'Product saved.', 'content-egg' ),
					{ type: 'snackbar' }
				)
			)
			.catch( ( e ) =>
				createErrorNotice(
					e?.message || __( 'Save failed.', 'content-egg' ),
					{ type: 'snackbar' }
				)
			);
	};

	// Close when the user clicks outside the drawer. Capture phase so it fires
	// even over the manager modal (portaled alongside this drawer). The drawer's
	// only dropdowns are native <select>s, which don't render DOM outside the
	// drawer, so an outside click is unambiguous.
	const drawerRef = useRef( null );
	useEffect( () => {
		const onDocMouseDown = ( event ) => {
			// The description "Expand" editor is a Modal portaled outside the
			// drawer, TinyMCE renders its dropdowns/link dialog at <body>
			// level ([class*="mce-"]), and the per-field AiMenu is a
			// DropdownMenu whose popover also portals to <body>
			// (.components-popover / .components-dropdown-menu__popover) —
			// clicks in any of these must not be treated as an outside click
			// that closes the drawer.
			if (
				event.target.closest(
					'.cegg-pm-desc-modal, [class*="mce-"], .components-popover, .components-dropdown-menu__popover'
				)
			) {
				return;
			}
			if (
				drawerRef.current &&
				! drawerRef.current.contains( event.target )
			) {
				onClose();
			}
		};
		document.addEventListener( 'mousedown', onDocMouseDown, true );
		return () =>
			document.removeEventListener( 'mousedown', onDocMouseDown, true );
	}, [ onClose ] );

	// Snapshot the item's field values once (per open) so the save can diff and
	// send only what actually changed — writing the whole draft would stamp
	// empty strings (and coerce absent numbers to 0, e.g. shipping_cost → "free
	// shipping") onto fields the user never touched.
	const initial = useMemo( () => {
		const snap = {};
		FIELDS.forEach( ( f ) => {
			snap[ f ] = item && item[ f ] !== undefined ? item[ f ] : '';
		} );
		// features is a list, not a scalar — normalize so the editor always has
		// an array to map over and the diff has a stable baseline.
		snap.features = Array.isArray( item?.features ) ? item.features : [];
		// Only the editable extra sub-keys — never the whole extra (it holds
		// parser-set keys like last_error we must not send back).
		const srcExtra = item?.extra || {};
		snap.extra = {};
		EXTRA_FIELDS.forEach( ( f ) => {
			snap.extra[ f ] = srcExtra[ f ] !== undefined ? srcExtra[ f ] : '';
		} );
		return snap;
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ moduleId, uniqueId ] );

	const [ draft, setDraft ] = useState( initial );
	// Which required fields the user has interacted with — a validation error is
	// shown only after a field is touched (blurred), so an empty drawer doesn't
	// open pre-flagged with errors.
	const [ touched, setTouched ] = useState( {} );
	const markTouched = ( f ) => () =>
		setTouched( ( t ) => ( { ...t, [ f ]: true } ) );
	const [ advancedOpen, setAdvancedOpen ] = useState( advancedOpenMemo );
	const [ attributesOpen, setAttributesOpen ] =
		useState( attributesOpenMemo );
	const set = ( f ) => ( v ) => setDraft( ( d ) => ( { ...d, [ f ]: v } ) );

	// Per-item AI (title/description rewrite) — edit mode only, and only when
	// the module has an AI key configured (aiConfig mirrors the server gate).
	const aiOn = aiEnabled() && ! create;
	const [ aiBusy, setAiBusy ] = useState( '' ); // '' | 'title' | 'description'
	const [ aiUndoData, setAiUndoData ] = useState( null );

	// Attributes (features) — a repeatable list of {name, value} rows. Each
	// mutation produces new row objects so the item's stored array is never
	// mutated in place and the diff can compare cleanly.
	const features = Array.isArray( draft.features ) ? draft.features : [];
	const setFeature = ( index, key ) => ( value ) =>
		setDraft( ( d ) => {
			const list = ( Array.isArray( d.features ) ? d.features : [] ).map(
				( row, i ) => ( i === index ? { ...row, [ key ]: value } : row )
			);
			return { ...d, features: list };
		} );
	const addFeature = () =>
		setDraft( ( d ) => ( {
			...d,
			features: [
				...( Array.isArray( d.features ) ? d.features : [] ),
				{ name: '', value: '' },
			],
		} ) );
	const removeFeature = ( index ) =>
		setDraft( ( d ) => ( {
			...d,
			features: ( Array.isArray( d.features ) ? d.features : [] ).filter(
				( _, i ) => i !== index
			),
		} ) );

	// Offer-only fields (custom XPath, deeplink) live under `extra`.
	const isManualModule = !! MANUAL_MODULE_ID && moduleId === MANUAL_MODULE_ID;
	const extra = draft.extra || {};
	const setExtra = ( key ) => ( value ) =>
		setDraft( ( d ) => ( {
			...d,
			extra: { ...( d.extra || {} ), [ key ]: value },
		} ) );

	// Group has an extra "+ New group…" entry; resolve it (prompt) before setting.
	const setGroup = ( value ) => {
		const group = resolveGroupValue( value );
		if ( group === null ) {
			return;
		}
		setDraft( ( d ) => ( { ...d, group } ) );
	};

	const toggleAdvanced = () => {
		setAdvancedOpen( ( open ) => {
			advancedOpenMemo = ! open;
			return ! open;
		} );
	};

	const toggleAttributes = () => {
		setAttributesOpen( ( open ) => {
			attributesOpenMemo = ! open;
			return ! open;
		} );
	};

	// Controlled inputs need strings; values may arrive as numbers or undefined.
	const val = ( f ) =>
		draft[ f ] === undefined || draft[ f ] === null
			? ''
			: String( draft[ f ] );

	// Price fields: show an empty input for an unset/zero price rather than "0"
	// (priceOld is stored as 0 when there's no genuine "was" price).
	const priceVal = ( f ) => {
		const v = draft[ f ];
		if ( v === '' || v === null || v === undefined || Number( v ) === 0 ) {
			return '';
		}
		return String( v );
	};

	const titleValid = String( draft.title || '' ).trim() !== '';
	// A new manual product needs a valid URL — a product without one is dropped
	// on save (it can't produce an affiliate link). Only enforced when creating.
	const urlRaw = String( draft.orig_url || '' ).trim();
	const urlValid = ! create || isValidUrl( urlRaw );
	const canSave = titleValid && urlValid;
	// Only surface a required-field error once that field has been touched.
	const showTitleError = !! touched.title && ! titleValid;
	const showUrlError = !! touched.orig_url && ! urlValid;
	const urlErrorText =
		urlRaw === ''
			? __( 'Product URL is required.', 'content-egg' )
			: __( 'Enter a valid URL (http:// or https://).', 'content-egg' );

	// Attributes: an attribute needs BOTH a name and a value — drop any
	// incomplete row silently (an empty pair or a half-filled one).
	const cleanFeatures = features
		.map( ( row ) => ( {
			name: String( row?.name || '' ).trim(),
			value: String( row?.value || '' ).trim(),
		} ) )
		.filter( ( row ) => row.name !== '' && row.value !== '' );

	// Editable `extra` sub-keys, trimmed. Compared against the loaded values so a
	// save sends `extra` only when one of them actually changed.
	const cleanExtra = {};
	EXTRA_FIELDS.forEach( ( f ) => {
		cleanExtra[ f ] = String( extra[ f ] || '' ).trim();
	} );
	const extraChanged = EXTRA_FIELDS.some(
		( f ) =>
			cleanExtra[ f ] !==
			String( ( initial.extra || {} )[ f ] || '' ).trim()
	);
	const hasExtra = EXTRA_FIELDS.some( ( f ) => cleanExtra[ f ] !== '' );

	const save = () => {
		if ( ! canSave ) {
			return;
		}

		// Create mode: assemble a fresh product from the filled fields (no diff —
		// there's no baseline) and add it to the manual module. unique_id is
		// generated client-side, matching the legacy "Add offer". rating is left
		// out — the server derives it from ratingDecimal.
		if ( create ) {
			const newItem = {
				unique_id: Math.random().toString( 36 ).slice( 2 ),
				module_id: moduleId,
			};
			FIELDS.forEach( ( f ) => {
				if ( f === 'features' || f === 'rating' ) {
					return;
				}
				const v = draft[ f ];
				if ( v !== '' && v !== null && v !== undefined ) {
					newItem[ f ] = v;
				}
			} );
			if ( cleanFeatures.length ) {
				newItem.features = cleanFeatures;
			}
			if ( isManualModule && hasExtra ) {
				newItem.extra = cleanExtra;
			}
			notifySaved( addProducts( moduleId, [ newItem ] ) );
			onClose();
			return;
		}

		// Edit mode: send only the fields that actually changed.
		const changed = {};
		FIELDS.forEach( ( f ) => {
			if ( f === 'features' ) {
				return; // array field, compared separately below
			}
			if ( draft[ f ] !== initial[ f ] ) {
				changed[ f ] = draft[ f ];
			}
		} );

		// Attributes: send only if the cleaned list differs from what we loaded.
		const initialFeatures = Array.isArray( initial.features )
			? initial.features
			: [];
		if (
			JSON.stringify( cleanFeatures ) !==
			JSON.stringify( initialFeatures )
		) {
			changed.features = cleanFeatures;
		}

		// Offer extra sub-keys: merged server-side into the existing extra.
		if ( isManualModule && extraChanged ) {
			changed.extra = cleanExtra;
		}

		if ( Object.keys( changed ).length ) {
			notifySaved( updateProduct( moduleId, uniqueId, changed ) );
		}
		onClose();
	};

	// Run AI (title rephrase / description rewrite) on the item's CURRENT,
	// possibly-unsaved drawer values, then pull the persisted result back into
	// the draft so the field reflects what the server actually stored.
	const runAi = async ( kind, method ) => {
		if ( aiBusy ) {
			return;
		}
		setAiBusy( kind );
		const aiItem = {
			unique_id: uniqueId,
			title: val( 'title' ),
			subtitle: val( 'subtitle' ),
			description: val( 'description' ),
		};
		const args =
			kind === 'title'
				? [ moduleId, [ aiItem ], method, '' ]
				: [ moduleId, [ aiItem ], '', method ];
		try {
			const result = await applyAi( ...args );
			if ( result?.error ) {
				// Soft failure: the store recorded the error, but this drawer
				// occludes the sidebar Notice that would render it — surface
				// it here as a snackbar instead.
				createErrorNotice( result.error, {
					type: 'snackbar',
					isDismissible: true,
				} );
				return;
			}
			const saved = select( STORE_NAME )
				.getModuleItems( moduleId )
				.find(
					( it ) => String( it.unique_id ) === String( uniqueId )
				);
			if ( saved ) {
				setDraft( ( d ) => ( {
					...d,
					title: saved.title || '',
					subtitle: saved.subtitle || '',
					description: saved.description || '',
				} ) );
			}
			if ( result?.previous?.length ) {
				setAiUndoData( result.previous );
			}
			createSuccessNotice( __( 'AI applied', 'content-egg' ), {
				type: 'snackbar',
			} );
		} catch ( error ) {
			// Hard failure (thrown/rejected promise) — also catches this so an
			// unhandled rejection never escapes the fire-and-forget onClick.
			createErrorNotice(
				error?.message || __( 'AI request failed.', 'content-egg' ),
				{ type: 'snackbar', isDismissible: true }
			);
		} finally {
			setAiBusy( '' );
		}
	};

	// Single-level undo: replay the pre-AI snapshot through the normal update
	// path, then re-sync the draft from the restored store item.
	const undoAi = async () => {
		if ( ! aiUndoData ) {
			return;
		}
		await runAiUndo( aiUndoData, updateProduct );
		setAiUndoData( null );
		const restored = select( STORE_NAME )
			.getModuleItems( moduleId )
			.find( ( it ) => String( it.unique_id ) === String( uniqueId ) );
		if ( restored ) {
			setDraft( ( d ) => ( {
				...d,
				title: restored.title || '',
				subtitle: restored.subtitle || '',
				description: restored.description || '',
			} ) );
		}
		createSuccessNotice( __( 'Reverted', 'content-egg' ), {
			type: 'snackbar',
		} );
	};

	if ( ! item ) {
		return null;
	}

	// Offer's last price-parse error, surfaced read-only under the XPath field.
	const lastError = String( item?.extra?.last_error || '' ).trim();

	// Portal to <body>: registerPlugin mounts this component inside Gutenberg's
	// PluginArea, which sits under transformed ancestors that trap position:fixed
	// in a hidden box. Rendering at the document root lets the fixed drawer show.
	return createPortal(
		<div className="cegg-pm cegg-pm-drawer" ref={ drawerRef }>
			<div className="cegg-pm-drawer__head">
				{ create
					? __( 'Add product', 'content-egg' )
					: __( 'Edit product', 'content-egg' ) }
				<Button
					className="cegg-pm-drawer__close"
					icon="no-alt"
					label={ __( 'Close', 'content-egg' ) }
					onClick={ onClose }
				/>
			</div>

			<div className="cegg-pm-drawer__body">
				<div className="cegg-pm-field-ai">
					<TextControl
						label={ requiredLabel( __( 'Title', 'content-egg' ) ) }
						value={ val( 'title' ) }
						onChange={ set( 'title' ) }
						onBlur={ markTouched( 'title' ) }
						className={
							showTitleError
								? 'cegg-pm-field--invalid'
								: undefined
						}
						help={
							showTitleError
								? __( 'Title is required.', 'content-egg' )
								: undefined
						}
						required
						__nextHasNoMarginBottom
					/>
					{ aiOn && (
						<AiMenu
							label={ __( 'AI', 'content-egg' ) }
							methods={ aiTitleMethods() }
							busy={ aiBusy === 'title' }
							disabled={ !! aiBusy }
							onPick={ ( method ) => runAi( 'title', method ) }
						/>
					) }
				</div>
				<TextControl
					label={ __( 'Subtitle', 'content-egg' ) }
					value={ val( 'subtitle' ) }
					onChange={ set( 'subtitle' ) }
					__nextHasNoMarginBottom
				/>
				{ create && (
					<TextControl
						type="url"
						label={ requiredLabel(
							__( 'Product URL', 'content-egg' )
						) }
						value={ val( 'orig_url' ) }
						onChange={ set( 'orig_url' ) }
						onBlur={ markTouched( 'orig_url' ) }
						className={
							showUrlError ? 'cegg-pm-field--invalid' : undefined
						}
						help={ showUrlError ? urlErrorText : undefined }
						required
						__nextHasNoMarginBottom
					/>
				) }
				<TextControl
					type="url"
					label={ __( 'Image URL', 'content-egg' ) }
					value={ val( 'img' ) }
					onChange={ set( 'img' ) }
					__nextHasNoMarginBottom
				/>
				<div className="cegg-pm-field-row">
					<TextControl
						label={ __( 'Price', 'content-egg' ) }
						value={ priceVal( 'price' ) }
						onChange={ set( 'price' ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Old price', 'content-egg' ) }
						value={ priceVal( 'priceOld' ) }
						onChange={ set( 'priceOld' ) }
						__nextHasNoMarginBottom
					/>
					{ /* Manual (Offer) products set their own price, so the
					     currency belongs right next to it. */ }
					{ isManualModule && (
						<SelectControl
							className="cegg-pm-field--currency"
							label={ __( 'Currency', 'content-egg' ) }
							value={ val( 'currencyCode' ) }
							options={ CURRENCY_OPTIONS }
							onChange={ set( 'currencyCode' ) }
							__nextHasNoMarginBottom
						/>
					) }
				</div>
				<div className="cegg-pm-field-row">
					<TextControl
						label={ __( 'Rating', 'content-egg' ) }
						placeholder={ __( 'e.g. 4.5', 'content-egg' ) }
						value={ val( 'ratingDecimal' ) }
						onChange={ set( 'ratingDecimal' ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						type="number"
						label={ __( 'Reviews', 'content-egg' ) }
						value={ val( 'reviewsCount' ) }
						onChange={ set( 'reviewsCount' ) }
						__nextHasNoMarginBottom
					/>
				</div>
				<div className="cegg-pm-field-row">
					<TextControl
						label={ __( 'Badge', 'content-egg' ) }
						value={ val( 'badge' ) }
						onChange={ set( 'badge' ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Badge color', 'content-egg' ) }
						value={ val( 'badge_color' ) }
						options={ BADGE_COLOR_OPTIONS }
						onChange={ set( 'badge_color' ) }
						__nextHasNoMarginBottom
					/>
				</div>
				<TextControl
					label={ __( 'Promo', 'content-egg' ) }
					value={ val( 'promo' ) }
					onChange={ set( 'promo' ) }
					__nextHasNoMarginBottom
				/>
				<div className="cegg-pm-field-row">
					<TextControl
						label={ __( 'Merchant', 'content-egg' ) }
						value={ val( 'merchant' ) }
						onChange={ set( 'merchant' ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Domain', 'content-egg' ) }
						value={ val( 'domain' ) }
						onChange={ set( 'domain' ) }
						__nextHasNoMarginBottom
					/>
				</div>
				<div className="cegg-pm-field-ai cegg-pm-field-ai--desc">
					<DescriptionField
						label={ __( 'Description (HTML)', 'content-egg' ) }
						value={ val( 'description' ) }
						onChange={ set( 'description' ) }
						actions={
							aiOn && (
								<AiMenu
									label={ __( 'AI', 'content-egg' ) }
									methods={ aiDescriptionMethods() }
									busy={ aiBusy === 'description' }
									disabled={ !! aiBusy }
									onPick={ ( method ) =>
										runAi( 'description', method )
									}
								/>
							)
						}
					/>
				</div>
				{ aiOn && aiUndoData && (
					<Button variant="link" onClick={ undoAi }>
						{ __( 'Undo AI', 'content-egg' ) }
					</Button>
				) }

				<button
					type="button"
					className="cegg-pm-drawer__more"
					aria-expanded={ advancedOpen }
					onClick={ toggleAdvanced }
				>
					<span className="cegg-pm-drawer__more-chevron">
						{ advancedOpen ? '▾' : '▸' }
					</span>
					{ __( 'More fields', 'content-egg' ) }
				</button>

				{ advancedOpen && (
					<div className="cegg-pm-drawer__advanced">
						<TextControl
							type="url"
							label={ __(
								'Affiliate Product URL',
								'content-egg'
							) }
							value={ val( 'url' ) }
							onChange={ set( 'url' ) }
							__nextHasNoMarginBottom
						/>
						{ /* In create mode orig_url is the required "Product URL"
						     field shown up top — don't repeat it here. */ }
						{ ! create && (
							<TextControl
								type="url"
								label={ __(
									'Direct Product URL',
									'content-egg'
								) }
								value={ val( 'orig_url' ) }
								onChange={ set( 'orig_url' ) }
								__nextHasNoMarginBottom
							/>
						) }
						<div className="cegg-pm-field-row">
							<SelectControl
								label={ __( 'Stock status', 'content-egg' ) }
								value={ val( 'stock_status' ) }
								options={ STOCK_OPTIONS }
								onChange={ set( 'stock_status' ) }
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __( 'Availability', 'content-egg' ) }
								value={ val( 'availability' ) }
								onChange={ set( 'availability' ) }
								__nextHasNoMarginBottom
							/>
						</div>
						<div className="cegg-pm-field-row">
							<SelectControl
								label={ __( 'Group', 'content-egg' ) }
								value={ val( 'group' ) }
								options={ groupSelectOptions( groups ) }
								onChange={ setGroup }
								__nextHasNoMarginBottom
							/>
							<TextControl
								type="number"
								label={ __( 'Order', 'content-egg' ) }
								value={ val( 'order_num' ) }
								onChange={ set( 'order_num' ) }
								__nextHasNoMarginBottom
							/>
						</div>
						<TextControl
							label={ __( 'Manufacturer', 'content-egg' ) }
							value={ val( 'manufacturer' ) }
							onChange={ set( 'manufacturer' ) }
							__nextHasNoMarginBottom
						/>
						<div className="cegg-pm-field-row">
							{ /* Currency sits by the price for manual products;
							     keep it here only for the rest. */ }
							{ ! isManualModule && (
								<SelectControl
									label={ __( 'Currency', 'content-egg' ) }
									value={ val( 'currencyCode' ) }
									options={ CURRENCY_OPTIONS }
									onChange={ set( 'currencyCode' ) }
									__nextHasNoMarginBottom
								/>
							) }
							<TextControl
								label={ __( 'Shipping cost', 'content-egg' ) }
								value={ val( 'shipping_cost' ) }
								onChange={ set( 'shipping_cost' ) }
								__nextHasNoMarginBottom
							/>
						</div>
						<div className="cegg-pm-field-row">
							<TextControl
								label={ __( 'EAN', 'content-egg' ) }
								value={ val( 'ean' ) }
								onChange={ set( 'ean' ) }
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __( 'UPC', 'content-egg' ) }
								value={ val( 'upc' ) }
								onChange={ set( 'upc' ) }
								__nextHasNoMarginBottom
							/>
						</div>
						<div className="cegg-pm-field-row">
							<TextControl
								label={ __( 'SKU', 'content-egg' ) }
								value={ val( 'sku' ) }
								onChange={ set( 'sku' ) }
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __( 'ISBN', 'content-egg' ) }
								value={ val( 'isbn' ) }
								onChange={ set( 'isbn' ) }
								__nextHasNoMarginBottom
							/>
						</div>
						<TextareaControl
							label={ __(
								'Short description (plain HTML)',
								'content-egg'
							) }
							value={ val( 'short_description' ) }
							onChange={ set( 'short_description' ) }
							rows={ 3 }
							onKeyDown={ ( e ) => {
								if ( e.key === 'Enter' ) {
									e.stopPropagation();
								}
							} }
							__nextHasNoMarginBottom
						/>
						{ isManualModule && (
							<>
								<TextControl
									type="url"
									label={ __(
										'Merchant logo URL',
										'content-egg'
									) }
									value={ val( 'logo' ) }
									onChange={ set( 'logo' ) }
									__nextHasNoMarginBottom
								/>
								<TextControl
									label={ __(
										'Custom XPath price selector',
										'content-egg'
									) }
									value={ String( extra.priceXpath || '' ) }
									onChange={ setExtra( 'priceXpath' ) }
									__nextHasNoMarginBottom
								/>
								<TextControl
									label={ __(
										'Custom deeplink',
										'content-egg'
									) }
									value={ String( extra.deeplink || '' ) }
									onChange={ setExtra( 'deeplink' ) }
									__nextHasNoMarginBottom
								/>
								{ lastError && (
									<div className="cegg-pm-drawer__last-error">
										{ sprintf(
											/* translators: %s: last price-parse error */
											__(
												'Last XPath error: %s',
												'content-egg'
											),
											lastError
										) }
									</div>
								) }
							</>
						) }
					</div>
				) }

				<div className="cegg-pm-attrs">
					<button
						type="button"
						className="cegg-pm-drawer__more cegg-pm-attrs__toggle"
						aria-expanded={ attributesOpen }
						onClick={ toggleAttributes }
					>
						<span className="cegg-pm-drawer__more-chevron">
							{ attributesOpen ? '▾' : '▸' }
						</span>
						{ sprintf(
							/* translators: %d: number of attributes */
							__( 'Attributes (%d)', 'content-egg' ),
							features.length
						) }
					</button>
					{ attributesOpen && (
						<div className="cegg-pm-attrs__list">
							{ features.map( ( row, index ) => (
								<div
									className="cegg-pm-attrs__row"
									key={ index }
								>
									<TextControl
										className="cegg-pm-attrs__name"
										label={ __( 'Name', 'content-egg' ) }
										hideLabelFromVision
										placeholder={ __(
											'Name',
											'content-egg'
										) }
										value={ String( row?.name || '' ) }
										onChange={ setFeature( index, 'name' ) }
										__nextHasNoMarginBottom
									/>
									<TextControl
										className="cegg-pm-attrs__value"
										label={ __( 'Value', 'content-egg' ) }
										hideLabelFromVision
										placeholder={ __(
											'Value',
											'content-egg'
										) }
										value={ String( row?.value || '' ) }
										onChange={ setFeature(
											index,
											'value'
										) }
										__nextHasNoMarginBottom
									/>
									<Button
										className="cegg-pm-attrs__remove"
										icon="no-alt"
										label={ __(
											'Remove attribute',
											'content-egg'
										) }
										size="small"
										onClick={ () => removeFeature( index ) }
									/>
								</div>
							) ) }
							<Button
								className="cegg-pm-attrs__add"
								variant="link"
								icon="plus"
								onClick={ addFeature }
							>
								{ __( 'Add attribute', 'content-egg' ) }
							</Button>
						</div>
					) }
				</div>
			</div>

			<div className="cegg-pm-drawer__foot">
				{ ! create && isWooProduct() && existing && (
					<div className="cegg-pm-drawer__foot-woo">
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Woo sync', 'content-egg' ) }
							checked={ wooFlagOn( existing, 'woo_sync' ) }
							disabled={ isSaving }
							onChange={ ( next ) =>
								setWooFlag(
									moduleId,
									uniqueId,
									'woo_sync',
									next
								)
							}
						/>
						{ features.length > 0 && (
							<ToggleControl
								__nextHasNoMarginBottom
								label={ sprintf(
									/* translators: %d: number of attributes */
									__( 'Woo attr (%d)', 'content-egg' ),
									features.length
								) }
								checked={ wooFlagOn( existing, 'woo_attr' ) }
								disabled={ isSaving }
								onChange={ ( next ) =>
									setWooFlag(
										moduleId,
										uniqueId,
										'woo_attr',
										next
									)
								}
							/>
						) }
					</div>
				) }
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'content-egg' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ save }
					disabled={ ! canSave }
				>
					{ create
						? __( 'Add product', 'content-egg' )
						: __( 'Save', 'content-egg' ) }
				</Button>
			</div>
		</div>,
		document.body
	);
}
