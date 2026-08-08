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
 * Replace the first `%s` in a template.
 *
 * The free-shipping badge is authored by the store owner as
 * "Frete grátis acima de %s", so the placeholder has to survive translation and
 * be filled at render time.
 *
 * @param {string} template Template string.
 * @param {string} value Replacement.
 * @return {string}
 */
export function interpolate( template, value ) {
    const text = String( template || '' );

    return text.includes( '%s' ) ? text.replace( '%s', value ) : text;
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
