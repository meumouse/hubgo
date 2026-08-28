import { createApp } from 'vue';
import { createPinia } from 'pinia';

/**
 * Mount a page component into a DOM node once the document is ready.
 *
 * @param {string} elementId Target mount node id.
 * @param {object} component Root Vue component.
 * @return {void}
 */
export function mountPage( elementId, component ) {
    const start = () => {
        const el = document.getElementById( elementId );

        if ( ! el ) {
            return;
        }

        // Clear skeleton placeholders before mounting.
        el.innerHTML = '';

        const app = createApp( component );
        app.use( createPinia() );
        app.mount( el );
    };

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', start );
    } else {
        start();
    }
}
