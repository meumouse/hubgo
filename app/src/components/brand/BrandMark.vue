<script setup>
/**
 * BrandMark.vue
 *
 * Renders the HubGo logo, resolving the SVG source from a color variant
 * (primary, dark, white) and computing its pixel height from a size keyword or
 * an explicit number. The asset base path comes from the localized bootstrap
 * config so the logo resolves on sites installed in a subdirectory.
 *
 * @since 3.0.0
 */
import { computed } from 'vue';
import { __ } from '../../utils/i18n';
import { getBootstrapConfig } from '../../utils/api';

const props = defineProps({
    alt: { type: String, default: () => __( 'HubGo' ) },
    title: { type: String, default: () => __( 'HubGo' ) },
    size: { type: [ String, Number ], default: 'md' },
    variant: {
        type: String,
        default: 'primary',
        validator: ( value ) => [ 'white', 'dark', 'primary' ].includes( value ),
    },
    basePath: { type: String, default: '' },
});

const variants = {
    white: 'logo-hubgo-white.svg',
    dark: 'logo-hubgo-dark.svg',
    primary: 'logo-hubgo-primary.svg',
};

const sizeMap = {
    xs: 20,
    sm: 26,
    md: 32,
    lg: 40,
    xl: 48,
};

/**
 * Resolve the brand asset directory, preferring the localized assets URL.
 *
 * @return {string}
 */
const resolvedBase = computed( () => {
    if ( props.basePath ) {
        return props.basePath.replace( /\/$/, '' );
    }

    const assetsUrl = getBootstrapConfig().assetsUrl || '/wp-content/plugins/hubgo/assets/';

    return `${ assetsUrl.replace( /\/$/, '' ) }/brand`;
} );

const src = computed( () => `${ resolvedBase.value }/${ variants[ props.variant ] || variants.primary }` );

const height = computed( () => {
    if ( typeof props.size === 'number' ) {
        return props.size;
    }

    return sizeMap[ props.size ] || sizeMap.md;
} );
</script>

<template>
    <img
        :alt="alt"
        :height="height"
        :src="src"
        :style="{ height: `${ height }px`, width: 'auto' }"
        :title="title || alt"
        class="block shrink-0"
        decoding="async"
        loading="eager"
    >
</template>
