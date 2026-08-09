import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

// Modules arrive priority-sorted from the REST layer. Show the top N as chips;
// the rest collapse behind "Show all" so a large module list stays manageable.
const CHIP_LIMIT = 8;

export default function ModuleChips( {
	modules,
	selectedIds,
	onToggle,
	onSelectAll,
	onClear,
} ) {
	const [ showAll, setShowAll ] = useState( false );

	// Collapsed: show the top N, plus any selected chips past the limit so the
	// selection is never hidden.
	let visible = modules;
	if ( ! showAll && modules.length > CHIP_LIMIT ) {
		const top = modules.slice( 0, CHIP_LIMIT );
		const selectedBeyond = modules
			.slice( CHIP_LIMIT )
			.filter( ( module ) => selectedIds.includes( module.id ) );
		visible = [ ...top, ...selectedBeyond ];
	}

	// Toggle is based on what's ACTUALLY hidden — so when everything is already
	// visible (e.g. all modules selected) it disappears instead of doing nothing.
	const actuallyHidden = modules.length - visible.length;
	const showToggle =
		modules.length > CHIP_LIMIT && ( showAll || actuallyHidden > 0 );

	return (
		<div className="cegg-pm-modules">
			<div className="cegg-pm-modules__actions">
				<Button variant="link" onClick={ onSelectAll }>
					{ __( 'Select all', 'content-egg' ) }
				</Button>
				<Button
					variant="link"
					onClick={ onClear }
					disabled={ selectedIds.length === 0 }
				>
					{ __( 'Clear', 'content-egg' ) }
				</Button>
			</div>

			<div className="cegg-pm-chips">
				{ visible.map( ( module ) => (
					<Button
						key={ module.id }
						size="small"
						variant={
							selectedIds.includes( module.id )
								? 'primary'
								: 'secondary'
						}
						onClick={ () => onToggle( module.id ) }
					>
						{ module.label }
					</Button>
				) ) }
				{ showToggle && (
					<Button
						variant="link"
						size="small"
						onClick={ () => setShowAll( ! showAll ) }
					>
						{ showAll
							? __( 'Show less', 'content-egg' )
							: sprintf(
									/* translators: %d: number of hidden modules */
									__( 'Show %d more', 'content-egg' ),
									actuallyHidden
							  ) }
					</Button>
				) }
			</div>
		</div>
	);
}
