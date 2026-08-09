import { Button, Tooltip } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { STORE_NAME } from './store';

// Shared "run an update for the keyworded products on this post" button. Used by
// both the sidebar footer and the Added-tab toolbar so the two surfaces look and
// behave identically. `type` is the refresh operation: 'prices' re-fetches
// price/availability, 'listings' re-runs the keyword search to refresh products.
// The visible label stays constant — a changing label resizes the button and
// jumps the toolbar; progress is the built-in busy spinner and completion is a
// snackbar (doneLabel).
export default function RefreshButton( {
	type,
	idleLabel,
	doneLabel,
	tooltip,
	disabled = false,
	disabledTooltip,
	variant = 'secondary',
	size,
} ) {
	const { refresh } = useDispatch( STORE_NAME );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );
	const [ busy, setBusy ] = useState( false );
	// Guard the post-await state update against a resolve after unmount. Set true
	// on every mount — React 18 double-invokes effects (StrictMode) and the
	// initial useRef(true) is not re-run on remount, so a cleanup-only flag would
	// stay false and freeze the button busy after the first cycle.
	const mounted = useRef( true );
	useEffect( () => {
		mounted.current = true;
		return () => {
			mounted.current = false;
		};
	}, [] );

	const isDisabled = disabled || busy;

	const run = async () => {
		if ( isDisabled ) {
			return;
		}
		setBusy( true );
		try {
			await refresh( type );
			if ( doneLabel ) {
				createSuccessNotice( doneLabel, { type: 'snackbar' } );
			}
		} catch ( e ) {
			createErrorNotice(
				e?.message || __( 'Update failed.', 'content-egg' ),
				{ type: 'snackbar', isDismissible: true }
			);
		} finally {
			if ( mounted.current ) {
				setBusy( false );
			}
		}
	};

	const button = (
		<Button
			variant={ variant }
			size={ size }
			onClick={ run }
			disabled={ isDisabled }
			// Keep an externally-disabled button focusable so its tooltip can
			// still explain why it's off (native disabled swallows hover/focus).
			accessibleWhenDisabled={ !! disabled }
			isBusy={ busy }
		>
			{ idleLabel }
		</Button>
	);

	// Show the "why it's off" tooltip when externally disabled, otherwise the
	// action tooltip. (A busy button is disabled too, but only transiently.)
	const tip = disabled ? disabledTooltip || tooltip : tooltip;
	return tip ? <Tooltip text={ tip }>{ button }</Tooltip> : button;
}
