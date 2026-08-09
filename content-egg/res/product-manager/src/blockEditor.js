import { select } from '@wordpress/data';

// True only on block-editor screens, where core/block-editor exists and blocks
// can be inserted. Classic / Woo / CPT screens have no such store, so block
// insert is unavailable there. Used to gate the "Insert block" affordance
// without threading a shell flag through the component tree.
export function blockEditorAvailable() {
	return !! select( 'core/block-editor' );
}
