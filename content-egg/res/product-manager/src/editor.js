import { createRoot } from '@wordpress/element';
import { select } from '@wordpress/data';
import { registerPlugin } from '@wordpress/plugins';
import { STORE_NAME } from './store'; // registers content-egg/products (window-flag guarded)
import './style.scss';
import ProductsSidebar from './ProductsSidebar';
import ClassicApp from './ClassicApp';

// Eagerly load the post's products/coupons on editor load so the shared editor
// snapshot (window.ceggEditorProducts) is populated immediately. Every block,
// empty state, and binding picker then has data from the start — no need to open
// the Content Egg sidebar or a picker first. Triggers the getProducts resolver
// (which republishes the snapshot on load); it no-ops without a post id.
select( STORE_NAME ).getProducts();

// One bundle, two shells. Classic/Woo/CPT screens render a metabox containing
// #cegg-pm-classic-root (see ProductManagerLoader); mount the app there. On
// block-editor screens there is no such node — register the Gutenberg sidebar.
const classicRoot = document.getElementById( 'cegg-pm-classic-root' );
if ( classicRoot ) {
	createRoot( classicRoot ).render( <ClassicApp /> );
} else {
	registerPlugin( 'content-egg-product-manager', {
		render: ProductsSidebar,
		icon: 'products',
	} );
}
