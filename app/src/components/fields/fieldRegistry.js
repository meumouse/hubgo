/**
 * fieldRegistry.js
 *
 * Central registry mapping field type/component names to their Vue components.
 * Third parties can register or override widgets through the global
 * window.HubgoFieldComponents API — mirroring the Joinotify extensibility
 * contract — and listen for `hubgo:field-registry-ready` to register late.
 *
 * @since 3.0.0
 */
import ToggleSwitch from '../toggles/ToggleSwitch.vue';
import TextField from './TextField.vue';
import TextareaField from './TextareaField.vue';
import SelectField from './SelectField.vue';
import ColorField from './ColorField.vue';

const registry = new Map();

/**
 * Normalize a field name into a lookup key (trimmed, lowercased, separators removed).
 *
 * @param {string} name Raw field type or component name.
 * @return {string}
 */
function normalize( name ) {
    return String( name || '' )
        .trim()
        .toLowerCase()
        .replace( /[\s_-]+/g, '' );
}

/**
 * Register a field component under a normalized alias.
 *
 * @param {string} type Field type or component name.
 * @param {object} component Vue component.
 * @return {void}
 */
export function registerFieldComponent( type, component ) {
    const key = normalize( type );

    if ( ! key || ! component ) {
        return;
    }

    registry.set( key, component );
    syncWindowRegistry();
}

/**
 * Resolve the component for a field definition, checking its explicit
 * `component` name first and then its `type`.
 *
 * @param {object} field Field definition.
 * @return {object|null}
 */
export function resolveFieldComponent( field ) {
    if ( ! field || typeof field !== 'object' ) {
        return null;
    }

    const names = [];

    if ( field.component ) {
        names.push( field.component );
    }

    if ( field.type ) {
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

/**
 * List the registered field aliases.
 *
 * @return {string[]}
 */
export function getRegisteredFieldComponents() {
    return Array.from( registry.keys() );
}

/**
 * Expose the registry API on window so external bundles can extend it.
 *
 * @return {object|null}
 */
function syncWindowRegistry() {
    if ( typeof window === 'undefined' ) {
        return null;
    }

    window.HubgoFieldComponents = window.HubgoFieldComponents || {};
    window.HubgoFieldComponents.register = registerFieldComponent;
    window.HubgoFieldComponents.resolve = resolveFieldComponent;
    window.HubgoFieldComponents.list = getRegisteredFieldComponents;

    return window.HubgoFieldComponents;
}

// Built-in field types.
registerFieldComponent( 'toggle', ToggleSwitch );
registerFieldComponent( 'text', TextField );
registerFieldComponent( 'textarea', TextareaField );
registerFieldComponent( 'select', SelectField );
registerFieldComponent( 'color', ColorField );
registerFieldComponent( 'color-picker', ColorField );

/**
 * Signal (once) that the registry accepts external registrations. The `ready`
 * flag lets listeners attached after the event still detect availability.
 *
 * @return {void}
 */
function announceReady() {
    const globalRegistry = syncWindowRegistry();

    if ( ! globalRegistry || typeof window === 'undefined' ) {
        return;
    }

    globalRegistry.ready = true;

    if ( typeof window.CustomEvent === 'function' ) {
        window.dispatchEvent( new window.CustomEvent( 'hubgo:field-registry-ready', {
            detail: globalRegistry,
        } ) );
    }
}

announceReady();
