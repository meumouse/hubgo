/**
 * async.js — small promise helpers shared by the SPA pages.
 *
 * @since 3.0.0
 */

/**
 * Resolve after the given delay.
 *
 * @param {number} ms Delay in milliseconds.
 * @return {Promise<void>}
 */
export function delay( ms ) {
    return new Promise( ( resolve ) => window.setTimeout( resolve, ms ) );
}

/**
 * Keep a promise pending for at least `ms` milliseconds.
 *
 * Save requests against a local site resolve in a few milliseconds, which makes
 * the button spinner flash for a frame or not paint at all — the click reads as
 * if nothing happened. Holding the loading state open for a short floor makes
 * the feedback perceptible without hiding a genuinely slow request: a call that
 * already takes longer than the floor is not delayed at all.
 *
 * Rejections propagate immediately so errors are never held back.
 *
 * @param {Promise} promise Promise to wrap.
 * @param {number}  [ms]    Minimum duration in milliseconds.
 * @return {Promise<*>}
 */
export function withMinimumDuration( promise, ms = 600 ) {
    return Promise.all( [ promise, delay( ms ) ] ).then( ( [ value ] ) => value );
}
