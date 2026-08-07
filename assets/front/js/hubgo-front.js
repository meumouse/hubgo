/**
 * HubGo storefront shipping calculator (API-first, vanilla JS).
 *
 * Consumes POST {rest_url}/shipping/calculate and renders the rates table
 * client-side. No jQuery, no admin-ajax.
 *
 * @since 3.0.0
 * @package HubGo
 * @author MeuMouse.com
 */
( function() {
    'use strict';

    var params = window.hubgo_front_params || {};
    var i18n = params.i18n || {};

    var COOKIE_NAME = 'savedCep';
    var COOKIE_DAYS = 30;
    var DEBOUNCE = 500;

    var els = {};

    function qs( selector, ctx ) {
        return ( ctx || document ).querySelector( selector );
    }

    function setCookie( name, value, days ) {
        var expires = '';

        if ( days ) {
            var date = new Date();
            date.setTime( date.getTime() + ( days * 24 * 60 * 60 * 1000 ) );
            expires = '; expires=' + date.toUTCString();
        }

        document.cookie = name + '=' + ( value || '' ) + expires + '; path=/';
    }

    function getCookie( name ) {
        var match = document.cookie.match( new RegExp( '(?:^|; )' + name + '=([^;]*)' ) );

        return match ? decodeURIComponent( match[1] ) : null;
    }

    function debounce( fn, wait ) {
        var timeout;

        return function() {
            var ctx = this;
            var args = arguments;
            clearTimeout( timeout );
            timeout = setTimeout( function() {
                fn.apply( ctx, args );
            }, wait );
        };
    }

    function isAutoEnabled() {
        return params.auto_shipping === 'yes';
    }

    function getQuantity() {
        var qty = qs( '.quantity input.qty' );

        return qty ? ( parseInt( qty.value, 10 ) || 1 ) : 1;
    }

    function detectProduct() {
        var variation = qs( 'input[name="variation_id"]' );
        var addToCart = qs( '*[name="add-to-cart"]' );
        var productId = qs( 'input[name="product_id"]' );

        if ( variation && parseInt( variation.value, 10 ) > 0 ) {
            return { product: 0, variation_id: parseInt( variation.value, 10 ) };
        }

        if ( addToCart && parseInt( addToCart.value, 10 ) > 0 ) {
            return { product: parseInt( addToCart.value, 10 ), variation_id: 0 };
        }

        if ( productId && parseInt( productId.value, 10 ) > 0 ) {
            return { product: parseInt( productId.value, 10 ), variation_id: 0 };
        }

        if ( params.current_product_id && parseInt( params.current_product_id, 10 ) > 0 ) {
            return { product: parseInt( params.current_product_id, 10 ), variation_id: 0 };
        }

        var bodyMatch = ( document.body.className || '' ).match( /\bpostid-(\d+)\b/ );

        if ( bodyMatch ) {
            return { product: parseInt( bodyMatch[1], 10 ), variation_id: 0 };
        }

        return null;
    }

    function escapeHtml( value ) {
        var div = document.createElement( 'div' );
        div.textContent = value == null ? '' : String( value );

        return div.innerHTML;
    }

    function renderMessage( message ) {
        els.response.innerHTML = '<div class="woocommerce-message woocommerce-error">' + escapeHtml( message ) + '</div>';
    }

    function renderRates( rates ) {
        if ( ! rates || ! rates.length ) {
            renderMessage( i18n.no_shipping || 'Nenhuma forma de entrega disponível.' );
            return;
        }

        var html = '<table cellspacing="0" class="hubgo-table-shipping-methods"><tbody>';

        if ( i18n.header_shipping || i18n.header_value ) {
            html += '<tr class="hubgo-shipping-header"><th>' + escapeHtml( i18n.header_shipping || '' ) + '</th><th>' + escapeHtml( i18n.header_value || '' ) + '</th></tr>';
        }

        rates.forEach( function( rate ) {
            html += '<tr class="hubgo-shipping-method"><td>' + escapeHtml( rate.label ) + '</td><td>' + escapeHtml( rate.cost_formatted ) + '</td></tr>';
        } );

        if ( i18n.bottom_note ) {
            html += '<tr class="hubgo-shipping-bottom"><td colspan="2"><span>' + escapeHtml( i18n.bottom_note ) + '</span></td></tr>';
        }

        html += '</tbody></table>';

        els.response.innerHTML = html;

        document.dispatchEvent( new CustomEvent( 'hubgo:shipping_calculated', { detail: { rates: rates } } ) );
    }

    function calculate() {
        var formatted = els.postcode.value || '';
        var raw = formatted.replace( /\D/g, '' );

        if ( raw.length < 3 ) {
            els.postcode.focus();
            return;
        }

        var detected = detectProduct();

        if ( ! detected ) {
            renderMessage( i18n.without_selected_variation || i18n.error || 'Selecione uma variação.' );
            return;
        }

        var originalHtml = els.button.innerHTML;
        els.button.style.width = els.button.offsetWidth + 'px';
        els.button.innerHTML = '<span class="hubgo-button-loader"></span>';
        els.response.innerHTML = '';

        fetch( params.rest_url + '/shipping/calculate', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': params.nonce || '',
            },
            body: JSON.stringify( {
                product: detected.product,
                variation_id: detected.variation_id,
                qty: getQuantity(),
                postcode: formatted,
            } ),
        } )
            .then( function( response ) {
                return response.json();
            } )
            .then( function( data ) {
                els.button.innerHTML = originalHtml;

                // The endpoint answers with status 'error' for an unusable
                // postcode, which must not read as "no delivery available".
                if ( data && 'error' === data.status ) {
                    renderMessage( data.message || i18n.error || '' );
                    document.dispatchEvent( new CustomEvent( 'hubgo:shipping_error' ) );

                    return;
                }

                renderRates( data && data.rates ? data.rates : [] );
            } )
            .catch( function() {
                els.button.innerHTML = originalHtml;
                renderMessage( i18n.error || 'Erro ao calcular o frete. Tente novamente.' );
                document.dispatchEvent( new CustomEvent( 'hubgo:shipping_error' ) );
            } );
    }

    function initMaskAndCookie() {
        var saved = getCookie( COOKIE_NAME );

        if ( saved ) {
            els.postcode.value = saved.replace( /^(\d{5})(\d{3})$/, '$1-$2' );
        }

        els.postcode.addEventListener( 'input', function( e ) {
            var value = e.target.value.replace( /\D/g, '' );
            e.target.value = value.length > 5 ? value.substring( 0, 5 ) + '-' + value.substring( 5, 8 ) : value;
            setCookie( COOKIE_NAME, value, COOKIE_DAYS );
        } );
    }

    function bind() {
        els.button.addEventListener( 'click', function( e ) {
            e.preventDefault();
            calculate();
        } );

        [ els.postcode, qs( 'form.cart' ) ].forEach( function( node ) {
            if ( ! node ) {
                return;
            }

            node.addEventListener( 'keypress', function( e ) {
                if ( ( e.keyCode || e.which ) === 13 ) {
                    e.preventDefault();
                    calculate();
                }
            } );
        } );

        if ( isAutoEnabled() ) {
            els.postcode.addEventListener( 'keyup', debounce( calculate, DEBOUNCE ) );
        }
    }

    function init() {
        var container = qs( '#hubgo-shipping-calc' );

        if ( ! container ) {
            return;
        }

        els.container = container;
        els.button = qs( '#hubgo-shipping-calc-button' );
        els.postcode = qs( '#hubgo-postcode' );
        els.response = qs( '#hubgo-response' );

        if ( ! els.button || ! els.postcode || ! els.response ) {
            return;
        }

        initMaskAndCookie();
        bind();

        window.addEventListener( 'load', function() {
            if ( isAutoEnabled() && els.postcode.value !== '' ) {
                calculate();
            }
        } );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
} )();
