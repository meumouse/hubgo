/**
 * api.js
 *
 * REST client for the public storefront endpoints of the hubgo/v1 namespace.
 *
 * Kept separate from `utils/api.js` (the admin client) because the two read
 * different bootstraps and have different auth expectations: these routes are
 * public, so the nonce is sent when present but never required.
 *
 * @since 3.0.0
 */
import { getNonce, getRestUrl } from './params';
import { __ } from '../utils/i18n';

/**
 * Issue a request against the storefront REST namespace.
 *
 * @param {string} path Route path, e.g. `/shipping/calculate`.
 * @param {object} options Fetch options: method, body, query, signal.
 * @return {Promise<object>} Decoded payload.
 * @throws {Error} On transport failure or an error-shaped payload.
 */
async function request( path, { method = 'GET', body = null, query = null, signal = null } = {} ) {
    const root = getRestUrl();

    if ( ! root ) {
        throw new Error( __( 'Could not calculate shipping. Please try again.' ) );
    }

    let url = `${ root }/${ String( path ).replace( /^\//, '' ) }`;

    if ( query ) {
        const search = new URLSearchParams();

        Object.keys( query ).forEach( ( key ) => {
            const value = query[ key ];

            if ( value !== undefined && value !== null && value !== '' ) {
                search.append( key, value );
            }
        } );

        const queryString = search.toString();

        if ( queryString ) {
            url += `?${ queryString }`;
        }
    }

    const headers = { 'Content-Type': 'application/json' };
    const nonce = getNonce();

    // Optional: these routes are public, but sending the nonce lets WordPress
    // resolve the current user, which matters for role-based shipping rules.
    if ( nonce ) {
        headers[ 'X-WP-Nonce' ] = nonce;
    }

    const options = { method, headers, credentials: 'same-origin' };

    if ( body !== null ) {
        options.body = JSON.stringify( body );
    }

    if ( signal ) {
        options.signal = signal;
    }

    const response = await fetch( url, options );
    let payload = null;

    try {
        payload = await response.json();
    } catch ( error ) {
        payload = null;
    }

    // The routes answer `status: 'error'` with a human message for expected
    // failures (a bad postcode, an exhausted rate limit). Surfacing that message
    // is the whole reason those cases are not modelled as empty results.
    if ( ! response.ok || ( payload && payload.status === 'error' ) ) {
        const message = ( payload && payload.message ) ? payload.message : __( 'Could not calculate shipping. Please try again.' );
        const error = new Error( message );
        error.status = response.status;
        error.payload = payload;

        throw error;
    }

    return payload || {};
}

export const storefrontApi = {
    /**
     * Quote shipping for a single product line.
     *
     * @param {object} payload product, variation_id, qty, postcode.
     * @param {AbortSignal|null} signal Abort signal.
     * @return {Promise<object>}
     */
    calculate: ( payload, signal = null ) => request( '/shipping/calculate', {
        method: 'POST',
        body: payload,
        signal,
    } ),

    /**
     * Suggest addresses for the CEP finder.
     *
     * @param {object} query q or uf/city/street, plus session.
     * @param {AbortSignal|null} signal Abort signal.
     * @return {Promise<object>}
     */
    searchAddress: ( query, signal = null ) => request( '/address/autocomplete', { query, signal } ),

    /**
     * Resolve a suggestion into a postcode.
     *
     * @param {object} query id and session.
     * @return {Promise<object>}
     */
    resolveAddress: ( query ) => request( '/address/resolve', { query } ),
};
