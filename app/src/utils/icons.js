/**
 * icons.js
 *
 * Maps the icon *names* the PHP schema emits (section tabs, integration
 * categories) to Boxicons Vue components. The backend never ships icon markup
 * for these, which keeps the schema readable and lets the frontend own the
 * visual language.
 *
 * A name with no mapping falls back to `Grid`, and `isMarkup()` lets callers
 * detect the one case where the backend does send raw SVG — integration card
 * logos, which are brand assets rather than UI glyphs.
 *
 * @since 3.0.0
 */
import {
    AlertCircle,
    AlignLeft,
    Bell,
    Cart,
    CheckCircle,
    Cog,
    Crown,
    Grid,
    InfoCircle,
    Key,
    Link,
    Package,
    Palette,
    Puzzle,
    RefreshCw,
    Rocket,
    SliderAlt,
    Trash,
    Truck,
} from '@boxicons/vue';

const icons = {
    'alert-circle': AlertCircle,
    'align-left': AlignLeft,
    'bell': Bell,
    'cart': Cart,
    'check-circle': CheckCircle,
    'cog': Cog,
    'crown': Crown,
    'grid': Grid,
    'info-circle': InfoCircle,
    'key': Key,
    'link': Link,
    'package': Package,
    'palette': Palette,
    'puzzle': Puzzle,
    'refresh': RefreshCw,
    'rocket': Rocket,
    'slider-alt': SliderAlt,
    'trash': Trash,
    'truck': Truck,
};

/**
 * Whether a value is inline SVG markup rather than an icon name.
 *
 * @param {string} value Raw icon value from the backend.
 * @return {boolean}
 */
export function isMarkup( value ) {
    return typeof value === 'string' && value.trim().startsWith( '<' );
}

/**
 * Resolve an icon name to its Boxicons component.
 *
 * @param {string} name Icon name, e.g. "package".
 * @param {object|null} fallback Component returned when the name is unknown.
 * @return {object|null}
 */
export function resolveIcon( name, fallback = Grid ) {
    if ( ! name || isMarkup( name ) ) {
        return fallback;
    }

    return icons[ String( name ).trim().toLowerCase() ] || fallback;
}

export { Grid };
