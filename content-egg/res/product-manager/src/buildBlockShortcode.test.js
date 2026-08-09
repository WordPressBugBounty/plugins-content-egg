import { buildBlockShortcode } from './buildBlockShortcode';

describe( 'buildBlockShortcode', () => {
	it( 'template only', () => {
		expect( buildBlockShortcode( { template: 'block_offers' } ) ).toBe(
			'[content-egg-block template=block_offers]'
		);
	} );

	it( 'template + groups (comma-joined, quoted)', () => {
		expect(
			buildBlockShortcode( {
				template: 'block_list',
				groups: [ 'Best', 'Budget' ],
			} )
		).toBe(
			'[content-egg-block template=block_list groups="Best,Budget"]'
		);
	} );

	it( 'template + modules + exclude_modules', () => {
		expect(
			buildBlockShortcode( {
				template: 'block_list',
				modules: [ 'Amazon' ],
				exclude_modules: [ 'Offers' ],
			} )
		).toBe(
			'[content-egg-block template=block_list modules="Amazon" exclude_modules="Offers"]'
		);
	} );

	it( 'next: "3" kept, 0 / blank / non-numeric omitted', () => {
		expect(
			buildBlockShortcode( { template: 'block_list', next: '3' } )
		).toBe( '[content-egg-block template=block_list next=3]' );
		expect(
			buildBlockShortcode( { template: 'block_list', next: 0 } )
		).toBe( '[content-egg-block template=block_list]' );
		expect(
			buildBlockShortcode( { template: 'block_list', next: '' } )
		).toBe( '[content-egg-block template=block_list]' );
		expect(
			buildBlockShortcode( { template: 'block_list', next: 'abc' } )
		).toBe( '[content-egg-block template=block_list]' );
	} );

	it( 'empty arrays omit their attribute', () => {
		expect(
			buildBlockShortcode( {
				template: 'block_list',
				groups: [],
				modules: [],
				exclude_modules: [],
			} )
		).toBe( '[content-egg-block template=block_list]' );
	} );

	it( 'no template still emits a valid (empty) shortcode', () => {
		expect( buildBlockShortcode( {} ) ).toBe( '[content-egg-block]' );
	} );

	it( 'products: a single composite coupon ref (string)', () => {
		expect(
			buildBlockShortcode( {
				template: 'coupons_ticket',
				products: 'Coupon:abc',
			} )
		).toBe( '[content-egg-block template=coupons_ticket products="Coupon:abc"]' );
	} );

	it( 'products: an array of refs is comma-joined', () => {
		expect(
			buildBlockShortcode( {
				template: 'coupons_ticket',
				products: [ 'Coupon:abc', 'SkimlinksCoupons:9' ],
			} )
		).toBe(
			'[content-egg-block template=coupons_ticket products="Coupon:abc,SkimlinksCoupons:9"]'
		);
	} );

	it( 'products: blank omits the attribute', () => {
		expect(
			buildBlockShortcode( { template: 'coupons_ticket', products: '' } )
		).toBe( '[content-egg-block template=coupons_ticket]' );
	} );
} );
