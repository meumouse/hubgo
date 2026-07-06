import { __ as wpTranslate, sprintf as wpSprintf } from '@wordpress/i18n';

export const TEXT_DOMAIN = 'hubgo';

/**
 * Translate a string using the WordPress i18n runtime.
 *
 * @param {string} text
 * @return {string}
 */
export function __( text ) {
    return wpTranslate( text, TEXT_DOMAIN );
}

export const sprintf = wpSprintf;
