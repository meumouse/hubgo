/**
 * object.js
 *
 * Structural helpers shared by the admin screens. `deepEqual` backs the
 * unsaved-changes check: comparing JSON.stringify() output would report a
 * false positive whenever the server returns the same values in a different
 * key order.
 *
 * @since 3.0.0
 */

/**
 * Deep clone a plain value (objects, arrays and primitives).
 *
 * @param {*} value Value to clone.
 * @return {*}
 */
export function cloneValue( value ) {
    if ( Array.isArray( value ) ) {
        return value.map( ( item ) => cloneValue( item ) );
    }

    if ( value && 'object' === typeof value ) {
        return Object.keys( value ).reduce( ( carry, key ) => {
            carry[ key ] = cloneValue( value[ key ] );

            return carry;
        }, {} );
    }

    return value;
}


/**
 * Compare two values structurally, ignoring key order.
 *
 * @param {*} a First value.
 * @param {*} b Second value.
 * @return {boolean}
 */
export function deepEqual( a, b ) {
    if ( a === b ) {
        return true;
    }

    if ( Array.isArray( a ) && Array.isArray( b ) ) {
        return a.length === b.length && a.every( ( item, index ) => deepEqual( item, b[ index ] ) );
    }

    if ( a && b && 'object' === typeof a && 'object' === typeof b ) {
        const keysA = Object.keys( a );
        const keysB = Object.keys( b );

        if ( keysA.length !== keysB.length ) {
            return false;
        }

        return keysA.every( ( key ) => Object.prototype.hasOwnProperty.call( b, key ) && deepEqual( a[ key ], b[ key ] ) );
    }

    return false;
}
