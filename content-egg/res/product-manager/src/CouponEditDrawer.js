import { Button, SelectControl, TextControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	createPortal,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { BADGE_COLOR_NAMES } from './badge';
import DescriptionField from './DescriptionField';
import { STORE_NAME } from './store';
import { couponsOnly } from './familyFilter';
import {
	groupsFromProducts,
	groupSelectOptions,
	resolveGroupValue,
} from './groups';

// Scalar coupon fields the drawer edits — the client mirror of the server's
// COUPON whitelist (ProductDataService::EDITABLE_FIELDS_COUPON). `discount` is
// edited separately (it lives under `extra`).
const FIELDS = [
	'title',
	'code',
	'startDate',
	'endDate',
	'url',
	'orig_url',
	'img',
	'logo',
	'merchant',
	'domain',
	'description',
	'badge',
	'badge_color',
	'group',
];

const BADGE_COLOR_OPTIONS = [
	{ value: '', label: __( '— Badge color —', 'content-egg' ) },
	...BADGE_COLOR_NAMES.map( ( name ) => ( { value: name, label: name } ) ),
];

const EMPTY_ITEM = {};

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

// Stored coupon dates are unix seconds; the <input type="date"> wants
// 'YYYY-MM-DD'. Convert both ways so the diff compares stable strings and the
// server's `date` sanitizer re-parses the string on save.
function secondsToDateInput( seconds ) {
	const n = Number( seconds );
	if ( ! Number.isFinite( n ) || n <= 0 ) {
		return '';
	}
	const d = new Date( n * 1000 );
	const p = ( x ) => String( x ).padStart( 2, '0' );
	return `${ d.getFullYear() }-${ p( d.getMonth() + 1 ) }-${ p(
		d.getDate()
	) }`;
}

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

// The coupon analogue of QuickEditDrawer — the same drawer chrome (portal,
// outside-click close, snackbar confirm) with a coupon field set: code, a
// start/end validity window (date inputs), discount, merchant/domain, links,
// image/logo, description, badge, group. No price/stock/rating/attributes/AI/Woo.
export default function CouponEditDrawer( {
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
	const item = create ? EMPTY_ITEM : existing;
	// Group options from the coupon family only (not product groups).
	const coupons = useSelect(
		( sel ) => couponsOnly( sel( STORE_NAME ).getProducts() ),
		[]
	);
	const groups = useMemo( () => groupsFromProducts( coupons ), [ coupons ] );
	const { updateProduct, addProducts } = useDispatch( STORE_NAME );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const notifySaved = ( promise ) => {
		Promise.resolve( promise )
			.then( () =>
				createSuccessNotice(
					create
						? __( 'Coupon added.', 'content-egg' )
						: __( 'Coupon saved.', 'content-egg' ),
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

	const drawerRef = useRef( null );
	useEffect( () => {
		const onDocMouseDown = ( event ) => {
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

	// Snapshot once per open; dates become 'YYYY-MM-DD' input strings, discount
	// is lifted out of extra so the diff can compare it as a scalar.
	const initial = useMemo( () => {
		const snap = {};
		FIELDS.forEach( ( f ) => {
			snap[ f ] = item && item[ f ] !== undefined ? item[ f ] : '';
		} );
		snap.startDate = secondsToDateInput( item?.startDate );
		snap.endDate = secondsToDateInput( item?.endDate );
		snap.discount = String( item?.extra?.discount || '' );
		return snap;
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ moduleId, uniqueId ] );

	const [ draft, setDraft ] = useState( initial );
	const [ touched, setTouched ] = useState( {} );
	const markTouched = ( f ) => () =>
		setTouched( ( t ) => ( { ...t, [ f ]: true } ) );
	const set = ( f ) => ( v ) => setDraft( ( d ) => ( { ...d, [ f ]: v } ) );

	const setGroup = ( value ) => {
		const group = resolveGroupValue( value );
		if ( group === null ) {
			return;
		}
		setDraft( ( d ) => ( { ...d, group } ) );
	};

	const val = ( f ) =>
		draft[ f ] === undefined || draft[ f ] === null
			? ''
			: String( draft[ f ] );

	const titleValid = String( draft.title || '' ).trim() !== '';
	const urlRaw = String( draft.url || draft.orig_url || '' ).trim();
	// A coupon needs a destination URL (its deal link) — the manual Coupon module
	// drops one without a valid URL. Enforced on create.
	const urlValid = ! create || isValidUrl( urlRaw );
	const canSave = titleValid && urlValid;
	const showTitleError = !! touched.title && ! titleValid;
	const showUrlError = !! touched.url && ! urlValid;

	const save = () => {
		if ( ! canSave ) {
			return;
		}

		if ( create ) {
			const newItem = {
				unique_id: Math.random().toString( 36 ).slice( 2 ),
				module_id: moduleId,
			};
			FIELDS.forEach( ( f ) => {
				const v = draft[ f ];
				if ( v !== '' && v !== null && v !== undefined ) {
					newItem[ f ] = v;
				}
			} );
			const discount = String( draft.discount || '' ).trim();
			if ( discount ) {
				newItem.extra = { discount };
			}
			notifySaved( addProducts( moduleId, [ newItem ] ) );
			onClose();
			return;
		}

		const changed = {};
		FIELDS.forEach( ( f ) => {
			if ( draft[ f ] !== initial[ f ] ) {
				changed[ f ] = draft[ f ];
			}
		} );
		if (
			String( draft.discount || '' ) !== String( initial.discount || '' )
		) {
			changed.extra = { discount: String( draft.discount || '' ).trim() };
		}

		if ( Object.keys( changed ).length ) {
			notifySaved( updateProduct( moduleId, uniqueId, changed ) );
		}
		onClose();
	};

	if ( ! item ) {
		return null;
	}

	return createPortal(
		<div className="cegg-pm cegg-pm-drawer" ref={ drawerRef }>
			<div className="cegg-pm-drawer__head">
				{ create
					? __( 'Add coupon', 'content-egg' )
					: __( 'Edit coupon', 'content-egg' ) }
				<Button
					className="cegg-pm-drawer__close"
					icon="no-alt"
					label={ __( 'Close', 'content-egg' ) }
					onClick={ onClose }
				/>
			</div>

			<div className="cegg-pm-drawer__body">
				<TextControl
					label={ requiredLabel( __( 'Title', 'content-egg' ) ) }
					value={ val( 'title' ) }
					onChange={ set( 'title' ) }
					onBlur={ markTouched( 'title' ) }
					className={
						showTitleError ? 'cegg-pm-field--invalid' : undefined
					}
					help={
						showTitleError
							? __( 'Title is required.', 'content-egg' )
							: undefined
					}
					required
					__nextHasNoMarginBottom
				/>
				<div className="cegg-pm-field-row">
					<TextControl
						label={ __( 'Coupon code', 'content-egg' ) }
						value={ val( 'code' ) }
						onChange={ set( 'code' ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Discount', 'content-egg' ) }
						placeholder={ __( 'e.g. 20%', 'content-egg' ) }
						value={ val( 'discount' ) }
						onChange={ set( 'discount' ) }
						__nextHasNoMarginBottom
					/>
				</div>
				<div className="cegg-pm-field-row">
					<TextControl
						type="date"
						label={ __( 'Start date', 'content-egg' ) }
						value={ val( 'startDate' ) }
						onChange={ set( 'startDate' ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						type="date"
						label={ __( 'End date', 'content-egg' ) }
						value={ val( 'endDate' ) }
						onChange={ set( 'endDate' ) }
						__nextHasNoMarginBottom
					/>
				</div>
				<TextControl
					type="url"
					label={ requiredLabel(
						__( 'Destination URL', 'content-egg' )
					) }
					value={ val( 'url' ) }
					onChange={ set( 'url' ) }
					onBlur={ markTouched( 'url' ) }
					className={
						showUrlError ? 'cegg-pm-field--invalid' : undefined
					}
					help={
						showUrlError
							? __(
									'Enter a valid URL (http:// or https://).',
									'content-egg'
							  )
							: undefined
					}
					required
					__nextHasNoMarginBottom
				/>
				<TextControl
					type="url"
					label={ __( 'Image URL', 'content-egg' ) }
					value={ val( 'img' ) }
					onChange={ set( 'img' ) }
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
				<SelectControl
					label={ __( 'Group', 'content-egg' ) }
					value={ val( 'group' ) }
					options={ groupSelectOptions( groups ) }
					onChange={ setGroup }
					__nextHasNoMarginBottom
				/>
				<div className="cegg-pm-field-ai cegg-pm-field-ai--desc">
					<DescriptionField
						label={ __( 'Description (HTML)', 'content-egg' ) }
						value={ val( 'description' ) }
						onChange={ set( 'description' ) }
					/>
				</div>
			</div>

			<div className="cegg-pm-drawer__foot">
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'content-egg' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ save }
					disabled={ ! canSave }
				>
					{ create
						? __( 'Add coupon', 'content-egg' )
						: __( 'Save', 'content-egg' ) }
				</Button>
			</div>
		</div>,
		document.body
	);
}
