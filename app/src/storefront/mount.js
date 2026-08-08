/**
 * mount.js
 *
 * Finds every calculator placeholder on the page and mounts one Vue app per
 * node. A page can legitimately hold several: the product-page hook, a
 * shortcode and one or more Elementor widgets can all be present at once, each
 * with its own config.
 *
 * Elementor re-renders a widget whenever a content control changes, replacing
 * the node. Scanning again on `frontend/element_ready` is what keeps the editor
 * preview live; unmounting the previous app first is what keeps that from
 * leaking a Vue instance per keystroke.
 *
 * @since 3.0.0
 */
import { createApp } from 'vue';
import ShippingCalculator from './components/ShippingCalculator.vue';

const SELECTOR = '[data-hubgo-calculator]';
const MOUNTED_FLAG = '__hubgoCalculatorApp';

/**
 * Parse the config a mount node carries.
 *
 * A malformed config is not fatal: rendering with defaults is better than
 * leaving a blank hole where the calculator should be.
 *
 * @param {HTMLElement} node Mount node.
 * @return {object}
 */
function readConfig( node ) {
    const raw = node.getAttribute( 'data-hubgo-calculator' );

    if ( ! raw ) {
        return {};
    }

    try {
        const parsed = JSON.parse( raw );

        return ( parsed && typeof parsed === 'object' ) ? parsed : {};
    } catch ( error ) {
        return {};
    }
}

/**
 * Mount a single node, replacing any app already on it.
 *
 * @param {HTMLElement} node Mount node.
 * @return {void}
 */
function mountNode( node ) {
    if ( node[ MOUNTED_FLAG ] ) {
        node[ MOUNTED_FLAG ].unmount();
        node[ MOUNTED_FLAG ] = null;
    }

    const app = createApp( ShippingCalculator, { config: readConfig( node ) } );

    // Clear the server-rendered fallback markup before Vue takes over.
    node.innerHTML = '';

    app.mount( node );
    node[ MOUNTED_FLAG ] = app;
}

/**
 * Mount every calculator inside a root.
 *
 * @param {ParentNode} root Search root, defaults to the document.
 * @return {void}
 */
export function mountCalculators( root = document ) {
    if ( ! root || typeof root.querySelectorAll !== 'function' ) {
        return;
    }

    root.querySelectorAll( SELECTOR ).forEach( mountNode );

    // Elementor hands the hook the widget wrapper, which may itself be the
    // mount node rather than one of its descendants.
    if ( typeof root.matches === 'function' && root.matches( SELECTOR ) ) {
        mountNode( root );
    }
}

/**
 * Boot on DOM ready and re-scan whenever Elementor renders a widget.
 *
 * @return {void}
 */
export function boot() {
    const start = () => mountCalculators( document );

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', start );
    } else {
        start();
    }

    window.addEventListener( 'elementor/frontend/init', () => {
        const frontend = window.elementorFrontend;

        if ( ! frontend || ! frontend.hooks ) {
            return;
        }

        frontend.hooks.addAction(
            'frontend/element_ready/hubgo_shipping_calculator.default',
            ( $scope ) => {
                // Elementor passes a jQuery collection; fall back to the raw
                // node so this keeps working if that ever changes.
                const element = ( $scope && $scope[ 0 ] ) ? $scope[ 0 ] : $scope;

                mountCalculators( element );
            },
        );
    } );
}
