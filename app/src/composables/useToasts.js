/**
 * useToasts.js
 *
 * Toast stack shared by every admin screen: pushes an entry, schedules its
 * fade-out and removal, and clears the pending timers when the owning
 * component unmounts.
 *
 * @since 3.0.0
 */
import { onBeforeUnmount, ref } from 'vue';
import { __ } from '../utils/i18n';

const HIDE_DELAY = 3000;
const REMOVE_DELAY = 3500;

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
     * Cancel and forget the timers scheduled for one toast.
     *
     * @param {number} id Toast id.
     * @return {void}
     */
    function clearTimers( id ) {
        const entry = timers.get( id );

        if ( ! entry ) {
            return;
        }

        window.clearTimeout( entry.hide );
        window.clearTimeout( entry.remove );
        timers.delete( id );
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

        toasts.value.push( { id, title, message, tone, closing: false } );

        const hide = window.setTimeout( () => {
            const item = toasts.value.find( ( entry ) => entry.id === id );

            if ( item ) {
                item.closing = true;
            }
        }, HIDE_DELAY );

        const remove = window.setTimeout( () => {
            toasts.value = toasts.value.filter( ( entry ) => entry.id !== id );
            timers.delete( id );
        }, REMOVE_DELAY );

        timers.set( id, { hide, remove } );
    }

    /**
     * Remove a toast immediately.
     *
     * @param {number} id Toast id.
     * @return {void}
     */
    function dismissToast( id ) {
        clearTimers( id );
        toasts.value = toasts.value.filter( ( entry ) => entry.id !== id );
    }

    onBeforeUnmount( () => {
        timers.forEach( ( entry ) => {
            window.clearTimeout( entry.hide );
            window.clearTimeout( entry.remove );
        } );
        timers.clear();
    } );

    return { toasts, toast, dismissToast };
}
