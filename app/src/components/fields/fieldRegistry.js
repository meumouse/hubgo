import ToggleField from './ToggleField.vue';
import TextField from './TextField.vue';
import TextareaField from './TextareaField.vue';
import SelectField from './SelectField.vue';
import ColorField from './ColorField.vue';

/**
 * Central field-component registry.
 *
 * Third parties can register/override field components by type via the global
 * window.HubgoFieldComponents API — mirroring the Joinotify extensibility pattern.
 */
const registry = new Map();

function normalize( name ) {
    return String( name || '' ).trim().toLowerCase();
}

export function registerFieldComponent( type, component ) {
    if ( ! type || ! component ) {
        return;
    }

    registry.set( normalize( type ), component );
    syncWindowRegistry();
}

export function resolveFieldComponent( field ) {
    const names = [];

    if ( field && field.component ) {
        names.push( field.component );
    }

    if ( field && field.type ) {
        names.push( field.type );
    }

    for ( const name of names ) {
        const component = registry.get( normalize( name ) );

        if ( component ) {
            return component;
        }
    }

    return null;
}

export function getRegisteredFieldComponents() {
    return Array.from( registry.keys() );
}

function syncWindowRegistry() {
    window.HubgoFieldComponents = {
        register: registerFieldComponent,
        resolve: resolveFieldComponent,
        list: getRegisteredFieldComponents,
    };
}

// Built-in field types.
registerFieldComponent( 'toggle', ToggleField );
registerFieldComponent( 'text', TextField );
registerFieldComponent( 'textarea', TextareaField );
registerFieldComponent( 'select', SelectField );
registerFieldComponent( 'color', ColorField );

// Expose immediately and announce readiness for late extensions.
syncWindowRegistry();

if ( typeof window !== 'undefined' ) {
    window.dispatchEvent( new CustomEvent( 'hubgo:field-registry-ready', {
        detail: window.HubgoFieldComponents,
    } ) );
}
