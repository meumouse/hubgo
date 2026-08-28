/**
 * tokens.js
 *
 * Canonical list of the CSS custom properties the storefront calculator is
 * styled with. This is the contract three independent parties agree on:
 *
 *   - `styles/calculator.css` declares the default value of each one.
 *   - `Views\Calculator_Styles` (PHP) emits the store-wide overrides.
 *   - The Elementor widget emits the per-instance overrides.
 *
 * The list exists in JS for one reason: modals are teleported to <body>, which
 * puts them outside the element the properties are declared on, so their values
 * have to be copied across explicitly. Keep it in sync with
 * `Calculator_Styles::get_token_map()`.
 *
 * @since 3.0.0
 * @version 3.1.0
 */

export const CALCULATOR_TOKENS = [
    '--hubgo-calc-font-family',
    '--hubgo-calc-radius',
    '--hubgo-calc-gap',
    '--hubgo-calc-surface-bg',
    '--hubgo-calc-surface-border',
    '--hubgo-calc-surface-padding',
    '--hubgo-calc-surface-shadow',
    '--hubgo-calc-muted-bg',
    '--hubgo-calc-text',
    '--hubgo-calc-text-muted',
    '--hubgo-calc-accent',
    '--hubgo-calc-accent-contrast',
    '--hubgo-calc-title-size',
    '--hubgo-calc-body-size',
    '--hubgo-calc-small-size',
    '--hubgo-calc-badge-bg',
    '--hubgo-calc-badge-text',
    '--hubgo-calc-badge-radius',
    '--hubgo-calc-input-bg',
    '--hubgo-calc-input-border',
    '--hubgo-calc-input-text',
    '--hubgo-calc-input-radius',
    '--hubgo-calc-input-height',
    '--hubgo-calc-button-bg',
    '--hubgo-calc-button-hover-bg',
    '--hubgo-calc-button-text',
    '--hubgo-calc-button-radius',
    '--hubgo-calc-option-bg',
    '--hubgo-calc-option-border',
    '--hubgo-calc-option-radius',
    '--hubgo-calc-option-selected-bg',
    '--hubgo-calc-modal-bg',
    '--hubgo-calc-modal-radius',
    '--hubgo-calc-modal-overlay',
    '--hubgo-calc-modal-blur',
];

/**
 * Copy the resolved token values from a widget root onto another element.
 *
 * Used by the modal shell: a teleported dialog would otherwise fall back to the
 * stylesheet defaults and ignore both the settings panel and Elementor, which
 * is exactly the mismatch a shopper notices when the modal opens.
 *
 * @param {HTMLElement|null} source Element the properties are declared on.
 * @param {HTMLElement|null} target Element to copy them to.
 * @return {void}
 */
export function bridgeTokens( source, target ) {
    if ( ! source || ! target || typeof window === 'undefined' ) {
        return;
    }

    const computed = window.getComputedStyle( source );

    CALCULATOR_TOKENS.forEach( ( token ) => {
        const value = computed.getPropertyValue( token );

        if ( value && value.trim() !== '' ) {
            target.style.setProperty( token, value.trim() );
        }
    } );
}
