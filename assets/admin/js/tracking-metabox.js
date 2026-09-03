/**
 * HubGo order tracking metabox (API-first, vanilla JS).
 *
 * Talks to the hubgo/v1 REST API to list, create and delete tracking items.
 *
 * @since 3.0.0
 * @package HubGo
 * @author MeuMouse.com
 */
( function() {
    'use strict';

    var params = window.hubgoOrderTrackingParams || {};
    var i18n = params.i18n || {};
    var providers = params.providers || {};

    var root;
    var orderId;

    function qs( selector, ctx ) {
        return ( ctx || document ).querySelector( selector );
    }

    function apiFetch( path, options ) {
        options = options || {};
        options.credentials = 'same-origin';
        options.headers = Object.assign( {
            'Content-Type': 'application/json',
            'X-WP-Nonce': params.nonce || '',
        }, options.headers || {} );

        return fetch( params.rest_url + path, options ).then( function( response ) {
            return response.json().catch( function() {
                return null;
            } ).then( function( data ) {
                if ( ! response.ok ) {
                    throw new Error( data && data.message ? data.message : 'HTTP ' + response.status );
                }

                return data;
            } );
        } );
    }

    /**
     * Whether an element is currently hidden, stylesheet rules included.
     *
     * The metabox stylesheet hides the form and the link preview with
     * `display: none`, so the inline style alone does not describe what the
     * user sees: an element carrying no inline value is still hidden by the
     * rule.
     */
    function isHidden( el ) {
        return window.getComputedStyle( el ).display === 'none';
    }

    /**
     * Show or hide an element with an explicit inline value.
     *
     * Clearing the inline style instead hands the element back to the
     * stylesheet rule above and leaves it hidden, which is what made the
     * "Add tracking code" button look inert.
     */
    function setVisible( el, visible ) {
        el.style.display = visible ? 'block' : 'none';
    }

    function escapeHtml( value ) {
        var div = document.createElement( 'div' );
        div.textContent = value == null ? '' : String( value );

        return div.innerHTML;
    }

    function itemMarkup( item ) {
        var id = item.tracking_id || '';
        var provider = item.provider_label || '';
        var number = item.tracking_number || '';
        var link = item.tracking_link || '';
        var date = item.date_label || '';

        var linkHtml = link
            ? ' - <a href="' + escapeHtml( link ) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml( i18n.track || 'Track' ) + '</a>'
            : '';

        return '' +
            '<div class="tracking-item" id="tracking-item-' + escapeHtml( id ) + '">' +
                '<p class="tracking-content"><strong>' + escapeHtml( provider ) + '</strong>' + linkHtml +
                    '<br><em>' + escapeHtml( number ) + '</em></p>' +
                '<p class="meta">' + escapeHtml( date ) +
                    ' <a href="#" class="delete-tracking" rel="' + escapeHtml( id ) + '">' + escapeHtml( i18n.remove || 'Remove' ) + '</a></p>' +
            '</div>';
    }

    function renderItems( items ) {
        var container = qs( '#hubgo-tracking-items', root );

        if ( ! container ) {
            return;
        }

        container.innerHTML = ( items || [] ).map( itemMarkup ).join( '' );
        bindDeleteLinks();
    }

    function resetForm() {
        [ '#hubgo_tracking_number', '#hubgo_custom_tracking_provider', '#hubgo_custom_tracking_link' ].forEach( function( sel ) {
            var input = qs( sel, root );
            if ( input ) input.value = '';
        } );
    }

    function saveItem() {
        var providerSelect = qs( '#hubgo_tracking_provider', root );
        var number = qs( '#hubgo_tracking_number', root );

        if ( ! number || number.value.trim() === '' ) {
            if ( number ) number.focus();
            return;
        }

        var payload = {
            order_id: orderId,
            tracking_number: number.value.trim(),
            provider: providerSelect ? providerSelect.value : '',
            custom_provider: ( qs( '#hubgo_custom_tracking_provider', root ) || {} ).value || '',
            custom_url: ( qs( '#hubgo_custom_tracking_link', root ) || {} ).value || '',
            ship_date: ( qs( '#hubgo_date_shipped', root ) || {} ).value || '',
        };

        var button = qs( '.button-save-form', root );

        if ( button ) button.disabled = true;

        apiFetch( '/tracking', { method: 'POST', body: JSON.stringify( payload ) } )
            .then( function( data ) {
                renderItems( data && data.items ? data.items : [] );
                resetForm();
            } )
            .catch( function() {
                window.alert( i18n.save_error || 'Could not save the tracking code.' );
            } )
            .finally( function() {
                if ( button ) button.disabled = false;
            } );
    }

    function deleteItem( trackingId ) {
        if ( ! window.confirm( i18n.confirm_delete || 'Remove this tracking code?' ) ) {
            return;
        }

        apiFetch( '/tracking/' + encodeURIComponent( trackingId ) + '?order_id=' + encodeURIComponent( orderId ), {
            method: 'DELETE',
        } )
            .then( function( data ) {
                renderItems( data && data.items ? data.items : [] );
            } )
            .catch( function() {
                window.alert( i18n.delete_error || 'Could not remove the tracking code.' );
            } );
    }

    function bindDeleteLinks() {
        root.querySelectorAll( '.delete-tracking' ).forEach( function( link ) {
            link.addEventListener( 'click', function( e ) {
                e.preventDefault();
                deleteItem( link.getAttribute( 'rel' ) );
            } );
        } );
    }

    function updatePreview() {
        var providerSelect = qs( '#hubgo_tracking_provider', root );
        var number = qs( '#hubgo_tracking_number', root );
        var previewWrap = qs( '.preview_tracking_link', root );
        var customWrap = qs( '.custom_tracking_provider_field', root );

        if ( customWrap && providerSelect ) {
            setVisible( customWrap, providerSelect.value === '' );
        }

        if ( ! previewWrap || ! providerSelect ) {
            return;
        }

        var anchor = previewWrap.querySelector( 'a' );
        var format = providers[ providerSelect.value ] ? decodeURIComponent( providers[ providerSelect.value ] ) : '';
        var code = number ? number.value.trim() : '';

        var hasPreview = !! ( anchor && format && code );

        if ( hasPreview ) {
            anchor.href = format.replace( /%s|\{code\}|%code%/gi, code );
        }

        setVisible( previewWrap, hasPreview );
    }

    function bind() {
        var showButton = qs( '.button-show-form', root );
        var form = qs( '#hubgo-shipment-tracking-form', root );

        if ( showButton && form ) {
            showButton.addEventListener( 'click', function() {
                setVisible( form, isHidden( form ) );
            } );
        }

        var saveButton = qs( '.button-save-form', root );
        if ( saveButton ) saveButton.addEventListener( 'click', saveItem );

        var providerSelect = qs( '#hubgo_tracking_provider', root );
        if ( providerSelect ) providerSelect.addEventListener( 'change', updatePreview );

        var number = qs( '#hubgo_tracking_number', root );
        if ( number ) number.addEventListener( 'input', updatePreview );

        bindDeleteLinks();
        updatePreview();
    }

    function init() {
        root = qs( '#hubgo-order-tracking-inner' );

        if ( ! root ) {
            return;
        }

        orderId = root.getAttribute( 'data-order-id' ) || params.order_id || 0;
        bind();
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
} )();
