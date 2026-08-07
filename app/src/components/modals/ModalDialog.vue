<script setup>
/**
 * ModalDialog.vue — base modal shell (backdrop, header, scrollable body).
 *
 * Teleported to <body> so wp-admin stacking contexts cannot clip it.
 *
 * @since 3.0.0
 */
import { onBeforeUnmount, watch } from 'vue';
import { __ } from '../../utils/i18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    eyebrow: { type: String, default: '' },
    sizeClass: { type: String, default: 'max-w-2xl' },
});

const emit = defineEmits([ 'close' ]);

/**
 * Close the dialog when Escape is pressed.
 *
 * @param {KeyboardEvent} event Native keydown event.
 * @return {void}
 */
function onKeydown( event ) {
    if ( 'Escape' === event.key ) {
        emit( 'close' );
    }
}

watch( () => props.open, ( isOpen ) => {
    if ( isOpen ) {
        document.addEventListener( 'keydown', onKeydown );
    } else {
        document.removeEventListener( 'keydown', onKeydown );
    }
}, { immediate: true } );

onBeforeUnmount( () => document.removeEventListener( 'keydown', onKeydown ) );
</script>

<template>
    <!--
        The wrapper — not the overlay — carries `hubgo-app`. Tailwind's scoping
        (`important: '.hubgo-app'`) compiles utilities to DESCENDANT selectors, so
        they never apply to the element holding the class itself. `display: contents`
        keeps the wrapper out of the layout.
    -->
    <Teleport to="body">
        <div class="hubgo-app" style="display: contents">
        <div
            v-if="open"
            class="hubgo-modal fixed inset-0 z-[160000] flex items-center justify-center overflow-y-auto px-4 py-4 sm:py-6"
        >
            <button
                type="button"
                class="absolute inset-0 cursor-default border-0 bg-slate-950/60 p-0 backdrop-blur-sm"
                :aria-label="__( 'Fechar' )"
                @click="$emit( 'close' )"
            />

            <div
                class="relative z-10 max-h-[calc(100dvh-4rem)] w-full overflow-y-auto rounded-lg border border-white/20 bg-white p-6 shadow-soft sm:max-h-[calc(100dvh-5rem)]"
                :class="sizeClass"
                role="dialog"
                aria-modal="true"
            >
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <p v-if="eyebrow" class="m-0 text-xs font-semibold uppercase tracking-[0.2em] text-shell-500">
                            {{ eyebrow }}
                        </p>
                        <h3 class="mb-0 mt-1 text-xl font-semibold text-ink">{{ title }}</h3>
                        <p v-if="description" class="mb-0 mt-2 text-sm leading-6 text-muted">{{ description }}</p>
                    </div>

                    <button
                        type="button"
                        class="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-[8px] border-0 bg-transparent text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        :aria-label="__( 'Fechar' )"
                        @click="$emit( 'close' )"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                            <path d="M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 1 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 1 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z" />
                        </svg>
                    </button>
                </div>

                <slot />
            </div>
        </div>
        </div>
    </Teleport>
</template>
