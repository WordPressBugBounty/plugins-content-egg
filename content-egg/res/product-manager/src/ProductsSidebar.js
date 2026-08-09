import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import EggIcon from './EggIcon';
import FamilySwitcher from './FamilySwitcher';
import ManagerModal from './ManagerModal';
import { subscribe, select } from '@wordpress/data';
import { familyByKey } from './families';
import { STORE_NAME } from './store';

function cfg() {
	return ( typeof window !== 'undefined' && window.ceggPmConfig ) || {};
}

// Gutenberg shell: the single pinned "Content Egg" sidebar. A family switcher at
// the top toggles between the Products roster (unchanged) and the roster-less
// Coupons launcher — room for a Media segment later. Per-family counts live on
// the switcher tabs; the header carries the edition (Pro/Free), and on the free
// build the Products footer closes with a quiet "Go PRO" link (same utm/GA4-
// tagged pricing link as elsewhere).
export default function ProductsSidebar() {
	const [ family, setFamily ] = useState( 'PRODUCT' );
	// Cross-bundle bridge: other bundles (e.g. the media block) dispatch a window
	// event to open the full manager to a given family without importing this
	// bundle. Opens as a full-screen modal regardless of whether the pinned
	// sidebar is currently visible, and also syncs the sidebar tab.
	const [ managerFamily, setManagerFamily ] = useState( null );
	useEffect( () => {
		function onOpenManager( e ) {
			const f = ( e && e.detail && e.detail.family ) || 'PRODUCT';
			setFamily( f );
			setManagerFamily( f );
		}
		window.addEventListener( 'cegg:open-manager', onOpenManager );
		return () =>
			window.removeEventListener( 'cegg:open-manager', onOpenManager );
	}, [] );

	// Live-refresh blocks whose contents come from the roster (Filter binding):
	// whenever a family's stored data changes — add, remove, in-place edit, or
	// reorder — fire a per-family event so that family's blocks refetch their
	// ServerSideRender preview. Debounced to coalesce bulk edits.
	//   - Media (image/video)  → cegg:media-updated   → media block
	//   - Coupons              → cegg:coupons-updated  → coupon block (filter mode)
	//   - Products             → cegg:products-updated → product block (filter mode)
	// The signature is the per-module content revision (md5 of the module's data,
	// bumped by the server on any write), so it catches drawer edits that leave
	// the item identity unchanged — not just adds/removes. Product-family modules
	// are those NOT in the coupon/image/video sets (no productModuleIds config —
	// products are the remainder).
	useEffect( () => {
		const mediaIds = new Set(
			[
				...( cfg().imageModuleIds || [] ),
				...( cfg().videoModuleIds || [] ),
			].map( String )
		);
		const couponIds = new Set(
			( cfg().couponModuleIds || [] ).map( String )
		);
		const nonProductIds = new Set( [ ...mediaIds, ...couponIds ] );
		// Signature over the modules matching `pred`: `moduleId:revision` for each,
		// sorted. Revisions only change after the authoritative server write, so a
		// refetch reflects persisted data (what the block SSR reads server-side).
		const sigFor = ( pred ) => () => {
			const store = select( STORE_NAME );
			return Object.keys( store.getByModule() || {} )
				.filter( pred )
				.map( ( id ) => id + ':' + store.getRevision( id ) )
				.sort()
				.join( ',' );
		};
		const families = [
			{
				event: 'cegg:media-updated',
				sig: sigFor( ( id ) => mediaIds.has( String( id ) ) ),
			},
			{
				event: 'cegg:coupons-updated',
				sig: sigFor( ( id ) => couponIds.has( String( id ) ) ),
			},
			{
				event: 'cegg:products-updated',
				sig: sigFor( ( id ) => ! nonProductIds.has( String( id ) ) ),
			},
		].map( ( f ) => ( { ...f, prev: f.sig(), timer: null } ) );

		const unsubscribe = subscribe( () => {
			families.forEach( ( f ) => {
				const sig = f.sig();
				if ( sig === f.prev ) {
					return;
				}
				f.prev = sig;
				window.clearTimeout( f.timer );
				f.timer = window.setTimeout( () => {
					window.dispatchEvent(
						new CustomEvent( f.event, { detail: {} } )
					);
				}, 300 );
			} );
		}, STORE_NAME );
		return () => {
			families.forEach( ( f ) => window.clearTimeout( f.timer ) );
			unsubscribe();
		};
	}, [] );
	const isPro = !! cfg().isPro;
	const title = isPro
		? __( 'Content Egg Pro', 'content-egg' )
		: __( 'Content Egg Free', 'content-egg' );

	return (
		<>
			<PluginSidebarMoreMenuItem
				target="cegg-products"
				icon={ <EggIcon /> }
			>
				{ __( 'Content Egg', 'content-egg' ) }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name="cegg-products"
				icon={ <EggIcon /> }
				title={ title }
			>
				{ /* Scope the switcher in `.cegg-pm` so the plugin's CSS custom
				     properties (--cegg-pm-accent, etc.) resolve — the panels
				     below carry their own .cegg-pm, but the switcher sits above
				     them. The classic shell already wraps everything this way. */ }
				<div className="cegg-pm cegg-pm-sidebar-shell">
					<FamilySwitcher family={ family } onChange={ setFamily } />
				</div>
				{ ( () => {
					const F = familyByKey( family );
					const Panel = F.Panel;
					// The free-build upsell rides the Products footer, next to
					// "Update prices" — the only family panel with a button row.
					return <Panel parserType={ F.parserType } showGoPro />;
				} )() }
			</PluginSidebar>
			{ managerFamily && (
				<ManagerModal
					family={ managerFamily }
					initialTab="search"
					onClose={ () => {
						// Let media blocks refetch their preview after add/manage.
						window.dispatchEvent(
							new CustomEvent( 'cegg:media-updated', {
								detail: { family: managerFamily },
							} )
						);
						setManagerFamily( null );
					} }
				/>
			) }
		</>
	);
}
