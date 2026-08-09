import {
	filterByModuleIds,
	excludeModuleIds,
	partitionByFamily,
	productsOnly,
	couponsOnly,
	imagesOnly,
	videosOnly,
} from './familyFilter';

const ROSTER = [
	{ module_id: 'Amazon', unique_id: 'a' },
	{ module_id: 'CjLinks', unique_id: 'b' },
	{ module_id: 'Coupon', unique_id: 'c' },
	{ module_id: 'Ebay', unique_id: 'd' },
];

describe( 'filterByModuleIds / excludeModuleIds', () => {
	const set = new Set( [ 'CjLinks', 'Coupon' ] );

	it( 'keeps only items whose module is in the set', () => {
		expect(
			filterByModuleIds( ROSTER, set ).map( ( p ) => p.unique_id )
		).toEqual( [ 'b', 'c' ] );
	} );

	it( 'excludes items whose module is in the set', () => {
		expect(
			excludeModuleIds( ROSTER, set ).map( ( p ) => p.unique_id )
		).toEqual( [ 'a', 'd' ] );
	} );

	it( 'returns the list unchanged when the set is null', () => {
		expect( filterByModuleIds( ROSTER, null ) ).toEqual( ROSTER );
		expect( excludeModuleIds( ROSTER, null ) ).toEqual( ROSTER );
	} );

	it( 'tolerates empty / undefined input', () => {
		expect( filterByModuleIds( undefined, set ) ).toEqual( [] );
		expect( excludeModuleIds( [], set ) ).toEqual( [] );
	} );
} );

describe( 'partitionByFamily', () => {
	it( 'splits a flat roster into products and coupons', () => {
		const { products, coupons } = partitionByFamily(
			ROSTER,
			new Set( [ 'CjLinks', 'Coupon' ] )
		);
		expect( products.map( ( p ) => p.unique_id ) ).toEqual( [ 'a', 'd' ] );
		expect( coupons.map( ( p ) => p.unique_id ) ).toEqual( [ 'b', 'c' ] );
	} );
} );

describe( 'productsOnly / couponsOnly read the config set', () => {
	beforeEach( () => {
		window.ceggPmConfig = { couponModuleIds: [ 'CjLinks', 'Coupon' ] };
	} );
	afterEach( () => {
		delete window.ceggPmConfig;
	} );

	it( 'productsOnly drops coupon-module items', () => {
		expect( productsOnly( ROSTER ).map( ( p ) => p.unique_id ) ).toEqual( [
			'a',
			'd',
		] );
	} );

	it( 'couponsOnly keeps only coupon-module items', () => {
		expect( couponsOnly( ROSTER ).map( ( p ) => p.unique_id ) ).toEqual( [
			'b',
			'c',
		] );
	} );
} );

describe( 'media partitioning', () => {
	const MEDIA_ROSTER = [
		{ module_id: 'Amazon', unique_id: 'a' },
		{ module_id: 'SkimlinksCoupons', unique_id: 'c' },
		{ module_id: 'BingImages', unique_id: 'i' },
		{ module_id: 'Youtube', unique_id: 'v' },
	];

	beforeEach( () => {
		window.ceggPmConfig = {
			couponModuleIds: [ 'SkimlinksCoupons' ],
			imageModuleIds: [ 'BingImages', 'Pexels' ],
			videoModuleIds: [ 'Youtube' ],
		};
	} );
	afterEach( () => {
		delete window.ceggPmConfig;
	} );

	it( 'imagesOnly returns only image-module items', () => {
		expect( imagesOnly( MEDIA_ROSTER ).map( ( p ) => p.unique_id ) ).toEqual(
			[ 'i' ]
		);
	} );
	it( 'videosOnly returns only video-module items', () => {
		expect( videosOnly( MEDIA_ROSTER ).map( ( p ) => p.unique_id ) ).toEqual(
			[ 'v' ]
		);
	} );
	it( 'productsOnly excludes coupons AND media', () => {
		expect(
			productsOnly( MEDIA_ROSTER ).map( ( p ) => p.unique_id )
		).toEqual( [ 'a' ] );
	} );
	it( 'couponsOnly is unaffected by media config', () => {
		expect(
			couponsOnly( MEDIA_ROSTER ).map( ( p ) => p.unique_id )
		).toEqual( [ 'c' ] );
	} );
} );
