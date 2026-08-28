export const TEXT_DOMAIN = 'hubgo';

/**
 * Resolve WordPress' i18n runtime (registered as the `wp-i18n` script handle and
 * exposed as window.wp.i18n). We read it lazily so translations injected by
 * wp_set_script_translations() are applied at call time. Not bundling
 * @wordpress/i18n keeps the WP-provided locale data authoritative.
 *
 * @return {object|null}
 */
function wpI18n() {
    return ( typeof window !== 'undefined' && window.wp && window.wp.i18n ) ? window.wp.i18n : null;
}

/**
 * Translate a string using the WordPress i18n runtime (falls back to the source).
 *
 * @param {string} text
 * @return {string}
 */
export function __( text ) {
    const i18n = wpI18n();

    return i18n ? i18n.__( text, TEXT_DOMAIN ) : text;
}

/**
 * sprintf proxy backed by wp.i18n (falls back to returning the format string).
 *
 * @param {string} format
 * @param {...*} args
 * @return {string}
 */
export function sprintf( format, ...args ) {
    const i18n = wpI18n();

    return i18n && i18n.sprintf ? i18n.sprintf( format, ...args ) : format;
}
