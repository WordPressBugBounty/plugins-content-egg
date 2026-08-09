import { __ } from '@wordpress/i18n';
import {
	productsOnly,
	couponsOnly,
	imagesOnly,
	videosOnly,
} from './familyFilter';
import ProductManagerPanel from './ProductManagerPanel';
import CouponsPanel from './CouponsPanel';
import MediaPanel from './MediaPanel';
import AttachedTable from './AttachedTable';
import CouponTable from './CouponTable';
import MediaTable from './MediaTable';
import QuickEditDrawer from './QuickEditDrawer';
import CouponEditDrawer from './CouponEditDrawer';
import MediaEditDrawer from './MediaEditDrawer';
import BuilderPanel from './BuilderPanel';
import CouponBuilderPanel from './CouponBuilderPanel';
import MediaBuilderPanel from './MediaBuilderPanel';

// Single source of truth for the manager's families. Adding a family is a data
// entry here; the switcher, shells, and workspace all read from this list.
// IMAGE and VIDEO share the SAME parameterized media components (a parserType
// prop distinguishes them).
export const FAMILIES = [
	{
		key: 'PRODUCT',
		label: __( 'Products', 'content-egg' ),
		manageTitle: __( 'Manage products', 'content-egg' ),
		filter: productsOnly,
		Panel: ProductManagerPanel,
		Table: AttachedTable,
		Drawer: QuickEditDrawer,
		Builder: BuilderPanel,
	},
	{
		key: 'COUPON',
		label: __( 'Coupons', 'content-egg' ),
		manageTitle: __( 'Manage coupons', 'content-egg' ),
		filter: couponsOnly,
		Panel: CouponsPanel,
		Table: CouponTable,
		Drawer: CouponEditDrawer,
		Builder: CouponBuilderPanel,
	},
	{
		key: 'IMAGE',
		label: __( 'Images', 'content-egg' ),
		manageTitle: __( 'Manage images', 'content-egg' ),
		parserType: 'IMAGE',
		filter: imagesOnly,
		Panel: MediaPanel,
		Table: MediaTable,
		Drawer: MediaEditDrawer,
		Builder: MediaBuilderPanel,
	},
	{
		key: 'VIDEO',
		label: __( 'Videos', 'content-egg' ),
		manageTitle: __( 'Manage videos', 'content-egg' ),
		parserType: 'VIDEO',
		filter: videosOnly,
		Panel: MediaPanel,
		Table: MediaTable,
		Drawer: MediaEditDrawer,
		Builder: MediaBuilderPanel,
	},
];

export function familyByKey( key ) {
	return FAMILIES.find( ( f ) => f.key === key ) || FAMILIES[ 0 ];
}
