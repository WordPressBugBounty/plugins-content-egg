import { useEffect, useState } from '@wordpress/element';
import { fetchModules } from './api';

// Per-family module catalog cache. The endpoint returns a different module set
// per parser-type family (PRODUCT vs COUPON), so cache and de-dupe requests
// keyed by the requested `types` string. Defaults to PRODUCT so existing
// callers (useModules()) are unchanged.
const cacheByTypes = {};
const inflightByTypes = {};

export default function useModules( { types = 'PRODUCT' } = {} ) {
	const key = types || 'PRODUCT';
	const [ modules, setModules ] = useState( cacheByTypes[ key ] || [] );
	const [ isLoading, setIsLoading ] = useState( ! cacheByTypes[ key ] );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		if ( cacheByTypes[ key ] ) {
			setModules( cacheByTypes[ key ] );
			setIsLoading( false );
			return undefined;
		}

		if ( ! inflightByTypes[ key ] ) {
			inflightByTypes[ key ] = fetchModules( key );
		}

		let cancelled = false;
		inflightByTypes[ key ]
			.then( ( result ) => {
				cacheByTypes[ key ] = Array.isArray( result ) ? result : [];
				if ( ! cancelled ) {
					setModules( cacheByTypes[ key ] );
					setIsLoading( false );
				}
			} )
			.catch( ( err ) => {
				inflightByTypes[ key ] = null;
				if ( ! cancelled ) {
					setError( err );
					setIsLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ key ] );

	return { modules, isLoading, error };
}
