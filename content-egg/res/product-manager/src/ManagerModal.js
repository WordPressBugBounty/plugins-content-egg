import { Modal } from '@wordpress/components';
import ManagerWorkspace from './ManagerWorkspace';
import { familyByKey } from './families';

// Gutenberg shell: the manager workspace in a full-screen modal, launched from
// the Products sidebar. The classic/Woo/CPT shell renders the same workspace
// inline in a metabox (ClassicApp) and can also open this modal for full width.
// compact defaults false — the modal has room for every column. Insert is gated
// at the row level by a runtime core/block-editor check, not a prop.
export default function ManagerModal( {
	onClose,
	initialTab = 'search',
	compact = false,
	family = 'PRODUCT',
} ) {
	const title = familyByKey( family ).manageTitle;
	return (
		<Modal
			className="cegg-pm"
			title={ title }
			onRequestClose={ onClose }
			isFullScreen
		>
			<ManagerWorkspace
				initialTab={ initialTab }
				compact={ compact }
				family={ family }
			/>
		</Modal>
	);
}
