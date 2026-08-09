/**
 * useToasts.js
 *
 * Toast stack shared by every admin screen: pushes an entry, schedules its
 * removal, and clears the pending timers when the owning component unmounts.
 *
 * @since 3.0.0
 * @version 3.1.0
 */
import { onBeforeUnmount, ref } from 'vue';
import { __ } from '../utils/i18n';

/**
 * How long a toast stays on screen.
 *
 * Published rather than kept private because the progress bar has to run for
 * exactly this long — `ToastStack` hands it to the CSS as a custom property.
 * The two used to be a `3000` here and a hard-coded `3s` in the markup, which
 * agreed only for as long as nobody touched either one.
 *
 * @type {number}
 */
export const TOAST_DURATION = 3000;

/**
 * Create a toast stack bound to the calling component's lifecycle.
 *
 * @return {{toasts: object, toast: Function, dismissToast: Function}}
 */
export function useToasts() {
    const toasts = ref( [] );
    const timers = new Map();
    let seed = 0;

    /**
     * Drop a toast from the stack. The fade-out belongs to the TransitionGroup
     * in `ToastStack`, so this only has to stop tracking it — the two-step
     * "flag it as closing, remove it half a second later" dance this replaced
     * was a hand-rolled leave transition running next to a real one.
     *
     * @param {number} id Toast id.
     * @return {void}
     */
    function remove( id ) {
        const handle = timers.get( id );

        if ( undefined !== handle ) {
            window.clearTimeout( handle );
            timers.delete( id );
        }

        toasts.value = toasts.value.filter( ( entry ) => entry.id !== id );
    }

    /**
     * Push a toast onto the stack.
     *
     * @param {string} message Body text.
     * @param {string} tone One of success|error|warning|info.
     * @param {string} title Header text.
     * @return {void}
     */
    function toast( message, tone = 'info', title = __( 'HubGo' ) ) {
        const id = ++seed;

        toasts.value.push( { id, title, message, tone } );
        timers.set( id, window.setTimeout( () => remove( id ), TOAST_DURATION ) );
    }

    /**
     * Remove a toast immediately.
     *
     * @param {number} id Toast id.
     * @return {void}
     */
    function dismissToast( id ) {
        remove( id );
    }

    onBeforeUnmount( () => {
        timers.forEach( ( handle ) => window.clearTimeout( handle ) );
        timers.clear();
    } );

    return { toasts, toast, dismissToast };
}
