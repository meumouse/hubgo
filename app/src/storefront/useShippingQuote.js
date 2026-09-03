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
 * @version 3.1.0
 */
import { computed, ref, shallowRef } from 'vue';
import { storefrontApi } from './api';
import { deleteCookie, readCookie, writeCookie } from './cookies';
import { cepDigits, isCompleteCep, resolvePreferredRate } from './format';
import { getPreferenceConfig } from './params';
import { lineSignature, readProductLine, watchProductLine } from './productLine';
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
    const stale = ref( false );

    let pending = null;

    // The line the rates on screen were quoted for. Compared against the live
    // one to decide whether a change on the page is worth a new request.
    let quotedLine = '';

    let unwatchLine = null;

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

        // Read once and quote exactly what was read: the shopper can change
        // the quantity while the request is in flight, and remembering the line
        // the answer belongs to is what lets the watcher notice and re-quote.
        const line = readProductLine( config );

        try {
            const payload = await storefrontApi.calculate( {
                product: line.product,
                variation_id: line.variation_id,
                qty: line.qty,
                postcode: digits,
            }, controller.signal );

            quotedLine = lineSignature( line );
            stale.value = false;

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
            stale.value = false;
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
     * Re-quote when the line on the page no longer matches the one on screen.
     *
     * The guard is what keeps this cheap: the watcher fires on every quantity
     * keystroke and on every variation event, and most of them leave the line
     * exactly where it was.
     *
     * @return {void}
     */
    function refresh() {
        if ( ! isCompleteCep( postcode.value ) ) {
            return;
        }

        if ( lineSignature( readProductLine( config ) ) === quotedLine ) {
            return;
        }

        // The price on screen is now known to be wrong. Saying so before the
        // request goes out is what stops the shopper from reading a freight for
        // one unit while they are buying forty.
        stale.value = true;

        calculate( postcode.value );
    }


    /**
     * Follow the quantity field and the variation picker.
     *
     * @return {Function} Unsubscribe.
     */
    function watchLine() {
        if ( ! unwatchLine ) {
            unwatchLine = watchProductLine( config, refresh );
        }

        return unwatchLine;
    }


    /**
     * Stop following them.
     *
     * @return {void}
     */
    function unwatchProductLine() {
        if ( unwatchLine ) {
            unwatchLine();
            unwatchLine = null;
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
        stale.value = false;
        error.value = '';
        quotedLine = '';
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
        stale,
        isPreferenceEnabled,
        calculate,
        refresh,
        watchLine,
        unwatchProductLine,
        selectMethod,
        clearPreference,
        resetPostcode,
    };
}
