/**
 * productLine.js
 *
 * Resolves — and watches — the product line the calculator is quoting: the
 * variation the shopper picked and the quantity currently in the add-to-cart
 * form.
 *
 * A quote is only as good as the line it was built from. Quoting the page's
 * default line and never looking again is what makes the card advertise the
 * freight for one unit next to a cart holding forty, so the reads live here and
 * every surface that can change them is subscribed to.
 *
 * Lookups are scoped to the add-to-cart form that actually sells the product
 * the widget was rendered for. A document-wide read would let a shortcode
 * quoting product A follow the quantity field of product B — and on a page
 * without a standard form (an out-of-stock product, an external product) the
 * scope falls back to the document, which is what the widget config is for.
 *
 * @since 3.1.0
 */

/**
 * WooCommerce's add-to-cart form, simple and variable alike.
 *
 * @type {string}
 */
const FORM_SELECTOR = 'form.cart';

/**
 * Quantity input inside that form. `input.qty` is WooCommerce's own class and
 * survives most theme overrides; the name attribute covers the rest.
 *
 * @type {string}
 */
const QTY_SELECTOR = 'input.qty, input[name="quantity"]';

/**
 * How long to wait before re-quoting a changed line.
 *
 * Long enough that typing "40" over a "1" is one request rather than two, short
 * enough that the card catches up before the shopper reads it.
 *
 * @type {number}
 */
const DEBOUNCE_MS = 600;

/**
 * WooCommerce's variation events, fired by jQuery on the variations form.
 *
 * `found_variation` carries the pick; `reset_data` and `hide_variation` are the
 * two ways back to "no variation chosen", and both have to reset the quote or
 * the card keeps pricing a variation the shopper has abandoned.
 *
 * @type {string}
 */
const VARIATION_EVENTS = 'found_variation reset_data hide_variation';

/**
 * Read the product a form sells.
 *
 * Variable products publish it on the form itself; simple ones only carry it on
 * the submit button.
 *
 * @param {HTMLElement} form Add-to-cart form.
 * @return {number}
 */
function readFormProduct( form ) {
    const fromData = parseInt( form.getAttribute( 'data-product_id' ), 10 );

    if ( fromData > 0 ) {
        return fromData;
    }

    const addToCart = form.querySelector( '[name="add-to-cart"]' );

    return addToCart ? ( parseInt( addToCart.value, 10 ) || 0 ) : 0;
}

/**
 * Resolve the element the line should be read from.
 *
 * @param {object} config Widget config.
 * @return {ParentNode|null} The form, the document as a fallback, or `null`
 *                           when the page sells nothing this widget quotes.
 */
export function resolveScope( config ) {
    const forms = Array.from( document.querySelectorAll( FORM_SELECTOR ) );

    // No standard form on the page: keep reading document-wide, which is what
    // themes with custom add-to-cart markup rely on.
    if ( ! forms.length ) {
        return document;
    }

    const wanted = Number( config.product ) || 0;

    if ( ! wanted ) {
        return forms[0];
    }

    const matched = forms.find( ( form ) => readFormProduct( form ) === wanted );

    if ( matched ) {
        return matched;
    }

    // A single product page has exactly one thing to sell, so its only form is
    // the right one even when the theme publishes no id for it to be matched
    // by. Anywhere else, an unmatched widget quotes a product this page does
    // not sell and must not borrow another one's fields.
    const isSingle = document.body && document.body.classList.contains( 'single-product' );

    return ( isSingle && 1 === forms.length ) ? forms[0] : null;
}

/**
 * Read the line currently selected on the page.
 *
 * @param {object} config Widget config.
 * @return {{ product: number, variation_id: number, qty: number }}
 */
export function readProductLine( config ) {
    const scope = resolveScope( config );
    const fallbackQty = Math.max( 1, Number( config.quantity ) || 1 );

    if ( ! scope ) {
        return {
            product: Number( config.product ) || 0,
            variation_id: 0,
            qty: fallbackQty,
        };
    }

    const variationInput = scope.querySelector( 'input[name="variation_id"]' );
    const variationId = variationInput ? ( parseInt( variationInput.value, 10 ) || 0 ) : 0;

    const qtyInput = scope.querySelector( QTY_SELECTOR );
    const domQty = qtyInput ? parseInt( qtyInput.value, 10 ) : 0;

    const fromForm = ( scope === document )
        ? ( () => {
            const addToCart = document.querySelector( '[name="add-to-cart"]' );

            return addToCart ? ( parseInt( addToCart.value, 10 ) || 0 ) : 0;
        } )()
        : readFormProduct( scope );

    return {
        product: Number( config.product ) || fromForm || 0,
        variation_id: variationId > 0 ? variationId : 0,
        qty: Math.max( 1, domQty > 0 ? domQty : fallbackQty ),
    };
}

/**
 * Collapse a line into a comparable string.
 *
 * @param {object} line Line as returned by {@see readProductLine}.
 * @return {string}
 */
export function lineSignature( line ) {
    return `${ line.product }:${ line.variation_id }:${ line.qty }`;
}

/**
 * Call back whenever the selected line changes.
 *
 * Both event systems are wired on purpose. Native listeners catch a shopper
 * typing in the quantity field; jQuery ones catch everything WooCommerce and
 * the themes do through `$.fn.trigger()` — the +/- buttons and the whole
 * variation dance — which never reaches a native listener.
 *
 * @param {object} config Widget config.
 * @param {Function} onChange Receives the new line.
 * @return {Function} Unsubscribe.
 */
export function watchProductLine( config, onChange ) {
    let signature = lineSignature( readProductLine( config ) );
    let handle = null;

    /**
     * Re-read the line and report it when it actually moved.
     *
     * @return {void}
     */
    const check = () => {
        const line = readProductLine( config );
        const next = lineSignature( line );

        if ( next === signature ) {
            return;
        }

        signature = next;
        onChange( line );
    };

    /**
     * Debounce the check: a single keystroke in the quantity field fires
     * `input` and `change`, and picking a variation fires several events at
     * once.
     *
     * @return {void}
     */
    const schedule = () => {
        window.clearTimeout( handle );
        handle = window.setTimeout( check, DEBOUNCE_MS );
    };

    document.addEventListener( 'input', schedule, true );
    document.addEventListener( 'change', schedule, true );

    const $ = window.jQuery;

    if ( $ ) {
        $( document ).on( 'change.hubgoLine input.hubgoLine', QTY_SELECTOR, schedule );
        $( document ).on(
            VARIATION_EVENTS.split( ' ' ).map( ( name ) => `${ name }.hubgoLine` ).join( ' ' ),
            FORM_SELECTOR,
            schedule,
        );
    }

    return () => {
        window.clearTimeout( handle );

        document.removeEventListener( 'input', schedule, true );
        document.removeEventListener( 'change', schedule, true );

        if ( $ ) {
            $( document ).off( '.hubgoLine', QTY_SELECTOR, schedule );
            $( document ).off( '.hubgoLine', FORM_SELECTOR, schedule );
        }
    };
}
