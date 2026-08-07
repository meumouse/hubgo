/**
 * Minimal REST client for the HubGo admin SPA.
 *
 * Reads the bootstrap config injected via wp_localize_script (window.hubgoBootstrapConfig)
 * and issues fetch() calls against the hubgo/v1 namespace with the WP REST nonce.
 */

/**
 * Read the localized bootstrap config injected by wp_localize_script().
 *
 * @return {object}
 */
export function getBootstrapConfig() {
    return window.hubgoBootstrapConfig || {};
}

function getConfig() {
    return getBootstrapConfig();
}

function buildUrl( endpoint ) {
    const { restUrl } = getConfig();
    const root = ( restUrl || '' ).replace( /\/$/, '' );
    const path = String( endpoint || '' ).replace( /^\//, '' );

    return `${root}/${path}`;
}

async function request( endpoint, { method = 'GET', body = null } = {} ) {
    const { nonce } = getConfig();

    const options = {
        method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce || '',
        },
    };

    if ( body !== null ) {
        options.body = JSON.stringify( body );
    }

    const response = await fetch( buildUrl( endpoint ), options );
    let payload = null;

    try {
        payload = await response.json();
    } catch ( error ) {
        payload = null;
    }

    if ( ! response.ok ) {
        const message = payload && payload.message ? payload.message : `HTTP ${response.status}`;
        const err = new Error( message );
        err.response = payload;
        err.status = response.status;
        throw err;
    }

    return payload;
}

export const api = {
    get: ( endpoint ) => request( endpoint, { method: 'GET' } ),
    post: ( endpoint, body ) => request( endpoint, { method: 'POST', body } ),
    del: ( endpoint ) => request( endpoint, { method: 'DELETE' } ),
};
