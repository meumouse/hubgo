/**
 * format.js
 *
 * Presentation helpers for the storefront calculator.
 *
 * Prices and delivery headlines are deliberately NOT formatted here: the REST
 * response already carries `cost_formatted` and `delivery.headline`, built with
 * the store's currency settings and the WordPress locale. Reformatting them in
 * the browser would reintroduce every rounding and locale bug WooCommerce
 * already solved.
 *
 * @since 3.0.0
 */

/**
 * Mask a Brazilian postcode as the shopper types (`01310100` -> `01310-100`).
 *
 * @param {string} value Raw input.
 * @return {string}
 */
export function formatCep( value ) {
    const digits = String( value || '' ).replace( /\D/g, '' ).slice( 0, 8 );

    return digits.length > 5 ? `${ digits.slice( 0, 5 ) }-${ digits.slice( 5 ) }` : digits;
}

/**
 * Reduce a postcode to its digits.
 *
 * @param {string} value Raw input.
 * @return {string}
 */
export function cepDigits( value ) {
    return String( value || '' ).replace( /\D/g, '' );
}

/**
 * Whether a value holds a complete postcode.
 *
 * @param {string} value Raw input.
 * @return {boolean}
 */
export function isCompleteCep( value ) {
    return cepDigits( value ).length === 8;
}

/**
 * Fill the placeholders of a template string.
 *
 * The free-shipping badge is authored by the store owner as
 * "Free shipping over %s", so the placeholder has to survive translation and
 * be filled at render time.
 *
 * Both sprintf spellings are accepted: `%s` consumes the arguments in order,
 * and `%1$s` picks one by position. The numbered form is what a translator
 * needs the moment a string carries two values — "to %1$s (postcode %2$s)"
 * reads with the street last in some languages, and only a numbered
 * placeholder survives that reordering.
 *
 * @param {string} template Template string.
 * @param {...string} values Replacements, in argument order.
 * @return {string}
 */
export function interpolate( template, ...values ) {
    let cursor = 0;

    // One pass over both spellings. Two passes would let a value that happens
    // to contain "%s" — a street name can be anything — be substituted again by
    // the second one.
    return String( template || '' ).replace( /%(?:(\d+)\$)?s/g, ( match, position ) => {
        const index = position ? Number( position ) - 1 : cursor++;
        const value = values[ index ];

        return value === undefined ? match : String( value );
    } );
}

/**
 * Pick the row the compact card should headline.
 *
 * Preference order: the shopper's own choice when it is still on offer, then
 * the cheapest option — never an arbitrary first row, which would make the card
 * advertise an expensive method for no reason.
 *
 * @param {Array<object>} rates Normalized rate rows.
 * @param {string} preferredId Rate id the shopper picked.
 * @return {object|null}
 */
export function resolvePreferredRate( rates, preferredId ) {
    if ( ! Array.isArray( rates ) || rates.length === 0 ) {
        return null;
    }

    if ( preferredId ) {
        const chosen = rates.find( ( rate ) => rate.id === preferredId );

        if ( chosen ) {
            return chosen;
        }
    }

    return rates.reduce(
        ( best, rate ) => ( ! best || Number( rate.cost ) < Number( best.cost ) ? rate : best ),
        null,
    );
}
