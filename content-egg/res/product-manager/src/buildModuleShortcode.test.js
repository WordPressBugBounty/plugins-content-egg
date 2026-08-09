import { buildModuleShortcode } from './buildModuleShortcode';

describe( 'buildModuleShortcode', () => {
	it( 'builds module + template', () => {
		expect(
			buildModuleShortcode( { module: 'CjLinks', template: 'coupons' } )
		).toBe( '[content-egg module=CjLinks template=coupons]' );
	} );

	it( 'omits a blank template', () => {
		expect( buildModuleShortcode( { module: 'Coupon' } ) ).toBe(
			'[content-egg module=Coupon]'
		);
	} );

	it( 'adds a quoted, comma-joined groups list', () => {
		expect(
			buildModuleShortcode( {
				module: 'CjLinks',
				template: 'universal',
				groups: [ ' Summer ', '', 'VIP' ],
			} )
		).toBe(
			'[content-egg module=CjLinks template=universal groups="Summer,VIP"]'
		);
	} );

	it( 'tolerates an empty field set', () => {
		expect( buildModuleShortcode() ).toBe( '[content-egg]' );
	} );
} );
