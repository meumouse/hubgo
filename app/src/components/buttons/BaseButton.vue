<script setup>
/**
 * BaseButton.vue
 *
 * Themeable button/link with color and size variants plus loading and disabled
 * states. Renders as an <a> when an href is provided, otherwise a <button>, and
 * suppresses clicks while disabled or loading.
 *
 * `icon` takes a Boxicons component (`import { Save } from '@boxicons/vue'`).
 * A string is still accepted and injected as raw markup, which is what the
 * integration cards need for brand logos shipped by the backend.
 *
 * @since 3.0.0
 */
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    href: { type: String, default: '' },
    icon: { type: [ String, Object, Function ], default: '' },
    iconClass: { type: String, default: '' },
    size: {
        type: String,
        default: 'md',
        validator: ( value ) => [ 'sm', 'md', 'lg' ].includes( value ),
    },
    color: {
        type: String,
        default: 'primary',
        validator: ( value ) => [ 'primary', 'outline', 'white', 'ghost', 'success', 'danger', 'warning', 'info' ].includes( value ),
    },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    type: { type: String, default: 'button' },
});

const emit = defineEmits([ 'click' ]);

const sizeClass = computed( () => ( {
    sm: 'px-3 py-2 text-[13px]',
    md: 'px-5 py-3 text-[14px]',
    lg: 'px-6 py-3.5 text-[15px]',
}[ props.size ] || 'px-5 py-3 text-[14px]' ) );

const colorClass = computed( () => ( {
    primary: 'bg-primary-700 text-white shadow-sm hover:bg-primary-800',
    outline: 'bg-white text-primary-700 ring-1 ring-primary-200 hover:bg-primary-50',
    white: 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50',
    ghost: 'bg-transparent text-ink hover:bg-slate-100',
    success: 'bg-success text-white hover:opacity-90',
    danger: 'bg-danger text-white hover:opacity-90',
    warning: 'bg-warning text-dark hover:opacity-90',
    info: 'bg-info text-white hover:opacity-90',
}[ props.color ] || 'bg-primary-700 text-white shadow-sm hover:bg-primary-800' ) );

const spinnerClass = computed( () => ( {
    sm: 'h-3.5 w-3.5',
    md: 'h-4 w-4',
    lg: 'h-5 w-5',
}[ props.size ] || 'h-4 w-4' ) );

const iconSize = computed( () => ( {
    sm: 14,
    md: 16,
    lg: 20,
}[ props.size ] || 16 ) );

/** Boxicons components arrive as objects; legacy raw markup as a string. */
const isIconComponent = computed( () => Boolean( props.icon ) && 'string' !== typeof props.icon );

/**
 * Handle a click, suppressing it while disabled or loading.
 *
 * @param {MouseEvent} event Native click event.
 * @return {void}
 */
function handleClick( event ) {
    if ( props.disabled || props.loading ) {
        event.preventDefault();
        event.stopPropagation();

        return;
    }

    emit( 'click', event );
}
</script>

<template>
    <component
        :is="href ? 'a' : 'button'"
        :href="href || undefined"
        :type="href ? undefined : type"
        :aria-disabled="disabled || loading ? 'true' : undefined"
        :tabindex="disabled || loading ? -1 : undefined"
        class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-[8px] border-0 font-semibold no-underline outline-none transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700"
        :class="[ sizeClass, colorClass, ( disabled || loading ) ? 'cursor-not-allowed opacity-60' : '' ]"
        @click="handleClick"
    >
        <span
            v-if="loading"
            class="inline-flex shrink-0 animate-spin rounded-full border-2 border-current border-r-transparent"
            :class="spinnerClass"
            aria-hidden="true"
        />
        <component
            :is="icon"
            v-else-if="isIconComponent"
            class="shrink-0"
            :class="iconClass"
            :width="iconSize"
            :height="iconSize"
            aria-hidden="true"
        />
        <span v-else-if="icon" class="inline-flex shrink-0 leading-none" :class="iconClass" v-html="icon" />
        <span>{{ title }}</span>
    </component>
</template>
