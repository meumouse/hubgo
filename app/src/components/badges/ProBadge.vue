<script setup>
/**
 * ProBadge.vue
 *
 * Marks a feature as part of the licensed (Pro) tier. Two tones: `unlocked`
 * when the site holds a valid license, `locked` otherwise — the label stays the
 * same so the badge reads as a tier marker, not as an error.
 *
 * @since 3.0.0
 */
import { computed } from 'vue';
import { Crown } from '@boxicons/vue';
import { __ } from '../../utils/i18n';

const props = defineProps({
    locked: { type: Boolean, default: false },
    label: { type: String, default: () => __( 'Pro' ) },
    size: {
        type: String,
        default: 'md',
        validator: ( value ) => [ 'sm', 'md' ].includes( value ),
    },
});

const toneClass = computed( () => ( props.locked
    ? 'bg-slate-100 text-slate-500 ring-slate-200'
    : 'bg-amber-50 text-amber-700 ring-amber-200'
) );

const sizeClass = computed( () => ( 'sm' === props.size
    ? 'px-2 py-0.5 text-[11px] gap-1'
    : 'px-2.5 py-1 text-xs gap-1.5'
) );

const glyphSize = computed( () => ( 'sm' === props.size ? 12 : 14 ) );
</script>

<template>
    <span
        class="inline-flex items-center rounded-full font-semibold uppercase tracking-wide ring-1"
        :class="[ toneClass, sizeClass ]"
        :title="locked ? __( 'Feature available with an active license' ) : __( 'Pro feature unlocked' )"
    >
        <component :is="Crown" :width="glyphSize" :height="glyphSize" aria-hidden="true" />
        <span>{{ label }}</span>
    </span>
</template>
