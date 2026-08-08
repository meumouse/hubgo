/**
 * params.js
 *
 * Accessors for the storefront bootstrap injected by `wp_localize_script()` as
 * `hubgo_front_params`. Everything the calculator needs that is not per-widget
 * lives here: the REST root, the address lookup descriptor and the preference
 * cookie contract PHP reads back at checkout.
 *
 * @since 3.0.0
 */

/**
 * Read the localized params, tolerating a missing bootstrap.
 *
 * @return {object}
 */
export function getParams() {
    return ( typeof window !== 'undefined' && window.hubgo_front_params ) ? window.hubgo_front_params : {};
}

/**
 * REST root for the hubgo/v1 namespace, without a trailing slash.
 *
 * @return {string}
 */
export function getRestUrl() {
    return String( getParams().rest_url || '' ).replace( /\/$/, '' );
}

/**
 * WordPress REST nonce, when one was issued.
 *
 * @return {string}
 */
export function getNonce() {
    return String( getParams().nonce || '' );
}

/**
 * Address lookup descriptor: { enabled, provider, mode, states }.
 *
 * @return {object}
 */
export function getAddressLookup() {
    return getParams().address_lookup || { enabled: false, provider: '', mode: '', states: [] };
}

/**
 * Preferred-method cookie contract: { name, days, secure, enabled }.
 *
 * @return {object}
 */
export function getPreferenceConfig() {
    return getParams().preference || { name: 'hubgo_ship_pref', days: 30, secure: false, enabled: false };
}

/**
 * Fallback strings for states the per-widget texts do not cover.
 *
 * @return {object}
 */
export function getStrings() {
    return getParams().i18n || {};
}
