/**
 * HubGo - "Check for updates" link on the plugins list.
 *
 * Runs the check over hubgo/v1 and writes the answer next to the link, so the
 * plugins screen is never reloaded. The link keeps a real, nonced href: when
 * this script does not run, following it performs the same check server-side.
 *
 * @since 3.0.0
 */
( function() {
    'use strict';

    var params = window.hubgoUpdateCheckParams || {};
    var i18n = params.i18n || {};

    /**
     * Post to the update-check endpoint.
     *
     * @return {Promise<Object>} Parsed REST payload.
     */
    function requestCheck() {
        return fetch( params.rest_url + '/updates/check', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': params.nonce || ''
            }
        } ).then( function( response ) {
            return response.json().catch( function() {
                return {};
            } ).then( function( data ) {
                if ( ! response.ok || 'error' === data.status ) {
                    throw new Error( data.message || i18n.error );
                }

                return data;
            } );
        } );
    }

    /**
     * Reset a status node before writing a new state into it.
     *
     * @param {HTMLElement} node Status node.
     * @param {string} state Modifier suffix: loading, update, uptodate or error.
     * @return {void}
     */
    function resetStatus( node, state ) {
        while ( node.firstChild ) {
            node.removeChild( node.firstChild );
        }

        node.className = 'hubgo-check-updates-status is-visible is-' + state;
    }

    /**
     * Render the result of a finished check.
     *
     * @param {HTMLElement} node Status node.
     * @param {Object} data REST payload.
     * @return {void}
     */
    function renderResult( node, data ) {
        resetStatus( node, data.update_available ? 'update' : 'uptodate' );
        node.appendChild( document.createTextNode( data.message || '' ) );

        if ( ! data.update_available || ! data.update_url ) {
            return;
        }

        var action = document.createElement('a');

        action.href = data.update_url;
        action.className = 'hubgo-check-updates-action';
        action.textContent = i18n.update_now || '';

        node.appendChild( document.createTextNode(' ') );
        node.appendChild( action );
    }

    /**
     * Run the check for one link.
     *
     * @param {HTMLElement} link Clicked link.
     * @return {void}
     */
    function runCheck( link ) {
        if ( 'true' === link.getAttribute('aria-busy') ) {
            return;
        }

        var status = link.parentNode.querySelector('.hubgo-check-updates-status');

        if ( ! status ) {
            return;
        }

        link.setAttribute( 'aria-busy', 'true' );
        link.classList.add('is-busy');

        resetStatus( status, 'loading' );
        status.appendChild( document.createTextNode( i18n.checking || '' ) );

        requestCheck().then( function( data ) {
            renderResult( status, data );
        } ).catch( function( error ) {
            resetStatus( status, 'error' );
            status.appendChild( document.createTextNode( error.message || i18n.error || '' ) );
        } ).then( function() {
            link.removeAttribute('aria-busy');
            link.classList.remove('is-busy');
        } );
    }

    // Delegated: the plugins list is re-rendered by core's own AJAX actions
    // (activate, enable auto-updates), which would drop a direct listener.
    document.addEventListener( 'click', function( event ) {
        var target = event.target;

        if ( ! target || ! target.closest ) {
            return;
        }

        var link = target.closest('[data-hubgo-check-updates]');

        if ( ! link ) {
            return;
        }

        event.preventDefault();
        runCheck( link );
    } );
} )();
