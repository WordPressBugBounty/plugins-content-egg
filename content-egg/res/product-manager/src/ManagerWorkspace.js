import { SnackbarList, TabPanel } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { STORE_NAME } from './store';
import { getKey } from './keys';
import { familyByKey } from './families';
import SearchPanel from './SearchPanel';
import SettingsPanel from './SettingsPanel';

// The manager workspace: Search | Added | Settings tabs + quick-edit drawer +
// snackbar host. Rendered inside a full-screen Modal on Gutenberg (ManagerModal,
// launched from the sidebar) and inline in the metabox at full width on classic
// / Woo / CPT screens (ClassicApp). It is shell-agnostic — the parent supplies
// the `.cegg-pm` scope (the modal frame, or ClassicApp's wrapper div).
export default function ManagerWorkspace( {
	initialTab = 'search',
	compact = false,
	family = 'PRODUCT',
} ) {
	const F = familyByKey( family );
	// Scope the flat store roster to this workspace's family, and pick the
	// family's table / edit-drawer / shortcode-builder from the registry.
	const familyItems = F.filter;
	const Table = F.Table;
	const Drawer = F.Drawer;
	const Builder = F.Builder;
	const postId = useSelect(
		( select ) => select( STORE_NAME ).getPostId(),
		[]
	);
	// Live count of items added to this post in this family — updates as items are
	// added/removed anywhere (store is the single source of truth).
	const addedCount = useSelect(
		( select ) =>
			familyItems( select( STORE_NAME ).getProducts() || [] ).length,
		[ familyItems ]
	);
	// Keys ("moduleId:uniqueId") of products already attached to the post, so the
	// Search tab can flag results that are in already. Derived from getProducts()
	// — a core selector present in every bundle's copy of the shared store —
	// rather than a dedicated selector: the store is registered first-wins across
	// several block bundles, so a newly-added selector isn't guaranteed to exist
	// at runtime.
	const attachedKeys = useSelect(
		( select ) =>
			familyItems( select( STORE_NAME ).getProducts() || [] ).map(
				( p ) => getKey( p.module_id, p.unique_id )
			),
		[ familyItems ]
	);
	// Snackbar toasts (save confirmations/errors). Gutenberg renders a global
	// host, but it sits behind the full-screen modal, and classic screens have no
	// host at all — so the workspace renders its own copy where it's visible.
	const snackbarNotices = useSelect(
		( select ) =>
			select( noticesStore )
				.getNotices()
				.filter( ( n ) => n.type === 'snackbar' ),
		[]
	);
	const { removeNotice } = useDispatch( noticesStore );

	const [ editing, setEditing ] = useState( null );
	// TabPanel is uncontrolled, so switching tabs programmatically (the empty
	// Added state's "Search products" CTA) is done by remounting it with a new
	// initial tab. Bumping the key remounts both panels; that only fires from the
	// empty-Added CTA, where starting Search fresh is exactly what's wanted.
	const [ startTab, setStartTab ] = useState( initialTab );
	const [ tabSeq, setTabSeq ] = useState( 0 );
	// An EAN handed to the Search tab on the next (re)mount, so clicking an EAN in
	// the Added tab jumps to Search and runs the cross-merchant compare there.
	const [ compareEan, setCompareEan ] = useState( '' );
	// "Update listings" on the Attached tab is gated on a keyword existing in
	// post meta; bump this after a Settings save so AttachedTable re-checks.
	const [ keywordStatusSeq, setKeywordStatusSeq ] = useState( 0 );

	const goToSearch = () => {
		setCompareEan( '' );
		setStartTab( 'search' );
		setTabSeq( ( n ) => n + 1 );
	};
	const compareEanInSearch = ( ean ) => {
		setCompareEan( String( ean || '' ) );
		setStartTab( 'search' );
		setTabSeq( ( n ) => n + 1 );
	};

	return (
		<>
			<TabPanel
				key={ tabSeq }
				initialTabName={ startTab }
				tabs={ [
					{ name: 'search', title: __( 'Search', 'content-egg' ) },
					{
						name: 'added',
						title: (
							<>
								{ __( 'Added', 'content-egg' ) }
								{ addedCount > 0 && (
									<span className="cegg-pm-tab-badge">
										{ addedCount }
									</span>
								) }
							</>
						),
					},
					{
						name: 'shortcode',
						title: __( 'Shortcode', 'content-egg' ),
					},
					{
						name: 'settings',
						title: __( 'Settings', 'content-egg' ),
					},
				] }
			>
				{ ( tab ) => (
					// Keep both panels mounted and just toggle visibility so the
					// Search tab's keyword/selection/results survive a switch to
					// Attached and back (TabPanel unmounts the inactive tab).
					<>
						<div hidden={ tab.name !== 'search' }>
							<SearchPanel
								postId={ postId }
								existingKeys={ attachedKeys }
								initialCompareEan={ compareEan }
								family={ family }
							/>
						</div>
						<div hidden={ tab.name !== 'added' }>
							<Table
								postId={ postId }
								onEdit={ setEditing }
								onGoToSearch={ goToSearch }
								onCompareEan={ compareEanInSearch }
								keywordStatusSeq={ keywordStatusSeq }
								compact={ compact }
								parserType={ F.parserType }
							/>
						</div>
						{ tab.name === 'settings' && (
							<SettingsPanel
								postId={ postId }
								family={ family }
								onSettingsSaved={ () =>
									setKeywordStatusSeq( ( s ) => s + 1 )
								}
							/>
						) }
						{ tab.name === 'shortcode' && (
							<Builder parserType={ F.parserType } />
						) }
					</>
				) }
			</TabPanel>

			{ editing && (
				<Drawer
					key={
						editing.create
							? 'create'
							: getKey( editing.moduleId, editing.uniqueId )
					}
					moduleId={ editing.moduleId }
					uniqueId={ editing.uniqueId }
					create={ !! editing.create }
					parserType={ F.parserType }
					onClose={ () => setEditing( null ) }
				/>
			) }

			<SnackbarList
				notices={ snackbarNotices }
				onRemove={ removeNotice }
				className="cegg-pm-snackbars"
			/>
		</>
	);
}
