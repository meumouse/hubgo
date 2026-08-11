/**
 * useShippingQuote.js
 *
 * State container for one calculator instance: the quote, the shopper's saved
 * postcode and their preferred delivery method.
 *
 * The postcode and the preference share a single cookie, because both have to
 * be readable by PHP at the cart and the checkout — see `Core\Shipping_Preference`.
 * The cookie is written here and never read by anything else in the browser
 * except this module, so its shape (`{ r, p, t }`) stays an implementation
 * detail of the pair.
 *
 * @since 3.0.0
 */
import { computed, ref, shallowRef } from 'vue';
import { storefrontApi } from './api';
import { deleteCookie, readCookie, writeCookie } from './cookies';
import { cepDigits, isCompleteCep, resolvePreferredRate } from './format';
import { getPreferenceConfig } from './params';
import { __ } from '../utils/i18n';

/**
 * Read the persisted postcode/preference pair.
 *
 * @return {{ postcode: string, rateId: string }}
 */
function readPreference() {
    const { name } = getPreferenceConfig();
    const raw = readCookie( name );

    if ( ! raw ) {
        return { postcode: '', rateId: '' };
    }

    try {
        const data = JSON.parse( raw );

        return {
            postcode: cepDigits( data && data.p ),
            rateId: String( ( data && data.r ) || '' ),
        };
    } catch ( error ) {
        return { postcode: '', rateId: '' };
    }
}

/**
 * Persist the postcode/preference pair.
 *
 * @param {string} postcode Eight-digit postcode.
 * @param {string} rateId WooCommerce rate id, or an empty string.
 * @return {void}
 */
function persistPreference( postcode, rateId ) {
    const { name, days, secure } = getPreferenceConfig();

    writeCookie( name, JSON.stringify( {
        r: rateId || '',
        p: postcode || '',
        t: Math.floor( Date.now() / 1000 ),
    } ), days, secure );
}

/**
 * Resolve which product line to quote.
 *
 * The widget config carries the product it was rendered for, but a variable
 * product only reveals the chosen variation once the shopper picks the
 * attributes — so the live DOM wins over the static config when it has an
 * answer.
 *
 * @param {object} config Widget config.
 * @return {{ product: number, variation_id: number }}
 */
function detectProduct( config ) {
    const variationInput = document.querySelector( 'input[name="variation_id"]' );
    const variationId = variationInput ? parseInt( variationInput.value, 10 ) : 0;

    if ( variationId > 0 ) {
        return { product: Number( config.product ) || 0, variation_id: variationId };
    }

    const addToCart = document.querySelector( '*[name="add-to-cart"]' );
    const fromForm = addToCart ? parseInt( addToCart.value, 10 ) : 0;

    return {
        product: Number( config.product ) || fromForm || 0,
        variation_id: 0,
    };
}

/**
 * Resolve the quantity currently selected on the page.
 *
 * @param {object} config Widget config.
 * @return {number}
 */
function detectQuantity( config ) {
    const input = document.querySelector( '.quantity input.qty' );
    const fromDom = input ? parseInt( input.value, 10 ) : 0;

    return Math.max( 1, fromDom || Number( config.quantity ) || 1 );
}

/**
 * Build the reactive state for one calculator instance.
 *
 * @param {object} config Widget config parsed from the mount node.
 * @return {object}
 */
export function useShippingQuote( config ) {
    const stored = readPreference();

    const postcode = ref( stored.postcode );
    const preferredId = ref( stored.rateId );
    const rates = shallowRef( [] );
    const freeShipping = ref( {} );
    const context = ref( {} );
    const loading = ref( false );
    const error = ref( '' );
    const hasQuoted = ref( false );

    let pending = null;

    const preferredRate = computed( () => resolvePreferredRate( rates.value, preferredId.value ) );

    /**
     * The destination address the server resolved for the current postcode.
     *
     * Always an object with every key present, so a consumer can read
     * `address.summary` without guarding: the lookup is optional (it needs an
     * address integration, and it gives up rather than delaying a quote), and a
     * quote that predates it carries no `address` block at all.
     */
    const address = computed( () => ( {
        street: '',
        neighborhood: '',
        city: '',
        state: '',
        summary: '',
        formatted: '',
        ...( context.value.address || {} ),
    } ) );

    const isPreferenceEnabled = computed(
        () => Boolean( config.features?.preference ) && Boolean( getPreferenceConfig().enabled ),
    );

    /**
     * Quote shipping for a postcode and persist it.
     *
     * @param {string} value Raw postcode (masked or not).
     * @return {Promise<void>}
     */
    async function calculate( value ) {
        const digits = cepDigits( value );

        if ( ! isCompleteCep( digits ) ) {
            error.value = __( 'Enter a valid postcode' );

            return;
        }

        // A slow response for an older postcode must never overwrite a newer
        // one, which is easy to trigger by editing the field mid-request.
        if ( pending ) {
            pending.abort();
        }

        // Held locally as well as in `pending`: by the time this call reaches
        // its `finally`, a newer call may already own `pending`, and clearing
        // that one would strand the request actually in flight.
        const controller = new AbortController();
        pending = controller;

        postcode.value = digits;
        loading.value = true;
        error.value = '';

        persistPreference( digits, isPreferenceEnabled.value ? preferredId.value : '' );

        const detected = detectProduct( config );

        try {
            const payload = await storefrontApi.calculate( {
                product: detected.product,
                variation_id: detected.variation_id,
                qty: detectQuantity( config ),
                postcode: digits,
            }, controller.signal );

            rates.value = Array.isArray( payload.rates ) ? payload.rates : [];
            freeShipping.value = payload.free_shipping || {};
            context.value = payload.context || {};
            hasQuoted.value = true;

            // The server re-resolves the preference against the rates this zone
            // actually offers, applying the same fallback the checkout will.
            // Trusting it here is what keeps the card and the checkout aligned.
            if ( isPreferenceEnabled.value ) {
                preferredId.value = String( context.value.preferred_id || '' );
            }

            emitEvent( 'hubgo:shipping_calculated', { rates: rates.value, postcode: digits } );
        } catch ( requestError ) {
            if ( requestError.name === 'AbortError' ) {
                return;
            }

            rates.value = [];
            // The previous destination has to go with the rates it described,
            // or a failed re-quote leaves the old street next to the new
            // postcode.
            context.value = {};
            hasQuoted.value = true;
            error.value = requestError.message || __( 'Could not calculate shipping. Please try again.' );

            emitEvent( 'hubgo:shipping_error', { message: error.value } );
        } finally {
            // Only the call that still owns the slot may release it. An aborted
            // call reaching here has already been superseded, and its spinner
            // belongs to the request that replaced it.
            if ( pending === controller ) {
                loading.value = false;
                pending = null;
            }
        }
    }

    /**
     * Record the shopper's preferred method.
     *
     * @param {string} rateId WooCommerce rate id.
     * @return {void}
     */
    function selectMethod( rateId ) {
        if ( ! isPreferenceEnabled.value ) {
            return;
        }

        preferredId.value = String( rateId || '' );
        persistPreference( postcode.value, preferredId.value );

        emitEvent( 'hubgo:shipping_preference_changed', { rateId: preferredId.value } );
    }

    /**
     * Forget the preferred method, keeping the postcode.
     *
     * @return {void}
     */
    function clearPreference() {
        preferredId.value = '';

        if ( postcode.value ) {
            persistPreference( postcode.value, '' );
        } else {
            deleteCookie( getPreferenceConfig().name );
        }

        emitEvent( 'hubgo:shipping_preference_changed', { rateId: '' } );
    }

    /**
     * Reset the quote so the empty state comes back.
     *
     * @return {void}
     */
    function resetPostcode() {
        postcode.value = '';
        rates.value = [];
        freeShipping.value = {};
        context.value = {};
        hasQuoted.value = false;
        error.value = '';
    }

    /**
     * Dispatch a DOM event so themes and third parties can react.
     *
     * These names predate 3.0.0 and are part of the public contract.
     *
     * @param {string} name Event name.
     * @param {object} detail Event detail.
     * @return {void}
     */
    function emitEvent( name, detail ) {
        if ( typeof window !== 'undefined' && typeof window.CustomEvent === 'function' ) {
            document.dispatchEvent( new window.CustomEvent( name, { detail } ) );
        }
    }

    return {
        postcode,
        preferredId,
        preferredRate,
        rates,
        freeShipping,
        context,
        address,
        loading,
        error,
        hasQuoted,
        isPreferenceEnabled,
        calculate,
        selectMethod,
        clearPreference,
        resetPostcode,
    };
}
