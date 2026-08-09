import { Button, SnackbarList } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import ManagerWorkspace from './ManagerWorkspace';
import ManagerModal from './ManagerModal';
import FamilySwitcher from './FamilySwitcher';
import { familyByKey } from './families';

// Snackbar host for the roster presentation — the roster (unlike ManagerWorkspace)
// renders no SnackbarList, and classic screens have no global host, so refresh /
// remove toasts would be invisible without this.
function ClassicSnackbars() {
	const notices = useSelect(
		( select ) =>
			select( noticesStore )
				.getNotices()
				.filter( ( n ) => n.type === 'snackbar' ),
		[]
	);
	const { removeNotice } = useDispatch( noticesStore );
	return (
		<SnackbarList
			notices={ notices }
			onRemove={ removeNotice }
			className="cegg-pm-snackbars"
		/>
	);
}

// Non-PluginSidebar mount for the React Product Manager: the classic/Woo/CPT
// metabox, and the block editor's meta-box area in "workspace" mode. The chosen
// presentation (localized by ProductManagerLoader) decides which UI renders:
//   sidebar   → the compact roster; its "Search & add…" opens the full modal,
//               matching the block PluginSidebar.
//   workspace → the full Search|Added|Settings workspace, with a "Full screen"
//               button to open it in the modal for extra room.
export default function ClassicApp() {
	const presentation =
		( typeof window !== 'undefined' &&
			window.ceggPmConfig?.presentation ) ||
		'workspace';
	const [ expanded, setExpanded ] = useState( false );
	const [ family, setFamily ] = useState( 'PRODUCT' );

	if ( presentation === 'sidebar' ) {
		return (
			<div className="cegg-pm cegg-pm-classic cegg-pm-classic--roster">
				<FamilySwitcher family={ family } onChange={ setFamily } />
				{ ( () => {
					const F = familyByKey( family );
					const Panel = F.Panel;
					return <Panel parserType={ F.parserType } />;
				} )() }
				<ClassicSnackbars />
			</div>
		);
	}

	return (
		<div className="cegg-pm cegg-pm-classic">
			<div className="cegg-pm-classic__bar">
				<FamilySwitcher family={ family } onChange={ setFamily } />
				<Button
					variant="secondary"
					size="compact"
					icon="fullscreen-alt"
					label={ __( 'Full screen', 'content-egg' ) }
					showTooltip
					onClick={ () => setExpanded( true ) }
				/>
			</div>
			{ /* Remount the workspace on family switch so its internal tab state
			     resets cleanly to the new family. */ }
			<ManagerWorkspace key={ family } compact family={ family } />
			{ expanded && (
				<ManagerModal
					family={ family }
					onClose={ () => setExpanded( false ) }
				/>
			) }
		</div>
	);
}
