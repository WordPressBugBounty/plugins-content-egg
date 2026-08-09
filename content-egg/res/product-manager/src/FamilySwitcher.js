import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from './store';
import { FAMILIES } from './families';

// Segmented control at the top of the unified "Content Egg" sidebar: switches
// the panel between the module families the manager handles (Products, Coupons,
// Images, Videos). Built as a small button group (not the experimental
// ToggleGroupControl) so it's stable and driven entirely by the families
// registry. Each tab shows its own live count, so the header stays a plain
// "Content Egg" and the per-family totals live where you switch between them.
function cfg() {
	return ( typeof window !== 'undefined' && window.ceggPmConfig ) || {};
}

export default function FamilySwitcher( { family, onChange } ) {
	// One store read, partitioned per family via each family's own filter — the
	// store spans all families, and counts update live as items change anywhere.
	const counts = useSelect( ( select ) => {
		const all = select( STORE_NAME ).getProducts() || [];
		const out = {};
		FAMILIES.forEach( ( f ) => {
			out[ f.key ] = f.filter( all ).length;
		} );
		return out;
	}, [] );

	// Only show a family that has active modules, OR already has items on the
	// post (so a deactivated module's residual data stays reachable), OR is the
	// currently-selected tab (never let the active tab vanish). Missing config
	// (older installs) → show everything.
	const activeFamilies =
		cfg().activeFamilies || FAMILIES.map( ( f ) => f.key );
	const visibleFamilies = FAMILIES.filter(
		( f ) =>
			f.key === family ||
			activeFamilies.includes( f.key ) ||
			( counts[ f.key ] || 0 ) > 0
	);

	return (
		<div
			className="cegg-pm-family"
			role="tablist"
			aria-label={ __( 'Content Egg families', 'content-egg' ) }
		>
			{ visibleFamilies.map( ( f ) => {
				const active = f.key === family;
				const count = counts[ f.key ] || 0;
				return (
					<button
						key={ f.key }
						type="button"
						role="tab"
						aria-selected={ active }
						className={
							'cegg-pm-family__tab' +
							( active ? ' is-active' : '' )
						}
						onClick={ () => ! active && onChange( f.key ) }
					>
						{ f.label }
						{ count > 0 && (
							<span className="cegg-pm-family__count">
								{ count }
							</span>
						) }
					</button>
				);
			} ) }
		</div>
	);
}
