/**
 * cookies.js
 *
 * Cookie helpers for the storefront calculator.
 *
 * The shopper's preferred method and last postcode live in a cookie rather than
 * localStorage because PHP has to read them back at the cart and the checkout —
 * see `Core\Shipping_Preference`. localStorage is invisible to the server, which
 * is why the reference React implementation could not carry the choice past the
 * product page.
 *
 * @since 3.0.0
 */

/**
 * Read a cookie value, decoded.
 *
 * @param {string} name Cookie name.
 * @return {string|null} Value, or null when absent.
 */
export function readCookie( name ) {
    if ( typeof document === 'undefined' ) {
        return null;
    }

    const match = document.cookie.match( new RegExp( '(?:^|;\\s*)' + escapeName( name ) + '=([^;]*)' ) );

    if ( ! match ) {
        return null;
    }

    try {
        return decodeURIComponent( match[ 1 ] );
    } catch ( error ) {
        return null;
    }
}

/**
 * Write a cookie scoped to the whole site.
 *
 * `SameSite=Lax` keeps it off cross-site requests while still surviving the
 * top-level navigation from the product page to the checkout, which is the only
 * journey that matters here.
 *
 * @param {string} name Cookie name.
 * @param {string} value Raw value (encoded here).
 * @param {number} days Lifetime in days.
 * @param {boolean} secure Whether to add the Secure attribute.
 * @return {void}
 */
export function writeCookie( name, value, days, secure ) {
    if ( typeof document === 'undefined' ) {
        return;
    }

    const expires = new Date( Date.now() + ( Math.max( 1, days ) * 86400000 ) ).toUTCString();
    const parts = [
        `${ name }=${ encodeURIComponent( value ) }`,
        `expires=${ expires }`,
        'path=/',
        'SameSite=Lax',
    ];

    if ( secure ) {
        parts.push( 'Secure' );
    }

    document.cookie = parts.join( '; ' );
}

/**
 * Expire a cookie.
 *
 * @param {string} name Cookie name.
 * @return {void}
 */
export function deleteCookie( name ) {
    if ( typeof document === 'undefined' ) {
        return;
    }

    document.cookie = `${ name }=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Lax`;
}

/**
 * Escape a cookie name for use inside a RegExp.
 *
 * @param {string} name Cookie name.
 * @return {string}
 */
function escapeName( name ) {
    return String( name ).replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
}
