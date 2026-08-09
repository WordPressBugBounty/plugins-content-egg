import { couponState } from './couponExpiry';

const NOW = 1_700_000_000; // fixed reference "now" in unix seconds
const DAY = 86_400;

describe( 'couponState', () => {
	it( 'is expired when endDate is in the past', () => {
		expect( couponState( { endDate: NOW - DAY }, NOW ) ).toBe( 'expired' );
	} );

	it( 'is upcoming when startDate is in the future', () => {
		expect( couponState( { startDate: NOW + DAY }, NOW ) ).toBe(
			'upcoming'
		);
	} );

	it( 'is active within the window', () => {
		expect(
			couponState( { startDate: NOW - DAY, endDate: NOW + DAY }, NOW )
		).toBe( 'active' );
	} );

	it( 'is active when both bounds are blank/zero', () => {
		expect( couponState( {}, NOW ) ).toBe( 'active' );
		expect( couponState( { startDate: '', endDate: 0 }, NOW ) ).toBe(
			'active'
		);
	} );

	it( 'prefers expired over upcoming when both apply (past end wins)', () => {
		// A malformed window (end before start, both past) still reads expired.
		expect(
			couponState( { startDate: NOW + DAY, endDate: NOW - DAY }, NOW )
		).toBe( 'expired' );
	} );
} );
