import { groupByModule } from './bridgeImport';

describe( 'groupByModule', () => {
	it( 'groups products by module_id', () => {
		const out = groupByModule( [
			{ module_id: 'Amazon', unique_id: 'a' },
			{ module_id: 'Offers', unique_id: 'b' },
			{ module_id: 'Amazon', unique_id: 'c' },
		] );
		expect( Object.keys( out ).sort() ).toEqual( [ 'Amazon', 'Offers' ] );
		expect( out.Amazon.map( ( p ) => p.unique_id ) ).toEqual( [
			'a',
			'c',
		] );
		expect( out.Offers ).toHaveLength( 1 );
	} );

	it( 'skips items without a module_id and tolerates empty input', () => {
		expect( groupByModule( [ { unique_id: 'x' } ] ) ).toEqual( {} );
		expect( groupByModule( [] ) ).toEqual( {} );
		expect( groupByModule( undefined ) ).toEqual( {} );
	} );
} );
