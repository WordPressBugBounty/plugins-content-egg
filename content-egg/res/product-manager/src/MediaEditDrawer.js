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
import DescriptionField from './DescriptionField';
import { STORE_NAME } from './store';
import { mediaOnly } from './familyFilter';
import {
	groupsFromProducts,
	groupSelectOptions,
	resolveGroupValue,
} from './groups';

// Scalar media fields the drawer edits — the client mirror of the server's
// IMAGE/VIDEO whitelist (ProductDataService::EDITABLE_FIELDS_MEDIA). Source
// identity (img URL, extra.guid, extra.video_url) is read-only and not listed.
const FIELDS = [ 'title', 'url', 'description', 'group' ];

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

// The media analogue of QuickEditDrawer/CouponEditDrawer — the same drawer
// chrome (portal, outside-click close, snackbar confirm) with the light media
// field set: title, url, description, group. Edit-only (media has no manual
// entry), so there is no create path.
export default function MediaEditDrawer( {
	moduleId,
	uniqueId,
	parserType,
	onClose,
} ) {
	const item = useSelect(
		( sel ) =>
			sel( STORE_NAME )
				.getModuleItems( moduleId )
				.find( ( p ) => String( p.unique_id ) === String( uniqueId ) ),
		[ moduleId, uniqueId ]
	);
	// Group options from the same media family only.
	const media = useSelect(
		( sel ) => mediaOnly( sel( STORE_NAME ).getProducts(), parserType ),
		[ parserType ]
	);
	const groups = useMemo( () => groupsFromProducts( media ), [ media ] );
	const { updateProduct } = useDispatch( STORE_NAME );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const notifySaved = ( promise ) => {
		Promise.resolve( promise )
			.then( () =>
				createSuccessNotice( __( 'Saved.', 'content-egg' ), {
					type: 'snackbar',
				} )
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

	const initial = useMemo( () => {
		const snap = {};
		FIELDS.forEach( ( f ) => {
			snap[ f ] = item && item[ f ] !== undefined ? item[ f ] : '';
		} );
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
	const canSave = titleValid;
	const showTitleError = !! touched.title && ! titleValid;

	const save = () => {
		if ( ! canSave ) {
			return;
		}
		const changed = {};
		FIELDS.forEach( ( f ) => {
			if ( draft[ f ] !== initial[ f ] ) {
				changed[ f ] = draft[ f ];
			}
		} );
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
				{ parserType === 'VIDEO'
					? __( 'Edit video', 'content-egg' )
					: __( 'Edit image', 'content-egg' ) }
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
				<TextControl
					type="url"
					label={ __( 'URL', 'content-egg' ) }
					value={ val( 'url' ) }
					onChange={ set( 'url' ) }
					__nextHasNoMarginBottom
				/>
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
					{ __( 'Save', 'content-egg' ) }
				</Button>
			</div>
		</div>,
		document.body
	);
}
